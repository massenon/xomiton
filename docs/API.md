---
name: xomiton-api
description: Référence de l'API REST /api/v1 de Xomiton (Sanctum) — conventions de réponse et d'erreur, limitation de débit, puis chaque endpoint (méthode, URL, corps, réponses, codes d'erreur). Lots « api-auth » (P2-06, P2-12 : authentification, mon compte, mes appareils) et « api-fondation » (P5-09 : enveloppe, format d'erreur, idempotence, groupes de routes), « api-alertes » (P5-06 : signalements, SOS, urgences). À charger avant d'écrire un client Flutter ou Vue, ou d'ajouter une famille de routes.
---

# API `/api/v1`

Référence : [SOFTWARE_ARCHITECTURE.md](SOFTWARE_ARCHITECTURE.md) §4, [LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md](LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md) §9, `prototype_mobile/src/simulateur.html` (charges utiles attendues par Flutter), décisions Q8 c, Q12 c, Q13 b, Q30 a du [plan](IMPLEMENTATION_PLAN.md) §7.

## 1. Conventions

| Sujet | Règle |
|---|---|
| Base | `https://<domaine>/api/v1` — préfixe posé dans `bootstrap/app.php`, routes dans `routes/api.php` |
| Format | JSON uniquement (`Accept: application/json` recommandé ; toute requête `api/*` reçoit du JSON même sans cet en-tête) |
| Authentification | **Sanctum** : en-tête `Authorization: Bearer <token>` (Flutter) ou cookie de session (Vue, même domaine : mode SPA `statefulApi()` d'ADR-31, en-tête `X-XSRF-TOKEN` requis, 419 → `GET /sanctum/csrf-cookie` puis rejeu) |
| Succès | `{ "data": …, "meta"?: …, "links"?: … }` (§1.1) — jamais de modèle Eloquent brut (Resources `App\Http\Resources\*`) |
| Erreur | `{ "message": "…", "code": "…", "errors"?: { champ: [messages] }, "retry_after"?: secondes }` pour **tous** les statuts (§1.2) ; le 422 de validation garde `{ message, errors }` de Laravel et y ajoute `code: validation_failed` |
| Textes | français ; tutoiement sur le tunnel d'authentification (Q36) ; dans `lang/fr/auth.php` (`auth.api.*`), `lang/fr/errors.php` (`errors.api.*`, sans registre ; `errors.419.public` au vous, `errors.419.member` au tu), `lang/fr/validation.php` et `lang/fr.json` (résumé « (et :count autres erreurs) » du 422) ; export client `resources/js/lang/fr.json` par `php artisan lang:export` |
| Codes HTTP | 200, 201 (`Location` si connue), 202 (traitement accepté : code envoyé), 204, 400, 401, 403, 404, 405, 409, 410, 413, 415, 419, 422, 429, 500, 503 |
| Débit | `throttle:api` 60/min par compte ou IP sur tout `/api/v1` ; `throttle:login` 5/min par identifiant + IP sur `register`, `login`, `otp/verify`, `password/reset` ; `throttle:otp` 5/h par adresse sur `otp/send`, `password/forgot` ; `throttle:report` 10/h par IP (signalement) ; `throttle:tracking` 20/h par IP (suivi par code) ; `throttle:sos_owner` 10/min par IP et par alerte (`sos/{id}/cancel` · `safe`, §6.6) ; `throttle:assistant` 20/min par IP (`assistant/question`, §5 ; un compte connecté reste compté par IP) ; `throttle:sos` **jamais bloquant** : au-delà de 5 SOS par minute et par IP, journal + cloche admin `SosBurstDetected` (comptes `alerts.manage`, une par adresse et par quart d'heure), IP en empreinte HMAC seulement (`config/xomiton.php` → `rate_limits`, `sos_log_per_minute_per_ip`). Sur une route `idempotent`, le limiteur nommé passe par l'option `limit=<nom>` : un rejeu ne consomme pas le quota (§1.3). Réponse 429 : `code: too_many_requests`, en-têtes `Retry-After`, `X-RateLimit-*` |
| Groupes | `routes/api.php` en deux groupes (ADR-31, §1.4) : public anonyme (limiteurs nommés) et `auth:sanctum` |
| Idempotence | en-tête `Idempotency-Key` (UUID) sur `POST signalements`, `POST sos`, `POST sync/push` (§1.3, ADR-32) |

### 1.1 Enveloppe `{ data, meta, links }`

`App\Http\Controllers\Api\V1\ApiController` (P5-09) : `respond(resource|tableau, statut, meta, links)`, `created(…, location)` (201 + `Location`), `accepted()` (202), `noContent()` (204), `paginated(paginator, Resource::class)`. `meta` et `links` sont omis quand ils sont vides. Comme `JsonResource::toResponse()`, `respond()` fusionne `with()` et `additional()` de la Resource (`meta` / `links` passés à `respond()` prioritaires, autres clés au premier niveau). Les liens de `paginated()` gardent les filtres de la requête (`withQueryString()`).

| Pagination | `meta` | `links` |
|---|---|---|
| par page (listes d'administration) | `current_page`, `last_page`, `per_page`, `total` | `first`, `last`, `prev`, `next` |
| par curseur (fil, messages : P6-01) | `per_page`, `next_cursor`, `prev_cursor` | `prev`, `next` |

### 1.2 Codes d'erreur stables (`code`)

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
| `too_many_requests` | 429 | limiteur nommé (`retry_after`) ; `abort(429)` (`retry_after` = `Retry-After`, sinon 60) | `auth.throttle` · `errors.api.throttled` |
| `not_found` | 404 | route ou ressource inconnue | `auth.api.not_found` |
| `method_not_allowed` | 405 | mauvaise méthode (route existante ou `abort(405)`) | `auth.api.method_not_allowed` |
| `validation_failed` | 422 | Form Request ou `validate()` refusé (`errors` par champ, `message` = premier message + « (et :count autres erreurs) ») | `lang/fr/validation.php`, `lang/fr.json` |
| `conflict` | 409 | conflit générique (`abort(409)`, conflit de champ de `sync/push`, P9-06) | `errors.api.conflict` |
| `session_expired` | 419 | jeton XSRF absent ou périmé (Vue en mode SPA, assistant de l'accueil) → `GET /sanctum/csrf-cookie` puis rejeu | `errors.419.public.text` (groupe public, vous) · `errors.419.member.text` (route `auth:sanctum`, tu) |
| `bad_request` · `gone` · `payload_too_large` · `unsupported_media_type` | 400 · 410 · 413 · 415 | requête mal formée, ressource retirée, corps trop gros, format refusé | `errors.api.*` |
| `http_error` | autre 4xx | statut HTTP sans code dédié | `errors.api.http` |
| `server_error` | 500 | erreur interne, **sans détail** hors débogage (l'exception est journalisée) | `errors.api.server` |
| `maintenance` | 503 | maintenance applicative (RG-14) ou `artisan down` (`retry_after` si connu) | `errors.api.maintenance` |
| `idempotency_key_required` | 422 | `Idempotency-Key` absent sur une route qui l'exige (`errors.idempotency_key`) | `errors.api.idempotency.*` |
| `idempotency_key_invalid` | 422 | `Idempotency-Key` qui n'est pas un UUID (`errors.idempotency_key`) | `errors.api.idempotency.*` |
| `idempotency_key_reused` | 422 | même clé, même route, même acteur, **corps différent** (`errors.idempotency_key` ; erreur du client, jamais un rejeu ; jamais 409, réservé au conflit de `sync/push` : ADR-32) | `errors.api.idempotency.*` |
| `idempotency_in_progress` | 429 | même clé encore en cours de traitement après 2 s d'attente (`retry_after`) : rejouable ; jamais sur `POST sos` (202 `processing`, §1.3) | `errors.api.idempotency.*` |

Rendu par `App\Exceptions\ApiExceptionRenderer` (enregistré dans `bootstrap/app.php` → `withExceptions`) pour toute requête `api/*` ou attendant du JSON ; le message brut d'une exception HTTP n'est jamais renvoyé (il peut être en anglais). Les exceptions métier héritent de `App\Domain\Shared\Exceptions\DomainException` (homonyme de la classe SPL `\DomainException` : toujours l'importer par `use` ; `errorCode()`, `status()` 422 par défaut, `userMessage()` depuis `lang/fr`, `errors()`, `extra()`, `headers()`) ; celles du tunnel d'authentification (`App\Domain\Auth\Exceptions\*`) gardent leur rendu propre (`status()`, `userMessage()`).

### 1.3 Idempotence (`Idempotency-Key`, ADR-32)

Middleware `idempotent` (`App\Http\Middleware\HandleIdempotencyKey`) : `idempotent` = en-tête facultatif, `idempotent:required` = obligatoire (`POST signalements`, `POST sos`, `POST sync/push`) ; options `nonblocking` (`POST sos`) et `limit=<limiteur>` (ex. `idempotent:required,limit=report` : le limiteur nommé ne s'applique qu'aux requêtes neuves ; un `throttle:<nom>` écrit à côté serait trié par Laravel **avant** `idempotent` et compterait les rejeux). Les clients (boîte d'envoi web `outbox.js`, Flutter) envoient l'UUID de l'entrée de boîte d'envoi et **le réutilisent à chaque rejeu, avec un corps identique** (aucun champ variable : ni compteur d'essais, ni `sent_at`).

| Cas | Réponse |
|---|---|
| Première requête | traitée normalement ; si 2xx, statut, corps, `Content-Type`, `Content-Language` et `Location` sont mémorisés |
| Même clé + même route (méthode + chemin) + même acteur (compte sur une route `auth:sanctum` ; sur une route publique, jamais le compte : jeton Bearer, sinon `guest`) + même corps | **réponse d'origine rejouée à l'identique** (même statut 200/201, même `tracking_code`), en-tête `Idempotent-Replayed: true`, action non réexécutée |
| Même clé, corps différent | 422 `idempotency_key_reused` (brouillon IETF *Idempotency-Key* ; jamais 409) |
| Même clé, requête d'origine encore en cours | attente jusqu'à `wait_seconds` (2 s, sondages de 100 ms doublés jusqu'à 500 ms) puis rejeu ; au-delà 429 `idempotency_in_progress` + `Retry-After` ; en mode `nonblocking` (`POST sos`, P2-08 : jamais de 429) 202 `{ "data": { "status": "processing", "retry_after": 1 } }` + `Retry-After` : la boîte d'envoi **garde l'entrée** et rejoue après le délai pour obtenir la réponse d'origine |
| Requête d'origine en échec (4xx, 5xx) | rien n'est mémorisé : la clé est libérée, le client corrige et renvoie avec la même clé |
| Sans en-tête | comportement normal (ou 422 `idempotency_key_required` en mode `required`) |
| GET, HEAD, OPTIONS | ignorés |

Empreinte du corps : HMAC-SHA256 (clé d'application) des champs triés par clé (fichiers : nom, taille, SHA-256 ; fichier refusé par PHP, ex. `UPLOAD_ERR_INI_SIZE` : nom et code d'erreur, puis 422 de validation). Stockage : table `idempotency_keys` (`DATABASE_DICTIONARY.md` §10) sans acteur ni chemin concret (portée HMAC, gabarit de route seulement : RG-01 / RG-02), en-têtes et corps **chiffrés**, jamais le cache fichier ; TTL `xomiton.idempotency.ttl_hours` (48 h, `XOMITON_IDEMPOTENCY_TTL_HOURS`), purge quotidienne `idempotency:purge` (03:00). Au-delà du TTL, la déduplication métier (`alerts.idempotency_key` unique, `client_uuid`) garantit encore l'absence de doublon.

### 1.4 Groupes de routes (ADR-31)

| Groupe | Middleware | Familles |
|---|---|---|
| 1. public, anonyme | `throttle:api` + un limiteur nommé sur chaque écriture | `health`, `zones`, `violence-types`, `needs`, `auth/*` (sauf `logout`), `assistant/question` (`throttle:assistant`, P4-01), `urgences`, `signalements` (`idempotent:required,limit=report`), `signalements/{code}` (`throttle:tracking`), `sos` (`idempotent:required,nonblocking,limit=sos` sans `throttle:api`), `sos/{id}`, `sos/{id}/cancel` · `safe` (`throttle:sos_owner`) — P5-06, §6 ; à venir : `thematiques`, `ressources` |
| 2. authentifié | `auth:sanctum` (jeton Bearer ou cookie de session SPA) | `auth/logout`, `me/*`, `signalements/mine` (P5-06) ; à venir : `feed/`, `conversations/`, `pair/`, `sync/`, `notifications/` |

Un test (`tests/Feature/Api/ApiFoundationTest.php`) refuse toute route publique hors des familles d'ADR-31 et toute écriture publique sans limiteur nommé (`throttle:<nom>` ou `idempotent:…,limit=<nom>`).

### 1.5 Objet `user` (`App\Http\Resources\UserResource`)

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

### `PATCH me/settings` — réglages et préférences de notification (P3-02)

Champs facultatifs ; seuls les champs présents sont modifiés (`App\Application\Auth\UpdateUserSettings`, journal `me.settings_updated` avec la liste des champs, jamais leurs valeurs).

| Champ | Règles |
|---|---|
| `theme` | `light` \| `dark` \| `auto` |
| `language` | locale active (`config('xomiton.locales')`, aujourd'hui `fr`) |
| `notification_prefs` | objet `{catégorie: {in_app?, email?, push?}}` ; catégories : `securite`, `sos`, `alertes`, `messages`, `communaute`, `evenements`, `moderation`, `systeme` ; catégorie ou canal inconnu → 422 ; fusion avec l'existant |
| `notification_digest` | `never` \| `daily` \| `weekly` — enregistré, **sans effet** tant que la commande `notifications:digest` (P3-07) n'est pas livrée |
| `mask_sensitive_notifications` | bool — texte neutre pour toute notification sensible (`isSensitive()`) |
| `sos_share_gps` | bool |
| `neutral_tab_title` | bool |

`securite` et `sos` sont **verrouillées** (`locked` dans `config/xomiton.php`) : une valeur `false` envoyée est ignorée et la réponse les renvoie à `true` (`App\Application\Shared\NotificationPreferences`).

**200** `{ "data": <user> }` (avec `settings` à jour) ; 422 par champ ; 401.

### `GET me/sessions` — « Appareils connectés »

**200** `{ "data": [ { "id": "token-12", "kind": "token", "name": "Pixel 7", "ip": null, "current": true, "last_active_at": "…", "created_at": "…" }, { "id": "web-3f9…", "kind": "web", "name": "Mozilla/5.0 …", "ip": "41.79.1.1", "current": false, "last_active_at": "…", "created_at": null } ], "meta": { "total": 2 } }`

`kind: token` = jeton Sanctum (Flutter, scripts) ; `kind: web` = session de navigateur en base (`SESSION_DRIVER=database`, D7). L'identifiant d'une session web est une **empreinte**, jamais l'identifiant de session lui-même (`App\Application\Auth\ConnectedDevices`).

### `DELETE me/sessions/{id}`

`id` = `token-<n>` ou `web-<empreinte>` tel que listé. Révoque uniquement un appareil de la personne connectée (révoquer le jeton courant équivaut à `auth/logout`). **200** `{ "data": { "revoked": true, "id": "…" } }` ; 404 `not_found` (inconnu ou d'un autre compte) ; 401. Journal `auth.session_revoked`.

## 4. Référentiels publics (P3-06) — sans authentification

`GET zones`, `GET violence-types`, `GET needs` : lecture depuis le cache serveur 24 h (`App\Application\Shared\Referentials`, invalidé à toute écriture d'un référentiel par `App\Observers\ReferentialObserver`). Réponses `Cache-Control: public, max-age=3600` (`xomiton.referentials.http_max_age`) et `ETag` dérivé du contenu ; `If-None-Match` identique → **304** sans corps. Seules les entrées actives sortent, triées par `position`.

| Route | `data[]` |
|---|---|
| `GET zones` | `{ id, name, slug, position, communes: [{ id, name, slug, position }] }` (départements et communes actives) |
| `GET violence-types` | `{ id, slug, label, icon, description, position }` |
| `GET needs` | `{ id, slug, label, routed_role, position }` (`routed_role` : rôle d'annuaire qui reçoit l'alerte, ou `null`) |

**200** `{ "data": [ … ], "meta": { "count": n } }`.

## 5. Divers

### `GET health`

**200** `{ "data": { "status": "ok", "version": "1.0" } }` — sans authentification.

### `POST assistant/question` — assistant public (P4-01, Q41 a, Q55)

Offcanvas de l'accueil (`<x-assistant-offcanvas>`, `resources/js/public/assistant.js`) ; même contrat pour Vue et Flutter, P8-11 l'étendra (membre, historique). Groupe public, `throttle:api` + `throttle:assistant` (20/min **par IP**, `rate_limits.assistant_per_minute`). `App\Application\Shared\KnowledgeBaseAnswerer` : garde-fous de crise d'abord, puis base `assistant_kb`, sinon repli. **Aucun échange enregistré**, même connecté·e ; seul `hits_count` de l'entrée appariée augmente.

Corps : `{ "question": "Comment porter plainte ?" }` — chaîne de 2 à 500 caractères (`AssistantQuestionRequest`).

**200** `{ "data": { "text", "kind", "emergency", "suggestions", "links" } }` (`AssistantAnswerResource`) :

| Champ | Contenu |
|---|---|
| `text` | réponse prête à afficher, retours à la ligne `\n` (bulle `white-space: pre-line`, jamais du HTML) ; registre tu (Q56) |
| `kind` | `kb` (entrée de la base) · `crisis` (mot-clé de `xomiton.assistant.crisis_keywords`) · `fallback` (rien d'assez proche) |
| `emergency` | `true` seulement pour `crisis` |
| `suggestions` | jusqu'à 3 questions de la base (proches, sinon les plus consultées) ; l'offcanvas ne les affiche que pour `fallback` |
| `links` | `[]` sauf `crisis` : `[{ label, url, variant }]` dans cet ordre — SOS (`danger`), un `tel:` par numéro de `settings.emergency_numbers` (sinon config), « Exprimer un besoin » (`/signaler?tab=besoin`), signalement (`/signaler`), contacts d'urgence (`/urgences`), tous `primary-soft` |

Réponse de crise publique : numéros en clair, SOS et signalement **sans URL dans le texte** (ce sont les `links`), renvoi vers « Exprimer un besoin » (lu sous 24 h) et **aucune promesse de rappel** : rien n'est enregistré, personne ne peut recontacter (`VOICE_AND_TONE.md` §3 règle 6). Pas de `matched_kb_id` ni de `conversation_id`.

Erreurs : **422** `validation_failed` (`errors.question`) ; **429** `too_many_requests` (`Retry-After`, `retry_after`, message `auth.throttle`) ; **419** `session_expired` sur le chemin stateful seulement (Referer ou Origin d'un domaine `SANCTUM_STATEFUL_DOMAINS`, cookie de session) sans `X-XSRF-TOKEN` valide → `GET /sanctum/csrf-cookie` puis un seul rejeu ; un client Bearer ou sans cookie n'est pas concerné. Hors réseau, l'offcanvas n'appelle pas l'API : texte `components.assistant.offline` (premier numéro d'urgence) avec un lien `tel:` et `/urgences`. Tests : `tests/Feature/Api/AssistantQuestionTest.php`.

## 6. Alertes : signalements, SOS, urgences (P5-06)

Groupe public (ADR-31), liste blanche de maintenance (RG-01, P2-11). Contrôleurs `App\Http\Controllers\Api\V1\Alerts\*`, cas d'usage `App\Application\Alerts\*` (P5-02), Form Requests `App\Http\Requests\Alerts\*` **partagées avec les pages `/signaler` et `/sos`** (P5-04, P5-05 ; Q11 a : même charge utile web et mobile). Réponses propres à la personne en `Cache-Control: no-store, private`. `source` de l'alerte : `web` si `Referer` / `Origin` est un domaine `SANCTUM_STATEFUL_DOMAINS` (boîte d'envoi `outbox.js` des pages Blade : chemin stateful, donc `X-XSRF-TOKEN` exigé, 419 → `GET /sanctum/csrf-cookie` puis un rejeu avec la même clé), `app` sinon (Flutter) ; `sync-offline` reste réservé à `sync/push` (P9-06).

### 6.1 `POST signalements` — signalement ou besoin, anonyme par défaut

`idempotent:required,limit=report` : en-tête `Idempotency-Key` (UUID) obligatoire, 10 envois **neufs** par heure et par IP (`rate_limits.report_per_hour_per_ip`), un rejeu n'est jamais compté (§1.3). JSON, ou `multipart/form-data` avec des preuves. Connecté·e (Bearer ou cookie), `anonymous: false` rattache l'alerte au compte (« Mes signalements ») ; sinon `user_id` reste vide (RG-01 / RG-02).

| Champ | Règle (`StoreReportRequest`) |
|---|---|
| `type` | `report` (signalement) · `need` (besoin) — Q29 ; le SOS a sa route |
| `category` | slug de `GET violence-types` actif, **obligatoire** pour `report` |
| `description` | 20 à 4000 caractères (`xomiton.alerts.description_min` / `_max`) |
| `occurred_on` | date facultative, jamais future |
| `needs[]` | slugs de `GET needs` actifs, distincts, 10 au plus ; **obligatoires** pour `need` (FR-ALT-02) |
| `zone_id` · `commune_id` | département actif obligatoire ; commune facultative, du même département |
| `lat` · `lng` · `accuracy` | position facultative (les deux ou aucune), précision en mètres |
| `anonymous` · `contactable` | booléens, `true` / `false` par défaut |
| `contact` · `preferred_channel` | exigés seulement si `contactable` : téléphone béninois ou e-mail ; `sms` · `whatsapp` · `call` · `email` |
| `attachments[]` | 5 fichiers au plus (image, PDF, audio ; `MediaUploader`, disque privé, métadonnées retirées) ; supprimés si l'envoi échoue ou s'il s'agit d'un rejeu (`SubmitReport`) |

**201** `{ "data": { "id", "tracking_code", "tracking_code_display", "type", "status", "status_label", "status_text", "priority", "created_at", "acknowledged", "acknowledged_at", "cancelled", "safe", "can_cancel_until", "routed", "history" } }` (`AlertResource`) + `Location: /api/v1/signalements/{tracking_code}`. `routed` : `{ role }`, plus `name`, `phone`, `whatsapp` si la fiche est publique ; jamais le récit, le contact, la position ni la clé. Rejeu de la même clé avec le même corps : **même 201**, même `tracking_code`, `Idempotent-Replayed: true`.

Erreurs : **422** `validation_failed`, `idempotency_key_required`, `idempotency_key_invalid`, `idempotency_key_reused` (même clé, corps différent) ; **409** `idempotency_key_reused` (clé déjà prise par une alerte d'un autre type, P5-02) ; **429** `too_many_requests` (`Retry-After`).

### 6.2 `GET signalements/mine` — `auth:sanctum`

Alertes **non anonymes** du compte (signalements, besoins et SOS), la plus récente d'abord (`ListAlertsOfUser`) ; un signalement anonyme ne se retrouve que par son code. Filtres : `type` (`report` · `need` · `sos`), `per_page` (1 à 50, 20 par défaut), `page`. **200** pagination par page (§1.1) : `data[]` (`AlertResource`, `history[]` = changements de statut `{ status, at }` du plus ancien au plus récent), `meta { current_page, last_page, per_page, total }`, `links { first, last, prev, next }` (filtres conservés). **401** `unauthenticated`.

### 6.3 `GET signalements/{code}` — suivi sans compte

`throttle:tracking` (20/h par IP). Saisie tolérante (`SIG-` facultatif, minuscules, espaces et tirets ignorés). **200** `{ "data": { "tracking_code_display", "type", "status", "status_label", "status_text", "received_at", "updated_at", "acknowledged", "routed_role", "history": [{ "status", "status_label", "at" }] } }` (`AlertTrackingResource` : rôle du contact seulement, jamais son nom). **404** `not_found` **neutre**, même message pour un code mal formé, inconnu, annulé ou clos depuis plus de 90 jours (`alerts.report.tracking.not_found`). **429** `too_many_requests`.

### 6.4 `POST sos` — jamais de 429

`withoutMiddleware('throttle:api')` + `idempotent:required,nonblocking,limit=sos` : aucun refus de débit (au-delà de 5/min par IP, journal + cloche admin) ; même clé pendant que l'envoi d'origine est en cours → **202** `{ "data": { "status": "processing", "retry_after": 1 } }`, la boîte d'envoi rejoue (§1.3). Corps facultatif (`StoreSosRequest`) : `message` (≤ 1000), `phone` (béninois), `share_location`, `lat` · `lng` · `accuracy` (ignorés si `share_location = false`), `zone_id` · `commune_id` (repli manuel), `needs[]`. Créé par `TriggerSos` : critique, besoin `securite`, routage RG-04, notifications dans la requête (P5-03).

**201** état du SOS (`SosStatusResource`) : champs d'`AlertResource` (§6.1) + `sos_token` (SOS **anonyme** seulement, rendu aussi aux rejeux : à garder sur l'appareil, en-tête `X-Sos-Token` des appels suivants, Q52 b) + `notified[]` + `primary_contact` + `meta.poll_interval_seconds` ; `Location: /api/v1/sos/{id}`. Erreurs : **422** `validation_failed`, `idempotency_key_*`.

### 6.5 `GET sos/{id}` — statut et personnes prévenues

Hors limiteur global `api` (un CGNAT ne bloque jamais le sondage) : `throttle:sos_status` (30/min par IP et par alerte, `rate_limits.sos_status_per_minute`). Réservé au compte propriétaire (Bearer ou cookie) ou au porteur de `X-Sos-Token` (`ShowSosRequest`, `ActsAsSosOwner`) : **403** `forbidden` sinon (l'ULID seul ne suffit pas ; un SOS identifié n'accepte pas le jeton seul, le jeton d'un SOS ne vaut pas pour un autre) ; **404** `not_found` pour un identifiant inconnu ou une alerte qui n'est pas un SOS. **200** même charge que la création, sans `sos_token`. Sonder toutes les `meta.poll_interval_seconds` (10 s, `xomiton.alerts.sos_poll_seconds`) tant que la valeur n'est pas `null` (SOS annulé, en sécurité, traité, clos, ou envoyé depuis plus de `sos_poll_minutes`, 30 min).

`notified[]` (`App\Application\Alerts\Dispatch\NotifiedPeople`, lot P5-03, lignes `alert_notifications` du déclenchement et de l'escalade) : `{ key, kind, label, role, state, state_label, phone, seen_at, updated_at }` — `key` : empreinte opaque et stable d'un sondage à l'autre (jamais `user:<id>` ni `contact:<id>`) ; `kind` `focal` · `contact` · `team` · `trusted` ; `state` `seen` (a vu l'alerte) · `sms_sent` · `sent` · `pending` ; `phone` seulement pour une fiche publique ou le contact de confiance de la personne ; contact routé en tête. `primary_contact` : première personne dont le numéro peut être montré (bouton d'appel), `null` → appeler le 117.

### 6.6 `POST sos/{id}/cancel` · `POST sos/{id}/safe`

Même règle d'accès que §6.5 ; hors limiteur global `api`, `throttle:sos_owner` seulement (10/min par IP et par alerte, `rate_limits.sos_owner_per_minute`). Réponse **200** = état du SOS (§6.5). `cancel` : repentir pendant 60 s après l'envoi (`CancelSos`, Q39) → `cancelled: true`, `status: clos`, prévient les personnes alertées ; au-delà **422** `cancel_window_expired` ; déjà annulé : idempotent. `safe` : « Je suis en sécurité » (`MarkSafe`) → `safe: true`, `status: traite` (jamais `clos`) ; SOS annulé **422** `alert_closed` ; idempotent. Autre alerte qu'un SOS : **422** `not_sos`.

### 6.7 `GET urgences?zone=` — contacts d'urgence

Lecture publique en cache HTTP comme les référentiels (`Cache-Control: public, max-age=3600`, `ETag`, `If-None-Match` → **304**). **Même source que la page `/urgences`** (`App\Application\Alerts\Queries\ListEmergencyContacts`, P4-06 doit la réutiliser). **200** `{ "data": { "services": [{ "label", "number", "tel" }], "contacts": [{ "id", "name", "role", "role_label", "org", "phone", "whatsapp", "availability", "zone", "commune", "national" }] }, "meta": { "zone": { id, name } | null, "count", "truncated" } }`. `zone` facultatif (identifiant d'un département actif ; **422** sinon) : fiches du département puis fiches nationales (sans département ou point focal national) ; sans zone, toutes les fiches d'urgence (100 au plus, `meta.truncated: true` au-delà). **Fiches d'urgence** (Q19 : l'annuaire complet reste membre) : actives, `is_public` **et** rôle `point_focal` · `juriste` · `psychologue` ou point focal national ; jamais pair·e animateur·rice, responsable de réseau, personnel identifié, etc. Jamais l'e-mail, les notes ni le compte lié d'une fiche. Cache serveur versionné (comme les référentiels), invalidé par toute écriture sur l'annuaire (`DirectoryContactObserver`).

Écart consigné (P5-06) : ni `emergency_services` ni `directory_contacts.is_emergency` (annoncés par P1-05) n'existent ; `services` vient du paramètre `emergency_numbers` (sinon `xomiton.assistant.emergency_numbers`, même source que l'assistant §5) et `contacts` des fiches d'urgence ci-dessus (filtre par rôle en attendant `is_emergency`, suivi en P4-06 et Q59).

Tests : `tests/Feature/Api/Alerts/ReportApiTest.php`, `SosApiTest.php`, `EmergencyApiTest.php`.

## 7. Parcours Flutter de référence

```
register ──201 token──► (accès limité) ──otp/verify signup──► email_verified
login ──202 otp_sent──► otp/verify login ──200 token + device_token──► me
login + device_token ──200 token──► me                (appareil reconnu, Q13 b)
password/forgot ──202──► otp/verify reset ──reset_token──► password/reset ──200──► login
```

Journal d'audit (`audit_logs`) : `auth.registered`, `auth.authenticated`, `auth.token_issued`, `auth.otp_verified`, `auth.email_verified`, `auth.password_reset_requested`, `auth.password_reset`, `auth.logout`, `auth.session_revoked`, `auth.failed`, `auth.refused_suspended`, `auth.refused_maintenance`. Aucune valeur sensible (mot de passe, code, jeton) n'y est écrite.

## 8. Familles à venir

`me/security`, `me/newsletter`, `me/push-subscriptions`, `me/export`, `DELETE me` (P6), `feed/`, `posts/` (P7), `conversations/`, `groups/`, `events/`, `forums/` (P8), `thematiques/`, `ressources/` (P4), `pair/`, `sync/` (P9), `notifications/` (P3-P6). `signalements/`, `sos/`, `urgences/` : livrés (§6). Chaque famille complète ce document à sa livraison.
