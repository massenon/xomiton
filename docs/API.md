---
name: xomiton-api
description: Référence de l'API REST /api/v1 de Xomiton (Sanctum) — conventions de réponse et d'erreur, limitation de débit, puis chaque endpoint (méthode, URL, corps, réponses, codes d'erreur). Lots « api-auth » (P2-06, P2-12 : authentification, mon compte, mes appareils) et « api-fondation » (P5-09 : enveloppe, format d'erreur, idempotence, groupes de routes), « api-alertes » (P5-06 : signalements, SOS, urgences), « api-me » (P6-01, P6-06, P6-07, P6-09 : lectures conditionnelles, Sanctum SPA, mon compte, cloche, appareils et abonnements push), « fil-domaine-api » (P7-01, P7-02, P7-07 : fil, publications, sondages, commentaires, signalement de contenu, partage par lien), « api-mobile-lecture » (P6-08 : tableau de bord mobile, thématiques, ressources et téléchargement signé, publications d'un profil). À charger avant d'écrire un client Flutter ou Vue, ou d'ajouter une famille de routes.
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
| Débit | `throttle:api` 60/min par compte ou IP sur tout `/api/v1` ; `throttle:login` 5/min par identifiant + IP sur `register`, `login`, `otp/verify`, `password/reset` ; `throttle:otp` 5/h par adresse sur `otp/send`, `password/forgot` ; `throttle:report` 10/h par IP (signalement) ; `throttle:tracking` 20/h par IP (suivi par code) ; `throttle:sos_owner` 10/min par IP et par alerte (`sos/{id}/cancel` · `safe`, §6.6) ; `throttle:assistant` 20/min par IP (`assistant/question`, §5 ; un compte connecté reste compté par IP) ; `throttle:account_sensitive` 5/h par compte (`DELETE me`, `POST me/export`, §3) ; `throttle:feed_write` 10/min par compte (`POST posts`, `PATCH posts/{ulid}`, `POST posts/{ulid}/comments`, §8) ; `throttle:content_report` 20/h par compte (signalement de contenu, §8) ; `throttle:community_write` 20/min par compte (créer, rejoindre, quitter un groupe, décider d'une demande, suivre une page, §13-14) ; `throttle:resource_download` 60/min par IP, route et ressource (`ressources/{id}/download`, §11, comme `/ressources/{slug}/telecharger`) ; `throttle:sos` **jamais bloquant** : au-delà de 5 SOS par minute et par IP, journal + cloche admin `SosBurstDetected` (comptes `alerts.manage`, une par adresse et par quart d'heure), IP en empreinte HMAC seulement (`config/xomiton.php` → `rate_limits`, `sos_log_per_minute_per_ip`). Sur une route `idempotent`, le limiteur nommé passe par l'option `limit=<nom>` : un rejeu ne consomme pas le quota (§1.3). Réponse 429 : `code: too_many_requests`, en-têtes `Retry-After`, `X-RateLimit-*` |
| Groupes | `routes/api.php` en deux groupes (ADR-31, §1.4) : public anonyme (limiteurs nommés) et `auth:sanctum` |
| Idempotence | en-tête `Idempotency-Key` (UUID) sur `POST signalements`, `POST sos`, `POST sync/push` (§1.3, ADR-32) |
| Lectures conditionnelles | `ETag` + `If-None-Match` → **304** sur toute lecture 200 du groupe `auth:sanctum` (§1.6, P6-01) |
| Appareil | en-tête `X-Device-Id: <device_uuid>` (appareil enregistré par `POST me/devices`, §3) sur les requêtes Flutter authentifiées : rattache SOS et signalements non anonymes à `sync_devices` (P6-09) |

### 1.1 Enveloppe `{ data, meta, links }`

`App\Http\Controllers\Api\V1\ApiController` (P5-09) : `respond(resource|tableau, statut, meta, links)`, `created(…, location)` (201 + `Location`), `accepted()` (202), `noContent()` (204), `paginated(paginator, Resource::class)`. `meta` et `links` sont omis quand ils sont vides. Comme `JsonResource::toResponse()`, `respond()` fusionne `with()` et `additional()` de la Resource (`meta` / `links` passés à `respond()` prioritaires, autres clés au premier niveau). Les liens de `paginated()` gardent les filtres de la requête (`withQueryString()`).

| Pagination | `meta` | `links` |
|---|---|---|
| par page (listes d'administration) | `current_page`, `last_page`, `per_page`, `total` | `first`, `last`, `prev`, `next` |
| par curseur (fil, messages : P6-01) | `per_page`, `next_cursor`, `prev_cursor` | `prev`, `next` |

Taille de page : `?per_page=` borné à `xomiton.api.max_per_page` (50) par `ApiController::perPage()` (valeur absente ou invalide → défaut de la route, 20) ; une Form Request qui valide `per_page` répond 422 au-delà. Curseur : `?cursor=<meta.next_cursor>` (opaque, à renvoyer tel quel).

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
| `age_confirmation_required` | 422 | case « J'ai 15 ans ou plus » (`age_confirmed`) absente ou fausse à l'inscription (Q68 ; `errors.age_confirmed`) | `auth.age_confirmation_required` |
| `invalid_credentials` | 422 | identifiant inconnu **ou** mot de passe faux (message indifférencié, `errors.login`) | `auth.failed` |
| `otp_invalid` | 422 | code faux, expiré, épuisé (5 essais), adresse inconnue, preuve de réinitialisation invalide (`errors.code` ou `errors.reset_token`) | `auth.otp.invalid` |
| `otp_too_soon` | 429 | renvoi demandé avant 30 s (`retry_after`) | `auth.otp.too_soon` |
| `otp_rate_limited` | 429 | plus de 5 codes par heure pour cette adresse et cet usage (`retry_after`) | `auth.otp.rate_limited` |
| `too_many_requests` | 429 | limiteur nommé (`retry_after`) ; `abort(429)` (`retry_after` = `Retry-After`, sinon 60) | `auth.throttle` · `errors.api.throttled` |
| `not_found` | 404 | route ou ressource inconnue | `auth.api.not_found` |
| `method_not_allowed` | 405 | mauvaise méthode (route existante ou `abort(405)`) | `auth.api.method_not_allowed` |
| `validation_failed` | 422 | Form Request ou `validate()` refusé (`errors` par champ, `message` = premier message + « (et :count autres erreurs) ») | `lang/fr/validation.php`, `lang/fr.json` |
| `conflict` | 409 | conflit générique (`abort(409)`) ; conflit de champ d'un élément de `sync/push` : `status: 409` **dans l'élément**, lot en 200 (§21.2, P9-06) | `errors.api.conflict` · `sync.items.conflict` |
| `session_expired` | 419 | jeton XSRF absent ou périmé (Vue en mode SPA, assistant de l'accueil) → `GET /sanctum/csrf-cookie` puis rejeu | `errors.419.public.text` (groupe public, vous) · `errors.419.member.text` (route `auth:sanctum`, tu) |
| `bad_request` · `gone` · `payload_too_large` · `unsupported_media_type` | 400 · 410 · 413 · 415 | requête mal formée, ressource retirée, corps trop gros, format refusé | `errors.api.*` |
| `http_error` | autre 4xx | statut HTTP sans code dédié | `errors.api.http` |
| `server_error` | 500 | erreur interne, **sans détail** hors débogage (l'exception est journalisée) | `errors.api.server` |
| `maintenance` | 503 | maintenance applicative (RG-14 ; `retry_after`, `ends_at`, `overdue`, `timezone`, `maintenance_message`, en-tête `X-Xm-Maintenance`, §5 `GET status`) ou `artisan down` (`retry_after` si connu) | `errors.api.maintenance` |
| `idempotency_key_required` | 422 | `Idempotency-Key` absent sur une route qui l'exige (`errors.idempotency_key`) | `errors.api.idempotency.*` |
| `idempotency_key_invalid` | 422 | `Idempotency-Key` qui n'est pas un UUID (`errors.idempotency_key`) | `errors.api.idempotency.*` |
| `idempotency_key_reused` | 422 | même clé, même route, même acteur, **corps différent** (`errors.idempotency_key` ; erreur du client, jamais un rejeu ; jamais 409, réservé au conflit de `sync/push` : ADR-32) | `errors.api.idempotency.*` |
| `idempotency_in_progress` | 429 | même clé encore en cours de traitement après 2 s d'attente (`retry_after`) : rejouable ; jamais sur `POST sos` (202 `processing`, §1.3) | `errors.api.idempotency.*` |
| `invitation_invalid` | 422 | lien / code d'invitation à l'équipe inconnu, expiré, déjà utilisé, annulé ou lié à une autre adresse (`errors.invitation`, §2, §23.6) | `auth.invitation_invalid` |
| `step_up_required` | 403 | action admin à fort impact sans jeton de confirmation valable (absent, expiré, déjà utilisé, d'un autre compte ; §23.1) | `admin.users.errors.step_up_required` |
| `maintenance_lockout` | 403 | `admin/maintenance/enable` par un compte qui perdrait l'accès (garde-fou P13-06, §23.8) | `maintenance.errors.lockout` |
| `conflict_already_resolved` · `conflict_not_mergeable` · `conflict_invalid_value` · `conflict_unknown_resolution` · `conflict_subject_missing` | 422 | `POST sync/conflicts/{id}/resolve` (§21.5) | `admin_system.sync.errors.*` |
| `device_revoked` | 403 | `POST me/devices` sur un appareil révoqué, **et toute requête authentifiée portant le `X-Device-Id` d'un appareil révoqué** (par la personne, l'équipe, `sync:prune` ou un ordre d'effacement à distance, §3) : l'application purge ses données locales et génère un nouvel UUID | `account.devices.revoked` |
| `wipe_not_requested` | 422 | `POST me/devices/{uuid}/wiped` pour un appareil que personne n'a marqué à effacer (§3) | `account.devices.wipe_not_requested` |
| `space_disabled` | 403 | espace Pair ou Admin coupé par l'interrupteur serveur (§24 bis) : `admin/*` et `pair/*` appelés avec le jeton de l'application ; l'application relit `GET me` | `account.spaces.disabled` |
| `device_taken` | 422 | `POST me/devices` avec l'UUID d'un appareil d'un autre compte (`errors.device_uuid`) | `account.devices.taken` |
| `device_required` | 403 | `sync/*`, `heartbeat` sans `X-Device-Id` d'un appareil enregistré du compte (§21, P9-06) | `sync.device.required` |
| `cursor_invalid` | 422 | `GET sync/pull?since=` illisible : relancer un tirage complet (`errors.since`, §21.3) | `sync.cursor_invalid` |
| `newsletter_bounced` · `newsletter_email_missing` · `newsletter_not_subscribed` | 422 | `PATCH me/newsletter` : adresse en rebond, compte sans e-mail, centres d'intérêt sans abonnement (§3, P6-06) | `account.newsletter.*` |
| `content_blocked` | 422 | texte refusé par le filtre de mots (`bad_words` « bloquer », §8.3) : `errors.<champ>`, message neutre sans le terme | `feed.api.content_blocked` |
| `poll_closed` | 422 | vote ou retrait sur un sondage terminé (§8.2) | `feed.poll.already_closed` |
| `group_last_admin` | 422 | `POST groups/{ulid}/leave` par la dernière admin du groupe (§13) | `community.groups.errors.last_admin` |
| `group_member_not_manageable` | 403 | geste sur la propriétaire ou sur un rôle égal ou supérieur (§13) | `community.groups.errors.not_manageable` |

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
| 1. public, anonyme | `throttle:api` + un limiteur nommé sur chaque écriture | `health`, `status` (P13-08), `zones`, `violence-types`, `needs`, `auth/*` (sauf `logout`), `assistant/question` (`throttle:assistant`, P4-01), `urgences`, `signalements` (`idempotent:required,limit=report`), `signalements/{code}` (`throttle:tracking`), `sos` (`idempotent:required,nonblocking,limit=sos` sans `throttle:api`), `sos/{id}`, `sos/{id}/cancel` · `safe` (`throttle:sos_owner`) — P5-06, §6 ; `thematiques`, `thematiques/{slug}`, `ressources`, `ressources/{id}/download` (`conditional` ; téléchargement `throttle:resource_download`) — P6-08, §11 |
| 2. authentifié | `auth:sanctum` (jeton Bearer ou cookie de session SPA) + `conditional` (§1.6) | `auth/logout`, `me/*` (§3), `notifications/*` (§7), `signalements/mine` (P5-06), `feed/`, `posts/*`, `comments/*` (§8, P7-02), `dashboard`, `users/{id}/posts` (§11, P6-08), `conversations/*` (§12, P8-01), `pair/*` (§22, P9-02), `sync/*`, `heartbeat` (§21, P9-06 ; `sync/push` sans `throttle:api`, `idempotent:required,limit=sync_push`) |

| 2 bis. administration mobile | `auth:sanctum` + `conditional` + `EnsureAdminMobile` (compte actif détenant `admin_mobile.access`, sinon **403**, **avant** la liaison des modèles de route : jamais de 404 qui révélerait un identifiant) + `throttle:admin_api` (120/min par compte) ; écritures `idempotent:required,limit=admin_write` (30/min) ; réponses `Cache-Control: no-store, private`, journal d'audit `source=mobile` | `admin/*` (§23, P15-16) |

Un test (`tests/Feature/Api/ApiFoundationTest.php`) refuse toute route publique hors des familles d'ADR-31 et toute écriture publique sans limiteur nommé (`throttle:<nom>` ou `idempotent:…,limit=<nom>`).

### 1.5 Objet `user` (`App\Http\Resources\UserResource`)

Vue « moi » (réponses d'inscription, de connexion, `GET me`) :

```json
{
  "id": 12, "pseudo": "Awa_S", "avatar_url": "https://…/avatar1.png",
  "status": "actif", "pending_validation": false,
  "role": "membre", "roles": ["membre"], "spaces": ["member"],
  "profile": {
    "pronouns": "elle", "member_type": "membre", "bio": null, "completion": 30, "visibility": "communaute",
    "zone": {"id": 1, "name": "Littoral"}, "commune": {"id": 3, "name": "Cotonou"},
    "age_range": null, "discreet_mode": false, "show_zone": true, "searchable": true,
    "trusted_contact_name": null, "trusted_contact_phone": null,
    "ally_status": null, "interests": ["sante", "droits"]
  },
  "created_at": "2026-10-17T10:00:00+01:00",
  "email": "awa@exemple.bj", "email_verified": false, "email_verified_at": null,
  "permissions": ["comments.create", "posts.create", "social.access", "…"],
  "settings": {"theme": "auto", "language": "fr", "notification_prefs": {…}, "notification_digest": "never",
               "mask_sensitive_notifications": false, "sos_share_gps": false, "pin_enabled": false, "neutral_tab_title": false,
               "sounds_enabled": true},
  "last_login_at": null
}
```

**Un seul compte, des rôles cumulables** (décisions client du 2026-10-29). `roles` liste **tous** les rôles du compte ; `role` est le plus étendu (`membre` < `pair` < `moderateur` < `admin` < `superadmin`). Un·e pair·e et un·e admin **restent membres** (réseau social) : un compte `pair` a `roles: ["membre", "pair"]`, un·e admin `["membre", "admin"]` (et `"pair"` s'il·elle l'est aussi). **`spaces`** (vue « moi » seulement) : espaces proposés au **sélecteur d'espace** du mobile — `member` (réseau social, tout compte), `peer` (rôle `pair`, compte actif avec `peer_space.access` ; un compte `pair` en attente reste en `member`), `admin` (`admin_mobile.access` : admin et super-admin, un·e modérateur·rice n'a pas d'espace Admin mobile). Un compte multi-rôle arrive toujours sur l'espace `member` (le fil) et ouvre les autres par un menu ; c'est de l'ergonomie, chaque route `pair/*` et `admin/*` revérifie permission, policy et scope (un 403 est la seule vérité : relire `GET me`). Les mêmes `spaces` sont dans les props Inertia partagées (`auth.user.spaces`, surface web : `admin` ⇔ `admin.access`).

Vue « autre compte » (futures routes `users/{id}`) : seuls `id`, `pseudo`, `avatar_url`, `status`, `pending_validation`, `role`, `roles`, `created_at` et un `profile` réduit (`pronouns`, `member_type`, `bio`, `completion`, `visibility`, `zone` / `commune` si `show_zone`). **Le nom réel, le téléphone, l'identité de genre et l'orientation ne sortent jamais** (RG-02).

| `status` | Sens |
|---|---|
| `actif` | accès normal (limité à lecture, SOS, signalement, profil tant que `email_verified` est faux) |
| `en_attente` | compte à valider par l'équipe (validation manuelle de tous les comptes, Q26, ou demande de pair, RG-03) : session ouverte, bandeau « compte en attente », collecte fermée |
| `suspendu` | connexion refusée (403 `account_suspended`) |

### 1.6 Lectures conditionnelles et Sanctum SPA (P6-01)

Middleware `conditional` (`App\Http\Middleware\HandleConditionalGet`), posé sur tout le groupe `auth:sanctum` : sur un `GET` / `HEAD` **200** au corps connu, `ETag` fort = empreinte xxh128 du corps (sauf ETag déjà posé), `Cache-Control: private, no-cache` (le client garde la réponse et la revalide ; aucun cache partagé), `Vary: Authorization, Cookie` ; `If-None-Match` identique → **304** sans corps — comparaison au suffixe `-gzip` / `-br` d'Apache `mod_deflate` (cPanel) et au `W/` près. Ignorés : écritures, erreurs, fichiers et flux, réponses `no-store` (suivi de signalement, `signalements/mine`, SOS, et les lectures sensibles `me`, `me/profile`, `me/security` : jamais gardées, même par le client d'un appareil partagé). Usage : Flutter (cache Hive) et la cloche Vue (polling 30 s de `notifications/unread-count`) renvoient le dernier `ETag` reçu. Les référentiels publics gardent leur cache public (`cache.headers:public;max_age=3600;etag`, §4) ; les contenus publics de l'application mobile (`thematiques`, `ressources`, §11) portent aussi `conditional` (revalidation à chaque lecture : une fiche programmée apparaît à son heure), sauf le lien de téléchargement signé (`no-store`).

Sanctum SPA (ADR-31, `statefulApi()` dans `bootstrap/app.php`) : une requête dont le `Referer` / `Origin` appartient à `SANCTUM_STATEFUL_DOMAINS` est authentifiée par le cookie de session, **sans jeton** ; toute écriture exige alors `X-XSRF-TOKEN` (cookie `XSRF-TOKEN`), sinon **419** `session_expired` (`errors.419.member.text`, au tu) → `GET /sanctum/csrf-cookie` puis un rejeu. Vérifié par `tests/Feature/Api/ApiConditionalAndSpaTest.php`.

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
| `age_confirmed` | requis, `true` (Q68 : « J'ai 15 ans ou plus », seuil `xomiton.accounts.min_age`) ; aucune date de naissance n'est collectée, seul l'horodatage est gardé (consentement `age_confirmed`). Sinon 422 `age_confirmation_required` |
| `phone` | facultatif, numéro béninois (`+229 01 XX XX XX XX`) |
| `invitation` | facultatif (≤ 40) : **code ou lien d'invitation à l'équipe** (pair, admin ; §23.6). L'inscription reste celle d'un membre (mêmes champs, case 15 ans comprise) ; le rôle est donné à la validation du compte (e-mail confirmé, ou approbation si la validation manuelle est active). Une invitation valable ouvre aussi l'inscription fermée au public. Code inconnu, expiré, déjà utilisé, annulé ou lié à une autre adresse → 422 `invitation_invalid` (`errors.invitation`), aucun compte créé |
| `device_name` | facultatif (≤ 100) : nom du jeton ; sinon navigateur ou « appareil » |
| `profile` | facultatif (phases 2-3) : `pronouns` (≤ 30), `zone_id` (requis avec `commune_id`), `commune_id` (dans le département), `member_type` (**`membre` seulement** : « pair » est refusé en 422, aucun rôle n'est auto-attribuable), `trusted_contact_phone` (béninois, chiffré), `trusted_contact_name` (≤ 80), `discreet_mode` (bool) |
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

Révoque le jeton de la requête (ou ferme la session web d'un client Vue en mode SPA). Corps facultatif `{ "push_token"?: "<jeton FCM>", "push_endpoint"?: "<endpoint Web Push>" }` : l'abonnement push de **cet appareil** est supprimé avec la session (P6-09 ; le serveur ne relie pas un jeton Sanctum à un abonnement, le client doit donc l'envoyer). **200** `{ "data": { "logged_out": true } }` ; 401 sans jeton. Journal `auth.logout` (une seule entrée : événement `Logout` émis par le garde `web` ou par le contrôleur pour un jeton).

## 3. Mon compte `me/*` — `auth:sanctum`

### `GET me`

**200** `{ "data": <user vue « moi »> }` (voir §1) : `roles[]` cumulés, `permissions[]`, `spaces[]` (`member`, `peer`, `admin`, le sélecteur d'espace de l'application ; un espace coupé par l'interrupteur serveur n'y figure plus, §24 bis) et, quand la requête porte un `X-Device-Id` du compte, `device` = `{device_uuid, revoked, wipe}` (`wipe` = ordre d'effacement à distance en attente d'accusé, `{requested_at, reason}`, sinon `null`, voir « Effacement à distance » plus bas). 401 `unauthenticated`.

### `GET me/profile` (P6-06)

**200** `{ "data": <profile de la vue « moi »> }` (objet `profile` du §1.5, avec `interests` et `ally_status`) ; 401.

### `PATCH me/profile` — phases 2-3 et profil progressif

Tous les champs sont facultatifs ; un champ absent **ou `null`** est laissé tel quel (le profil progressif ne vide rien par cette route, sauf `interests: []` et `ally_status: "aucun"`).

| Champ | Règles |
|---|---|
| `pronouns` | ≤ 30 |
| `zone_id` | département actif ; requis si `commune_id` est fourni |
| `commune_id` | commune active du département (`commune_in_zone`) |
| `member_type` | `membre` \| `pair` — `pair` place le compte `en_attente` et lui donne le rôle `pair` (RG-03), la session reste ouverte ; **ignoré** pour un compte du personnel (`moderateur`, `admin`, `superadmin` : ni rôle retiré, ni mise en attente, groupe marqué traité) |
| `trusted_contact_phone` | numéro béninois (stocké chiffré) |
| `trusted_contact_name` | ≤ 80 |
| `discreet_mode` | bool |
| `age_range` | `10-14` \| `15-17` \| `18-24` \| `25-34` \| `35-49` \| `50+` (`AgeRange`, P6-06) |
| `interests` | tableau (≤ 20) de slugs **distincts** de `newsletter_interests` (`sante`, `droits`, `bien-etre`, `evenements`, `ressources`, `securite-numerique`) ; `[]` vide la liste (P6-06) |
| `ally_status` | `allie` \| `ressource` \| `aucun` (efface) — Q10 c : stocké **chiffré** (`profiles.ally_status`), aucun droit associé, montré à un autre compte **seulement** s'il figure dans `shared_fields` (P6-06, P8-08) |
| `sensitive_consent` | bool (P8-08) — nouvelle ligne `consents` `sensitive_data` (jamais mise à jour, sans IP) ; `false` **efface** `gender_identity` et `orientation` et les retire de `shared_fields` |
| `gender_identity` | `femme_cis` \| `femme_trans` \| `homme_cis` \| `homme_trans` \| `non_binaire` \| `intersexe` \| `questionnement` \| `autre` \| `prefer_not` (efface) — stocké **chiffré** (`encrypted_columns.profiles`) ; une valeur exige un consentement `sensitive_data` en cours **ou** `sensitive_consent: true` dans la même requête, sinon **422** (`profile.progressive.sensitive_required`) ; un retrait dans la même requête l'emporte (P8-08) |
| `orientation` | `lesbienne` \| `bisexuelle` \| `pansexuelle` \| `queer` \| `asexuelle` \| `heterosexuelle` \| `questionnement` \| `autre` \| `prefer_not` (efface) — mêmes règles que `gender_identity` |
| `shared_fields` | tableau ⊂ `gender_identity`, `orientation`, `ally_status` : champs sensibles **montrés sur le profil** (choix explicite ; `[]` = aucun, défaut) ; `gender_identity` / `orientation` exigent le consentement (422 sur `shared_fields`) (P8-08) |
| `answered_groups` | tableau ⊂ `pronouns`, `member_type`, `ally_status`, `gender_identity`, `orientation`, `age`, `location`, `interests` : groupes du profil progressif traités sans valeur (« Je préfère ne pas le dire ») (P8-08) |
| `show_zone` | bool — « Afficher ma zone sur mon profil » (groupe Ville du bandeau) ; une autre personne ne voit jamais que le département (P8-08, Q80) |

`pronouns` et `age_range` acceptent aussi `prefer_not` (efface). Chaque champ renseigné marque son groupe comme traité (`profiles.progressive_answered`) ; la complétion ne compte jamais les champs sensibles. Réponse `no-store`.

**200** `{ "data": <user> }` (avec `profile.completion` recalculé) ; 422 par champ ; 401.

**Vue « moi » du profil** (`GET me`, `GET me/profile`, réponse du `PATCH me/profile`, P8-08 — jamais les réponses de connexion, d'inscription, d'OTP ni de `me/settings`) : en plus du §1.5, `gender_identity`, `orientation` (clés ci-dessus, déchiffrées, null sinon), `shared_fields`, `cover_url` et `progressive` = `{ groups: [{key, answered}], next, done, answered, total, sensitive_consent }` (ordre : pronoms, type de membre, statut allié·e, identité de genre, orientation, âge, ville, centres d'intérêt ; `next` = premier groupe ni renseigné ni traité).

### `POST me/profile/avatar` · `POST me/profile/cover` · `DELETE me/profile/{avatar|cover}` (P8-07)

Multipart `image` (collection `images` : JPEG, PNG, WebP, 5 Mo ; type réel lu dans le contenu) ; envoi : **e-mail confirmé** (`verified`, **403** sinon, comme toute publication) ; envoi et retrait : limiteur `profile_image` (20 / h par compte, **429**). Image ré-encodée par `MediaUploader` (orientation appliquée, **EXIF, ICC et XMP retirés**, WebP + repli + variantes) sur le disque public (adresse ULID), ancienne image supprimée **avec son repli et ses variantes** (de même à l'anonymisation du compte), complétion recalculée ; un visuel importé `assets/…` n'est jamais supprimé. **200** `{ avatar_url, cover_url, completion, has_avatar, has_cover }` ; 422 `image` ; 401.

### `GET me/activity` (P8-07)

**200** journal de la personne seule (`no-store`), 40 entrées au plus, plus récentes d'abord : `{ id, type, icon, label, detail, href, at }` ; types `login`, `email`, `password`, `settings`, `security`, `data`, `device`, `group`, `page`, `event` (journal d'audit, jamais l'IP ni le navigateur), `post` et `forum` (contributions **non anonymes**), `event` (« Je participe »), `group` (groupes rejoints), `invitation` (connexions acceptées).

### `GET me/settings` (P6-06)

**200** `{ "data": <settings de la vue « moi »> }` (§1.5, avec `sounds_enabled`) **+ les noms d'écriture du prototype mobile** `locale` (= `language`), `sounds` (= `sounds_enabled`), `notifications` (vrai si au moins une catégorie désactivable garde le push) ; `theme` est relu tel qu'enregistré (`auto` = `system` du prototype, accepté aussi en écriture) ; la ligne `user_settings` est créée avec les valeurs par défaut si elle manque ; 401.

### `PATCH me/settings` — réglages et préférences de notification (P3-02, P6-06)

Champs facultatifs ; seuls les champs présents sont modifiés (`App\Application\Auth\UpdateUserSettings`, journal `me.settings_updated` avec la liste des champs, jamais leurs valeurs).

| Champ | Règles |
|---|---|
| `theme` | `light` \| `dark` \| `auto` \| `system` (alias du prototype mobile « système », **enregistré et relu `auto`**) |
| `language` · `locale` | locale active (`config('xomiton.locales')`, aujourd'hui `fr`, Q40) ; `locale` = alias du prototype mobile ; 422 « Cette langue n’est pas encore disponible. » pour `fon`, `yo`, `en` |
| `sounds` · `sounds_enabled` | bool (P6-06, `user_settings.sounds_enabled`, défaut `true`) : sons de l'application ; le son du SOS reste actif pour qui le reçoit |
| `notifications` | bool (P6-06, interrupteur global du prototype mobile) : canal `push` de toutes les catégories désactivables ; `notification_prefs` envoyé dans la même requête l'emporte catégorie par catégorie |
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

`id` = `token-<n>` ou `web-<empreinte>` tel que listé. Révoque uniquement un appareil de la personne connectée (révoquer le jeton courant équivaut à `auth/logout`) ; les abonnements push enregistrés avec ce jeton (`push_subscriptions.token_id`) partent avec lui (P6-09). **200** `{ "data": { "revoked": true, "id": "…" } }` ; 404 `not_found` (inconnu ou d'un autre compte) ; 401. Journal `auth.session_revoked`.

### `DELETE me/sessions` — tous les autres appareils (P8-09)

Ferme tous les jetons Sanctum sauf le jeton courant (abonnements push en cascade) et toutes les sessions web sauf la session courante. **200** `{ "data": { "revoked": 3 } }` ; 401. Journal `auth.sessions_revoked_others` (nombre seulement).

### `PUT me/password` — changer de mot de passe connecté·e (P8-09)

`{ "current_password", "password", "password_confirmation" }` — actuel vérifié (`current_password:sanctum`), nouveau selon `PasswordStrength` (`StrongPassword`, même règles que la jauge), différent de l'actuel, `password_confirmation` requis et identique (`same:password` : l'erreur est rangée sous `password_confirmation`). Limiteur `account_sensitive`. Les **autres appareils sont toujours déconnectés** (`DELETE me/sessions`), l'accès courant reste ouvert (session web : identifiant régénéré ; cookie « se souvenir de moi » reposé avec le nouveau `remember_token` s'il était présent), l'e-mail système `mot-de-passe-modifie` est déposé dans `mail_outbox` (`EmailTemplateService::enqueue`, aucun SMTP pendant la requête). **200** `{ "data": { "changed": true, "revoked": 1 } }` `no-store` ; 422 par champ (`current_password` : « Le mot de passe actuel est incorrect. ») ; 429 ; 401. Journal `auth.password_changed`.

### `GET me/privacy` · `PATCH me/privacy` — confidentialité du profil (P8-09)

`{ "visibility": "public|community|followers|private", "show_zone": true, "searchable": true, "sos_share_gps": false, "sensitive_consent": false }` — chaque champ absent est laissé tel quel ; `searchable: false` retire le profil des suggestions « Qui suivre » et de la recherche membre (§20) ; `sensitive_consent` = consentement `sensitive_data` en cours (P8-08) : une réponse différente ajoute une ligne `consents` sans IP, un retrait efface identité de genre et orientation (et les retire de `shared_fields`). **200** même objet, `no-store` ; 422 valeur inconnue ; 401. Journal `me.settings_updated` (section `confidentialite`, noms des champs).

### `GET me/identity` · `PATCH me/identity` — section « Profil » de /parametres (P8-09)

`{ "name", "pseudo", "email" (lecture seule), "phone", "bio", "zone_id", "commune_id" }` — nom **facultatif** (`null` ou vide efface ; 2 à 120 caractères s'il est rempli ; jamais public, RG-02), pseudonyme unique sans casse (le sien excepté, règle `Pseudo`) : après un changement, l'ancien pseudonyme reste réservé à son compte `pseudo_reservation_days` (30 j) et un nouveau changement attend `pseudo_change_cooldown_hours` (24 h, 422 `pseudo` « Réessaie dans N h » ; la casse seule n'est pas un changement) ; téléphone béninois normalisé (`+229 01 …`, `null` efface), présentation ≤ 200 caractères (`profiles.bio`), commune du département envoyé (`CommuneInZone`) ou, sans `zone_id`, du département actuel du compte ; changer de département sans commune efface la commune ; `profiles.completion` recalculé (zone, commune, présentation). Limiteur `identity_update` (20 / h par compte). Les champs du profil progressif restent sur `PATCH me/profile`. **200** même objet, `no-store` ; 422 par champ ; 429 ; 401.

### `GET me/security` · `PATCH me/security` (P6-06, Q37)

Noms du prototype mobile (`simulateur.html` l.1260). **200** `{ "data": { "discreet": false, "mask_previews": false, "pin_enabled": false, "neutral_tab_title": false } }`.

| Champ (PATCH, facultatifs) | Colonne |
|---|---|
| `discreet` | `profiles.discreet_mode` — **l'activer active aussi `mask_previews` et `neutral_tab_title`**, sauf valeur contraire dans la même requête ; le désactiver ne touche à rien d'autre |
| `mask_previews` | `user_settings.mask_sensitive_notifications` (texte neutre des notifications sensibles, cloche et push) |
| `pin_enabled` | `user_settings.pin_enabled` — Q37 b : le code PIN reste sur l'appareil (stockage sécurisé) ; `pin`, `pin_code`, `pin_hash` → 422 (« Ton code PIN reste sur ton appareil : ne l’envoie jamais au serveur. ») ; tout `pin_hash` hérité est effacé |
| `neutral_tab_title` | `user_settings.neutral_tab_title` |

422 par champ ; 401. Journal `me.security_updated` (liste des champs, jamais les valeurs).

### `GET me/newsletter` · `PATCH me/newsletter` (P6-06)

**200** `{ "data": { "subscribed": true, "status": "actif", "pending_confirmation": false, "interests": ["sante"], "available_interests": [{ "slug": "sante", "label": "Santé" }, …], "confirmed_at": "…" } }` (`status` `null` = jamais abonné·e).

PATCH `{ "subscribed"?: bool, "interests"?: [slugs] }` (`App\Application\Account\MemberNewsletter`) :

| Cas | Effet |
|---|---|
| `subscribed: true`, e-mail du compte confirmé (OTP) | abonnement **actif tout de suite**, source `app`, ligne `consents` accordée, `consent_meta` sans IP — y compris pour une adresse désabonnée : c'est la personne elle-même (Q66 ne vise que les tiers) |
| `subscribed: true`, e-mail non confirmé | double opt-in public (e-mail `newsletter-confirmation`), `pending_confirmation: true` |
| adresse en rebond | 422 `newsletter_bounced` (jamais réactivée) |
| compte sans e-mail | 422 `newsletter_email_missing` |
| `subscribed: false` | désabonnement tracé (UnsubscribeFromNewsletter : `consents` refusé, audit) |
| `interests` sans abonnement | 422 `newsletter_not_subscribed` (`errors.interests`) |

Pas de fréquence : le schéma n'en a pas (prototype « mensuelle · bimensuelle ») — un champ `frequency` est ignoré.

### `POST me/export` — export RGPD (P6-06)

**202** `{ "data": { "status": "preparing", "link_valid_hours": 48, "message": "Ton export est en préparation. Tu recevras un e-mail avec le lien de téléchargement (valable 48 h)." } }`. Construit en file (`BuildPersonalDataExport`, `QUEUE_CONNECTION=database`, une tâche à la fois par compte) : fichier JSON **chiffré** (`Crypt::encryptString`, `.json.enc`) sur le disque privé (`storage/app/private/exports/personal/<compte>/`, un seul par compte), déchiffré en mémoire au téléchargement ; e-mail `export-donnees` avec un **lien signé** `GET /mes-donnees/export/{jeton}` (route **web**, `auth`, signature vérifiée par le contrôleur : il faut être connecté·e au même compte ; autre compte → 404, lien altéré ou expiré → 403 page « Lien expiré » avec « Redemander un export »), valable `xomiton.accounts.export_link_hours` (48 h), fichier effacé ensuite par `accounts:purge-exports` (03:30). Contenu (`format_version: 2`, `App\Application\Account\PersonalDataExport`) : compte, profil (données sensibles déchiffrées : droit d'accès ; chemins de l'avatar et de la couverture), réglages, consentements, publications (et médias), commentaires (ULID du post), j'aime, enregistrements, publications masquées de son fil (`hides`), votes, comptes et pages suivis, groupes, événements, invitations, conversations et messages **écrits par la personne**, sujets et réponses de forum, conversations avec l'assistant, albums, blocages, avertissements, signalements de modération déposés, alertes rattachées au compte (lieu, contact, besoins, pièces jointes), appareils, abonnements push (libellés), newsletter ; jamais mot de passe, jetons, endpoints, fichiers eux-mêmes, messages d'autrui, identité de qui signale ou avertit, notes internes (Q70). `throttle:account_sensitive` ; 401. Journal `account.export_requested`, `account.export_ready`, `account.export_downloaded`.

### `DELETE me` — suppression du compte (P6-06)

Corps `{ "password": "…" }` (mot de passe actuel, requis : un appareil resté ouvert ne suffit pas). **200** `{ "data": { "deleted": true, "erased_from": "2026-11-17T10:00:00+01:00", "message": "Ton compte est supprimé. Tes données seront effacées sous 30 jours." } }`.

Suppression logique (`users.deleted_at`) puis anonymisation par `accounts:anonymize` après `xomiton.accounts.deletion_grace_days` (30 j) ; **tout accès s'arrête tout de suite** : jetons Sanctum, sessions web, abonnements push et export en attente supprimés, appareils de synchronisation révoqués, newsletter désabonnée (motif « Compte supprimé », consentement retiré ; adresse effacée de la ligne et des consentements à l'anonymisation) ; e-mail `compte-supprime` par la boîte d'envoi (prévient la personne si quelqu'un d'autre a supprimé son compte). 422 `password` ; **403** `forbidden` pour un compte de l'équipe d'administration (`admin.access`, superadmin compris : `UserPolicy::deleteOwnAccount`, son rôle doit d'abord lui être retiré) ; 429 `throttle:account_sensitive` (compteur par compte **et par route** : DELETE me et POST me/export ne se bloquent pas l'un l'autre) ; 401. Journal `account.deletion_requested`. La restauration pendant les 30 jours passe par l'équipe (aucune route libre-service).

### `GET me/devices` · `POST me/devices` · `DELETE me/devices/{device_uuid}` (P6-09)

Appareils de l'application (`sync_devices`, utilisés par `alerts.device_id`, `sync_outbox_receipts`, `sync/pull`). POST `{ "device_uuid": "<UUID généré à l'installation>", "label": "Pixel 7", "platform"?: "Android 14", "app_version"?: "1.5.0" }` :

| Réponse | Quand |
|---|---|
| **201** + `Location` | appareil nouveau |
| **200** | déjà enregistré pour ce compte : libellé, plateforme, version et `last_seen_at` mis à jour (idempotent, à rappeler au lancement) |
| **403** `device_revoked` | appareil révoqué (par la personne, l'équipe ou `sync:prune`) : jamais réactivé, l'application génère un nouvel UUID |
| **422** `device_taken` (`errors.device_uuid`) | UUID déjà enregistré par un autre compte (téléphone partagé) : jamais réattribué, l'application génère un nouvel UUID |

`data` : `{ device_uuid, label, platform, app_version, revoked, last_seen_at, last_sync_at, created_at }`. DELETE → **204** (révoqué, jamais supprimé ; idempotent ; ses abonnements push `push_subscriptions.sync_device_id` sont supprimés) ; 404 si l'appareil n'est pas à moi. Ensuite, chaque requête authentifiée de l'application porte `X-Device-Id: <device_uuid>` : un SOS ou un signalement non anonyme reçoit `device_id` (appareil actif du même compte seulement ; un signalement anonyme ne consulte même pas l'appareil, RG-01 ; résolution en lecture seule sur ces routes : `last_seen_at` est tenu par POST me/devices et par `sync/push`, `sync/pull` et `heartbeat`, §21, `SyncDeviceResolver::touch`). Journal `device.registered`, `device.revoked` (libellé, jamais l'UUID).

#### Effacement à distance `POST me/devices/{device_uuid}/wiped` (P15-11)

Une personne de l'équipe (`device.wipe`, §23.9), **la personne elle-même** pour un de ses autres appareils (`POST me/devices/{device_uuid}/wipe`, ci-dessous) ou le **retrait du rôle pair** (`DELETE admin/members/{id}/roles/peer`, `PUT admin/members/{id}/role` vers `membre`, écran Utilisateurs & rôles : tous les appareils **actifs** de la personne) marque l'appareil « à effacer » : `sync_devices.wipe_requested_at` + `wipe_reason` (`admin`, `owner` ou `peer_role_removed`), et **révoque l'appareil dans le même geste** (`is_revoked`, abonnements push supprimés). Dès lors :

- **toute requête authentifiée portant son `X-Device-Id`** (middleware `EnsureDeviceNotRevoked`, groupes `auth:sanctum`, `admin/*` et `search/*`) répond **403 `device_revoked`** : l'application purge ses données locales et génère un nouvel UUID. Un en-tête absent, invalide, inconnu ou d'un autre compte ne déclenche rien. **SOS, signalements, urgences et référentiels (routes publiques) ne sont jamais bloqués** (RG-01) ;
- exceptions, pour qu'il puisse se mettre en règle : `POST me/devices/{uuid}/wiped`, `DELETE me/devices/{uuid}`, `POST me/devices` (rend le même 403), `POST auth/logout`, `DELETE me/push-subscriptions` ; et, **tant que l'accusé n'est pas arrivé**, `GET me` et `POST heartbeat`, qui portent l'ordre :
  - `GET me` → `data.device` = `{device_uuid, revoked, wipe}` (null sans `X-Device-Id` valable) ; `wipe` = `{requested_at, reason}` tant que l'ordre attend son accusé, sinon `null` ;
  - `POST heartbeat` (§21.6) → `{server_time, wipe}` seulement (aucune donnée, dernier contact non mis à jour) ;
- **effacement par la personne** `POST me/devices/{device_uuid}/wipe` (appareil perdu ou volé, depuis un autre appareil) : `{password}` (mot de passe actuel, **422** sinon), limiteur `account_sensitive` (5/h par compte), idempotent ; **202** l'appareil (`wipe`, `revoked: true`, `meta.already_requested: false`) ou **200** (`already_requested: true`, date et motif d'origine conservés) ; **404** si l'appareil n'est pas à la personne ; **422** `device_uuid` si c'est l'appareil de la requête (`X-Device-Id`) ; motif `owner`, journal `device.wipe_requested` ;
- **accusé** `POST me/devices/{device_uuid}/wiped` (jeton de la personne propriétaire, sans corps, `Cache-Control: no-store`) : l'appareil a vidé ses données. **200** `data` = l'appareil (`wipe: null`, `wiped_at`) ; **idempotent** (le premier accusé fixe `wiped_at`, les suivants rendent l'appareil tel quel, un seul journal `device.wiped`) ; **404** si l'appareil n'est pas à la personne ; **422 `wipe_not_requested`** si personne n'a demandé d'effacement. Après l'accusé l'ordre n'est plus lisible (l'appareil, révoqué, reçoit 403 `device_revoked` partout) ;
- `GET me/devices` expose aussi `wipe` (ordre en attente ou null), `wipe_requested_at` et `wiped_at`.

Limites assumées : un téléphone **éteint ou sans réseau** ne reçoit l'ordre qu'à son retour en ligne (l'expiration locale des données, 14 jours, et la révocation du jeton restent le filet) ; un appareil qui n'envoie pas `X-Device-Id` n'est pas reconnu — pour couper aussi l'accès du jeton, révoquer la session (`DELETE me/sessions/{id}`). Journal : `device.wipe_requested` (libellé et motif, jamais l'UUID) et `device.wiped`.

### `GET · POST · DELETE me/push-subscriptions` (P6-09, ADR-25)

| Type | Corps POST |
|---|---|
| Web Push (navigateur) | `{ "kind": "webpush", "endpoint": "https://…", "keys": { "p256dh": "…", "auth": "…" }, "device_label"?: "Chrome sur Windows" }` — forme de `PushSubscription.toJSON()` ; `public_key` / `auth_token` à plat acceptés |
| FCM (Flutter) | `{ "kind": "fcm", "token": "<jeton FCM>", "device_label"?: "Pixel 7" }` (après la 1re connexion et à chaque `onTokenRefresh`, P15-08) |

**201** + `Location` à la création, **200** si l'abonnement existait déjà pour ce compte. Un endpoint est **unique** (`endpoint_hash` SHA-256) : sur un appareil partagé, l'abonnement passe au compte qui vient de s'abonner (l'ancien n'y est plus notifié). `data` : `{ id, kind, fingerprint, device_label, last_used_at, created_at }` — **jamais l'endpoint, le jeton ni les clés** ; `fingerprint` = 12 premiers caractères de l'empreinte. GET : liste + `meta.vapid_public_key` (clé publique VAPID pour `pushManager.subscribe()`, `null` tant que `VAPID_PUBLIC_KEY` n'est pas configurée). DELETE `{ "endpoint" | "token", "kind"? }` → **204** toujours (déconnexion, `onTokenRefresh` : idempotent ; l'abonnement d'un autre compte n'est jamais touché). L'abonnement retient le jeton Sanctum de la requête (`token_id`) et l'appareil `X-Device-Id` (`sync_device_id`) : `DELETE me/sessions/{id}`, toute suppression du jeton ou `DELETE me/devices/{uuid}` le suppriment (un téléphone perdu n'est plus notifié). 422 : `kind` inconnu, endpoint non `https` ou hors des services push connus (`xomiton.notifications.push.webpush.allowed_hosts` : `fcm.googleapis.com`, `updates.push.services.mozilla.com`, `*.notify.windows.com`, `web.push.apple.com` ; SSRF), clés manquantes, jeton manquant. Journaux (`push.subscribed`, `push.unsubscribed`, `Log`) : type et empreinte tronquée seulement, **jamais l'endpoint ni le jeton en clair**. Remise : canaux `webpush` / `fcm` des `Notification` Xomiton (pilote `log` tant que VAPID / FCM ne sont pas configurés, Q7) ; abonnement disparu (404 / 410) supprimé à la remise.

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

### `GET status` — état public de la plateforme (P13-08)

Sans authentification, `throttle:api`, **servi pendant la maintenance** (liste blanche `EnsureNotInMaintenance`, RG-01). Sondé chaque minute par la page publique de maintenance (P13-07, réouverture puis rechargement), l'écran plein `XmMaintenance` de l'espace Vue et l'application mobile (P15). État calculé par `MaintenanceStatus::publicStatus()`, mis en cache **15 s** côté serveur (vidé par chaque action de l'écran `/admin/maintenance` et de la commande `xomiton:maintenance`), `Cache-Control: public, max-age=15`, `Retry-After` pendant une maintenance. Route **sans session** (exclue d'`EnsureFrontendRequestsAreStateful`, comme `GET zones` · `violence-types` · `needs` · `urgences`, elles aussi `Cache-Control: public`) : aucun `Set-Cookie`, même appelée depuis le site avec un `Referer` de même origine — un cache partagé (LSCache, proxy, CDN) ne peut jamais garder ni resservir une session ; la page l'appelle avec `credentials: 'omit'`.

**200** `{ "data": { "status": "ok" | "maintenance", "maintenance": { "active", "message", "starts_at", "ends_at", "retry_after", "overdue" }, "announcement": { "starts_at", "ends_at", "message" } | null, "emergency_numbers": [ { "label", "number" } ], "timezone", "server_time" } }` — `maintenance.*` nuls hors maintenance (`overdue` faux) ; `ends_at` = reprise **estimée** (activation immédiate : la plateforme ne rouvre qu'à la désactivation ; fenêtre planifiée : fin automatique), nul une fois passée ; `overdue` = reprise annoncée dépassée, maintenance toujours active (le client affiche « La reprise prend un peu plus de temps que prévu », jamais une heure passée ni un compte à rebours à zéro) ; `timezone` = fuseau d'affichage des heures (`xomiton.display_timezone`, `Africa/Porto-Novo`) : les dates restent en ISO 8601 UTC, le client les formate dans ce fuseau pour montrer les mêmes heures que la page publique ; `announcement` = fenêtre planifiée entrée dans son délai d'annonce (bandeau des gabarits) ; `emergency_numbers` = même source que `GET urgences` (paramètre `emergency_numbers`, sinon `xomiton.assistant.emergency_numbers`) ; `server_time` corrige l'horloge de l'appareil pour le compte à rebours. Dates ISO 8601.

**Réponse 503 `maintenance`** (toute route hors liste blanche, compte autre qu'admin / superadmin ; aussi une visite Inertia de l'espace Vue, en-tête `X-Inertia`) : `{ "message": "Plateforme en maintenance. Réessayer dans quelques minutes.", "code": "maintenance", "retry_after": 600, "ends_at": "…" | null, "overdue": false, "timezone": "Africa/Porto-Novo", "maintenance_message": "texte public administré" }`, en-têtes `Retry-After`, `X-Xm-Maintenance: 1`, `X-Robots-Tag: noindex, nofollow`. Le client affiche `maintenance_message` (et la fin prévue), garde sa file d'envoi (rejouée après la réouverture ; `POST sos` et `POST signalements` restent acceptés) et sonde `GET status` avant de réessayer ; un jeton Bearer n'est jamais révoqué par la maintenance. Restent servis pendant la maintenance, en plus des parcours de crise : `GET zones`, `GET violence-types`, `GET needs` (référentiels du formulaire de signalement : `category` et `needs` y sont obligatoires) et `POST auth/password/forgot` · `reset` (anti-verrouillage : la réinitialisation ne connecte personne, `auth/login` reste refusé aux rôles autres qu'admin / superadmin).

### `GET app/version` — version de la plateforme et mise à jour minimale mobile (P13-02)

Sans authentification ni session (aucun `Set-Cookie`), `throttle:api`, `Cache-Control: public, max-age=300`. `?version=1.4.2` (ou l'en-tête `X-App-Version`) annonce la version du client ; une valeur mal formée est ignorée.

**200** `{ "data": { "current": "1.5.0", "minimum": "1.4.0", "released_at": "…" | null, "kind": "correctif" | "fonctionnalite" | "securite" | null, "notes": "…" | null, "client_version": "1.4.2" | null, "update_available": true | false | null, "update_required": true | false | null } }` — `current` : dernière version déployée (`app_versions`) ; `minimum` : paramètre `app_min_version` réglé dans *Mises à jour & optimisation* (jamais au-dessus de `current`) ; `update_available` : le client est en dessous de `current` ; `update_required` : le client est en dessous de `minimum`, l'application bloque l'écran jusqu'à la mise à jour (`null` sans version annoncée).


**Application mobile unique (P15-11, P15-22)** : la réponse porte aussi `offline_expiry_days` (14 par défaut, `xomiton.accounts.offline_expiry_days` : jours sans contact avec le serveur au-delà desquels l'application purge les données de l'espace pair ; horloge locale **et** dernier `server_time` vu) et `spaces_enabled` = `{peer: bool, admin: bool}` (interrupteurs serveur, §24 bis). Aucune de ces valeurs n'est sensible.
### `POST assistant/question` — assistant public et membre (P4-01, Q41 a, Q55 ; P8-11)

Offcanvas de l'accueil (`<x-assistant-offcanvas>`, `resources/js/public/assistant.js`) et page membre `/assistant` (`Assistant/Index`) ; même contrat pour Vue et Flutter. Groupe public, `throttle:api` + `throttle:assistant` (20/min **par IP**, `rate_limits.assistant_per_minute`), `Cache-Control: no-store, private`. `App\Application\Assistant\AskAssistant` : garde-fous de crise d'abord (**jamais d'appel au fournisseur** pour un message de crise), visiteur·se = base `assistant_kb` seule (`KnowledgeBaseAnswerer`, Q41), compte connecté (cookie ou Bearer) = fournisseur `AssistantProvider` configuré (`xomiton.assistant.provider`, pilote `kb` seul livré, Q7 ; échec du pilote → la base), sinon repli. **Rien n'est enregistré** pour un·e visiteur·se, ni pour un compte sans `persist` ; seul `hits_count` de l'entrée appariée augmente.

Corps : `{ "question": "Comment porter plainte ?" }` — chaîne de 2 à 500 caractères (`AssistantQuestionRequest`). Compte connecté seulement (ignorés sinon) : `persist` (booléen, faux par défaut : échange gardé chiffré dans `assistant_conversations` / `assistant_messages`, une crise marque la conversation pour relecture, `is_flagged`) et `conversation_id` (conversation enregistrée **de la personne** à poursuivre, avec `persist` ; celle d'un autre compte → **404** `not_found`).

**200** `{ "data": { "text", "kind", "emergency", "suggestions", "links" } }` (`AssistantAnswerResource`) :

| Champ | Contenu |
|---|---|
| `text` | réponse prête à afficher, retours à la ligne `\n` (bulle `white-space: pre-line`, jamais du HTML) ; registre tu (Q56) |
| `kind` | `kb` (entrée de la base) · `crisis` (mot-clé de `xomiton.assistant.crisis_keywords`) · `fallback` (rien d'assez proche) |
| `emergency` | `true` seulement pour `crisis` |
| `suggestions` | jusqu'à 3 questions de la base (proches, sinon les plus consultées) ; l'offcanvas ne les affiche que pour `fallback` |
| `links` | `[]` sauf `crisis` : `[{ label, url, variant }]` dans cet ordre — SOS (`danger`), un `tel:` par numéro de `settings.emergency_numbers` (sinon config), « Exprimer un besoin » (`/signaler?tab=besoin`), signalement (`/signaler`), contacts d'urgence (`/urgences`), tous `primary-soft` |

Réponse de crise publique : numéros en clair, SOS et signalement **sans URL dans le texte** (ce sont les `links`), renvoi vers « Exprimer un besoin » (lu sous 24 h) et **aucune promesse de rappel** : rien n'est enregistré, personne ne peut recontacter (`VOICE_AND_TONE.md` §3 règle 6). Pas de `matched_kb_id` ni de `conversation_id` pour un·e visiteur·se. Compte connecté (cookie de session ou jeton Bearer) : deux champs de plus, `conversation_id` (échange enregistré, sinon `null`) et `escalation` (`true` pour une réponse de crise : « Parler à un humain » par `POST assistant/escalate`), texte de crise qui propose « Parler à un humain » (`assistant.crisis.human`), enregistré ou non ; échange enregistré : adresses SOS / signalement dans le texte en plus.

Erreurs : **422** `validation_failed` (`errors.question`) ; **429** `too_many_requests` (`Retry-After`, `retry_after`, message `auth.throttle`) ; **419** `session_expired` sur le chemin stateful seulement (Referer ou Origin d'un domaine `SANCTUM_STATEFUL_DOMAINS`, cookie de session) sans `X-XSRF-TOKEN` valide → `GET /sanctum/csrf-cookie` puis un seul rejeu ; un client Bearer ou sans cookie n'est pas concerné. Hors réseau, l'offcanvas n'appelle pas l'API : texte `components.assistant.offline` (premier numéro d'urgence) avec un lien `tel:` et `/urgences`. Tests : `tests/Feature/Api/AssistantQuestionTest.php`, `tests/Feature/Assistant/AssistantMemberApiTest.php`.

Fournisseur (P8-11, Q7) : `App\Application\Assistant\AssistantProvider`, résolu par `App\Infrastructure\Assistant\AssistantProviders` depuis `ASSISTANT_PROVIDER` (`kb` par défaut ; vide, `null` ou inconnu → `kb`). Point d'extension LLM : une classe déclarée dans `AssistantProviders::DRIVERS`, jamais appelée en crise ni pour un·e visiteur·se, qui enregistre l'échange par `KnowledgeBaseAnswerer::record()` si `persist` ; aucune clé d'API lue aujourd'hui.

### `POST assistant/escalate` — « Parler à un humain » (P8-11)

Groupe `auth:sanctum`, `idempotent:required,limit=report` (en-tête `Idempotency-Key` UUID obligatoire, quota des signalements, rejeu sans doublon). `App\Application\Assistant\EscalateToHuman` : alerte **besoin** ordinaire (`CreateReport`, P5-02) routée par RG-04 depuis le département et la commune du compte, besoin `xomiton.assistant.escalation_need` (`soutien_psychologique`, repli `autre`), `AlertCreated` (notification du contact routé, accusé et escalade P5-03), visible dans `/admin/alertes` onglet Besoins. Le contenu de la conversation n'est **jamais** copié dans l'alerte.

Corps : `{ "contact": "+229 01 97 38 88 47", "preferred_channel": "whatsapp", "message": "…", "anonymous": true, "conversation_id": 41 }` — `contact` obligatoire (téléphone béninois ou e-mail, ≤ 190, `PhoneOrEmail`) ; `preferred_channel` `sms | whatsapp | call | email` facultatif ; `message` ≤ `xomiton.assistant.escalation_message_max` (1000), texte neutre sinon ; `anonymous` vrai par défaut (pseudonyme transmis seulement à `false`) ; `conversation_id` facultatif : conversation enregistrée de la personne, marquée pour relecture et `escalated_at` posé (404 sinon), **ignoré si `anonymous` est vrai** (la conversation porte le compte : la dater au moment d'une alerte anonyme permettrait de les rapprocher).

**201** `{ "data": AlertResource }` (§6 : `tracking_code`, `type: need`, `routed`, jamais le contact ni le récit), `Cache-Control: no-store, private`. Erreurs : **401** sans compte ; **422** `idempotency_key_required`, `validation_failed` ; **404** conversation d'un autre compte ; **429** quota `report`. Tests : `tests/Feature/Assistant/EscalateToHumanTest.php`.

### `DELETE assistant/conversations/{id}` — effacer une conversation enregistrée (P8-11)

Groupe `auth:sanctum`. **204** : conversation et messages supprimés (« Effacer la conversation »), signalée ou non ; une alerte déjà créée par `assistant/escalate` reste. Conversation d'un autre compte ou inexistante : **404** `not_found`.

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

**201** état du SOS (`SosStatusResource`) : champs d'`AlertResource` (§6.1) + `located` (booléen : position jointe ou non, jamais la position) + `sos_token` (SOS **anonyme** seulement, rendu aussi aux rejeux : à garder sur l'appareil, en-tête `X-Sos-Token` des appels suivants, Q52 b) + `notified[]` + `primary_contact` + `meta.poll_interval_seconds` ; `Location: /api/v1/sos/{id}`. Erreurs : **422** `validation_failed`, `idempotency_key_*`.

### 6.5 `GET sos/{id}` — statut et personnes prévenues

Hors limiteur global `api` (un CGNAT ne bloque jamais le sondage) : `throttle:sos_status` (30/min par IP et par alerte, `rate_limits.sos_status_per_minute`). Réservé au compte propriétaire (Bearer ou cookie) ou au porteur de `X-Sos-Token` (`ShowSosRequest`, `ActsAsSosOwner`) : **403** `forbidden` sinon (l'ULID seul ne suffit pas ; un SOS identifié n'accepte pas le jeton seul, le jeton d'un SOS ne vaut pas pour un autre) ; **404** `not_found` pour un identifiant inconnu ou une alerte qui n'est pas un SOS. **200** même charge que la création, sans `sos_token`. Sonder toutes les `meta.poll_interval_seconds` (10 s, `xomiton.alerts.sos_poll_seconds`) tant que la valeur n'est pas `null` (SOS annulé, en sécurité, traité, clos, ou envoyé depuis plus de `sos_poll_minutes`, 30 min).

`notified[]` (`App\Application\Alerts\Dispatch\NotifiedPeople`, lot P5-03, lignes `alert_notifications` du déclenchement et de l'escalade) : `{ key, kind, label, role, state, state_label, phone, seen_at, updated_at }` — `key` : empreinte opaque et stable d'un sondage à l'autre (jamais `user:<id>` ni `contact:<id>`) ; `kind` `focal` · `contact` · `team` · `trusted` ; `state` `seen` (a vu l'alerte) · `sms_sent` · `sent` · `pending` ; `phone` seulement pour une fiche publique ou le contact de confiance de la personne ; contact routé en tête. `primary_contact` : première personne dont le numéro peut être montré (bouton d'appel), `null` → appeler le 117.

### 6.6 `POST sos/{id}/cancel` · `POST sos/{id}/safe`

Même règle d'accès que §6.5 ; hors limiteur global `api`, `throttle:sos_owner` seulement (10/min par IP et par alerte, `rate_limits.sos_owner_per_minute`). Réponse **200** = état du SOS (§6.5). `cancel` : repentir pendant 60 s après l'envoi (`CancelSos`, Q39) → `cancelled: true`, `status: clos`, prévient les personnes alertées ; au-delà **422** `cancel_window_expired` ; déjà annulé : idempotent. `safe` : « Je suis en sécurité » (`MarkSafe`) → `safe: true`, `status: traite` (jamais `clos`) ; SOS annulé **422** `alert_closed` ; idempotent. Autre alerte qu'un SOS : **422** `not_sos`.

### 6.7 `GET urgences?zone=` — contacts d'urgence

Lecture publique en cache HTTP comme les référentiels (`Cache-Control: public, max-age=3600`, `ETag`, `If-None-Match` → **304**). **Même source que la page `/urgences`** (`App\Application\Alerts\Queries\ListEmergencyContacts`, P4-06 doit la réutiliser). **200** `{ "data": { "services": [{ "label", "number", "tel" }], "contacts": [{ "id", "name", "role", "role_label", "org", "phone", "whatsapp", "availability", "zone", "commune", "national" }] }, "meta": { "zone": { id, name } | null, "count", "truncated" } }`. `zone` facultatif (identifiant d'un département actif ; **422** sinon) : fiches du département puis fiches nationales (sans département ou point focal national) ; sans zone, toutes les fiches d'urgence (100 au plus, `meta.truncated: true` au-delà). **Fiches d'urgence** (Q19 : l'annuaire complet reste membre) : actives, `is_public` **et** rôle `point_focal` · `juriste` · `psychologue` ou point focal national ; jamais pair·e animateur·rice, responsable de réseau, personnel identifié, etc. Jamais l'e-mail, les notes ni le compte lié d'une fiche. Cache serveur versionné (comme les référentiels), invalidé par toute écriture sur l'annuaire (`DirectoryContactObserver`).

Écart consigné (P5-06) : ni `emergency_services` ni `directory_contacts.is_emergency` (annoncés par P1-05) n'existent ; `services` vient du paramètre `emergency_numbers` (sinon `xomiton.assistant.emergency_numbers`, même source que l'assistant §5) et `contacts` des fiches d'urgence ci-dessus (filtre par rôle en attendant `is_emergency`, suivi en P4-06 et Q59).

Tests : `tests/Feature/Api/Alerts/ReportApiTest.php`, `SosApiTest.php`, `EmergencyApiTest.php`.

## 7. Cloche `notifications/*` — `auth:sanctum` (P6-07)

Notifications en base de la personne connectée (`App\Application\Notifications\NotificationInbox`) ; une notification d'un autre compte est **introuvable (404)**, jamais « interdite ».

| Route | Réponse |
|---|---|
| `GET notifications?filter=all\|unread\|read&category=&tab=&page=&per_page=` | **200** liste paginée par page (§1.1), plus récentes d'abord ; `meta` + `filter`, `category`, `tab`, `unread` ; 422 filtre, catégorie ou onglet inconnus |
| `GET notifications/unread-count` | **200** `{ "data": { "all": 12, "unread": 3, "read": 9, "categories": { "messages": { "all": 4, "unread": 1 }, … }, "tabs": { "all": {…}, "invitations": { "all": 2, "unread": 1 }, … } } }` — compteurs des onglets **Non lues · Lues · Toutes**, par catégorie et par filtre de la page /notifications (P8-10) ; polling 30 s avec `If-None-Match` → 304 (§1.6) |
| `POST notifications/{id}/read` | **200** la notification lue (idempotent : `read_at` d'origine conservé) + `meta.unread`, `meta.tabs` ; 404 |
| `POST notifications/read-all` `{ "category"?, "tab"? }` | **200** `{ "data": { "marked": 2, "unread": 1 } }` |
| `DELETE notifications/{id}` | **204** ; 404 |

Objet : `{ id, type, category, tab, title, body, url, icon, sensitive, read, read_at, created_at, data }` — `tab` = filtre de la page /notifications (P8-10, `App\Application\Notifications\NotificationTab`, même règle en SQL et en PHP) : `invitations` (invitations et adhésions à un groupe), `events` (catégorie `evenements` et événement créé dans un groupe), `exchanges` (reste de `messages` et `communaute` : messages, réponses, réactions, sondages), `system` (sécurité, SOS, alertes, modération, système) ; `type` = nom court de la classe (`invitation_received`, même valeur que `data.type` de la charge push), `data` = compléments d'ouverture profonde (identifiants, jamais de donnée sensible). Une notification sensible est **neutralisée à la lecture** (`notifications.discreet.*`, « Nouvelle notification ») tant que `mask_sensitive_notifications` est actif, même reçue avant l'activation du masquage. Catégories : `securite`, `sos`, `alertes`, `messages`, `communaute`, `evenements`, `moderation`, `systeme`. 401 sans session ni jeton.

## 8. Fil et sondages `feed/`, `posts/*`, `comments/*` — `auth:sanctum` (P7-02)

Lot « fil-domaine-api » (P7-01, P7-02, P7-07). Domaine `App\Domain\Feed` (`PostAudience`, `ContentFilter`, `PollBallot`), `App\Domain\Moderation\AutoHideRule` (RG-16) ; cas d'usage `App\Application\Feed\*` et `App\Application\Moderation\ReportContent` ; lectures `App\Application\Feed\Queries\FeedQuery`. Publications adressées par **ULID** (ADR-27), commentaires par identifiant numérique. Une publication que la personne ne peut pas voir (visibilité, masquée, supprimée, groupe privé, blocage dans un sens ou dans l'autre) est **introuvable (404)**, jamais « interdite » ; le 403 est réservé à une action refusée sur une publication visible (modifier celle d'une autre personne, signaler la sienne) et à `email_unverified`.

### 8.1 Visibilité (P7-01)

| `visibility` | Qui la voit |
|---|---|
| `public` (« Communauté ») | tout compte du réseau (`social.access`) |
| `followers` | les abonné·es de l'auteur·rice (`follows`) |
| `me` | l'auteur·rice seulement |

L'auteur·rice voit toujours sa publication (même masquée par la modération : `moderated: true`) ; `posts.moderate` voit tout. Le fil exclut en plus : les publications masquées par la personne (`post_hides`), celles des groupes (P8), celles des comptes bloqués (`user_blocks`, deux sens), les publications non publiées. **Contenu anonyme** (publication ou commentaire, Q72) : seul le blocage posé par l'auteur·rice compte, jamais celui posé par la personne qui lit (bloquer ne doit pas révéler qui a écrit) ; anonyme + `followers` refusé (422 `visibility` au POST et au PATCH ; une ligne ancienne est traitée comme `me`). `feed/saved` applique la même règle de groupe que le détail (groupe public actif, ou dont on est propriétaire ou membre active).

### 8.2 Routes

| Route | Réponse |
|---|---|
| `GET feed?tag=&cursor=&per_page=` | **200** liste par curseur (§1.1, 20 par page), plus récentes d'abord ; `meta.tag` ; 422 étiquette inconnue |
| `GET feed/saved?cursor=` | **200** publications enregistrées encore visibles (P7-06) |
| `POST posts` | **201** + `Location` ; `verified`, limiteur `feed_write` (10/min par compte) ; `Idempotency-Key` facultative (`idempotent:optional,limit=feed_write`, lot fil-ecrans : publication mise en file hors connexion par la boîte d'envoi, rejouée sans doublon, ADR-32) ; corps ci-dessous ; 403 `forbidden` sans `posts.create` (sondage : `polls.create`), 403 `email_unverified`, 422 `validation_failed` ou `content_blocked` |
| `GET posts/{ulid}` | **200** publication ; 404 |
| `PATCH posts/{ulid}` `{body?, tag?, visibility?}` | **200** ; auteur·rice seulement (403), `verified`, `throttle:feed_write` ; étiquette jamais vers ou depuis `sondage` (422) ; anonyme jamais vers `followers` (422) |
| `DELETE posts/{ulid}` | **204** ; auteur·rice ou `posts.moderate` (journal `post.deleted_by_moderation`) ; statut `supprime` + suppression logique |
| `POST · DELETE posts/{ulid}/like` | **200** `{ liked, likes_count, changed }` — idempotent (`changed: false` sur un rejeu) ; cloche regroupée de l'auteur·rice (`ReactionGrouped`) |
| `POST · DELETE posts/{ulid}/save` | **200** `{ saved, changed }` |
| `POST · DELETE posts/{ulid}/hide` | **200** `{ hidden, changed }` — « Masquer » : retirée du seul fil de la personne ; `DELETE` = « Annuler » |
| `POST posts/{ulid}/report` `{reason, details?}` | **201** `{ reported: true, reason }` ; **200** si la personne l'avait déjà signalée (rien n'est compté deux fois) ; `throttle:content_report` (20/h par compte) ; 403 sur son propre contenu ; 422 motif inconnu |
| `GET posts/{ulid}/comments?cursor=&per_page=` | **200** commentaires publiés du plus ancien au plus récent, réponses comprises (`parent_id`), `meta.comments_count` |
| `POST posts/{ulid}/comments` `{body, parent_id?, is_anonymous?}` | **201** + `meta.comments_count` ; `verified`, `throttle:feed_write` ; 2 à 1000 car. ; `parent_id` = commentaire publié de la **même** publication (422 sinon) ; filtre de mots |
| `DELETE comments/{id}` | **204** ; auteur·rice ou `posts.moderate` ; 404 si invisible |
| `POST comments/{id}/report` `{reason, details?}` | comme `posts/{ulid}/report` |
| `POST posts/{ulid}/poll/vote` `{choices: [option_id…]}` | **200** sondage (§8.4) ; le vote **remplace** le précédent, les mêmes choix ne changent rien (idempotent) ; 422 `validation_failed` (aucun choix, réponse étrangère, deux réponses en choix unique), 422 `poll_closed`, 404 sans sondage |
| `DELETE posts/{ulid}/poll/vote` | **200** sondage ; sans effet si la personne n'avait pas voté ; 422 `poll_closed` |

Motifs `reason` (`App\Enums\ContentReportReason`, libellé gardé dans `moderation_reports.reason`) : `harcelement`, `discrimination`, `menaces`, `donnees_personnelles`, `desinformation`, `usurpation`, `spam`, `autre`.

### 8.3 `POST posts` — publication ou sondage (JSON ou multipart)

```json
{ "kind": "post", "body": "Merci à toutes…", "tag": "temoignage", "visibility": "public", "is_anonymous": false,
  "image": <fichier>, "image_alt": "Atelier en plein air" }
{ "kind": "poll", "body": "Aidez-nous à choisir.", "visibility": "followers",
  "poll": { "question": "Quel thème pour la causerie ?", "options": ["Santé", "Droits", "Bien-être"],
            "multiple": false, "results_before_vote": false, "duration_days": 7 } }
```

| Champ | Règle |
|---|---|
| `kind` | `post` (défaut) ou `poll` |
| `body` | ≤ 3000 car. ; obligatoire pour `post` sauf image seule ; facultatif pour `poll` |
| `tag` | `PostTag` sauf `sondage` (défaut `temoignage`) ; `sondage` imposé pour `poll` |
| `visibility` | `public` (défaut), `followers`, `me` |
| `group` | ULID d'un groupe (P8-02, §13) où la personne peut publier (membre active ou propriétaire) ; sinon **422** `errors.group` (« Tu dois être membre du groupe pour faire cela. », sans dire si le groupe existe) ; `visibility` alors `public` seulement (422 sinon, au POST comme au PATCH : le groupe est l'audience) ; la publication n'entre pas dans `feed` mais dans `groups/{ulid}/posts` ; `groups.posts_count` suit |
| `image` | collection `images` de MediaUploader (JPEG, PNG, WebP, 5 Mo) : type réel lu dans le contenu, ré-encodage GD (**EXIF retiré**), WebP + variantes, **disque privé**, servie en lien signé `media.signed` (`xomiton.feed.media_url_minutes`, 60 min au moins), expiration alignée sur une fenêtre de 15 min (`media_url_window_minutes`) : même lien, même ETag, 304 dans la fenêtre |
| `poll.question` | 5 à 200 car. |
| `poll.options` | 2 à 6 réponses ≤ 80 car., distinctes (casse et accents ignorés) ; réponses vides retirées (comme le prototype) |
| `poll.duration_days` | 1, 3, 7, 14, 30 ; 0 ou absent = sans fin |

**Filtre de mots** (`bad_words`, FR-SOC-06) sur le corps, le texte alternatif, la question et chaque réponse, avant tout téléversement : terme « bloquer » → 422 `content_blocked` (`errors.<champ>`, message neutre, jamais le terme) ; terme « signaler » → publication en ligne **et** signalement automatique (`reporter_user_id` nul, motif « Mots surveillés (filtre automatique) », termes dans `details` lu par la seule modération), sans incrémenter `reports_count`.

### 8.4 Objets

Publication (`App\Http\Resources\Feed\PostResource`) :

```json
{ "id": "01JC…", "author": { "id": 12, "pseudo": "Awa_S", "avatar_url": "…", "role": "membre", "anonymous": false },
  "is_mine": false, "is_anonymous": false, "body": "…", "tag": "temoignage", "tag_label": "Témoignage", "tag_color": "success",
  "visibility": "public", "visibility_label": "Public", "moderated": false, "likes_count": 3, "comments_count": 1,
  "liked": true, "saved": false, "media": [{ "kind": "image", "url": "…signature=…", "alt": null, "width": 1600, "height": 1200 }],
  "poll": null, "url": "https://…/fil/01JC…", "share_url": "https://…/p/01JC…", "published_at": "…", "edited": false,
  "can": { "update": false, "delete": false, "report": true, "comment": true } }
```

`tag_color` : variante Bootstrap de l'étiquette (`PostTag::badge()`, couleurs du prototype `inc/render.php` : témoignage `success`, question `warning`, ressource `info`, événement et sondage `primary`, violences `danger`), à afficher en badge doux.

Publication anonyme (RG-02) : `author` = `{ "id": null, "pseudo": "Membre anonyme", "avatar_url": "…/personnage-1.png", "role": null, "anonymous": true }`, **même pour l'auteur·rice** (`is_mine` suffit). Le statut, le motif de masquage et `reports_count` ne sortent jamais.

Sondage (`PollResource`, RG-09) : `question`, `multiple`, `results_before_vote`, `ends_at`, `closed`, `closed_at`, `voters_count` (toujours), `votes_count`, `results_visible`, `has_voted`, `my_choices` (**les seuls choix de la personne qui lit**), `can_vote`, `options[] { id, label, position, votes_count, percentage, leading }`. Les résultats (`votes_count`, `percentage`, `leading`) valent `null` tant que la personne n'a pas voté, sauf sondage terminé ou `results_before_vote`. **Aucun vote individuel n'est jamais exposé** ; `PollVoted` ne porte que des identifiants et n'est jamais journalisé.

Commentaire (`CommentResource`) : `{ id, parent_id, body, author, is_mine, is_anonymous, created_at, can: { delete, report } }` ; `parent_id` nul dans la liste si le parent est supprimé ou masqué (la réponse remonte au premier niveau). `edited` (publication) : vrai seulement après un `PATCH` qui change le texte, l'étiquette ou la visibilité (`posts.edited_at`), jamais après une réaction.

### 8.5 Signalement et RG-16

`ReportContent` (FR-SOC-06) : un signalement par personne et par contenu (`moderation_reports`, extrait figé ≤ 250 car.), `posts.reports_count` + 1, événement `ContentReported` → cloche des comptes `moderation.manage` (`NotifyModerators`). **RG-16** (`AutoHideRule`) : au seuil de signalements **en attente** de personnes distinctes, le contenu passe `masque` (`hidden_reason` lisible par l'équipe), journal `moderation.auto_hidden` (acteur « système ») ; un commentaire masqué recalcule `comments_count`. Seuil : `config('xomiton.feed.auto_hide_after_reports')` (3, `XOMITON_AUTO_HIDE_REPORTS`), surcharge `Settings` `moderation_auto_hide_reports`, **0 désactive**. Après un rejet par la modération, il faut de nouveaux signalements. La réponse ne dit jamais si le contenu a été masqué.

### 8.6 Partage par lien (Q31 b) et clôture des sondages (P7-07)

`share_url` = `/p/{ulid}` (route web `p.show`) : page publique **neutre** (titre « Publication partagée depuis Xomiton », invitation à se connecter, aucun contenu ni pseudonyme, `noindex`, `no-store`), **sans lecture en base** : même réponse que la publication existe ou non. Connecté·e : redirection vers `/fil/{ulid}` ; « Se connecter » y ramène par `?next=`.

`polls:close` (toutes les 5 min, `routes/console.php`) : `closed_at` = `ends_at` pour chaque sondage échu (une seule fois), cloche « Sondage terminé » (`PollEnded`) à l'auteur·rice active d'une publication encore publiée. Le vote est refusé dès l'échéance, même avant le passage de la tâche.

## 9. Parcours Flutter de référence

```
register ──201 token──► (accès limité) ──otp/verify signup──► email_verified
login ──202 otp_sent──► otp/verify login ──200 token + device_token──► me
login + device_token ──200 token──► me                (appareil reconnu, Q13 b)
me ──► POST me/devices (X-Device-Id ensuite) ──► POST me/push-subscriptions {kind: fcm, token}   (P6-09)
auth/logout {push_token} ──► abonnement push de l'appareil supprimé, jeton révoqué
password/forgot ──202──► otp/verify reset ──reset_token──► password/reset ──200──► login
```

Journal d'audit (`audit_logs`) : `auth.registered`, `auth.authenticated`, `auth.token_issued`, `auth.otp_verified`, `auth.email_verified`, `auth.password_reset_requested`, `auth.password_reset`, `auth.logout`, `auth.session_revoked`, `auth.failed`, `auth.refused_suspended`, `auth.refused_maintenance` ; mon compte (P6) : `me.settings_updated`, `me.security_updated`, `account.deletion_requested`, `account.export_requested`, `account.export_ready`, `account.export_downloaded`, `device.registered`, `device.revoked`, `push.subscribed`, `push.unsubscribed`, `newsletter.subscribed_from_account`. Aucune valeur sensible (mot de passe, code, jeton) n'y est écrite.

## 10. Familles à venir

`admin/*` : livré (§23, P15-16) ; `sync/*`, `heartbeat` : livrés (§21, P9-06) ; `pair/` : livré (§22, P9-02) ; `conversations/` : livré (§12, P8-01) ; `groups/`, `pages/` : livrés (§13, §14, P8-02, P8-03) ; `events/` : livré (§15, P8-04) ; `forums/` : livré (§16, P8-05) ; `follows/`, `invitations/` : livrés (§17, P8-06) ; `members/{pseudo}…`, `me/activity`, `me/profile/{avatar|cover}` : livrés (§18, §3, P8-07) ; `directory/*` : livré (§19, P8-12). `signalements/`, `sos/`, `urgences/` : livrés (§6) ; `me/*` (§3) et `notifications/*` (§7) : livrés (P6-06, P6-07, P6-09) ; `feed/`, `posts/*`, `comments/*` (§8) : livrés (P7-02) ; `dashboard`, `thematiques/`, `ressources/`, `users/{id}/posts` (§11) : livrés (P6-08). Chaque famille complète ce document à sa livraison.

## 11. Lecture mobile : tableau de bord, thématiques, ressources, publications d'un profil (P6-08)

Lot « api-mobile-lecture » : routes de lecture attendues par l'application Flutter (`simulateur.html` : `dashboard` l.756, `thematiques` l.785, `thematique` l.802, `ressources` l.819, `profil` l.1202). Aucune donnée nouvelle : mêmes requêtes que le site (`App\Application\Content\Queries\ListThematiques`, `ShowThematique`, `ListResources::page()`, `ResourceDownload`, P4-02 / P4-03), la cloche (P6-07), les alertes (P5) et le fil (`FeedQuery`, P7-02). Toutes les lectures 200 portent un `ETag` (`conditional`, §1.6) : `If-None-Match` → **304** ; aucun texte d'interface (les libellés sont au client, catalogue ARB).

| Route | Groupe | Réponse |
|---|---|---|
| `GET dashboard` | `auth:sanctum` | **200** tableau de bord selon le rôle (ci-dessous) ; avec la section `peer` : `Cache-Control: no-store` (résumés d'alertes d'autres personnes, jamais gardés par l'appareil), sans ETag ; 401 |
| `GET thematiques?categorie=&q=` | public | **200** fiches publiées (cartes), plus récentes d'abord, jusqu'à `xomiton.content.max_per_load` (300) ; `meta` : `total`, `total_all`, `has_more`, `categorie`, `q`, `categories` (puces `{slug, label, icon, color, count}`) ; 404 `categorie` inconnue ou inactive ; 422 format invalide |
| `GET thematiques/{slug}` | public | **200** fiche complète ; 404 inconnue, brouillon, programmée, supprimée |
| `GET ressources?q=&type=&categorie=&page=&per_page=` | public | **200** catalogue de `/ressources` paginé par page (§1.1), 20 par page, même ordre ; `meta` + `categorie`, `type`, `q`, `categories` ; 422 `type` inconnu ou `article` (actualités, Q1) ; 404 `categorie` inconnue |
| `GET ressources/{id}/download` | public, `throttle:resource_download` | **200** `{ id, url, expires_at, kind, file_size, file_size_label, filename }` : lien signé périssable (`xomiton.uploads.signed_url_minutes`, 10 min) vers le disque privé, `expires_at` = échéance de la signature, `Cache-Control: no-store` (jamais d'ETag : la signature expire) ; téléchargement compté (`downloads_count`, mêmes règles que le site : ni `HEAD`, ni robot, ni préchargement), **à chaque délivrance du lien** (le redemander pour une reprise ou après expiration compte de nouveau, Q73) ; 404 ressource sans fichier (lien, fiche), non publiée, actualité ou inconnue |
| `GET users/{id}/posts?cursor=&per_page=&tag=` | `auth:sanctum` | **200** publications du profil au format du fil (§8.4), pagination par curseur (§1.1), `meta.user_id` ; 404 profil inconnu ou invisible ; 403 sans `social.access` |

**Tableau de bord** (`App\Application\Member\MemberDashboard`, `DashboardResource`) :

```json
{ "data": {
  "user": { "id": 12, "pseudo": "Awa_S", "avatar_url": "…", "role": "pair", "pending_validation": false, "email_verified": true },
  "tiles": [ { "key": "report", "route": "/sighub", "count": null }, { "key": "emergency", "route": "/urgences", "count": null },
             { "key": "assistant", "route": "/assistant", "count": null }, { "key": "learn", "route": "/thematiques", "count": 5 } ],
  "counters": { "notifications": 3, "messages": 1 },
  "profile": { "completion": 43, "complete": false },
  "featured": { "total": 5, "items": [ { "id": 4, "slug": "…", "title": "…", "summary": "…", "cover_url": "…", "reading_minutes": 6, "category": { "slug": "vbg", "label": "…", "icon": "…", "color": "…" }, "published_at": "…", "url": "https://…/thematiques/…" } ] },
  "next_event": { "id": "01J…", "title": "…", "starts_at": "…", "ends_at": "…", "is_online": true },
  "peer": { "kpi": { "collected": 12, "this_month": 3, "in_progress": 4, "closed": 8 },
            "routed_alerts": { "open": 2, "new": 1, "items": [ { "id": "01J…", "type": "need", "type_label": "…", "status": "nouveau", "status_label": "…", "priority": "haute", "priority_label": "…", "place": "Cotonou", "acknowledged": false, "created_at": "…" } ] },
            "tasks": [ { "key": "alerts_to_take", "count": 1 }, { "key": "cases_to_contact", "count": 2 } ] }
} }
```

- `tiles` : identiques pour tous les rôles (la tuile SOS est la barre d'onglets) ; `learn.count` = fiches publiées. `counters` = compteurs de l'en-tête de l'espace membre (`MemberShell::counters` : notifications non lues de la cloche, conversations ayant un message non lu — 0 tant que la messagerie P8-01 n'écrit rien) ; le polling reste `notifications/unread-count` (§7).
- `featured` : `xomiton.api.dashboard_featured` (5) fiches les plus récentes ; `category` à null si la catégorie n'est pas un filtre de la liste. `next_event` : premier événement publié à venir sans groupe ou d'un groupe public actif (`UpcomingEvents`), **jamais le lieu** ; null s'il n'y en a pas (les événements eux-mêmes : `events/`, §15, P8-04).
- `peer` : null hors `peer_space.access` **et** pour un compte en attente de validation (RG-03) ; `routed_alerts` vide sans `alerts.view_routed`. KPI du prototype `espace-pair.php` (collectés, ce mois-ci par date de saisie, en cours = ouverts + en cours, clos) sur **ses** dossiers (`cases.collected_by`), archivés exclus — jamais ceux des autres. Alertes : celles routées vers ses fiches d'annuaire (`directory_contacts.user_id`), encore ouvertes, `new` = sans accusé de réception, `xomiton.api.dashboard_routed_alerts` (5) plus récentes ; jamais le récit, le contact, la position ni l'autrice ou l'auteur (`RoutedAlertResource`) : le détail et la prise en charge passent par l'espace pair (P9-03). `tasks` : seulement les compteurs non nuls.

**Fiche thématique** (`ThematiqueResource`) : `id, slug, title, summary, cover_url, reading_minutes, category, content_warning` (avertissement de contenu saisi par l'équipe, null sinon), `body` et `sections[{title, body}]` (HTML nettoyé à l'écriture, profil purifier `content`), `contacts[]` (annuaire **actif et public** des rôles liés, Q19 : `id, name, role, role_label, org, phone, whatsapp, zone, availability, is_national_focal` ; jamais e-mail, notes ni compte lié), `related[]` (cartes complètes : résumé, catégorie si elle est un filtre de la liste), `primary_resource` (carte de ressource ou null), `faqs[{id, question, answer}]` (3 à 5 ou aucune), `published_at, updated_at, url`. Consultation comptée comme sur le site (`views_count`, ni robot ni préchargement) au premier chargement seulement : une revalidation (`If-None-Match`) n'est pas une lecture.

**Carte de ressource** (`ResourceCardResource`) : `id, slug, title, kind, kind_label, summary, category, cover_url, file_size` (octets, null sans fichier), `file_size_label` (« 1,2 Mo », LocaleFormatter), `duration_minutes, is_offline_available, downloadable, playable, external_url` (adresse `http(s)` seulement), `published_at, url` (fiche publique), `download_url` (cette API, jamais un lien signé gardé en cache) ; ni `file_path` ni compteurs.

**Publications d'un profil** (`App\Application\Feed\Queries\UserPostsQuery`) : profil visible selon `App\Domain\Account\ProfileAudience` — soi-même toujours ; `public` et `community` pour tout compte ; `followers` pour ses abonné·es ; `private` pour personne d'autre ; compte suspendu, anonymisé ou supprimé, blocage dans un sens ou dans l'autre → **404** (on ne révèle pas qu'un compte existe). Publications : celles que la personne verrait dans son fil (visibilité, hors groupes, hors masquées par elle), plus récentes d'abord ; **jamais une publication anonyme**, même pour son autrice ou son auteur (RG-02 : elle n'est rattachée à aucun profil).

## 12. Messagerie `conversations/*` — `auth:sanctum` (P8-01)

Conversations privées (une seule par paire) et de groupe, messages chiffrés **au repos** (`messages.body`, cast `encrypted`, liste `config('xomiton.encrypted_columns')` ; aucune promesse « de bout en bout », Q32 b). Identifiants publics : ULID de la conversation (ADR-27), id numérique du message. Une conversation où la personne n'est pas (ou plus) présente est **introuvable** (404), même pour l'administration. Écrire (créer, envoyer) et gérer un groupe (renommer, ajouter, retirer) exigent un e-mail confirmé (`verified`, 403 sinon) et passent par le limiteur `messages_write` (30/min par compte ; l'envoi le prend par `idempotent:optional,limit=messages_write` : un rejeu de la boîte d'envoi avec la même `Idempotency-Key` ne consomme pas le quota, ADR-32) ; supprimer un message et la sourdine aussi par `messages_write` ; signaler par `content_report`, bloquer sous `throttle:api` seulement (gestes de sécurité, sans `verified`, comme sur le fil). Lectures `Cache-Control: no-store, private` (corps déchiffrés : jamais gardés par un appareil partagé, donc ni ETag ni 304). Code : `App\Application\Messaging\*`, `App\Domain\Messaging\*`, `App\Http\Controllers\Api\V1\Messaging\*`. Tests : `tests/Feature/Api/Messaging/*`.

### 12.1 Routes

| Route | Réponse |
|---|---|
| `GET conversations?q=&filter=all\|unread&page=&per_page=&since=` | **200** liste paginée par page (30), dernière activité en tête ; `q` (≤ 60) cherche le titre et les pseudonymes des autres participant·es, **jamais le corps** (ADR-33) ; **sans** `participants` (réservé à `GET conversations/{ulid}`) ; une conversation privée encore vide n'apparaît que chez la personne qui l'a ouverte ; `meta.fingerprint` (empreinte calculée par agrégats) — renvoyée en `since`, inchangée → `data: []`, `meta.unchanged = true` (rien de déchiffré) ; `meta.poll_seconds` (15), `meta.idle_poll_seconds` (30 : repli après 3 réponses inchangées) |
| `POST conversations` | `{kind: "private", user_id}` → **201** nouvelle, **200** existante (rendue, réaffichée pour qui l'avait masquée) ; `{kind: "group", title (3–120), user_ids[] (1–49)}` → **201**, la personne qui crée est `admin`, refus si deux des personnes réunies ont un blocage entre elles ; **422** `recipient_unavailable` (`errors.user_id` ou `errors.user_ids`) — même message neutre pour un compte bloqué dans un sens ou dans l'autre, suspendu, anonymisé, inconnu, soi-même, profil `private` ou `followers` sans abonnement ; **422** `participants_max` |
| `GET conversations/recipients?q=` | **200** `[{id, pseudo, avatar_url}]` (≤ 10) : début de pseudonyme (2–40 car.), comptes `searchable` ou suivis, joignables et visibles ; sans `q`, les connexions (abonnements et abonné·es) |
| `GET conversations/{ulid}` | **200** objet conversation (§12.3) ; 404 |
| `PATCH conversations/{ulid}` | `{title}` : renommer un groupe (créatrice ou `admin`, **403** sinon ; conversation privée → **422** `conversation_group_only` : une conversation privée n'a pas de gestionnaire) |
| `GET conversations/{ulid}/messages?cursor=&per_page=` | **200** historique du plus récent au plus ancien, pagination par curseur (30) ; dans un groupe, seulement les messages postérieurs à l'entrée de la personne (`joined_after_message_id`) ; `meta.read_up_to` (conversation privée : dernier message lu par l'autre personne, 0 si rien), `meta.poll_seconds` (4), `meta.checked_at` (horloge du serveur) |
| `GET conversations/{ulid}/messages?after=<id>&since=<checked_at>` | **200** sondage court de la conversation ouverte : messages d'id > `after`, du plus ancien au plus récent (≤ 100), `meta.last_id`, `meta.has_more`, `meta.read_up_to`, `meta.deleted_ids` (messages d'id ≤ `after` supprimés pour tout le monde depuis `since`, à défaut depuis `messaging.deleted_window_minutes` = 15 min ; au plus un jour en arrière), `meta.checked_at` (prochain `since`) |
| `POST conversations/{ulid}/messages` | JSON ou multipart `{body (≤ 1000, requis sans pièce jointe), client_uuid (UUID), attachment? (image JPEG/PNG/WebP ou PDF)}` ; `client_uuid` absent → repris de l'en-tête `Idempotency-Key` ; **201** `meta.duplicate = false` ; **200** `meta.duplicate = true` pour un rejeu (même `client_uuid`, même autrice : message d'origine, jamais de doublon ni de 409, ADR-32) ; **422** `client_uuid_conflict` (uuid pris par quelqu'un d'autre, son message n'est pas renvoyé) ; **403** `conversation_unavailable` (conversation privée : blocage dans un sens ou dans l'autre, autre compte injoignable — message neutre « Cette conversation n’est plus disponible. ») |
| `DELETE conversations/{ulid}/messages/{id}` | **200** message supprimé pour tout le monde (corps vidé, pièce jointe effacée du disque) : son autrice, ou l'équipe d'un **groupe** — jamais l'autre personne d'une conversation privée (**403**, même si c'est elle qui l'a ouverte) ; **message signalé** (un `moderation_reports` le vise) : seulement masqué (`deleted`, `body: null`, pas de pièce jointe dans l'API), corps chiffré et fichier gardés pour la modération (P12-06) ; 404 si le message n'est pas de cette conversation |
| `POST conversations/{ulid}/read` | `{message_id?}` : lu jusqu'à ce message (sinon jusqu'au dernier ; jamais en arrière) ; marque lue la notification « Nouveau message » de la conversation ; **200** `{last_read_message_id, unread_count, unread_conversations}` — `unread_count` : non lus restants de la conversation (non nul si `message_id` précède le dernier message), `unread_conversations` : compteur « Messages » de l'en-tête (`MemberShell::counters`, mêmes exclusions que la liste) |
| `POST conversations/{ulid}/mute` · `DELETE …/mute` | `{duration: "8h" \| "1w" \| "always"}` (`xomiton.messaging.mute_durations`) ; **200** objet conversation (`muted`, `muted_until`) ; aucune notification pendant la sourdine |
| `POST conversations/{ulid}/leave` | **200** `{left: true}` : groupe quitté (si plus aucun·e `admin` n'y reste, la personne présente depuis le plus longtemps le devient) ; conversation privée **masquée** (elle revient, avec son historique, au prochain message reçu) |
| `POST conversations/{ulid}/block` · `DELETE …/block` | conversation privée : bloque / débloque l'autre personne (`user_blocks`, FR-SOC-04 : vaut aussi pour le fil et le profil) ; **200** objet conversation ; groupe → **422** `conversation_private_only` |
| `POST conversations/{ulid}/report` | `{reason (motifs de ContentReportReason, comme le fil), details? (≤ 500), message_id?}` → `moderation_reports` + cloche de la modération ; sujet : le message désigné (d'une autre personne), sinon le dernier message reçu, sinon (conversation privée sans message reçu) l'autre personne (`utilisateur`) ; **201**, **200** si déjà signalé par la même personne ; groupe sans message d'autrui → **422** `nothing_to_report` ; **aucun extrait** dans `moderation_reports.excerpt` (en clair, ADR-33) |
| `POST conversations/{ulid}/participants` · `DELETE …/participants/{user}` | groupe, équipe seulement (**403** sinon) : `{user_ids[]}` (mêmes contrôles que la création, plus : aucun blocage entre une personne ajoutée et une personne présente ; jamais une personne **partie d'elle-même**, `left_reason = left` — une personne retirée, `removed`, peut l'être de nouveau ; **422** `recipient_unavailable` neutre dans tous les cas) ; la personne ajoutée ne lit que la suite (ni historique ni non-lus d'avant) ; on ne retire ni soi-même (→ `leave`) ni la créatrice (**403**) |

### 12.2 Blocage, groupes, notifications

- **Conversation privée** : on n'y écrit plus dès qu'il y a un blocage, dans un sens ou dans l'autre ; seule la personne qui bloque le sait (`blocked_by_me`), l'autre lit `can_send = false` sans raison.
- **Groupe** : on n'y entre jamais avec une personne qu'on a bloquée ou qui nous a bloqué·e (création et ajout) ; un blocage posé ensuite n'empêche pas d'écrire, mais les messages sont masqués **dans les deux sens** (la personne bloquée ne lit plus celle qui l'a bloquée ; fil, aperçu, non lus, compteur de l'en-tête) et aucune notification ne passe entre les deux.
- **Entrée dans un groupe** (création exceptée) : par ajout, retour après un retrait, ou « Discussion du groupe » (P8-02) — la personne lit à partir de son entrée (`conversation_participants.joined_after_message_id`), jamais l'historique ni ce qui s'est dit pendant son absence.
- **« Nouveau message »** (`App\Notifications\NewMessage`, catégorie `messages`, cloche + push, jamais d'e-mail) aux participant·es présent·es, hors sourdine : **aucun extrait du message** (le corps est chiffré, `notifications.data` est en clair et le push transite par FCM / Web Push) — « Awa : 2 nouveaux messages » ; une seule notification non lue par conversation (remplacée ; mise à jour sans nouveau push dans les `notify_cooldown_seconds` = 60 s) ; texte neutre « Nouveau message » si la personne masque ses aperçus ; lien `/messages?c=<ulid>`.

### 12.3 Objets

**Conversation** (`ConversationResource`) : `id` (ULID), `kind` (`private` \| `group`), `name` (l'autre personne, sinon le titre, sinon les trois premiers pseudonymes), `title`, `avatar_url`, `other` (`{id, pseudo, avatar_url}`, conversation privée), `participants` (`[{id, pseudo, avatar_url, role, is_me}]`, présent·es ; `GET conversations/{ulid}` et réponses d'action seulement, absent de la liste), `participants_count`, `last_message` (`{id, preview, preview_hidden, mine, author, deleted, attachment, at}` ou null — `preview` **calculé à la lecture** depuis le corps déchiffré, 80 car., `null` et `preview_hidden = true` en mode discret ou aperçus masqués ; `conversations.last_message_preview` n'est plus écrit, ADR-33), `last_message_at`, `unread_count`, `muted`, `muted_until`, `role`, `can_send`, `can_manage`, `blocked_by_me`, `others_count`, `created_at`. Jamais d'e-mail ni de nom réel (RG-02).

**Message** (`MessageResource`) : `id`, `client_uuid`, `body` (null si supprimé), `deleted`, `mine`, `author` (`{id, pseudo, avatar_url}`), `attachment` (`{kind: image \| document, url}` : lien signé `media.signed`, expiration alignée sur `feed.media_url_window_minutes`), `status` (ses propres messages d'une conversation privée : `sent` \| `seen`), `created_at`.

### 12.4 Client web et hors connexion

Page `/messages` (`Member/Messages`, `?c=<ulid>` ouvre une conversation ; `?with=<id>` ne crée rien au chargement : conversation privée déjà ouverte → sélectionnée, sinon confirmation « Écrire à … ? » avant `POST conversations`, personne injoignable → avis neutre) ; sondage court sans WebSocket (D7) : conversation ouverte toutes les 4 s (`feed.messages_poll_seconds`, DECISIONS.md, `since` → `deleted_ids` appliqués), liste toutes les 15 s (`messaging.list_poll_seconds`, avec `since` = empreinte), 30 s après 3 réponses inchangées (`list_poll_idle_seconds`), seulement onglet visible et réseau présent. Après un rechargement, seuls les envois en attente du compte de la page sont réaffichés, pièce jointe reprise de l'entrée IndexedDB (sinon « Réessayer » masqué). Envoi : `client_uuid` = `Idempotency-Key` ; réseau coupé, 408, 429 ou 5xx → boîte d'envoi commune (`shared/outbox.js`, type `message`, rejeu avec la même clé), bulle « En attente d'envoi » (horloge grisée) jusqu'à ce que le serveur rende ce `client_uuid` ; refus → « Non envoyé », Réessayer (même uuid) ou Retirer.

## 13. Groupes `groups/*` — `auth:sanctum` (P8-02)

Groupes de la communauté (prototype `groups.php`, `group-details.php`), adressés par **ULID** (ADR-27). Code : `App\Domain\Community\GroupMembershipRules`, `App\Application\Community\Groups\*`, `App\Http\Controllers\Api\V1\Community\Group*Controller`, `GroupPolicy` ; tests `tests/Feature/Api/Community/GroupsApiTest.php`. Créer exige un e-mail confirmé (`verified`) ; créer, rejoindre, quitter et décider d'une demande passent par le limiteur `community_write` (20/min par compte) ; la discussion par `messages_write`. Groupe inactif ou supprimé : **404** (sauf `moderation.manage`).

**Confidentialité (FR-SOC-05, Q75)** : un groupe **privé** n'apparaît jamais dans les listes d'une non-membre ; par son lien, elle n'en voit qu'une **fiche réduite** (`restricted: true` : nom, zone, confidentialité, nombre de membres, couverture — ni description, ni demandes) pour demander à rejoindre ; ses publications (`posts`, `posts/{ulid}`, `feed/saved`), ses membres et sa discussion sont introuvables (**404**) ou refusés (**403** pour `conversation`). Membres : seuls les profils visibles par la personne qui lit sont listés (même règle que `ProfileAudience` : `private` → personne, `followers` → abonné·es, ni compte suspendu ou anonymisé, ni blocage dans un sens ou dans l'autre), les autres comptés dans `meta.hidden_count` ; l'équipe du groupe voit tout le monde. Aucune adhésion n'est écrite au journal d'audit (appartenir à un groupe peut révéler une identité, RG-02).

### 13.1 Routes

| Route | Réponse |
|---|---|
| `GET groups?tab=discover\|mine&q=&page=&per_page=` | **200** liste paginée par page (6, `xomiton.community.groups_per_page`) ; `discover` : groupes actifs publics + ceux dont on est propriétaire ou membre ; `mine` : ceux-là + ses demandes en attente ; `q` (≤ 60) « contient » sur nom, description, département ; groupes du département de la personne d'abord, puis les plus fréquentés ; `meta.tab` ; 422 onglet inconnu |
| `POST groups` | `{name (3–80), description? (≤ 300), privacy: public \| prive, zone_id? (département actif, null = national)}` → **201** + `Location` (`/groupes/{ulid}`) : propriétaire **et** `admin` active, `members_count = 1` ; filtre de mots → 422 `content_blocked` ; journal `group.created` |
| `GET groups/{ulid}` | **200** objet groupe (fiche réduite pour un groupe privé dont on n'est pas membre) |
| `POST groups/{ulid}/join` | **200** groupe : public → membre active ; privé → demande (`membership.status = demande`) notifiée **une fois** à la propriétaire et aux admins (`GroupJoinRequested`, sensible, cloche + push, préférence « Communauté », lien `/groupes/{ulid}?tab=members` ; ni comptes inactifs, ni blocage dans un sens ou dans l'autre ; annuler puis redemander ne renotifie qu'après `community.join_request_renotify_hours`) ; rejeu sans effet ; **403** personne exclue (`banni`) |
| `POST groups/{ulid}/leave` | **200** groupe : membre → départ (la personne quitte aussi la discussion) ; demande → annulée ; possible aussi dans un groupe désactivé ; **422** `group_last_admin` pour la dernière admin (la propriétaire qui part cède la propriété à l'admin la plus ancienne) ; **404** sans adhésion |
| `GET groups/{ulid}/members?status=active\|requested&page=` | **200** membres (24 par page), équipe d'abord ; `meta.hidden_count`, `meta.pending_requests` (équipe) ; `requested` (demandes) : équipe seulement (**403**) ; 404 si le groupe n'est pas lisible |
| `POST groups/{ulid}/members/{user}/accept` | **200** groupe ; demande acceptée, `GroupJoinAccepted` à la personne ; équipe (`moderate`) ; 404 sans demande |
| `DELETE groups/{ulid}/members/{user}` | **200** groupe : refuser une demande ou retirer une membre (journal `group.member_removed`) ; **403** `group_member_not_manageable` (propriétaire, rôle égal ou supérieur) |
| `PATCH groups/{ulid}/members/{user}` | `{role: membre \| moderateur \| admin}` → **200** membre ; propriétaire ou admin du groupe (`changeRole`), `admin` nommée par la propriétaire seule ; **403** sinon ; journal `group.role_changed` |
| `GET groups/{ulid}/posts?cursor=&per_page=` | **200** publications du groupe au format du fil (§8.4, curseur, 20) ; `can.comment` faux pour une non-membre (commenter une publication de groupe est réservé à ses membres, `POST posts/{ulid}/comments` → 403) ; publier : `POST posts` avec `group` (§8.3) |
| `POST groups/{ulid}/conversation` | « Discussion du groupe » : **201** (créée) puis **200** `{id (ULID de la conversation), url (/messages?c=)}` ; une seule conversation de groupe par groupe (`conversations.group_id`, §12), la personne y entre (équipe du groupe en `admin`) ; membres actives seulement (**403**) |

Hiérarchie (`GroupMembershipRules`) : la propriétaire gère tout le monde sauf elle-même ; une admin gère modérateur·rices et membres ; un·e modérateur·rice gère les membres (accepter, refuser, retirer) ; changer un rôle : propriétaire ou admin.

### 13.2 Objets

**Groupe** (`GroupResource`) : `id` (ULID), `name`, `description` (null en fiche réduite), `privacy` (`public` \| `prive`), `privacy_label`, `zone` (`{id, name}` ou null), `zone_label` (« National »), `members_count`, `cover_url`, `url`, `restricted`, `is_owner`, `membership` (`{status: actif \| demande, status_label, role, role_label}` ou null), `pending_requests` (équipe, sinon null), `created_at`, `can` (`join`, `cancel_request`, `leave`, `post`, `discuss`, `moderate`, `change_role`).

**Membre** (`GroupMemberResource`) : `id` (compte), `pseudo`, `avatar_url` (jamais le nom réel ni l'e-mail), `role`, `role_label` (« Propriétaire » pour elle), `status`, `is_owner`, `is_me`, `joined_at`, `requested_at`, `can` (`accept`, `remove`, `change_role`, `make_admin`).

## 14. Pages d'organisations `pages/*` — `auth:sanctum` (P8-03)

Pages d'organisations partenaires (prototype `pages.php`, `create-page.php`), adressées par **ULID**. Code : `App\Application\Community\Pages\*`, `PageController`, `PagePolicy` ; tests `tests/Feature/Api/Community/PagesApiTest.php`. Une page créée par un·e membre est **en attente** (`pages.is_approved = false`) : visible de sa seule propriétaire et de la modération jusqu'à son approbation (P12-05, écran `/admin/reseau` à venir : `PagePolicy::approve`, `feature`) ; pour les autres, **404** (fiche, suivre) et absente des listes. Pages importées du prototype : approuvées.

| Route | Réponse |
|---|---|
| `GET pages?tab=all\|following\|mine&category=&q=&page=` | **200** liste paginée par page (12) : pages approuvées et actives (`following` : celles qu'on suit ; `mine` : les siennes, en attente comprises) ; mises en avant d'abord, puis les plus suivies ; `meta.categories` (catégories des pages visibles) ; 422 onglet inconnu |
| `POST pages` | `{name (3–80), category (xomiton.community.page_categories : Organisation, Santé, Juridique, Réseau, Éducation, Autonomisation), description? (≤ 400), website? (http(s), ≤ 190)}` → **201** + `Location` : `status = pending`, la propriétaire la suit ; filtre de mots → 422 `content_blocked` ; journal `page.created` |
| `GET pages/{ulid}` | **200** page ; 404 (en attente d'une autre personne, inactive, supprimée) |
| `POST · DELETE pages/{ulid}/follow` | **200** `{following, followers_count, changed}` — idempotent (`changed: false` sur un rejeu, compteur jamais sous zéro) ; page approuvée seulement |

**Page** (`PageResource`) : `id` (ULID), `name`, `category`, `description`, `website`, `cover_url`, `followers_count`, `is_verified` (« Page vérifiée »), `is_featured` (« En avant »), `status` (`approved` \| `pending`), `status_label`, `is_mine`, `following`, `url`, `created_at`, `can` (`follow`, `update`).

## 15. Événements `events/*` — `auth:sanctum` (P8-04)

Événements de la communauté (prototype `events.php`, `event-details.php` ; `simulateur.html` l.1155-1166), adressés par **ULID** (ADR-27). Code : `App\Domain\Community\EventDisclosure`, `App\Application\Community\Events\*` (`EventAccess`, `EventDirectory`, `CreateEvent`, `RespondToEvent`, `SendEventReminders`), `App\Http\Controllers\Api\V1\Events\EventController`, `EventPolicy` ; tests `tests/Feature/Api/Community/EventsApiTest.php`. `social.access` (**403** sur la liste sans ce droit) ; créer exige un e-mail confirmé (`verified`), répondre « Je participe » aussi (403 `email_unverified`, annuler reste possible) ; créer et répondre passent par `community_write` (20/min par compte). Toutes les réponses sont **`Cache-Control: no-store, private`** (sans ETag : adresse et lien déchiffrés, comme la messagerie, Q74).

**Confidentialité (FR-SOC-05, simulateur l.1164)** : `place` est la **ville ou la zone**, visible de toute personne qui voit l'événement ; **`address` (adresse exacte) et `online_url` (lien de connexion), tous deux chiffrés au repos, ne figurent dans la réponse que si le compte a répondu « Je participe »**, organise l'événement ou modère (`moderation.manage`) — sinon les clés sont **absentes** et `exact_place_reserved: true` signale qu'une adresse ou un lien attend les participant·es. Événement d'un groupe privé (non-membre), brouillon, masqué, supprimé, d'une page en attente ou désactivée, ou dont l'organisateur·rice et la personne qui lit **se bloquent** (dans un sens ou dans l'autre, `user_blocks` ; la modération garde l'accès) : **404** (jamais 403), absent des listes ; le rappel de la veille et la cloche de groupe ne partent pas non plus dans ce cas. La liste des participant·es n'est jamais exposée (seul `going_count`) ; aucune réponse n'est écrite au journal d'audit. Un événement **public** (`is_public`, Q35 a, ouvert par la modération seule) est aussi servi aux visiteur·ses par la page Blade `/evenements` (ville seulement, organisation : page, groupe ou « Équipe Xomiton », jamais un pseudo ; JSON-LD `Event`, plan du site).

| Route | Réponse |
|---|---|
| `GET events?filter=upcoming\|going\|past&from=today&category=&q=&page=&per_page=` | **200** liste paginée par page (6, `xomiton.events.per_page`) : `upcoming` (défaut, `from=today` équivalent) à venir ou en cours du plus proche au plus lointain, `going` mes participations à venir, `past` terminés du plus récent ; `category` : slug d'une catégorie d'événement active ; `q` (≤ 60) « contient » sur titre, ville, description (jamais l'adresse) ; `meta.filter` ; 422 filtre ou catégorie inconnus |
| `POST events` | `{title (3–200), starts_at (date et heure à venir ; sans décalage = fuseau de l'application), ends_at? (après le début, 31 jours au plus), place? (ville ou zone, ≤ 120), address? (≤ 255), is_online?, online_url? (https, ≤ 500 ; en ligne sans lien ni ville → 422), category_id? (catégorie d'événement active), description? (≤ 2000), group? (ULID d'un groupe où l'on publie : propriétaire ou membre active, sinon 422), is_public? (modération seulement, sinon 422 ; jamais pour un groupe privé)}` → **201** + `Location` (`/evenements/{ulid}`) : publié aussitôt, la personne est l'organisatrice ; filtre de mots (titre, description, ville, adresse) → 422 `content_blocked` ; événement de groupe : cloche + push « Événement créé dans un de mes groupes » (`EventCreatedInMyGroup`, préférence « Communauté ») aux autres membres actives ; journal `event.created` |
| `GET events/{ulid}` | **200** objet événement (adresse et lien selon la règle ci-dessus) |
| `POST events/{ulid}/rsvp` | `{going: bool, reminder?: bool (oui par défaut)}` → **200** événement à jour : `going: true` → « Je participe » (adresse et lien désormais livrés, rappel de la veille choisi ; **403** `email_unverified` sans e-mail confirmé) ; `going: false` → réponse supprimée ; idempotent, `going_count` recompté ; **422** `event_finished` pour participer à un événement terminé (annuler reste possible) ; **404** événement invisible |

**Événement** (`EventResource`) : `id` (ULID), `title`, `starts_at`, `ends_at` (ISO 8601), `day`, `month_short`, `date_label` (« jeudi 7 novembre 2026 »), `time_label` (« 15 h »), `end_time_label`, `place` (ville ou zone, ou null), `is_online`, `location_label` (« Cotonou », « En ligne », « Porto-Novo · en ligne », « Lieu à préciser »), `address` et `online_url` (**seulement** pour participant·e, organisation, modération), `exact_place_reserved`, `category` (`{slug, label}` ou null), `description`, `image_url`, `organizer` (pseudo ou libellé, « Équipe Xomiton » par défaut ; membres seulement), `group` (`{id, name, url}` ou null), `going_count`, `is_public`, `is_past`, `status`, `url`, `rsvp` (`{status: going, reminder}` ou null), `reminder_available` (le rappel de la veille n'est pas encore parti et l'événement n'a pas commencé : une réponse donnée maintenant le recevra ; faux sinon, l'écran ne promet alors pas de rappel), `can` (`rsvp`, `cancel`, `update`), `created_at`.

**Rappel de la veille** : tâche `events:remind` (toutes les quinze minutes, `routes/console.php`) — `EventReminder` (cloche, push, e-mail `rappel-evenement`) aux participant·es confirmé·es qui ont gardé le rappel, une seule fois par événement (`events.reminder_sent_at` posé avant l'envoi : une réponse arrivée après n'en reçoit pas, `reminder_available` faux), hors blocage avec l'organisateur·rice, dans les 24 h qui précèdent le début (`xomiton.events.reminder_hours`) ; texte : titre, date, heure, **ville seulement** (jamais l'adresse ni le lien). Côté mobile, « Je participe » ajoute aussi l'événement au calendrier du téléphone (`add_2_calendar`, P15-06) à partir de `starts_at`, `ends_at`, `address`.

## 16. Forums `forums/*` — `auth:sanctum` (P8-05)

Les **5 forums fixes** du prototype (`forums.php`, `forum-topic.php` ; `ForumsSeeder`, clés `f1`…`f5`), leurs **sujets** adressés par **ULID** (ADR-27) et leurs **réponses** (id numérique, comme un commentaire). Code : `App\Domain\Community\ForumAudience`, `App\Application\Community\Forums\*` (`ForumDirectory`, `StartTopic`, `ReplyToTopic`, `ForumReplyNotifier`, `RemoveForumContent`, `RecordTopicView`, `ForumCounters`), `App\Http\Controllers\Api\V1\Forums\*`, `ForumTopicPolicy`, `ForumReplyPolicy` ; tests `tests/Feature/Api/Community/ForumsApiTest.php`, `ForumPrivacyTest.php`. Aucune permission propre : `social.access` lit (**403** sans ce droit), `posts.create` ouvre un sujet, `comments.create` répond, `posts.moderate` modère ; ouvrir un sujet et répondre exigent un e-mail confirmé (`verified`) et passent par `feed_write` (10/min), signaler par `content_report` (20/h), comme le fil.

**Anonymat (RG-02, Q72, mêmes règles que le fil)** : un sujet ou une réponse anonyme sort avec `author = {id: null, pseudo: « Membre anonyme », avatar neutre, role: null, badge: null, anonymous: true}` — **même pour son auteur·rice** (`is_mine` suffit) ; la recherche ne porte jamais sur l'auteur·rice ; la cloche dit « Membre anonyme ». Blocages : celui posé par l'auteur·rice retire son contenu (anonyme ou non) ; celui posé par la personne qui lit ne retire qu'un contenu **signé** (bloquer ne révèle pas qui a écrit). Sujet masqué, supprimé, d'un forum fermé ou retiré par un blocage : **404** (jamais 403) ; l'auteur·rice voit le sien avec `moderated: true`. L'auteur·rice réel·le reste en base (modération, export RGPD `forum_topics` / `forum_replies`).

| Route | Réponse |
|---|---|
| `GET forums` | **200** les forums actifs dans l'ordre du prototype |
| `GET forums/topics?forum=f1&q=&page=&per_page=` | **200** « Sujets récents » paginés par page (6, `xomiton.forums.topics_per_page`) : sujets publiés des forums actifs, épinglés d'abord puis dernière activité ; `forum` : clé d'un forum actif (422 sinon) ; `q` (≤ 60) « contient » sur le titre et le message ; sans `body` ; `meta.forum` |
| `POST forums/topics` | `{forum (clé), title (5–140), body (10–5000), is_anonymous?}` → **201** + `Location` (`/forums/{ulid}`) : publié aussitôt, `topics_count` du forum recalculé ; filtre de mots (titre, message) → 422 `content_blocked`, terme « à surveiller » → signalement automatique |
| `GET forums/topics/{ulid}` | **200** sujet avec `body` ; vue comptée une fois (`views_count` : navigateur, une par session ; jeton Bearer, une par compte et par `xomiton.forums.view_window_minutes` ; ni l'auteur·rice, ni `HEAD`, ni préchargement, ni robot) |
| `DELETE forums/topics/{ulid}` | **204** : auteur·rice ou modération (journal `forum.topic_deleted_by_moderation`) ; **403** sinon ; statut `supprime` + suppression douce, compteurs du forum recalculés |
| `GET forums/topics/{ulid}/replies?page=` | **200** réponses publiées de la plus ancienne à la plus récente (30 par page) ; `meta.replies_count` |
| `POST forums/topics/{ulid}/replies` | `{body (2–2000), is_anonymous?}` → **201** réponse + `meta.replies_count` ; `replies_count`, `last_reply_at` du sujet et `posts_count` du forum recalculés sous verrou ; **422** `topic_locked` pour un sujet fermé (`is_locked`) ; forum fermé (`is_active` faux) : **403** pour l'auteur·rice ou la modération qui lisent encore le sujet (404 pour les autres), 422 `topic_locked` pour le superadmin ; filtre de mots comme ci-dessus ; cloche `ForumReplyPosted` (« Nouvelle réponse », préférence « Communauté », lien `/forums/{ulid}#reponses`) à l'auteur·rice du sujet, jamais pour soi-même ni vers un compte inactif, **seulement si la réponse lui est visible** (aucune absence ne trahit un blocage) ; une non lue du même sujet est remplacée |
| `DELETE forums/replies/{id}` | **204** : auteur·rice ou modération (journal `forum.reply_deleted_by_moderation`) |
| `POST forums/topics/{ulid}/report` · `POST forums/replies/{id}/report` | `{reason, details?}` comme §8.5 → **201** au premier signalement, **200** ensuite ; **403** sur son propre contenu ; `moderation_reports.kind` `sujet` / `reponse`, extrait « titre — message » ; RG-16 : masqué au seuil (`moderation_auto_hide_reports`), compteurs du sujet et du forum recalculés |

**Forum** (`ForumResource`) : `key` (`f1`…), `slug`, `name`, `description`, `icon` (Bootstrap Icons sans « bi- »), `topics_count` (sujets publiés), `posts_count` (réponses publiées : « N sujets · M messages »), `url` (`/forums?forum=f1`).

**Sujet** (`ForumTopicResource`) : `id` (ULID), `forum` (`{key, name, url}`), `title`, `body` (fiche seulement), `author` (pseudo, avatar, rôle, `badge` comme le prototype : « Pair animateur·rice » pour `pair`, « Équipe Xomiton » pour `admin` et `superadmin`, aucun pour `moderateur` ; anonyme ci-dessus), `is_mine`, `is_anonymous`, `is_pinned`, `is_locked`, `moderated`, `replies_count`, `views_count`, `created_at`, `last_reply_at`, `url`, `can` (`reply` : faux pour un sujet fermé, non publié ou d'un forum fermé, calculé sans requête par sujet ; `delete`, `report`).

**Réponse** (`ForumReplyResource`) : `id`, `body`, `author`, `is_mine`, `is_anonymous`, `created_at`, `can` (`delete`, `report`).

## 17. Qui suivre et invitations `follows/*`, `invitations/*` — `auth:sanctum` (P8-06)

Page `/qui-suivre` du prototype (`who-to-follow.php`) et carte « Qui suivre » du fil. Code : `App\Domain\Community\NetworkRules`, `App\Application\Community\Network\*` (`NetworkPeople`, `NetworkSuggestions`, `InvitationDirectory`, `ManageInvitations`, `FollowMember`, `InvitationNotifier`, `NetworkLinks`), `App\Http\Controllers\Api\V1\Network\*`, `InvitationPolicy`, `FollowPolicy` ; tests `tests/Feature/Api/Community/NetworkApiTest.php`, `tests/Feature/Member/WhoToFollowPageTest.php`. `social.access` lit et agit (**403** sans ce droit) ; inviter exige un e-mail confirmé (`verified` : l'invitation notifie l'autre personne) ; tous les gestes passent par `community_write` (20/min), inviter aussi par `network_invite` (**30 par jour** et par compte, `xomiton.rate_limits.network_invites_per_day`, contre le harcèlement : chaque invitation notifie) → **429**. Listes **par curseur** (`?cursor=`, `meta.next_cursor` opaque, `meta.per_page`) : la suite reprend après le dernier élément reçu, jamais à un rang — une personne invitée ou suivie, une invitation tranchée ou annulée entre deux pages ne fait rien sauter ; curseur illisible → première page.

**Aucune donnée privée (RG-02)** : une personne sort sous la forme `{id, pseudo, avatar_url, role, role_label, zone, profile_url}` — rôle **public** calculé sur tous les rôles du compte, équipe > pair > membre (`member` · `peer` « Pair animateur·rice » · `team` « Équipe Xomiton », `moderateur` sans badge comme les forums ; une pair validée garde `membre` et porte le badge pair), `zone` (`{id, name}`, département) **seulement si la personne l'affiche** (`profiles.show_zone`), `profile_url` (`/membres/{pseudo}`, P8-07) ; jamais le nom réel, l'e-mail, le téléphone, la commune, l'âge, l'identité de genre ni l'orientation. **Blocage** dans un sens ou dans l'autre (comme le fil, les événements et les forums) : la personne n'apparaît pas dans les suggestions, une invitation entre les deux disparaît des listes et des compteurs et répond **404** à tout geste ; suivre et inviter répondent le 422 neutre ci-dessous.

| Route | Réponse |
|---|---|
| `GET follows/suggestions?q=&zone=&role=&cursor=&per_page=` | **200** suggestions par curseur (12, `xomiton.network.suggestions_per_page` ; `meta.next_cursor`, `meta.per_page`) : comptes actifs, non anonymisés, avec `social.access`, profil ouvert au réseau (`public`, `community`) et `searchable`, jamais superadmin ; sans les personnes déjà suivies, ni celles avec une invitation en attente (dans un sens ou dans l'autre), ni celles qui ont refusé une invitation de la personne depuis moins de `xomiton.network.reinvite_after_refusal_days` (30 j) ; `q` (≤ 60) « contient » sur le **pseudonyme** seulement ; `zone` : id d'une zone active, limitée aux profils qui l'affichent ; `role` : `member` · `peer` · `team` ; valeur inconnue → 422 ; même zone que la personne d'abord, puis pseudonyme |
| `POST follows` | `{user_id}` → **200** `{user_id, following: true, changed}` — idempotent ; profil ouvert au réseau seulement : un profil « connexions » (`followers`) ou privé ne se suit qu'au travers d'une invitation acceptée ; compte inconnu, soi-même, inactif, bloqué ou fermé → **422** `follow_unavailable` (même réponse dans tous les cas) |
| `DELETE follows/{user_id}` | **200** `{user_id, following: false, changed}` — idempotent, permis même après un blocage |
| `GET invitations?box=received\|sent&cursor=&per_page=` | **200** invitations **en attente** reçues (défaut) ou envoyées, plus récentes d'abord (20 par page, par curseur : `meta.next_cursor`) ; `meta.box`, `meta.counts` `{received, sent}` (compteurs des onglets) |
| `POST invitations` | `{user_id}` → **201** invitation créée (cloche `InvitationReceived`), **200** si une invitation de la personne est déjà en attente (`meta.created: false`, **jamais de doublon** : unique `(from_user_id, to_user_id)`) ; `meta.counts` ; une invitation acceptée dont la connexion a été défaite, ou refusée il y a plus de 30 j, est rouverte sur la même ligne ; **422** `invitation_unavailable` (compte inconnu, soi-même, inactif, sans `social.access`, profil non visible selon `ProfileAudience`, compte « non trouvable » (`searchable` faux, P8-09) ou superadmin sans lien déjà fait — abonnement ou invitation en attente, dans un sens ou dans l'autre —, blocage, refus récent : un seul message, rien n'est révélé), `already_connected`, `invitation_from_them` (l'autre personne a déjà invité : l'accepter) ; **429** au-delà de `network_invite` (30 par jour). Un profil « connexions » (`followers`) ne se suit pas d'un geste et n'est jamais proposé : il ne reçoit d'invitation que d'une personne qui le suit déjà ; **l'initiative du lien lui revient** (il suit ou invite lui-même, Q79) |
| `POST invitations/{id}/accept` | **200** invitation `acceptee` + `meta.counts` : abonnement **dans les deux sens** (« Vous êtes connecté·es »), cloche `InvitationAccepted` à la personne qui avait invité ; personne invitée seulement (**403** sinon) ; répéter : 200 ; invitation refusée : **422** `invitation_not_pending` |
| `POST invitations/{id}/refuse` | **200** invitation `refusee` + `meta.counts` ; **aucune notification** ; répéter : 200 ; acceptée : 422 `invitation_not_pending` |
| `DELETE invitations/{id}` | **204** : la personne qui a invité annule une invitation **en attente** (ligne supprimée, nouvel envoi possible aussitôt) ; **403** pour la personne invitée ; acceptée ou refusée : **404**, comme un blocage ou une suspension (la personne qui a invité ne distingue jamais un refus, Q79) |

**Suggestion** (`NetworkPersonResource`) : la personne ci-dessus + `following` et `can` (`follow` : profil ouvert au réseau ; `invite`). **Invitation** (`InvitationResource`) : `id`, `status` (`en_attente` · `acceptee` · `refusee`), `status_label`, `direction` (`received` · `sent`, pour la personne qui lit), `person` (**l'autre personne** seulement), `created_at`, `responded_at`, `can` (`accept`, `refuse` pour la personne invitée ; `cancel` pour celle qui a invité ; tant que l'invitation est en attente).

**Cloche** : `InvitationReceived` (« Nouvelle invitation », `url` `/qui-suivre?tab=recues`, `data.invitation_id`, `data.user_id` = compte qui invite) et `InvitationAccepted` (« Invitation acceptée », `url` `/qui-suivre`), préférence « Communauté », pseudonyme seulement ; jamais vers un compte inactif ou bloqué ; inviter, annuler puis réinviter ne renotifie pas avant `xomiton.network.invite_renotify_hours` (24 h) ; une invitation acceptée, refusée ou annulée passe ses `InvitationReceived` à « lue ». Aucun journal d'audit (qui se connecte à qui peut révéler une identité, comme les adhésions de groupe, Q75).

**Page web** `/qui-suivre` (`Community/WhoToFollow`) : `?tab=recues|envoyees`, `?q=&zone=&role=membre|pair|equipe` (lecture tolérante : valeur inconnue → défaut) ; **`?invite=<id>`** (carte « Qui suivre » du fil, `inc/render.php` l.80) **prépare** l'invitation (`invite: {status: ready|pending|connected|from_them|unavailable, person}` ; `person.following` : déjà suivie ; `person.can.invite` vrai seulement pour `ready` ; `unavailable` sans personne aussi pour un compte « non trouvable » ou superadmin sans lien, comme `POST invitations` ; aperçus bornés par `network_preview`, 30/min, **429**) et n'envoie **rien** sans confirmation : un lien ne suffit jamais à relier deux comptes (le prototype l'envoyait dès le GET). Carte « Qui suivre » du fil : prop différée `who_to_follow` (5 suggestions, `xomiton.network.card_suggestions`), « + » → `/qui-suivre?invite=<id>`.

## 18. Profil d'un·e membre `members/{pseudo}…` — `auth:sanctum` (P8-07)

Lot « profil » : en-tête et onglets du profil (`prototype_social/my-profile*.php`), pour la page Vue `Member/Profile` (`/profil/{onglet?}`, `/membres/{pseudo}/{onglet?}`) et l'application mobile. Profil visible selon `App\Domain\Account\ProfileAudience` (comme `users/{id}/posts`, §11) : soi-même toujours ; sinon `social.access` et `public` / `community` pour tout compte, `followers` pour ses abonné·es ; `private`, blocage dans un sens ou dans l'autre, compte suspendu, anonymisé ou inconnu → **404** (jamais 403). Toutes les réponses sont `no-store`. Publications du profil : `GET users/{id}/posts` (§11).

| Route | Réponse |
|---|---|
| `GET members/{pseudo}` | **200** profil (`MemberProfileResource`, ci-dessous) |
| `GET members/{pseudo}/connections?q=&page=` | **200** connexions (invitation **acceptée** dans un sens ou dans l'autre) paginées par page (24, `xomiton.profile.connections_per_page`), par pseudo ; comptes actifs seulement, sans blocage avec le profil ni avec la personne qui lit ; sur le profil d'une autre personne, seulement les connexions au profil `public` / `community` ; `q` ≤ 60 sur le pseudo (422 au-delà) ; `meta.q` |
| `GET members/{pseudo}/media` | **200** jusqu'à 24 images : celles des publications que la personne qui lit peut voir (jamais une publication anonyme) et des albums visibles ; `{ id, url, alt, width, height, href, at }` (image privée en lien signé à fenêtre fixe) |
| `GET members/{pseudo}/events` | **200** `data` : son profil — événements auxquels la personne participe ou qu'elle organise ; une autre personne — **seulement ceux qu'elle organise** (sa participation n'est jamais exposée) ; à venir puis passés, 12 au plus, format §15 (adresse exacte réservée) ; `suggestions` (son profil : 3 prochains événements auxquels elle ne participe pas) |

**Profil** (`MemberProfileResource`) : `id, pseudo, url, is_self, avatar_url, cover_url, role, role_label, verified` (pair validé·e ou équipe), `member_type, member_type_label, pronouns, bio, zone_label` (si `show_zone`), `joined_at, joined_label, interests[{slug, label}]`, `stats{posts, connections, followers, following}` (publications que la personne qui lit peut voir), `relationship{following, follows_you, connected}` (null pour soi), `message_url` ; pour soi en plus `completion, visibility, visibility_label, show_zone, has_avatar, has_cover, can_change_images` (e-mail confirmé). `zone_label` : « commune, département » pour soi, **département seul** pour une autre personne ; `member_type` / `role_label` d'une autre personne : `membre` ou `pair`, `pair` seulement une fois validé·e (statut actif, RG-03) ; `stats.followers` / `following` sans compte suspendu, en attente, anonymisé ou bloqué. **Jamais** : nom réel, e-mail, téléphone, tranche d'âge, contact de confiance, mode discret (RG-02). `sensitive[{field, label, value, value_label, shared}]` : pour une autre personne, **seulement** les champs de `shared_fields` renseignés — et pour `gender_identity` / `orientation`, tant que le consentement `sensitive_data` du profil court ; pour soi, tous les champs renseignés avec leur état `shared`.


## 19. Annuaire membre `directory/*` — `auth:sanctum` (P8-12)

Annuaire dynamique de l'espace connecté (prototype `annuaire.php` ; page Vue `/annuaire`). Code : `App\Domain\Directory\DirectoryReach`, `App\Application\Directory\BrowseDirectory` (+ `DirectoryFilters`), `App\Http\Controllers\Api\V1\Directory\DirectoryController`, `App\Http\Requests\Directory\BrowseDirectoryRequest`, `DirectoryContactResource` ; demande de rappel : `App\Application\Alerts\RequestCallback` (P5-02) et `RequestCallbackRequest` ; tests `tests/Feature/Api/Directory/DirectoryApiTest.php`, `tests/Feature/Member/DirectoryPageTest.php`. Réponses **`Cache-Control: no-store, private`** (une portée réseau porte des numéros non publics).

**Portée** (`meta.reach`, ROLE_ET_PERMISSIONS.md §3 ; Q19) : `public` — membre et compte pair·e **en attente** de validation (RG-03) : fiches actives `is_public` ; `network` — pair·e validé·e (`peer_space.access`), modération et administration (`admin.access`), compte actif : fiches actives non publiques comprises (`is_public: false`) ; sans `directory.view` : **403** (la page `/annuaire` aussi : route `can:directory.view`). Jamais une fiche inactive ou supprimée, jamais `notes` ni `user_id`. Les contacts servis **sans compte** restent ceux de `GET urgences` (§6.7).

| Route | Effet |
|---|---|
| `GET directory?q=&role=&zone=&commune=&page=` | **200** fiches par ordre alphabétique, `config('xomiton.directory.per_page')` (24) par page ; `q` (≤ 80 caractères) cherche dans le nom, l'organisation, le libellé du rôle et la commune ; `role` = valeur de `DirectoryRole`, `zone` = département actif, `commune` = commune de ce département ; **valeur inconnue ignorée** (jamais 422, comme le prototype). `meta` : pagination (`current_page, last_page, per_page, total`), `reach`, `filters{q, role, zone, commune}` lus, `map{zones[{id, name, slug, count}], national, total}` — décompte par département des **mêmes filtres hors département et commune** (chaque département actif, `count` 0 compris), fiches sans département dans `national` ; **page 1 seulement** (`map: null` ensuite : « Charger plus » n'en a pas besoin) |
| `POST directory/{id}/callback` | « Demander à être rappelé·e » : `{contact (téléphone ou e-mail, obligatoire, ≤ alerts.callback_contact_max, 190), message? (≤ alerts.callback_message_max, 1000), anonymous? (vrai par défaut : pseudo non transmis), preferred_channel?}` + **`Idempotency-Key` obligatoire** (`idempotent:required,limit=directory_callback` : rejeu → même réponse `Idempotent-Replayed: true`, aucune alerte de plus, quota non consommé ; limiteur **par compte**, `rate_limits.directory_callback_per_hour` = 10, à part du quota `report` par IP des signalements anonymes). **201** alerte (§6, `AlertResource` : `tracking_code`, `type: need`, `routed{role, name, phone, whatsapp}`), `Location` = suivi `signalements/{code}` : alerte besoin `source = annuaire` confiée **au contact choisi sans RG-04** (`routing_fallback = manual`), dans son département, besoin déduit du rôle (`needs.routed_role`, sinon `autre`), notification `AlertRouted` (cloche, push, e-mail `alerte-routee`) au compte lié à la fiche et à l'équipe de garde. **422** `contact_unavailable` : fiche inactive, supprimée ou **non publique** (Q51, même pour une portée réseau) ; **422** erreurs par champ ; **422** `idempotency_key_required` ; **403** sans `directory.view` ; **429** `too_many_requests` (+ `Retry-After`) |

**Fiche** (`DirectoryContactResource`) : `id, name, role, role_label, role_color` (`DirectoryRole::memberBadge()`, la même que `/urgences` : `danger` et `secondary` deviennent `primary`, rouge réservé à l'urgence, Q62 — un client l'applique telle quelle), `org, phone, tel` (lien `tel:`), `whatsapp, whatsapp_url` (`App\Support\WhatsAppLink` : numéro WhatsApp, sinon le téléphone ; numéro béninois local mis au format `https://wa.me/229…`, autre numéro seulement avec son indicatif, null sinon), `email, availability, zone{id, name}|null, commune{id, name}|null, national` (sans département ou point focal national : afficher « National » même si `zone` est renseigné), `is_public`, `can{callback}` (`DirectoryReach::canRequestCallback(is_public)` : fiche publique seule, Q51).

## 20. Recherche membre `search/*` — `auth:sanctum` (P8-13)

Champ de recherche de l'en-tête (`XmHeader`, prototype `partials/header.php` l.30-33 « Rechercher une ressource, un contact… ») et page Vue `/recherche?q=&type=` (Inertia `Search/Index`, noindex, `Disallow: /recherche`, hors sitemap). Code : `App\Domain\Search\{SearchTerm, SearchType, Exceptions\SearchUnavailable}`, `App\Application\Search\MemberSearch` (+ `SearchSection`), `App\Http\Controllers\Api\V1\Search\SearchController`, `App\Http\Controllers\Web\Member\SearchPageController`, `App\Http\Requests\Search\SearchRequest`, `App\Http\Resources\Search\{SearchHitResource, SearchSuggestionResource, SearchSectionPayload}`, `App\Http\Middleware\ForgetSearchTerms` ; tests `tests/Feature/Api/Search/*`, `tests/Feature/Member/SearchPageTest.php`, `tests/Unit/Search/SearchTypeTest.php`, Vitest `Pages/Search/Index.test.ts`, `components/search/HeaderSuggest.test.ts`, `search/suggest.test.ts`.

**Familles** (`type`, dans l'ordre) — colonnes **en clair** seulement (ADR-33) : `ressources` (catalogue de `/ressources` : titre, résumé ; publiées, hors actualités) · `thematiques` (publiées : titre, résumé) · `contacts` (annuaire selon la portée `DirectoryReach`, §19 : nom, organisation, libellé du rôle, commune ; jamais `notes`) · `membres` (comptes **trouvables** : `NetworkPeople::discoverableBy`, §17 — actifs, profil `public` ou `community`, `searchable` vrai, sans blocage dans un sens ou dans l'autre, jamais un rôle caché ni soi-même ; **pseudonyme** seulement, jamais le nom réel). `contacts` exige `directory.view`, `membres` l'accès au réseau (`social.access`) : `meta.types` liste les familles du compte. Groupes et événements : non compris (ligne P8-13, Q84).

**Terme** `q` : lecture tolérante (jamais 422) — caractères de contrôle retirés, espaces normalisés, tronqué à `xomiton.search.max_length` (80) ; en deçà de `min_length` (2 caractères), **aucune requête** : familles vides, `meta.too_short: true`. Recherche « contient » (`LIKE` échappé, `App\Support\LikePattern`).

| Route | Effet |
|---|---|
| `GET search?q=&type=&page=` | Sans `type` (ou famille inconnue / fermée au compte) : **200** `data[]` = une section par famille du compte `{type, label, icon, total, more, items[]}`, `overview_per_type` (4) résultats chacune ; `meta{types[{type, label, icon}], too_short}`. Avec `type` : **200** `data[]` = résultats de la famille, `per_page` (10) par page ; `meta{types, too_short, type, current_page, last_page, per_page, total}`. Résultat (`SearchHitResource`) : `type` puis la forme de l'écran de la famille — `ResourceCardResource` + `icon` (§11), `ThematiqueCardResource` + `icon` (§11), `DirectoryContactResource` + `url` (`/annuaire?q=<nom>`, §19), `NetworkPersonPayload` (§17 : `id, pseudo, avatar_url, role, role_label, zone, profile_url`) |
| `GET search/suggestions?q=` | Suggestions instantanées de l'en-tête (anti-rebond 300 ms côté client, une requête à la fois) : **200** `data[]` = sections **non vides** `{type, label, icon, total, more, items[]}`, `suggestions_per_type` (3) suggestions chacune, forme courte `{type, id, title, meta, url, inertia, icon, avatar_url}` (`inertia` : page de l'espace membre, sinon page publique Blade) ; `meta{too_short}` |

Erreurs : **401** sans compte ; **429** `too_many_requests` (+ `Retry-After`) au-delà de `rate_limits.search_per_minute` (60) par compte — limiteur `search`, **hors `throttle:api`** (les suggestions n'épuisent pas le quota de la cloche ni de la messagerie), **même compteur** que la page `/recherche` (page 429) ; **503** `search_unavailable` si la base échoue (la page `/recherche` affiche alors un message, `failed: true` ; signalée côté API comme côté page avec la classe de l'erreur d'origine et son SQLSTATE seulement). Réponses `Referrer-Policy: strict-origin`, comme la page.

**Confidentialité — aucun historique serveur** : réponses `Cache-Control: no-store, private` (ni navigateur, ni proxy, ni service worker) ; le terme n'est jamais renvoyé (ni `meta`, ni liens de pagination), jamais écrit en base ni en journal : `ForgetSearchTerms` (avant `auth`, priorité dans `bootstrap/app.php`) retire la chaîne de requête avant que la session ne retienne l'adresse (`_previous.url` d'un chargement complet, `url.intended` d'une visite sans compte) ; `SearchTerm` masque sa valeur dans les dumps et refuse la sérialisation ; toute erreur pendant la recherche devient `SearchUnavailable` **sans** l'exception d'origine (une `QueryException` recopierait les liaisons SQL). Côté client : rien dans `localStorage`, `sessionStorage` ni IndexedDB, champ de l'en-tête vidé après l'envoi, historique Inertia chiffré (`encryptHistory`). Hors application : les journaux d'accès du serveur web gardent l'adresse `?q=` (à régler sur l'hébergement, Q84).

## 21. Synchronisation hors connexion `sync/*`, `heartbeat` — `auth:sanctum` + appareil (P9-06)

Moteur de SOFTWARE_ARCHITECTURE §5 pour l'application mobile (boîte d'envoi Hive) : **appareils enregistrés seulement** (`POST me/devices`, §3 ; en-tête `X-Device-Id` obligatoire, sinon **403** `device_required`, appareil révoqué **403** `device_revoked`, ADR-26 ; la boîte d'envoi web rejoue les routes dédiées, §21.6). `sync/push`, `sync/pull` et `heartbeat` tiennent `sync_devices.last_seen_at` (et `last_sync_at` pour push / pull ; `SyncDeviceResolver::touch`). Réponses `Cache-Control: no-store, private`. Liste blanche de maintenance : `sync/push` et `heartbeat` (RG-01 ; hors administration, seuls les éléments `sos` et `signalement` d'un lot sont traités, §21.2) ; `sync/pull` et `sync/conflicts` répondent 503 pendant la maintenance. Espace pair (collecte, collections du pair) : compte **actif** détenant la permission (`PeerSpace`, RG-03 : un compte pair en attente de validation reçoit 403 sur un élément `collecte` et ne tire que les collections publiques). Code : domaine `App\Domain\Sync\{FieldMerge, FieldOwner, SyncItemType, Exceptions\*}` ; application `App\Application\Sync\{SyncDeviceResolver, Push\*, Pull\*, Conflicts\ApplyCaseChanges}` ; `App\Http\Controllers\Api\V1\Sync\*`, `App\Http\Requests\Api\Sync\*` (`SyncItemDecoder` : Form Requests des routes dédiées) ; tests `tests/Feature/Api/Sync/*`, `tests/Unit/Sync/*`.

### 21.1 Routes

| Route | Rôle |
|---|---|
| `POST sync/push` | lot de 50 éléments au plus (`xomiton.sync.push_batch_size`), `Idempotency-Key` = **uuid du lot** (obligatoire ; lot encore en cours : **202** `{data: {status: processing, retry_after}}` + `Retry-After`, à rejouer tel quel, jamais 429) ; hors `throttle:api`, limiteur `sync_push` (20 lots/min par compte, rejeux non comptés, **jamais limité si le lot n'a que des SOS**) et quotas des routes dédiées **par élément** (§21.2) |
| `GET sync/pull?since=&collections[]=` | deltas par collection depuis le jeton `since` (§21.3) |
| `GET sync/conflicts?status=open\|resolved\|all&per_page=` | conflits de l'appareil, par page ; `meta.open` |
| `POST sync/conflicts/{id}/resolve` | **le pair tranche lui-même** un conflit 409 de ses appareils (§21.5) |
| `POST heartbeat` | signal de vie `{pending?, app_version?}` → `{server_time, device{device_uuid, last_seen_at, last_sync_at, pending_count}, open_conflicts}` |

### 21.2 `POST sync/push`

Corps `{ items: [{uuid, type, op?, payload, created_at?}], pending? }` (`pending` : éléments encore dans la boîte d'envoi, gardé dans `sync_devices.pending_count` ; `created_at`, heure de l'appareil, accepté mais pas encore exploité : l'alerte et son escalade datent de la réception, Q86). Lot mal formé (plus de 50 éléments, uuid en double, `payload` absent) : **422** pour tout le lot. Sinon **200** `data.items[]` dans l'ordre du lot, `meta{created, updated, duplicates, conflicts, rejected, server_time}` ; traitement SOS d'abord, puis signalements, collecte, messages ; **un élément en erreur n'arrête jamais le lot**.

| `type` | `payload` (mêmes champs que la route dédiée, Q11 a) | Cas d'usage |
|---|---|---|
| `sos` | `POST sos` (§6.4) ; un champ refusé est retiré, jamais le SOS (RG-01) | `TriggerSos`, compte et appareil, source `sync-offline` |
| `signalement` | `POST signalements` (§6.1) **avec `anonymous: false`** ; sans preuves (multipart : route dédiée) | `CreateReport` ; un élément anonyme → 422 `anonymous_not_allowed` sans reçu (ADR-26, RG-01 : passer par `POST signalements`) |
| `collecte` (`op: create`) | `POST pair/cases` (§22, `CaseValidator::newCase`) | `CreateCase` : code `VBG-0000` / `VDH-0000` attribué par le serveur (`reference`), `client_uuid` = uuid, `warnings` (CaseQuality) ; sans espace pair actif (RG-03) ou sans `cases.create` → 403 |
| `collecte` (`op: update`) | `{server_id \| code, changes{champ: valeur}, base{champ: valeur vue avant la modification}}` ; champs `occurred_on, commune_id, population_id, sex, age_range, violence_type_id, referral, notes, status` (règles de `PATCH pair/cases/{code}`, `CaseValidator::changes` : commune ↔ département du dossier selon `commune_in_zone`) | `ApplyCaseChanges` : fusion par champ (§21.4), `case` = état courant du dossier (contenu de la collection `cases`), `warnings` ; journal `cases.updated` (`source=sync`) |
| `message` | `{conversation: <ulid>, body}` | `SendMessage` (§12), `client_uuid` = uuid ; e-mail confirmé exigé (403 `email_unverified`) |

Élément : `{uuid, type, result, status, server_id, reference, code, message?, errors?, conflicts?, applied?, kept?, case?, warnings?}` — `result` = `created` (201) · `updated` (200) · `duplicate` (200) · `conflict` (409) · `rejected` (4xx / 5xx) ; `server_id` = ULID d'une alerte, identifiant d'un dossier ou d'un message ; `reference` = code de suivi d'une alerte, code du dossier ; `code` = code stable d'un refus (`validation_failed` + `errors`, `anonymous_not_allowed`, `unsupported_type`, `uuid_reused`, `not_found`, `forbidden`, `email_unverified`, `case_locked`, `conversation_unavailable`, `too_many_requests`, `retry_later`…) ou `conflict`. **Règle client** : 2xx, 409 et 4xx → l'entrée sort de la boîte d'envoi (409 : le conflit est suivi par `sync/conflicts`) ; **429** (`too_many_requests`) et **5xx** (`retry_later` 503) → l'entrée reste et repart **dans un nouveau lot** (rejouer le même lot rend la même réponse).

Gardes par élément (aucun reçu, l'élément repart) : **maintenance** (RG-14) pour un compte hors administration → seuls `sos` et `signalement` sont traités, les autres reçoivent 503 `retry_later` ; **quotas des routes dédiées** → `message` compte sur `messages_write` (30/min par compte), `signalement` sur `report` (10/h par IP), même compteur que `POST conversations/{ulid}/messages` et `POST signalements`, au-delà 429 `too_many_requests` ; `sos` jamais limité, chaque SOS neuf passe par la surveillance des rafales (`SosBurstWatch`, P5-09) ; **budget de temps** du lot (`xomiton.sync.push_time_budget_seconds` = 20 s, sous les 30 s de D7) → au-delà, éléments restants en 503 `retry_later` (le premier élément est toujours traité, SOS en tête).

Idempotence (ADR-32) : même lot + même clé → **réponse d'origine** (middleware `idempotent`, `Idempotent-Replayed: true`) ; même clé + autre corps → 422 `idempotency_key_reused`. Chaque **élément** est dédoublonné par son uuid : reçu `sync_outbox_receipts` (appareil + uuid, empreinte du contenu, `kind`, sujet, `result`) puis clé métier unique (`alerts.idempotency_key`, `cases.client_uuid`, `messages.client_uuid`) — un élément déjà reçu rend `duplicate` avec les mêmes `server_id` / `reference`, jamais un doublon ni un 409 ; même uuid avec un autre contenu → 422 `uuid_reused` ; uuid déjà pris par l'objet d'un autre compte → `duplicate` **sans** identifiant. Modification de dossier rejouée : `case` (état courant) ; conclue en conflit, elle rend **la réponse d'origine** (`conflict`, 409, `conflicts` relus dans `sync_conflicts` ; `applied` et `kept` vides) ; dossier plus visible du compte → ni identifiant, ni référence, ni état. Un reçu `rejected` n'empêche pas un nouvel essai (référentiel corrigé). Aucun reçu pour un élément anonyme, un type inconnu, un 429 ou une erreur 5xx.

### 21.3 `GET sync/pull`

`since` = jeton opaque `meta.cursor` du tirage précédent (absent : tirage complet ; illisible : 422 `cursor_invalid`) ; `collections[]` = sous-ensemble (absent : toutes celles du compte). **200** `data{<collection>: {reset, upserts[], deletes[], has_more}}`, `meta{cursor, has_more, collections[], until, server_time, emergency_numbers[]}`. Tant que `meta.has_more`, rappeler aussitôt avec le nouveau `cursor`.

| Collection | Pour | Clé | Contenu (`upserts`) | Retirée de l'appareil (`deletes`) quand… |
|---|---|---|---|---|
| `emergency_contacts` | tous | `id` | `EmergencyContactResource` (§6.7) | fiche supprimée, masquée, inactive, plus d'urgence |
| `directory_contacts` | tous | `id` | `DirectoryContactResource` (§19), fiches publiques (une fiche réservée au réseau n'est jamais servie, ni dans `deletes`) | fiche supprimée, masquée (réseau : pierre tombale), inactive |
| `thematiques` | tous | `id` | carte (§11) + `content_warning`, `body`, `sections`, `updated_at` ; 25 par page ; fiche programmée servie à son heure | dépubliée, supprimée |
| `validation_rules` | espace pair actif + `cases.create` | `id` | `{id, key, label, description, is_locked, params, valid_example, invalid_example, position}` | désactivée, supprimée |
| `cases` | espace pair actif + `cases.create` | `id` | dossiers **collectés par le compte** (jamais tous, même pour l'administration) : `{id, code, kind, occurred_on, zone_id, commune_id, population_id, sex, age_range, violence_type_id, status, referral, notes, client_uuid, closed_at, created_at, updated_at}` | archivé, anonymisé, réattribué |
| `case_followups` | espace pair actif + `cases.create` | `id` | suivis de ces dossiers `{id, case_id, date, step, outcome, next_on, comment, client_uuid, updated_at}` | dossier archivé, anonymisé, réattribué ; suivi supprimé |
| `alerts` | espace pair actif + `alerts.view_routed` | ULID | `RoutedAlertResource` (résumé, jamais récit, contact ni position) des alertes routées vers une fiche liée au compte | close, routée ailleurs |

`reset: true` (première page seulement) : l'appareil **remplace** la collection (premier tirage, collection nouvelle pour le compte, ou dernier tirage plus vieux que `xomiton.sync.tombstone_days` = 90 j). Position par collection = (horodatage de modification, identifiant) ; lignes modifiées depuis moins de `xomiton.sync.pull_lag_seconds` (2 s) gardées pour le tirage suivant ; `xomiton.sync.pull_page_size` (200) lignes par page. Une ligne supprimée logiquement, dépubliée, archivée ou close se lit sur la ligne ; une suppression définitive, une fiche d'annuaire qui quitte l'annuaire public et une sortie de portée d'un compte (réattribution, routage ailleurs) passent par `sync_tombstones` (écouteurs Eloquent `TombstoneRecorder` ; une mise à jour de masse SQL n'en écrit pas ; jamais de pierre tombale pour tous les comptes dans `cases`, `case_followups`, `alerts`), purgées par `sync:prune`. Une suppression n'est jamais servie pour une ligne visible au moment du tirage. `sync_cursors` garde la position servie et l'heure du tirage par appareil et collection.

### 21.4 Conflits (`GET sync/conflicts`)

Fusion d'un `collecte` en `op: update` (SOFTWARE_ARCHITECTURE §5), dossier relu sous verrou (`lockForUpdate`) : champ inchangé côté serveur depuis `base` → valeur de l'appareil appliquée ; modifié des deux côtés → **statut** (et l'attribution, jamais modifiable depuis l'appareil) : le serveur gagne (`kept`) ; **notes** : l'appareil gagne si son compte a collecté le dossier ; **autre champ** : conflit — `result: conflict`, `status: 409`, `conflicts[{field, conflict_id}]`, la valeur serveur reste en place, ligne `sync_conflicts` (valeurs chiffrées), `sync_devices.conflicts_count` + 1, `SyncConflictDetected` → cloche admin `SyncConflict` (`sync.manage`) et journal sans les valeurs ; tranché dans *Administration › Hors connexion & synchro* (P13). Dossier d'une autre personne : 404 neutre ; archivé ou anonymisé : 422 `case_locked`. `GET sync/conflicts` : `{id, subject{type: collecte, server_id, reference}, field, status (a_resoudre | resolu), resolution, server_value, device_value, resolved_value, created_at, resolved_at}` — valeurs et sujet servis seulement si le compte voit encore le dossier.

### 21.5 `POST sync/conflicts/{id}/resolve` — le pair tranche lui-même (décision client du 2026-10-29)

Corps `{resolution: serveur | appareil}` (`fusion` + `merged` : administration seulement). `serveur` garde la valeur déjà en place ; `appareil` applique la valeur de l'appareil au dossier, après les règles de la collecte (`CaseValidator`, dossier verrouillé refusé). `X-Device-Id` d'un appareil enregistré du compte (comme tout `sync/*`) ; `Idempotency-Key` facultative. **Autorisation** (`SyncConflictPolicy`, permission `sync.resolve_own`, pair et admin) : seulement un conflit **de l'un de ses appareils** sur **un dossier qu'il·elle a collecté** (`CaseRecordPolicy::update`) ; le conflit d'un autre compte, d'un dossier qui n'est plus à soi, ou un identifiant inconnu → **404** neutre ; `fusion` par un·e pair·e → **403** ; conflit déjà tranché → **422** `conflict_already_resolved`. **200** : le conflit (`status: resolu`, `resolution`, `resolved_value`, `can`), `meta.open` (conflits encore ouverts de l'appareil). Effets : `sync_devices.conflicts_count` − 1 ; journal `sync.conflict_resolved` (choix, `par=pair|admin`, sujet, champ, appareil — **jamais les valeurs**). `GET sync/conflicts` annonce `can{resolve, merge}` pour le compte. L'administration (`sync.manage`) peut toujours trancher et fusionner depuis *Hors connexion & synchro* (Livewire). Tests `tests/Feature/Api/Sync/SyncConflictResolveApiTest.php`.

### 21.6 `POST heartbeat`

Signal de vie (prototype `api.php ping`) : `last_seen_at`, `pending_count` et `app_version` de l'appareil ; jamais de donnée de compte en retour hormis l'appareil et le nombre de conflits ouverts. Réponse : `server_time`, `device{…}`, `open_conflicts`, **`rules_updated_at`** (version des règles de validation, la même que `meta.updated_at` de `GET pair/validation-rules` : plus récente que celle du cache de l'application → relire les règles, P15-12) et **`wipe`** (ordre d'effacement à distance, toujours `null` pour un appareil actif, §3 « Effacement à distance »). Pour un appareil révoqué par un ordre d'effacement qui attend son accusé, la réponse se limite à `{server_time, wipe}`.

### 21.7 Boîte d'envoi web (P9-07)

Le web n'utilise pas `sync/push` (navigateur non enregistré, ADR-26) : `resources/js/shared/outbox.js` rejoue chaque entrée sur sa route dédiée avec son `Idempotency-Key` — `sos` → `POST sos`, `signalement` → `POST signalements`, `collecte` → `POST pair/cases` (`client_uuid` = clé ajouté au corps), `message` → `POST conversations/{ulid}/messages` (`client_uuid`) ; ordre SOS, signalement, collecte, message ; au retour du réseau, toutes les 5 min et au retour sur l'onglet ; **un SOS mis en file part tout de suite si le réseau est là** (hors du passage en cours et du verrou entre onglets). Store Pinia `outbox` : `queue(type, payload, {idempotencyKey?, url?})`, `byType` (envois en attente du compte de la page par type).

## 22. Espace pair `pair/*` — `auth:sanctum` (P9-01, P9-02)

Dossiers VBG / VDH, suivi des victimes, alertes routées et règles de validation de l'espace pair éducateur·rice (prototype `collecte.php`, `espace-pair.php`, `admin/suivi.php` ; écrans P9-03, P9-04, P9-08). Code : domaine `App\Domain\Cases\{CaseCode, Plausibility, DuplicateCriteria, ValidationRuleKey, CaseNotes, CaseReferral, FollowupStep, Exceptions\*}` ; application `App\Application\Cases\{ValidationCatalog, CaseRules, CaseValidator, CaseQuality, CreateCase, UpdateCase, AddFollowup, UpdateFollowup, PeerSpace, Queries\{ListCases, ListRoutedAlerts}}` ; alertes : `AcknowledgeAlert`, `ChangeAlertStatus` (P5-02) ; règles `App\Rules\{NotFutureDate, MinimumLength, AgeRangeWithinBounds, CaseCodeFormat, CommuneInZone}` ; `App\Http\Controllers\Api\V1\Peer\*`, `App\Http\Requests\Peer\*`, `App\Http\Resources\Peer\*`, middleware `EnsurePeerSpaceAccess` ; tests `tests/Unit/Domain/Cases/*`, `tests/Feature/Cases/*`, `tests/Feature/Api/Peer/*`.

**Notifications SOS et alertes** : destinataires = **les admins et les pair·es éducateur·rices** (`OnCallRoster::alertTeam` ; option `xomiton.alerts.peer_recipients = zone` pour limiter aux pair·es du département), pas seulement l'admin de garde ; push FCM / Web Push, cloche et SMS de repli inchangés ; `alerts:escalate` (SOS sans accusé à 5 min, rappel 24 h, escalade 48 h) prévient le point focal national **et** l'équipe ; l'accusé de réception d'un·e admin ou d'un·e pair·e arrête l'escalade pour tous. Une alerte jamais notifiée à un·e pair·e (et non routée vers lui·elle) reste fermée (403).

**Accès** : compte **actif** détenant `peer_space.access` (pair validé·e, administration ; RG-03 : un compte pair en attente reste membre) — sinon **403** `forbidden`, sans détail. **Scope** (ROLE_ET_PERMISSIONS.md §5) : les dossiers passent **toujours** par `CaseRecord::visibleTo` (le pair : ceux qu'il a collectés ; l'administration : tous). Le code d'un dossier est énumérable : dossier d'un autre pair, code inconnu ou illisible → **le même 404** `not_found`, en lecture comme en écriture (aucun oracle). Les alertes sont adressées par ULID opaque (ADR-27) : alerte non routée vers le compte → **403** (`AlertPolicy`). Réponses **`Cache-Control: no-store, private`** (notes, commentaires, récit, contact et position déchiffrés), sauf `validation-rules` (ETag, 304).

**Écrans web (lot espace-pair, P9-03, P9-04, P9-08)** : pages Inertia `PeerSpace/Home` (`/espace-pair`), `PeerSpace/Collecte` (`/collecte`), `PeerSpace/Cases` (`/espace-pair/dossiers`) et `PeerSpace/CaseShow` (`/espace-pair/dossiers/{code}`), contrôleurs `App\Http\Controllers\Web\Member\Peer\*`, même garde que l'API (`EnsurePeerSpaceAccess` : 403 pour un compte pair en attente), mêmes cas d'usage (`MemberDashboard::peer`, `ListCases`, `CollectOptions`, `CaseSheet` pour les cinq sections de la fiche en lecture). Ils appellent cette API : alertes (liste toutes les 10 s si le compte est de garde, détail, accusé, statut), suivi (`POST` / `PATCH` des étapes, `PATCH` du statut) ; la collecte passe par la boîte d'envoi (`POST pair/cases`, `client_uuid` = `Idempotency-Key`). Tests `tests/Feature/Member/PeerSpacePagesTest.php`, Vitest `resources/js/Pages/PeerSpace/*.test.ts`.

| Route | Effet |
|---|---|
| `GET pair/cases?status=&kind=&q=&page=&per_page=` | **200** dossiers visibles, les plus récents d'abord, par page (`meta` : pagination + `filters`) ; archivés exclus sauf `status=archive` ; `q` (≤ 12) cherche **dans le code seulement** (colonne en clair, ADR-33) ; **jamais les notes** dans la liste ; valeur hors liste → **422** |
| `POST pair/cases` | Collecte `{kind (VBG\|VDH), occurred_on (Y-m-d), zone_id, commune_id?, population_id, sex, age_range, violence_type_id, referral?, notes?, client_uuid? (UUID)}` → **201** dossier (`code` attribué par le serveur, RG-06 : `VBG-0001`, un compteur par nature), `Location`, `meta{warnings[], duplicate: false}` ; **200** `meta.duplicate: true` au rejeu du même `client_uuid` par la même personne (dossier d'origine, jamais de doublon ni de 409, ADR-32) ; **422** `idempotency_key_reused` (même `client_uuid`, même compte, autre contenu : la première saisie est gardée, ADR-32 précision P5-09) ; **422** `client_uuid_conflict` (uuid porté par le dossier d'un autre compte, rien n'en est dit) ; **422** erreurs par champ (catalogue ci-dessous) ; `Idempotency-Key` facultative (middleware `idempotent` : même clé → réponse d'origine rejouée, `Idempotent-Replayed: true` ; autre corps → **422** `idempotency_key_reused`) ; source `web` (site) ou `app` (Flutter), appareil `X-Device-Id` |
| `GET pair/cases/{code}` | **200** détail : notes déchiffrées (null après anonymisation), frise `followups[]`, `can{update, add_followup}` ; code lu avec tolérance (`vbg 42` → `VBG-0042`) |
| `PATCH pair/cases/{code}` | Champs envoyés seulement (+ `status` : `ouvert`, `en_cours`, `clos` ; `archive` réservé à `cases.view_all`) ; `kind` et `client_uuid` interdits (**422**) ; commune contrôlée avec le département final (commune seule → département du dossier ; département seul → commune du dossier, à renvoyer ou à vider) ; transitions de `CaseStatus` (**422** `invalid_transition`), `clos` pose `closed_at`, une réouverture l'efface ; dossier archivé ou anonymisé → **422** `case_locked`. Pas de `DELETE` : un dossier se clôt puis s'archive (administration, P11-01) |
| `GET pair/cases/{code}/followups` | **200** étapes chronologiques `{id, date, step, outcome, outcome_label, next_on, overdue, comment, author (pseudonyme), by_me}` |
| `POST pair/cases/{code}/followups` | `{date (non future), step (liste de admin/suivi.php), outcome (planifie\|realise\|reporte\|annule), next_on? (≥ date), comment? (≤ 1 000), client_uuid?}` → **201** ; **200** `meta.duplicate: true` au rejeu (même contenu) ; **422** `idempotency_key_reused` (même `client_uuid`, autre contenu), `client_uuid_conflict`, `case_locked` ; `Idempotency-Key` facultative comme `POST pair/cases` |
| `PATCH pair/cases/{code}/followups/{id}` | Champs envoyés seulement (« marquer comme réalisée ») ; étape d'un autre dossier → **404** ; dossier archivé ou anonymisé → **422** `case_locked` ; pas de suppression pour un pair (`case_followups.manage`) |
| `GET pair/alerts?state=open\|all&page=` | **200** alertes **routées vers les fiches d'annuaire du compte** (`directory_contacts.user_id`) **ou dont l'équipe l'a prévenu·e** (SOS, alerte routée, rappel, escalade : les pair·es éducateur·rices sont destinataires, décision client du 2026-10-29) — même pour un compte `alerts.view` : l'administration des alertes est en P10 / `admin/alerts` — résumé `RoutedAlertResource` (§11), `meta{state, new}` (non accusées) ; sans `alerts.view_routed` → **403** |
| `GET pair/alerts/{ulid}` | **200** détail pour répondre : `type, status, priority, category(_label), needs[{slug, label}], occurred_on, description, author` (pseudonyme, « Anonyme » si l'alerte l'est, RG-01/RG-02), `contact, preferred_channel, place, zone, commune, position{lat, lng, accuracy}` (SOS géolocalisé), `acknowledged(_at), cancelled, safe, history[], can{acknowledge, take, close, statuses[]}` ; jamais le code de suivi, la clé d'idempotence ni le compte de l'autrice ou de l'auteur |
| `POST pair/alerts/{ulid}/acknowledge` | Accusé de réception (AcknowledgeAlert, P5-02 : `nouveau → pris_en_charge`, arrêt de l'escalade RG-05) → **200** détail, `meta{acknowledged_now, already_acknowledged, closed}` (idempotent) |
| `POST pair/alerts/{ulid}/status` | `{status}` (ChangeAlertStatus, P5-02) : `pris_en_charge`, `en_cours` (prendre en charge, `alerts.take` ; vaut accusé s'il manque), `traite`, `clos` (`alerts.close`) → **200** détail ; **422** `invalid_transition` ; **403** si l'alerte n'est pas routée vers le compte, **même pour l'administration** (`alerts.view` : middleware `EnsureAlertRoutedToViewer` sur le détail, l'accusé et le statut, avant la validation du corps). **Ni réattribution, ni note interne, ni export** (`alerts.manage`) |
| `GET pair/validation-rules` | **200** règles **actives** du catalogue `{key, label, description, locked, params, valid_example, invalid_example}` (sans les clés documentaires `forms`, `available_fields`), `meta{updated_at, options{age_ranges[{value, label}] (tranches admises par les bornes si `age_range` est active, toutes sinon), referrals[], followup_steps[], notes_min_length}}` ; ETag, **304** (cache mobile, collecte hors ligne) |

**Catalogue de règles activables** (table `validation_rules`, Administration › Règles de validation, `ValidationCatalog`) — les mêmes règles s'appliquent côté serveur (`CaseRules`, partagées par les Form Requests et par `sync/push` via `CaseValidator`) et côté client (`validation-rules`). Règle absente du catalogue = active ; `required` verrouillée. Toujours appliqués : champs obligatoires de la collecte, valeurs des listes, référentiels actifs, formats. Selon l'interrupteur : `date_not_future` (RG-07, jour calendaire du Bénin), `age_range` (RG-08 reformulée par **Q25 a** : tranche d'âge de la liste, jamais un âge exact, comprise entre `age_min` et `age_max` de la plausibilité — 10 et 99 par défaut, soit 10-14 à 50+), `commune_in_zone` (RG-08 ; désactivée, la commune doit seulement exister et être active ; une commune envoyée seule est contrôlée contre le département du dossier, sans revalider ce département), `min_len_20` (notes vides ou ≥ 20 caractères). **Avertissements non bloquants** (`meta.warnings[{code, message, codes[]}]`, après enregistrement) : `date_old` (date de plus de `max_past_years` ans, règle `plausibility`, RG-07) ; `duplicate_suspected` (règle `duplicates` : dossiers partageant les champs `params.fields`, **parmi ceux que le compte voit** — un pair ne découvre jamais l'existence du dossier d'un autre ; le rapprochement entre pair·es relève du rapport de qualité, P11). `email`, `phone_bj`, `positive_int`, `password_strong`, `case_code` : règles des autres formulaires (`App\Rules`), servies au client par la même route. **Pas encore activables** : `email`, `phone_bj`, `positive_int` et `password_strong` sont **toujours** appliquées par les Form Requests de leurs formulaires (inscription, profil, SOS, paramètres), qui ne lisent pas le catalogue ; leur interrupteur n'a d'effet que sur cette liste (désactivée → absente de la réponse, contrôle serveur maintenu). Branchement prévu avec l'écran *Règles de validation* (P11-05 ; `password_strong` à verrouiller, `is_locked`).

**Journal d'audit** : `cases.created` (code, nature, source), `cases.updated` (noms des champs modifiés, transition de statut), `case_followups.created`, `case_followups.updated` — jamais le contenu des notes ni des commentaires. Notes et commentaires sont chiffrés au repos (`encrypted_columns`).

## 23. Administration mobile `admin/*` — `auth:sanctum` + `admin_mobile.access` (P15-16)

API de l'espace **Admin** de l'application mobile unique (alertes et SOS d'astreinte, demandes de pair, rôles, invitations, maintenance, modération essentielle). Elle **réutilise les cas d'usage de l'administration Livewire** (`AcknowledgeAlert`, `ChangeAlertStatus`, `ReassignAlert`, `AddInternalNote`, `ReviewRegistration`, `ManagePeerRole`, `ChangeUserRole`, `TeamInvitations`, `EnableMaintenance` / `DisableMaintenance`, `DecideReport`, `ReportQueue`, `NotificationInbox`) : aucune règle n'est dupliquée, chaque cas d'usage autorise par policy ; les contrôleurs `App\Http\Controllers\Api\V1\Admin\*` ne font que valider, appeler et présenter (`App\Http\Resources\Admin\*`). Tests `tests/Feature/Api/Admin/AdminMobileApiTest.php`.

**Accès.** Middleware `EnsureAdminMobile` : compte **actif** détenant la permission `admin_mobile.access` (rôles `admin` et `superadmin`, retirable au rôle par le super-admin), sinon **403** `forbidden`, **avant** la liaison des modèles de route (un identifiant inexistant donne 403, pas 404). Membre, pair, modérateur·rice, compte suspendu ou admin sans la permission : 403 sur **chaque** route. Le jeton Sanctum ne fige aucune capacité : les rôles sont cumulables et changent sans reconnexion, la permission lue à chaque requête est la seule vérité (écart assumé avec la capacité `admin-mobile` du plan §6.3). Les actions d'audit de la requête portent `source=mobile` dans leur détail.

**Écritures.** `Idempotency-Key` (UUID) **obligatoire** sur tout `POST` / `PUT` / `DELETE` (`idempotent:required,limit=admin_write` : même clé et même corps → réponse d'origine rejouée, `Idempotent-Replayed: true`, sans second effet ; autre corps → 422 `idempotency_key_reused` ; absente → 422 `idempotency_key_required`). Limiteurs : `admin_api` 120/min par compte sur toutes les routes (en plus de `api`), `admin_write` 30/min sur les écritures, `admin_step_up` 10/h sur le mot de passe. Réponses `Cache-Control: no-store, private`. Listes paginées `{data, meta{current_page, last_page, per_page, total, …}, links}` (`?per_page=`, 50 max).

### 23.1 Confirmation `step-up` et tableau d'astreinte

| Route | Effet |
|---|---|
| `POST admin/step-up` | `{password}` (mot de passe du compte) → **200** `{token, expires_in (300 s, xomiton.accounts.step_up_minutes), header: "X-Step-Up-Token"}` ; mot de passe faux → **422** `errors.password`. Le jeton est **à usage unique**, lié au compte, gardé en empreinte |
| `GET admin/dashboard` | **200** compteurs d'astreinte : `alerts{new, sos_active, critical_open, handled, sos_unacknowledged, open}` (`alerts.view`), `pending_accounts`, `pending_peer_requests` (`users.validate_peers`), `pending_moderation` (`moderation.manage`), `open_sync_conflicts` (`sync.manage`) — un bloc sans la permission vaut `null` —, `maintenance{state, active, ends_at}`, `on_call{me, admins, peers}`, `poll_seconds` (10 pour la garde, 30 sinon), `server_time` |

**Actions à fort impact** (maintenance, attribution / retrait de rôle, invitations, refus d'une demande, réattribution d'une alerte) : l'application appelle `POST admin/step-up`, puis renvoie l'action avec l'en-tête `X-Step-Up-Token` ; sans jeton valable (absent, expiré, déjà utilisé, d'un autre compte) → **403** `step_up_required`.

### 23.2 Alertes et SOS — `admin/alerts` (`alerts.view`, `alerts.take`, `alerts.close`, `alerts.manage`)

| Route | Effet |
|---|---|
| `GET admin/alerts?type=sos\|report\|need&status=&priority=&zone_id=&state=open\|all&unacknowledged=1&per_page=` | liste (ouvertes par défaut, les plus récentes d'abord) ; `meta{state, new}`. Résumé : `id` (ULID), `code`, `type`, `status`, `priority`, `place` (commune sinon département), `zone`, `commune`, `routed_to`, `acknowledged(_at)`, `escalated`, `age_minutes` — **jamais** le récit, le contact ni la position |
| `GET admin/alerts/{ulid}` | détail : résumé + `category`, `needs[]`, `occurred_on`, `description`, `author` (pseudonyme, « Anonyme » si l'alerte l'est), `contact`, `preferred_channel`, **`position{lat, lng, precision_m, rounded: true}` ARRONDIE** (décision client du 2026-10-29 : `xomiton.alerts.admin_position_decimals`, 2 décimales ≈ 1 km ; la position exacte n'est jamais servie à l'admin mobile ; `null` sans position), `history[]`, `notes[]` (notes internes `{id, author, body, at}`), `escalations[]`, **`notified[]`** (personnes prévenues, y compris l'équipe : admins de garde et pair·es destinataires du push — `{key (empreinte opaque), kind, label, role, state, state_label, phone, seen_at, updated_at}` ; libellés publics, jamais le nom d'un·e admin ; `phone` nul pour le contact de confiance de l'auteur·e), `can{acknowledge, take, close, reassign, note, statuses[]}` ; jamais le code de suivi, la clé d'idempotence ni le compte de l'auteur·e |
| `POST admin/alerts/{ulid}/acknowledge` | **accuser réception** (arrête l'escalade RG-05) → **200** détail, `meta{acknowledged_now, already_acknowledged, closed}` (idempotent) |
| `POST admin/alerts/{ulid}/take` | **prendre en charge** : statut `en_cours` (vaut accusé s'il manque) |
| `POST admin/alerts/{ulid}/status` | `{status}` (`pris_en_charge`, `en_cours`, `traite`, `clos`) ; transition refusée → **422** `invalid_transition` |
| `POST admin/alerts/{ulid}/close` | **clore** (`clos`, définitif) |
| `GET admin/alerts/{ulid}/contacts?role=&all_zones=1` | contacts de l'annuaire proposés à la réattribution (même département d'abord) `[{id, name, role, zone}]` |
| `POST admin/alerts/{ulid}/reassign` | `{contact_id}` — **réattribuer** (`alerts.manage`, `step-up`) ; contact inactif → 422 ; alerte close → 422 |
| `POST admin/alerts/{ulid}/notes` | `{body}` — **note interne** (chiffrée, jamais modifiée ni supprimée) → **201** détail |

### 23.3 Inscriptions et demandes de pair en attente — `admin/registrations` (`users.validate_peers`)

| Route | Effet |
|---|---|
| `GET admin/registrations?kind=` | comptes `en_attente`, les plus anciens d'abord ; filtre `kind` = `registration` (inscription signalée ou à valider), `peer_request` (demande de pair ou pair·e invité·e), `invited` (pair·e invité·e dont l'inscription attend la validation), autre valeur → 422 ; chaque ligne : compte (§23.4) + `kind` = `peer_request` (demande de rôle pair) ou `registration`, `invited` (booléen : invitation à l'équipe utilisée), et `flags[]` = motifs du **signalement** (`disposable_email`, `repeated_fingerprint`) ; `meta.pending`. **D-MOB-17 (tranché 2026-10-29, recommandation retenue)** : seules les inscriptions signalées attendent ici (le réglage `reg_manual_validation = all` met tout le monde en attente) |
| `POST admin/registrations/{user}/approve` | valide : compte actif, notification ; une demande de pair reçoit le rôle `pair` (et reste membre) ; une invitation à l'équipe en attente est honorée ; déjà traité → 422 |
| `POST admin/registrations/{user}/reject` | `{reason}` (3–200, **écrit pour la personne**, `step-up`) : une **demande de pair** refusée laisse un compte **membre actif**, notifié avec le motif (`RoleNotice`, journal `users.peer_refused`) ; une **inscription** refusée supprime le compte (e-mail `compte-supprime`, journal `users.registration_rejected`) |

### 23.3 bis Demander le rôle pair — `me/role-requests` (membre connecté, D-MOB-15)

**Tranché 2026-10-29 (recommandation retenue)** : un membre peut **demander** le rôle pair depuis son profil (carte « Mes espaces »). **Jamais d'auto-attribution** : la demande crée seulement une ligne `pending` ; le rôle ne vient que de la décision d'un compte habilité (§23.4 bis). Le corps n'accepte aucun champ de rôle (seul le rôle `pair` est demandable).

| Route | Effet |
|---|---|
| `POST me/role-requests` | `{reason?}` (≤ 200 caractères, facultatif, court). **`Idempotency-Key` obligatoire** (`idempotent:required,limit=role_request` : même clé → même réponse rejouée, `Idempotent-Replayed: true`), limiteur `role_request` (`xomiton.rate_limits.role_request_per_day`, 3/jour par compte, 429 au-delà). **Une seule demande en cours à la fois** (index unique en base, sûr en cas de requêtes simultanées). → **201** `{id (ULID), role: "pair", status: "pending", reason, decision_reason: null, created_at, decided_at: null}`. Erreurs 422 : `already_peer`, `request_open`, `email_unverified`, `not_active` ; `reason` trop long → 422. Journal `roles.request_created`, notification `RoleRequested` (cloche + push) aux comptes habilités (`peers.assign`) |
| `GET me/role-requests` | mes 20 dernières demandes (plus récentes d'abord) ; `meta.can_request` (faux si déjà pair ou demande en cours). Après un refus, `decision_reason` porte le motif écrit pour la personne ; elle peut redemander |

### 23.4 Recherche d'un membre et rôles — `admin/members` (`users.view`, `peers.assign`, `roles.assign_admin`)

| Route | Effet |
|---|---|
| `GET admin/members?q=&role=&status=` | recherche (`q` 2–60) par pseudonyme ou nom ; l'e-mail aussi pour `users.manage` ; compte : `id, pseudo, avatar_url, status, role, roles[], spaces[], is_peer, zone, email_verified, member_type, requested_role, created_at, last_seen_at, can{grant_peer, revoke_peer, change_role, validate}` (+ `email`, `phone` pour `users.manage`) — **jamais** le nom réel, le contact de confiance ni un réglage |
| `GET admin/members/{id}` | un compte (même objet) |
| `POST admin/members/{id}/roles/peer` | **donner le rôle pair** à un membre existant (`peers.assign`, `step-up`) : il **reste membre**, `profile.member_type = pair`, notification `RoleNotice`, journal `roles.peer_granted` → **201** (200 si déjà pair, `meta.changed`). Compte non actif → 422 `not_active` ; e-mail non confirmé → 422 `email_unverified` ; soi-même → 403 |
| `DELETE admin/members/{id}/roles/peer` | `{reason?}` — **retirer le rôle pair** (`step-up`) : il reste membre, journal `roles.peer_revoked`, notification |
| `PUT admin/members/{id}/role` | `{role: membre\|pair}` (`step-up`) — bascule membre ↔ pair (ChangeUserRole) ; **D-MOB-16 (tranché 2026-10-29, recommandation retenue) : le rôle `moderateur` / `admin` / `superadmin` ne s'attribue ni ne se retire QUE depuis le web (super-admin) : tout niveau d'administration demandé, ou toute cible déjà administratrice, répond 403, même pour un·e super-admin** ; jamais son propre rôle (422 `own_role`) ; le rôle `membre` est toujours conservé ; journal `users.role_changed`. `can.change_role` est faux pour une cible administratrice |

**Non-escalade** : un·e pair·e ou un membre ne peut pas appeler `admin/*` (403) ; un·e admin ne peut ni se donner ni donner un rôle d'administration ; aucun rôle ne s'obtient par l'inscription ni par `PATCH me/profile` (`member_type: pair` → 422).

### 23.4 bis Demandes du rôle pair — `admin/role-requests` (`peers.assign`, D-MOB-15)

File des demandes faites par les membres (§23.3 bis ci-dessous, `POST me/role-requests`). Mêmes cas d'usage que la file web (page Inscriptions), `ReviewRoleRequest` : le rôle est donné par `ManagePeerRole` (la personne **reste membre**), jamais à soi-même (403, `assignPeerRole`).

| Route | Effet |
|---|---|
| `GET admin/role-requests?status=pending\|approved\|rejected\|all` | demandes (en attente par défaut, les plus anciennes d'abord) : `{id (ULID), role: "pair", status, reason (motif de la personne), decision_reason, user{id, pseudo, avatar_url, zone, created_at}, created_at, decided_at, can{decide}}` ; `meta.pending` ; jamais l'e-mail ni le téléphone |
| `POST admin/role-requests/{ulid}/approve` | **approuve** (`step-up`) : rôle `pair` donné, notification `RoleNotice` (`peer_granted`), journal `roles.request_approved` + `roles.peer_granted via=role_request` → **200** ; déjà traitée → 422 `already_decided` |
| `POST admin/role-requests/{ulid}/reject` | `{reason}` (3–200, **écrit pour la personne**, `step-up`) : la demande est close, le compte reste membre actif, notification `RoleNotice` (`peer_refused`) avec le motif, journal `roles.request_rejected` → **200** |

### 23.5 Notifications — `admin/notifications`

`GET admin/notifications?filter=all\|unread\|read&category=sos\|alertes\|moderation\|systeme\|securite` : la cloche du compte restreinte aux catégories d'astreinte, `meta.unread_by_category` ; `POST admin/notifications/read-all` → `{marked}`. Mêmes objets que `GET notifications` (§7).

### 23.6 Invitations à l'équipe — `admin/invitations`

Un lien / code **signé, à usage unique** (7 jours, `xomiton.accounts.team_invitation_days`) mène à l'inscription **ordinaire** (`/inscription?invitation=…` ou champ `invitation` de `POST auth/register`, §2) : la personne crée un compte membre (case 15 ans comprise) et reçoit le rôle **à la validation** de son compte (e-mail confirmé ; ou approbation par l'équipe si la validation manuelle est active) — à condition que la personne qui a invité détienne encore le droit (sinon l'invitation est refusée, jamais d'escalade).

| Route | Effet |
|---|---|
| `GET admin/invitations?status=` | invitations récentes `{id, role, status (pending\|redeemed\|accepted\|refused\|revoked\|expired), code_hint (4 derniers caractères), email, note, invited_by, user, expires_at, redeemed_at}` ; un·e admin ne voit que les invitations `pair` |
| `POST admin/invitations` | `{role: pair, email?, note?}` (`step-up`) → **201** invitation + **`code`** (`XM-ABCD-EFGH-JKMN`) et **`link`** (URL signée), **rendus une seule fois** (le code n'est conservé qu'en SHA-256 et n'apparaît jamais au journal). `pair` : `peers.invite`. **D-MOB-16 : une invitation `moderateur` / `admin` / `superadmin` ne se crée QUE depuis le web (super-admin) ; l'API mobile répond 403** (valeur inconnue → 422). Adresse e-mail facultative : lie l'invitation à cette adresse |
| `DELETE admin/invitations/{id}` | annule (`step-up`) → 204 ; déjà utilisée ou annulée → 422 |

### 23.7 Modération essentielle — `admin/moderation/reports` (`moderation.manage`)

| Route | Effet |
|---|---|
| `GET admin/moderation/reports?state=pending\|handled\|rejected\|all` | une ligne par contenu signalé en attente : `id, kind, reason, excerpt (≤ 200), reported_user{id, pseudo}, status, action, hidden, reports, people, created_at, handled_at, can{hide, restore, dismiss}` ; `meta.pending` ; jamais l'identité de la personne qui signale |
| `POST admin/moderation/reports/{id}/hide` | `{reason?}` — **masquer** le contenu (auteur·e prévenu·e) ; vaut pour tous les signalements en attente du même contenu → `meta.closed` |
| `POST admin/moderation/reports/{id}/restore` | **rendre visible** un contenu masqué (RG-16 ou décision) sans toucher au signalement |
| `POST admin/moderation/reports/{id}/dismiss` | **ignorer** (signalements rejetés ; un masquage automatique est levé) |

| Route | Effet |
|---|---|
| `GET admin/moderation/reports/{id}` | un signalement (même objet que la liste, avec `reports` / `people` du même contenu) |
| `POST admin/moderation/reports/{id}/warn` | `{reason}` (3–200, écrit pour la personne) — **avertir** l'auteur·e (ligne `user_warnings`, notification `ModerationNotice`) ; vaut pour tous les signalements en attente du même contenu |
| `POST admin/moderation/reports/{id}/decision` | décision unique de l'écran : `{action: hide\|keep\|warn, reason?}` (`reason` obligatoire pour `warn`) ; `keep` = conserver le contenu (équivaut à `dismiss`) ; autre action (dont `suspend`) → 422 |

`can` d'un signalement : `hide`, `restore`, `dismiss`, `warn`. **Les messages privés** (kind `message`) ne sont **jamais** proposés à la modération mobile : absents de la liste, du compteur (`meta.pending`, `admin/dashboard.pending_moderation`), **404** en détail et en décision ; ils se traitent sur le web. **Suspendre un compte** reste sur le web (`/admin/moderation`).

### 23.8 Mode maintenance — `admin/maintenance` (`platform.maintenance`, RG-14)

| Route | Effet |
|---|---|
| `GET admin/maintenance` | `{state (inactive\|announced\|ongoing\|ended), state_label, active, message, starts_at, ends_at, overdue, announced_at, upcoming[]}` |
| `POST admin/maintenance/enable` | `{reason (3–255, motif interne), duration_minutes? (5–1440), message? (≤ 280), confirm: true}` + **`step-up`** → état ; déjà active → **422** ; compte qui perdrait l'accès → **403** `maintenance_lockout` (admin et super-admin gardent l'accès : l'API `admin/*` reste servie pendant la maintenance) |
| `POST admin/maintenance/disable` | `{confirm: true, reason?}` + **`step-up`** → état |

### 23.9 Appareils et effacement à distance — `admin/members/{id}/devices`, `admin/devices/{id}/wipe` (`device.wipe`, P15-11)

| Route | Effet |
|---|---|
| `GET admin/members/{id}/devices` | les 20 derniers appareils du compte : `{id, label, platform, app_version, revoked, last_seen_at, last_sync_at, wipe_pending, wipe_requested_at, wipe_reason (admin\|peer_role_removed), wiped_at, can{wipe}}` — **jamais l'UUID** de l'appareil ; `device.wipe` requis (sinon 403) |
| `POST admin/devices/{id}/wipe` | **marque l'appareil à effacer** et le révoque (§3 « Effacement à distance ») ; `step-up` obligatoire, `Idempotency-Key` obligatoire → **202** l'appareil (**200** `meta.already_requested = true` si l'ordre existait : date et motif d'origine conservés, un seul journal) ; sans `device.wipe` → 403 ; identifiant inconnu → 403 pour un compte non habilité, 404 sinon |

Même cas d'usage que l'écran web *Hors connexion & synchro* (bouton « Effacer à distance », `RequestDeviceWipe`). Le **retrait du rôle pair** (`DELETE admin/members/{id}/roles/peer`, `PUT admin/members/{id}/role` vers `membre`, écran web Utilisateurs & rôles) marque aussi tous les appareils actifs de la personne (`wipe_reason = peer_role_removed`, un journal `device.wipe_requested` par appareil) ; accorder le rôle, ou passer pair → admin (le rôle pair est conservé), ne marque rien.

**Journal d'audit** : toute action écrite ici l'est comme sur le web, avec `source=mobile` en fin de détail (`alerts.acknowledged`, `alerts.status_changed`, `alerts.reassigned`, `alerts.note_added`, `users.registration_approved`, `users.peer_refused`, `roles.peer_granted`, `roles.peer_revoked`, `users.role_changed`, `invitations.created`, `invitations.revoked`, `maintenance.enabled`, `maintenance.disabled`, `moderation.*`, `device.wipe_requested`, `admin.step_up_failed`) ; jamais le corps d'une note, le récit, le contact ni la position.

## 24. Push de l'application mobile unique — types, ouverture profonde, discrétion (P15-19, P15-20)

Remise par les canaux `fcm` (jeton d'appareil, `POST me/push-subscriptions`) des notifications Xomiton (`XomitonNotification`) ; les mêmes notifications alimentent la cloche (`GET notifications`, §7) sauf les types silencieux. **Charge `data` FCM** : `type`, `id` (chaîne), `category` et, si le serveur la connaît, `url` relative ; **jamais** de récit, de nom, de position, de contact ni d'extrait. Le détail est relu par l'API après authentification (et après le PIN de l'application). Le push n'est **jamais l'unique chemin** : polling au premier plan (`admin/dashboard.poll_seconds`), `heartbeat` et escalade serveur (RG-05) y suppléent.

| `data.type` | Destinataires | `data.id` | Ouverture (application) | Canal Android · priorité |
|---|---|---|---|---|
| `alert_routed` | pair·e (espace pair) prévenu·e d'un SOS, d'une alerte routée, d'un rappel, d'une escalade ou d'un SOS non accusé | **ULID de l'alerte** (`pair/alerts/{ulid}`) | `/pair/alertes/:id` | `duty` (Astreinte) · haute |
| `duty_sos` | admin (`admin_mobile.access`) de garde, ou après l'escalade nationale à 5 min : SOS et SOS non accusé | **ULID de l'alerte** (`admin/alerts/{ulid}`) | `/admin/alertes/:id` | `duty` · haute |
| `duty_alert` | admin prévenu d'une alerte routée (signalement, besoin), rappel 24 h, escalade | ULID de l'alerte | `/admin/alertes/:id` | `duty` · haute |
| `peer_pending` | comptes `users.validate_peers` (inscription signalée D-MOB-17 ou « tout valider », pair·e invité·e) ; comptes `peers.assign` (demande du rôle pair, D-MOB-15) | identifiant du compte (inscription) ou de la demande ; **sans effet sur l'ouverture** | `/admin/inscriptions` (liste « Inscriptions à valider ») | `community` · normale |
| `content_reported` | comptes `moderation.manage` **qui ont l'espace Admin mobile** (jamais Web Push, jamais un·e modérateur·rice sans `admin_mobile.access`) | identifiant du signalement (`admin/moderation/reports`) | `/admin/moderation` | `community` · normale (un signalement ne réveille personne la nuit) |
| `sync` | **silencieux** : le pair dont le dossier a été modifié, archivé, rétabli par quelqu'un d'autre, ou dont un conflit a été tranché par l'administration | `null` (jamais d'identifiant de dossier) | aucune : lancer un cycle `sync/pull` puis `sync/push` | aucun bloc `notification` · normale, regroupé (`collapse_key`) |
| `rules` | **silencieux** : les pair·es (rôle `pair` actif ayant un jeton FCM) quand l'administration change une règle de validation | `null` | aucune : relire `GET pair/validation-rules` (ETag ; `heartbeat.rules_updated_at` rattrape un push manqué) | aucun bloc `notification` · normale, regroupé |

Types historiques inchangés pour les autres personnes : `sos` et `alert` (auteur·e, contact de l'annuaire sans espace mobile), `sos_acknowledged`… (retours à l'auteur·e). Un type inconnu de l'application s'ouvre sur `/notifications`.

**Choix du type pour un SOS ou une alerte** (`NotificationBuilder::audience`) : l'espace par lequel la personne peut ouvrir l'alerte. Un compte prévenu comme équipe (admin de garde, pair de la zone) est d'abord `admin` s'il a `admin_mobile.access`, sinon `pair` s'il a l'espace pair ; une fiche d'annuaire liée à un compte (contact routé, point focal) est d'abord `pair`. Sans espace mobile (compte sans la permission, fiche sans compte, auteur·e) : type historique. Un·e admin non de garde n'est pas réveillé·e à la création (D-MOB-10) ; il·elle l'est à l'escalade nationale.

**Silencieux** (`sync`, `rules`) : FCM seulement (jamais Web Push, qui afficherait une notification vide), ni cloche ni texte, les préférences de notification ne s'appliquent pas (la personne ne voit rien). Anti-rafale : un même pair ne reçoit pas deux `sync` (ni deux `rules`) en moins de `xomiton.sync.push_debounce_seconds` / `rules_push_debounce_seconds` (20 s). Une panne du fournisseur est journalisée, jamais bloquante pour l'action d'administration.

**Discrétion (textes neutres)** : pour un destinataire de l'équipe (types ci-dessus, hors silencieux), le **mode discret** du profil (`profile.discreet_mode`, activé par défaut au premier accès Pair / Admin côté application, D-MOB-8) vaut « aperçus masqués », comme `mask_sensitive_notifications` : titre « Xomiton » et corps neutre (« Alerte urgente », « Nouvelle alerte », « Nouvelle demande », « Signalement à modérer »), **jamais « SOS », « VBG », « admin » ni le lieu, le pseudonyme ou le motif**. Le même texte neutre sert la cloche. Les textes complets ne partent que si la personne a désactivé le mode discret et le masquage. L'application peut aussi composer elle-même son texte local à partir de `{type, id}`.

**Préférences** : `moderation` et `systeme` ont désormais le push actif par défaut (nouveaux comptes), pour que `content_reported` et `peer_pending` arrivent sans réglage ; la personne peut les couper (`PATCH me/settings`). Les catégories `sos` et `securite` restent non désactivables.

Tests : `tests/Feature/Notifications/MobilePushTypesTest.php`.

### 24 bis. Interrupteurs d'espace et durée de vie du jeton (P15-22, D-MOB-9)

**Interrupteurs par espace** (`App\Application\Member\MobileSpaces`, réglages `mobile_space_peer_enabled` / `mobile_space_admin_enabled`, vrais par défaut) : couper l'espace Pair ou l'espace Admin de l'application **sans la republier** (revue Play, incident). Commande de secours cPanel / SSH / cron : `php artisan xomiton:mobile-space admin off` (`peer` ou `admin` ; `on`, `off` ou `status`), journal `platform.mobile_space_switched` (acteur « système »). Un espace coupé : est retiré de `GET me` → `spaces` ; fait répondre **403 `space_disabled`** à `admin/*` et à `pair/*` quand ils sont appelés avec un **jeton Bearer** (application) ; reste visible dans `GET app/version` → `spaces_enabled`. Le site web (session) et l'API qu'il utilise ne sont **jamais** touchés ; l'espace Membre ne se coupe pas (SOS, signalement, urgences : RG-01). L'application traite `space_disabled` comme un retrait de droit : relire `GET me`, retirer l'espace, afficher « Cet espace n'est plus disponible ».

**Durée de vie glissante du jeton** (`SlideMobileTokenExpiry`, `xomiton.accounts.mobile_token_days`, recommandation retenue de D-MOB-9) : le jeton Sanctum de l'application expire s'il reste **inutilisé** 7 jours pour un compte qui a l'espace Admin (`admin_mobile.access`), 30 jours pour un compte pair ; un compte membre garde un jeton sans échéance propre. Chaque requête authentifiée repousse l'échéance (au plus une écriture par jour et par jeton) ; l'échéance suit le rôle **actuel** (un jeton qui reçoit l'espace Admin est ramené à 7 jours). Un jeton expiré répond **401 `unauthenticated`** : reconnexion (et OTP pour un compte admin, Q13 b). Sans effet pour une session web. Le jeton ne porte aucune capacité figée (écart assumé avec la capacité `admin-mobile` du plan §6.3) : les rôles se cumulent et changent sans reconnexion, la permission lue à chaque requête reste la seule vérité.

Tests : `tests/Feature/Api/MobileSpacesAndTokensApiTest.php`.
