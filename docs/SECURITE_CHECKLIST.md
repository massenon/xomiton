# Checklist de sécurité — revue ASVS niveau 2 (P16-03)

Statut : **partielle**. Seul ce qui est prouvé par un test, une commande ou une lecture de code citée est coché `[x]`. Le reste est `[ ]` « à vérifier à la recette » et exige un humain. La signature (bas de page) n'est pas donnée.

## 1. Audits de dépendances (2026-10-27)

| Commande | Résultat |
|---|---|
| `composer audit` | Aucune alerte (« No security vulnerability advisories found »). |
| `npm audit` | 6 alertes avant correction ; `npm audit fix` (sans `--force`) en a corrigé 2 (`fast-uri`, `serialize-javascript`, mises à jour du seul `package-lock.json`). **Restent 4** : `tinypool` (critique) et `@vitest/mocker` / `vitest` (modérée) — chaîne de **dev uniquement** (Vitest, jamais livrée en production), correctif = Vitest 5 (rupture) ; `quill` 2.0.3 (basse), sans correctif amont. À traiter par une montée de Vitest planifiée ; pas de `--force` ici. |

## 2. Contrôles

### V14 / V3 — En-têtes et CSP
- [x] `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, pas de `X-Powered-By` sur 5 pages types (`tests/Feature/Security/SecurityHeadersTest.php`, jeu « 5 pages types »).
- [x] CSP : `default-src 'self'`, `object-src 'none'`, `frame-ancestors 'none'`, `base-uri`, `form-action 'self'`, nonce par requête, jamais `unsafe-inline` sur les scripts (même fichier).
- [x] HSTS un an avec sous-domaines en production sur HTTPS uniquement (même fichier).
- [x] Pages membres Inertia en `Cache-Control: no-store, private` (`app/Http/Middleware/HandleInertiaRequests.php`).
- [ ] CSP : `'unsafe-eval'` est présent dans `script-src` (test d'accueil) — justification et retrait possible à examiner.
- [ ] Sondage d'en-têtes sur l'environnement déployé (cPanel, TLS, redirection HTTP→HTTPS) : à la recette.

### V3 — Sessions
- [x] Cookie de session `HttpOnly`, `SameSite=lax` (`config/session.php`, test « cookies de session SameSite lax et HttpOnly »), `Secure` par défaut en production (`config/session.php` l.176).
- [ ] Rotation d'identifiant à la connexion, invalidation à la déconnexion et à la réinitialisation du mot de passe, expiration d'inactivité : à vérifier à la recette.

### V12 — Téléversements
- [x] Images ré-encodées, EXIF retiré, 2048 px au plus (`tests/Feature/Shared/MediaUploaderTest.php`, `PostsApiTest`, `MemberProfileApiTest`).
- [x] Médias privés servis en lien signé (`PostsApiTest`, `SignedMediaController`).
- [ ] Antivirus / pièces jointes non image du signalement, quotas de volume : à vérifier à la recette.

### V6 / V8 — Chiffrement et données
- [x] Colonnes sensibles chiffrées par cast `encrypted` (par exemple `Alert` : description, lat, lng, contact ; `AlertNote.body` ; `Message` ; `CaseFollowup.comment` ; `AssistantMessage.body`).
- [ ] Gestion et rotation de `APP_KEY`, chiffrement des sauvegardes, TLS de bout en bout : à la recette (`RUNBOOK.md`).

### V11 — Limitation et rejeu
- [x] Limiteurs `login` (5/min), `otp` (5/h), signalement anonyme (10/h/IP), 429 (`tests/Feature/Security/RateLimitersTest.php`, `ReportApiTest`).
- [x] Rejeu d'idempotence : même clé = même réponse sans doublon, corps différent refusé (`ReportApiTest`, `SosApiTest`, ADR-32).
- [ ] Test de charge et réglage des limites en conditions réelles : à la recette.

### V2 — Énumération de comptes
- [x] Même message pour identifiant inconnu et mauvais mot de passe (web : `LoginTest`, API : `LoginApiTest`).
- [ ] Réponses identiques à la demande de réinitialisation et à l'inscription pour un e-mail existant, et temps de réponse comparables : tests existants dans `PasswordResetWebTest` / `OtpTest` à relire ; à vérifier à la recette.

### V7 — Journaux et exports (ADR-33)
- [x] Journal d'audit : champs sensibles masqués avant écriture (`app/Support/Audit.php`, `AuditTest` « masque les champs sensibles »).
- [x] Les appels `Log::*` relevés par grep (`app/`) ne consignent ni mot de passe, ni OTP, ni jeton, ni coordonnées ; ils portent des identifiants (ulid, report) et des classes d'exception.
- [ ] `GenerateReport` journalise `$exception->getMessage()` (`app/Jobs/GenerateReport.php` l.81) et d'autres appels journalisent des messages d'exception : un message peut citer une valeur. À relire.
- [ ] Revue des exports CSV / rapports pour toute donnée sensible : à la recette (humain).

### V4 — Contrôle d'accès
- [x] Aucune route protégée ne répond 200 à un·e visiteur·se ; un membre simple reçoit 403/404 sur chaque route d'administration et d'espace pair (`tests/Feature/Authorization/ProtectedRoutesTest.php`, parcours de la table de routes réelle).
- [ ] Contrôles d'objet (IDOR) route par route avec données de tiers : couverts en partie par les politiques (`tests/Feature/Authorization`), revue complète à la recette.

### Hors périmètre automatisable
- [ ] Test d'intrusion, revue de configuration serveur, gestion des secrets en production, plan de réponse aux incidents.

## 3. Signature

Relecteur·rice : ____________  Date : ____________  Décision : ____________
