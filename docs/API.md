---
name: xomiton-api
description: Référence de l'API REST /api/v1 de Xomiton (Sanctum) — conventions de réponse et d'erreur, limitation de débit, puis chaque endpoint (méthode, URL, corps, réponses, codes d'erreur). Lot « api-auth » (P2-06, P2-12) : authentification, mon compte, mes appareils. À charger avant d'écrire un client Flutter ou Vue, ou d'ajouter une famille de routes.
---

# API `/api/v1`

Référence : [SOFTWARE_ARCHITECTURE.md](SOFTWARE_ARCHITECTURE.md) §4, [LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md](LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md) §9, `prototype_mobile/src/simulateur.html` (charges utiles attendues par Flutter), décisions Q8 c, Q12 c, Q13 b, Q30 a du [plan](IMPLEMENTATION_PLAN.md) §7.

## 1. Conventions

| Sujet | Règle |
|---|---|
| Base | `https://<domaine>/api/v1` — préfixe posé dans `bootstrap/app.php`, routes dans `routes/api.php` |
| Format | JSON uniquement (`Accept: application/json` recommandé ; toute requête `api/*` reçoit du JSON même sans cet en-tête) |
| Authentification | **Sanctum** : en-tête `Authorization: Bearer <token>` (Flutter) ou cookie de session (Vue, même domaine : mode SPA `statefulApi()` d'ADR-31, en-tête `X-XSRF-TOKEN` requis, 419 → `GET /sanctum/csrf-cookie` puis rejeu) |
| Succès | `{ "data": …, "meta"?: … }` — jamais de modèle Eloquent brut (Resources `App\Http\Resources\*`) |
| Erreur | `{ "message": "…", "code": "…", "errors"?: { champ: [messages] }, "retry_after"?: secondes }` ; le 422 de validation garde la forme Laravel `{ message, errors }` |
| Textes | français, tutoiement sur le tunnel d'authentification (Q36), tous dans `lang/fr/auth.php` et `lang/fr/validation.php` |
| Codes HTTP | 200, 201, 202 (traitement accepté : code envoyé), 401, 403, 404, 405, 422, 429, 503 |
| Débit | `throttle:api` 60/min par compte ou IP sur tout `/api/v1` ; `throttle:login` 5/min par identifiant + IP sur `register`, `login`, `otp/verify`, `password/reset` ; `throttle:otp` 5/h par adresse sur `otp/send`, `password/forgot` (`config/xomiton.php` → `rate_limits`). Réponse 429 : `code: too_many_requests`, en-têtes `Retry-After`, `X-RateLimit-*` |

### Codes d'erreur stables (`code`)

| `code` | HTTP | Quand | Message (`lang/fr/auth.php`) |
|---|---|---|---|
| `unauthenticated` | 401 | jeton absent, révoqué ou session expirée | `auth.api.unauthenticated` |
| `forbidden` | 403 | policy ou permission refusée | `auth.api.forbidden` |
| `email_unverified` | 403 | action réservée aux e-mails confirmés (publier, commenter, écrire, rejoindre un groupe) tant que `email_verified` est faux (Q12 c) | `auth.email_not_verified` — « Confirme ton e-mail pour continuer. » |
| `account_suspended` | 403 | compte suspendu, signalé **après** vérification du mot de passe | `auth.suspended` (avec l'adresse de contact) |
| `maintenance` | 503 | maintenance active (RG-14), rôle autre qu'admin / superadmin, après vérification du mot de passe | `auth.maintenance` |
| `registrations_closed` | 403 | inscriptions fermées par l'administration (message configuré) | `auth.registrations_closed` ou texte administré |
| `consent_required` | 422 | CGU + charte ou confidentialité non acceptées | `auth.consent_required` |
| `invalid_credentials` | 422 | identifiant inconnu **ou** mot de passe faux (message indifférencié, `errors.login`) | `auth.failed` |
| `otp_invalid` | 422 | code faux, expiré, épuisé (5 essais), adresse inconnue, preuve de réinitialisation invalide (`errors.code` ou `errors.reset_token`) | `auth.otp.invalid` |
| `otp_too_soon` | 429 | renvoi demandé avant 30 s (`retry_after`) | `auth.otp.too_soon` |
| `otp_rate_limited` | 429 | plus de 5 codes par heure pour cette adresse et cet usage (`retry_after`) | `auth.otp.rate_limited` |
| `too_many_requests` | 429 | limiteur nommé (`retry_after`) | `auth.throttle` |
| `not_found` | 404 | route ou ressource inconnue | `auth.api.not_found` |
| `method_not_allowed` | 405 | mauvaise méthode | `auth.api.method_not_allowed` |

Rendu par `App\Exceptions\ApiExceptionRenderer` (enregistré dans `bootstrap/app.php` → `withExceptions`). Les exceptions métier `App\Domain\Auth\Exceptions\*` portent elles-mêmes leur statut (`status()`) et leur message (`userMessage()`).

### Objet `user` (`App\Http\Resources\UserResource`)

Vue « moi » (réponses d'inscription, de connexion, `GET me`) :

```json
{
  "id": 12, "pseudo": "Awa_S", "avatar_url": "https://…/avatar1.png",
  "status": "actif", "pending_validation": false,
  "role": "membre", "roles": ["membre"],
  "profile": {
    "pronouns": "elle", "member_type": "membre", "bio": null, "completion": 30, "visibility": "communaute",
    "zone": {"id": 1, "name": "Littoral"}, "commune": {"id": 3, "name": "Cotonou"},
    "age_range": null, "discreet_mode": false, "show_zone": true, "searchable": true,
    "trusted_contact_name": null, "trusted_contact_phone": null
  },
  "created_at": "2026-10-17T10:00:00+01:00",
  "email": "awa@exemple.bj", "email_verified": false, "email_verified_at": null,
  "permissions": ["comments.create", "posts.create", "social.access", "…"],
  "settings": {"theme": "auto", "language": "fr", "notification_prefs": {…}, "notification_digest": "never",
               "mask_sensitive_notifications": false, "sos_share_gps": false, "pin_enabled": false, "neutral_tab_title": false},
  "last_login_at": null
}
```

Vue « autre compte » (futures routes `users/{id}`) : seuls `id`, `pseudo`, `avatar_url`, `status`, `pending_validation`, `role`, `roles`, `created_at` et un `profile` réduit (`pronouns`, `member_type`, `bio`, `completion`, `visibility`, `zone` / `commune` si `show_zone`). **Le nom réel, le téléphone, l'identité de genre et l'orientation ne sortent jamais** (RG-02).

| `status` | Sens |
|---|---|
| `actif` | accès normal (limité à lecture, SOS, signalement, profil tant que `email_verified` est faux) |
| `en_attente` | pair éducateur·rice en attente de validation (RG-03) : session ouverte, bandeau « compte en attente », collecte fermée |
| `suspendu` | connexion refusée (403 `account_suspended`) |

## 2. Authentification `auth/*`

### `POST auth/register` — phase 1 (+ phases 2 à 4 facultatives)

Compte **créé et connecté immédiatement** (Q12 c, D4) : code `signup` envoyé par e-mail (`code-verification`) puis e-mail `bienvenue` ; la vérification est différée et non bloquante.

Corps :

| Champ | Règles |
|---|---|
| `pseudo` | requis, 3–40, lettres / chiffres / `_` `.` `-`, unique (casse ignorée) |
| `email` | requis, e-mail valide, unique (normalisé en minuscules) |
| `password` | requis, ≥ 10 caractères, majuscule, minuscule, chiffre (Q30 a ; contrôle « compromis » hors test) |
| `cgu`, `privacy` | requis, `true` chacun (cases distinctes, jamais pré-cochées) |
| `phone` | facultatif, numéro béninois (`+229 01 XX XX XX XX`) |
| `device_name` | facultatif (≤ 100) : nom du jeton ; sinon navigateur ou « appareil » |
| `profile` | facultatif (phases 2-3) : `pronouns` (≤ 30), `zone_id` (requis avec `commune_id`), `commune_id` (dans le département), `member_type` (`membre` \| `pair` → compte `en_attente`, rôle `pair`), `trusted_contact_phone` (béninois, chiffré), `trusted_contact_name` (≤ 80), `discreet_mode` (bool) |
| `consents` | facultatif (phase 4) : `sensitive_data` (bool), `newsletter` (bool) — chaque réponse, acceptée ou refusée, est horodatée dans `consents` |

Réponses :

- **201** `{ "data": { "token": "1|…", "token_type": "Bearer", "user": {…}, "email_verification_pending": true } }`
- 422 `{ message, errors }` — par champ ; 403 `registrations_closed` ; 429.

### `POST auth/login`

| Champ | Règles |
|---|---|
| `login` | requis : e-mail **ou** pseudonyme, casse ignorée |
| `password` | requis |
| `device_name` | requis (≤ 100) : nom du jeton (« Appareils connectés ») |
| `device_token` | facultatif : valeur reçue après un OTP de connexion réussi (équivalent API du cookie `remember_device`, 30 jours, lié au compte et à son mot de passe courant, Q13 b) |

Réponses, **toujours après vérification du mot de passe** :

- **202** — un code OTP `login` est requis (appareil inconnu, `device_token` absent / altéré / expiré, **ou** rôle admin / superadmin — toujours) :
  `{ "data": { "otp_sent": true, "purpose": "login", "email": "awa@exemple.bj", "resend_after": 30, "expires_in": 600 } }` → enchaîner sur `otp/verify`.
- **200** — appareil reconnu : `{ "data": { "token", "token_type": "Bearer", "user", "device_token", "device_token_expires_at" } }` (nouveau `device_token`, à remplacer côté client).
- 422 `invalid_credentials` (`errors.login`, message identique pour identifiant inconnu et mot de passe faux) ; 403 `account_suspended` ; 503 `maintenance` ; 429 (5/min par identifiant + IP).

Un compte `en_attente` se connecte normalement : lire `user.pending_validation`.

### `POST auth/otp/send`

| Champ | Règles |
|---|---|
| `purpose` | requis : `login` \| `signup` \| `reset` |
| `email` | requis sans jeton ; facultatif avec jeton (adresse du compte connecté) |

Réponse **202 identique que le compte existe ou non** : `{ "data": { "otp_sent": true, "purpose": "…", "resend_after": 30, "expires_in": 600 } }`.
Comportement réel : `login` ne renouvelle un code que si une connexion (mot de passe vérifié, code encore valable) est en cours ; `signup` seulement pour un compte dont l'e-mail n'est pas confirmé ; `reset` équivaut à `password/forgot` (un seul e-mail `reinitialisation-mot-de-passe` avec code **et** lien magique, Q8 c). Le nouveau code annule le précédent.
Erreurs : 429 `otp_too_soon` (`retry_after`), 429 `otp_rate_limited`, 429 `too_many_requests` (5/h par adresse), 422.

### `POST auth/otp/verify`

| Champ | Règles |
|---|---|
| `purpose` | requis : `login` \| `signup` \| `reset` |
| `email` | requis sans jeton ; facultatif avec jeton |
| `code` | requis, 6 chiffres (espaces tolérés : collage du code complet) |
| `device_name` | facultatif : nom du jeton délivré (`login`) |

Réponses selon `purpose` :

- `login` → **200** `{ "data": { "token", "token_type", "user", "device_token", "device_token_expires_at" } }` : l'appareil est désormais reconnu (stocker `device_token` en sécurité, le renvoyer à `auth/login`).
- `signup` → **200** `{ "data": { "email_verified": true, "user": {…} } }` : `users.email_verified_at` posé, accès complet. Aucun jeton délivré ici : le client tient déjà celui de l'inscription (ou se connecte).
- `reset` → **200** `{ "data": { "reset_token": "…", "expires_at": "…" } }` : le code est consommé (5 essais comptés) et remplacé par une preuve signée valable aussi longtemps que lui, à présenter à `password/reset` (`App\Application\Auth\ExchangeResetCode` : même enregistrement `otp_codes`, secret tourné ; le code à 6 chiffres ne sert plus).
- 422 `otp_invalid` (`errors.code`, même message pour code faux / expiré / épuisé / adresse inconnue) ; pour `login`, **après un code juste seulement** : 403 `account_suspended`, 503 `maintenance` (membre dont la maintenance a commencé entre les deux étapes) ; 429.

### `POST auth/password/forgot`

| Champ | Règles |
|---|---|
| `email` | requis, e-mail valide |

**202 toujours**, même corps que le compte existe ou non, même durée de traitement :
`{ "data": { "sent": true, "message": "Si un compte correspond à cette adresse, un code vient d’être envoyé.", "expires_in": 600 } }`.
Si le compte existe : code OTP `reset` + lien magique signé dans l'e-mail `reinitialisation-mot-de-passe` (le lien ouvre l'écran web `nouveau-mot-de-passe?email=…&token=…`). Limites : 5/h par adresse (429).

### `POST auth/password/reset`

| Champ | Règles |
|---|---|
| `email` | requis |
| `reset_token` | requis : `reset_token` rendu par `otp/verify` (`reset`), **ou** jeton du lien magique, **ou** le code à 6 chiffres lui-même |
| `password`, `password_confirmation` | requis, confirmé, ≥ 10 caractères, majuscule, minuscule, chiffre |

- **200** `{ "data": { "reset": true, "message": "Ton mot de passe est modifié. Connecte-toi avec le nouveau." } }` — **toutes** les sessions web et **tous** les jetons Sanctum sont révoqués, les `device_token` deviennent invalides (empreinte du mot de passe), e-mail `mot-de-passe-modifie` envoyé.
- 422 `otp_invalid` (`errors.reset_token`, même message pour adresse inconnue, preuve forgée, expirée ou déjà utilisée) ; 422 par champ ; 429.

### `POST auth/logout` — `auth:sanctum`

Révoque le jeton de la requête (ou ferme la session web d'un client Vue en mode SPA). **200** `{ "data": { "logged_out": true } }` ; 401 sans jeton. Journal `auth.logout` (une seule entrée : événement `Logout` émis par le garde `web` ou par le contrôleur pour un jeton).

> Non couvert ici (P6-09) : la suppression de l'abonnement push de l'appareil à la déconnexion, qui attend `me/push-subscriptions`.

## 3. Mon compte `me/*` — `auth:sanctum`

### `GET me`

**200** `{ "data": <user vue « moi »> }` (voir §1). 401 `unauthenticated`.

### `PATCH me/profile` — phases 2-3 et profil progressif

Tous les champs sont facultatifs ; un champ absent **ou `null`** est laissé tel quel (le profil progressif ne vide rien par cette route).

| Champ | Règles |
|---|---|
| `pronouns` | ≤ 30 |
| `zone_id` | département actif ; requis si `commune_id` est fourni |
| `commune_id` | commune active du département (`commune_in_zone`) |
| `member_type` | `membre` \| `pair` — `pair` place le compte `en_attente` et lui donne le rôle `pair` (RG-03), la session reste ouverte |
| `trusted_contact_phone` | numéro béninois (stocké chiffré) |
| `trusted_contact_name` | ≤ 80 |
| `discreet_mode` | bool |

**200** `{ "data": <user> }` (avec `profile.completion` recalculé) ; 422 par champ ; 401.

### `GET me/sessions` — « Appareils connectés »

**200** `{ "data": [ { "id": "token-12", "kind": "token", "name": "Pixel 7", "ip": null, "current": true, "last_active_at": "…", "created_at": "…" }, { "id": "web-3f9…", "kind": "web", "name": "Mozilla/5.0 …", "ip": "41.79.1.1", "current": false, "last_active_at": "…", "created_at": null } ], "meta": { "total": 2 } }`

`kind: token` = jeton Sanctum (Flutter, scripts) ; `kind: web` = session de navigateur en base (`SESSION_DRIVER=database`, D7). L'identifiant d'une session web est une **empreinte**, jamais l'identifiant de session lui-même (`App\Application\Auth\ConnectedDevices`).

### `DELETE me/sessions/{id}`

`id` = `token-<n>` ou `web-<empreinte>` tel que listé. Révoque uniquement un appareil de la personne connectée (révoquer le jeton courant équivaut à `auth/logout`). **200** `{ "data": { "revoked": true, "id": "…" } }` ; 404 `not_found` (inconnu ou d'un autre compte) ; 401. Journal `auth.session_revoked`.

## 4. Divers

### `GET health`

**200** `{ "data": { "status": "ok", "version": "1.0" } }` — sans authentification.

## 5. Parcours Flutter de référence

```
register ──201 token──► (accès limité) ──otp/verify signup──► email_verified
login ──202 otp_sent──► otp/verify login ──200 token + device_token──► me
login + device_token ──200 token──► me                (appareil reconnu, Q13 b)
password/forgot ──202──► otp/verify reset ──reset_token──► password/reset ──200──► login
```

Journal d'audit (`audit_logs`) : `auth.registered`, `auth.authenticated`, `auth.token_issued`, `auth.otp_verified`, `auth.email_verified`, `auth.password_reset_requested`, `auth.password_reset`, `auth.logout`, `auth.session_revoked`, `auth.failed`, `auth.refused_suspended`, `auth.refused_maintenance`. Aucune valeur sensible (mot de passe, code, jeton) n'y est écrite.

## 6. Familles à venir

`me/settings`, `me/security`, `me/newsletter`, `me/push-subscriptions`, `me/export`, `DELETE me` (P6), `feed/`, `posts/` (P7), `conversations/`, `groups/`, `events/`, `forums/` (P8), `thematiques/`, `ressources/`, `urgences/`, `zones/` (P4-P5), `signalements/`, `sos/` (P5), `pair/`, `sync/` (P9), `notifications/` (P3-P6). Chaque famille complète ce document à sa livraison.
