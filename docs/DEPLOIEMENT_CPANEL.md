# Déploiement cPanel : checklists recette puis production (P17-01, P17-02, P17-06)

Cases à cocher et à joindre au rapport de recette. Procédures détaillées : [RUNBOOK.md](RUNBOOK.md). Contexte : D7 (`design-system/DECISIONS.md`), ADR-22, [PERFORMANCE.md](PERFORMANCE.md) §7-8.

## 0. Assainissement de l'hébergement compromis (P17-01) — liste de contrôle seulement

À faire **avant** tout déploiement en recette ; rien n'est exécuté depuis le dépôt. Rapport d'incident signé en sortie.

- [ ] Préférer un **compte d'hébergement neuf** ; sinon assainir l'existant :
- [ ] Sauvegarder (copie hors serveur) puis supprimer `xomiton/cronjdfp0i4.php` (porte dérobée AES-256-GCM) et `f.php` ; **ne jamais exécuter** ces fichiers.
- [ ] Rechercher d'autres fichiers récents ou inconnus : `find ~ -type f \( -name '*.php' -o -name '.htaccess' -o -name '*.phtml' \) -mtime -90 ! -path '*/vendor/*'` dans `public/`, `storage/`, racine, `public_html`, `.cpanel`, `tmp`.
- [ ] Revoir les tâches cron, comptes FTP, comptes e-mail, redirections et filtres e-mail, clés/jetons API cPanel, clés SSH autorisées, domaines et sous-domaines ajoutés.
- [ ] Journaux d'accès (cPanel › Raw Access) : POST vers `cronjdfp0i4.php`, dates, adresses IP ; croiser avec les connexions FTP/SSH.
- [ ] Changer : mot de passe cPanel (+ 2FA), FTP, SSH, MySQL, SMTP, e-mail ; révoquer les clés API.
- [ ] Données de l'ancien site : considérer comme exposées ; `APP_KEY` neuve + `xomiton:reencrypt` s'il y a des données chiffrées à conserver (RUNBOOK §8).
- [ ] Rapport d'incident (chronologie, périmètre, actions) signé par la coordination.

## 1. Serveur (recette)

- [ ] **Sous-domaine** (ex. `recette.xomiton.org`) pointé sur `~/xomiton/web/public` (cPanel › Domaines › racine du document). Jamais sur `web/` ni `public_html` contenant le code. Vérifier : `https://…/.env` et `/composer.json` répondent 403/404.
- [ ] **PHP 8.3** : cPanel › MultiPHP Manager, version `ea-php83` pour le domaine. Chemin du cron : `/opt/cpanel/ea-php83/root/usr/bin/php` (le `php` du terminal peut rester 8.2). `composer.json` exige `^8.3`.
- [ ] **Extensions** (MultiPHP Manager › Extensions, vérifier avec `php -m` de ce binaire). Requises : `ctype`, `curl`, `fileinfo`, `intl`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `sodium`, `tokenizer`, `xml` (+ `dom`, `xmlwriter`, `zip` pour PhpWord / OpenSpout). `zip` avec chiffrement AES (libzip ≥ 1.2) : archives ADR-22 (`ZipArchive::EM_AES_256`). `gd` avec WebP (`imagewebp`, utilisé par `MediaUploader`) ou `imagick`. `gmp` ou `bcmath` : `minishlink/web-push` (VAPID). `intl` avec les locales `fr-BJ` / `fr-FR` (repli documenté). Contrôle automatique : `xomiton:preflight` et `scripts/deploy.sh`.
- [ ] **Directives PHP** (MultiPHP INI Editor) : `display_errors = Off`, `log_errors = On`, `memory_limit ≥ 256M`, `max_execution_time` 30 s accepté (le code est budgété pour 30 s / 128 Mo), `upload_max_filesize` et `post_max_size` ≥ 12M, `max_input_vars ≥ 3000` (tableaux d'administration), `opcache.enable=1`.
- [ ] **`disable_functions`** : `proc_open` et `exec` doivent être permises pour `mysqldump` (sinon : sauvegarde JSON de repli ADR-22 seule, et `scripts/backup.sh` inutilisable ; compter alors sur le mysqldump cPanel). `symlink()` : si désactivée, `storage:link` échoue, voir §3.
- [ ] **« Entry Processes »** et CPU : noter les limites du forfait (cPanel › Resource Usage) ; le polling Livewire (15-30 s, 4 s en conversation) et deux crons par minute les consomment. Atteindre la limite = passage VPS (D7).
- [ ] **HTTPS** : AutoSSL actif (cPanel › SSL/TLS Status), certificat valide, redirection HTTP→HTTPS par `public/.htaccess` (testée), `APP_URL=https://…`, cookies `Secure` (automatiques en production), HSTS posé par l'application (`SecurityHeaders`, seulement sur requête HTTPS).
- [ ] **`public/.htaccess`** (versionné) : redirection HTTPS, dotfiles et fichiers de projet refusés, `Options -Indexes -MultiViews`, `/build` en `Cache-Control: public, max-age=31536000, immutable`, `.webmanifest` en `no-cache`, gzip/Brotli. Vérifier : `curl -sI https://…/build/assets/<fichier>.css` (immutable) et `curl -sI https://…/` (`no-cache`, `private`).
- [ ] **En-têtes** (posés par l'application) : `curl -sI https://…/` montre `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security`, et ni `X-Powered-By` ni `Server` bavard. Test automatisé des en-têtes de cache de P17-02 : script `curl` à joindre au rapport.
- [ ] **Base** : MySQL 8 / MariaDB 10.6, utf8mb4, utilisateur dédié, accès distant fermé.
- [ ] **Courrier** : SPF, DKIM, DMARC (cPanel › Email Deliverability) sur le domaine d'envoi.
- [ ] **Robots** : en recette `SEO_ALLOW_INDEXING=false` (hors production tout est interdit à l'exploration), protection par mot de passe si le projet l'exige.

## 2. Mécanisme de déploiement

Aucun Node ni Composer garantis : `vendor/` et `public/build` sont construits en local (RUNBOOK §1). Option Git cPanel : un `.cpanel.yml` copiant `web/` (sans `public/`) vers `/home/COMPTE/xomiton/web`, puis `scripts/deploy.sh` ; `vendor/` et `public/build` étant ignorés par Git, ils restent déposés en SFTP. Critère : déploiement rejoué **deux fois** sans étape manuelle non documentée.

- [ ] Premier envoi : RUNBOOK §1 avec `scripts/deploy.sh --first`.
- [ ] Second envoi (rejeu) : `scripts/deploy.sh`, sans autre manipulation ; noter toute étape ajoutée au RUNBOOK.
- [ ] `.env` et clés (`FIREBASE_CREDENTIALS`, VAPID) hors dépôt et hors `public/`.

## 3. Alternative sans `symlink()`

Si `storage:link` échoue : déposer les médias publics dans un disque servi par route (`FILESYSTEM_DISK`/disque `public` servi par l'application) ou demander l'activation de `symlink()` à l'hébergeur. À trancher en recette ; l'état est signalé en WARN par `xomiton:preflight`.

## 4. Cron et supervision (P17-03)

- [ ] Deux lignes cron (RUNBOOK §1.6), binaire PHP 8.3 complet, chaque minute.
- [ ] Vérification de supervision créée (période 1 min, grâce 3 min, alerte e-mail + SMS) et `HEARTBEAT_URL` posée.
- [ ] Essai d'alerte : désactiver le cron 5 min → alerte reçue ; simuler une exception dans `alerts:escalate` → alerte reçue (le signal de vie doit être conditionné aux marqueurs de succès : voir le plan P17-03).
- [ ] `storage/framework/cache` ne grossit plus après 24 h (`cache:purge-expired`).
- [ ] Sondes HTTP externes sur `/` et `/sos`.

## 5. Recette (clôture)

- [ ] `php artisan xomiton:preflight` : 0 `FAIL`, avertissements justifiés.
- [ ] Sauvegarde puis **restauration** réussies sur le chemin de production (RUNBOOK §9).
- [ ] Procédures du RUNBOOK rejouées une fois (cron arrêté, SMTP coupé, maintenance, rotation de clé test).
- [ ] Rapport de recette : cette liste cochée, sortie de `php -m`, limites d'hébergement, captures des en-têtes.

## 6. Production (P17-06)

- [ ] `.env` de production (`APP_ENV=production`, `APP_DEBUG=false`, `SEO_ALLOW_INDEXING=true`), nouvelle `APP_KEY` propre à la production.
- [ ] Domaine principal pointé sur `web/public`, AutoSSL émis **avant** la bascule du DNS final si possible.
- [ ] **DNS** : TTL abaissé à 300 s 48 h avant ; enregistrements `A` (apex) / `CNAME www` vers l'IP de l'hébergement ; `MX` inchangés ; SPF / DKIM / DMARC ; `CAA` optionnel. Plan de repli : ancien enregistrement conservé, TTL remonté après 48 h de stabilité.
- [ ] `SANCTUM_STATEFUL_DOMAINS=xomiton.org,www.xomiton.org`, redirection `www` ↔ apex unique.
- [ ] `scripts/deploy.sh --first`, `xomiton:preflight` (sans `--skip-cron` une fois le cron en place), crons, signal de vie, sondes, planification de `scripts/backup.sh` et copie externe.
- [ ] `php artisan xomiton:import-prototype <dossier data/> --dry-run` puis réel, si des données réelles existent.
- [ ] Fournisseurs réels : `WEBPUSH_DRIVER=vapid`, `FCM_DRIVER=firebase`, `SMS_PROVIDER` ; plus aucun WARN « pilote log » inattendu.
- [ ] Formation de l'équipe : alerte de signal de vie, SOS non escaladé, maintenance, restauration (RUNBOOK §4, §7, §9, §12) ; planning de garde renseigné.
