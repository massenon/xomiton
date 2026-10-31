# Guide de mise en production de Xomiton sur cPanel

Ce guide vous accompagne de l'hébergement vide jusqu'au site ouvert au public, puis jusqu'à l'application sur Google Play. Il s'adresse à la personne qui met le site en ligne (le client ou son prestataire) : chaque étape dit **quoi faire**, **où cliquer** et **comment vérifier**.

**Soyons clairs sur son état.** Rien n'a encore tourné sur l'hébergement cible : ces procédures sont **rédigées d'après le code et les scripts du dépôt, jamais rejouées sur un vrai cPanel**. Les commandes `artisan` ont été vérifiées sous Linux (PHP 8.3) ; tout ce qui touche à cPanel (interfaces, chemins, limites du forfait) est **(non vérifié)** tant qu'une première mise en ligne en recette n'a pas eu lieu. Notez toute différence rencontrée et corrigez ce guide. Le calendrier est dans [PLAN_FINALISATION.md](PLAN_FINALISATION.md).

Pour ne pas tout répéter, ce guide s'appuie sur les documents existants :

| Sujet | Document |
|---|---|
| Listes de contrôle serveur (assainissement, extensions, en-têtes, DNS) | [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) |
| Procédures d'exploitation (alerte, SMTP, clés, restauration, incident) | [RUNBOOK.md](RUNBOOK.md) |
| Recette à cocher avant l'ouverture | [GUIDE_RECETTE.md](GUIDE_RECETTE.md) et [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) |
| Sécurité | [SECURITE_CHECKLIST.md](SECURITE_CHECKLIST.md) |
| Application Android (clé, AAB, Firebase) | [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) |
| Fiche Google Play | [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) |
| Application mobile : périmètre | [PLAN_MOBILE_APP_UNIQUE.md](PLAN_MOBILE_APP_UNIQUE.md) |
| Installer sur son ordinateur d'abord | [INSTALLATION_LOCALE.md](INSTALLATION_LOCALE.md) |

## 0. Avant de commencer : ce qu'il faut réunir

| À réunir | Qui |
|---|---|
| Accès cPanel (si possible un **compte d'hébergement neuf**, voir §1), accès SSH ou terminal cPanel | Client / hébergeur |
| Nom de domaine de production (l'exemple du dépôt est `xomiton.org`, **à confirmer**) et accès à sa zone DNS | Client |
| Les **12 informations légales** (éditeur, hébergeur, protection des données, juridiction), voir §14 | Client |
| Une **boîte e-mail** de contact et un compte SMTP (cPanel ou Brevo, Mailjet…) | Client |
| Comptes optionnels : Firebase (push), Vonage ou Twilio (SMS), Healthchecks.io ou UptimeRobot (supervision), stockage externe de sauvegardes | Client |
| Un poste de développement avec PHP 8.3, Composer et Node (le serveur n'a pas Node), voir [INSTALLATION_LOCALE.md](INSTALLATION_LOCALE.md) | Développeur |

À ne jamais faire : ouvrir le site au public avant d'avoir parcouru la checklist de sécurité du §17, ou activer l'assistant Gemini avant l'avis juridique (§11).

## 1. Hébergement : repartir d'un terrain sain

L'ancien hébergement du projet a été **compromis** (fichiers `cronjdfp0i4.php` et `f.php`). Suivez d'abord la liste d'assainissement de [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §0. En résumé : de préférence un **compte neuf** ; sinon sauvegarde hors serveur, suppression des fichiers suspects (sans jamais les exécuter), revue des tâches cron, comptes FTP et clés, changement de **tous** les mots de passe, nouvelle `APP_KEY`. Un rapport d'incident signé par la coordination conclut cette étape.

## 2. Arborescence sur le serveur

Le code n'est **jamais** déposé dans `public_html`. Seul le dossier `public/` est visible depuis Internet.

```
/home/COMPTE/
├── xomiton/
│   └── web/                  <- le code de l'application (envoyé par SFTP)
│       ├── app/ config/ vendor/ storage/ bootstrap/ ...
│       ├── .env              <- configuration de production (créée une fois, jamais versionnée)
│       ├── scripts/deploy.sh, scripts/backup.sh
│       └── public/           <- RACINE WEB du (sous-)domaine (index.php, .htaccess, build/)
├── secrets/                  <- clé Firebase (chmod 600), hors de toute racine web
├── sauvegardes/              <- archives locales avant copie hors serveur
└── public_html/              <- inchangé (ne contient pas le code)
```

`COMPTE` est votre identifiant cPanel. Les commandes ci-dessous supposent ces chemins.

## 3. Sous-domaine, PHP 8.3, base de données

1. **Sous-domaine ou domaine** : cPanel › Domaines › Créer un domaine (ou sous-domaine, par exemple `recette.xomiton.org` pour la recette puis le domaine principal pour la production). Dans « Racine du document », saisissez **`xomiton/web/public`**. Jamais `xomiton/web` ni `public_html`.
2. **PHP 8.3** : cPanel › MultiPHP Manager, choisissez `ea-php83` pour ce domaine. Le chemin à utiliser dans le terminal et le cron est `/opt/cpanel/ea-php83/root/usr/bin/php` (le `php` du terminal peut rester en 8.2). Vérification : `/opt/cpanel/ea-php83/root/usr/bin/php -v`.
3. **Extensions et réglages PHP** : cPanel › MultiPHP Manager (extensions) et MultiPHP INI Editor. Liste exacte (`ctype`, `curl`, `fileinfo`, `intl`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `sodium`, `tokenizer`, `xml`, `zip`, `gd` avec WebP…) et directives (`display_errors = Off`, `memory_limit ≥ 256M`, `upload_max_filesize` et `post_max_size ≥ 12M`, `max_input_vars ≥ 3000`) : [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §1. Contrôle : `/opt/cpanel/ea-php83/root/usr/bin/php -m`.
4. **Base MySQL** : cPanel › Bases de données MySQL. Créez une base, **un utilisateur dédié** avec un mot de passe long, et attribuez-lui **tous les privilèges sur cette base seulement**. Notez le nom exact (cPanel ajoute le préfixe du compte, par exemple `compte_xomiton`). Jeu de caractères : `utf8mb4` (le `.env` le précise). Version attendue : MySQL 8 ou MariaDB 10.6+. Aucun accès distant.
5. **Courrier** : cPanel › Email Deliverability, activez SPF, DKIM, DMARC sur le domaine d'envoi.

## 4. Préparer le code sur votre ordinateur

Le serveur n'a ni Node ni forcément Composer : on construit **chez vous** puis on envoie. Dans le dossier `web` d'un dépôt à jour, branche `claude/friendly-darwin-abyzn0` (ou la branche de livraison convenue) :

```powershell
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

Contrôles : `public/build/manifest.json` existe ; `vendor/` ne contient plus d'outils de développement. Vérifié : `composer install --no-dev --optimize-autoloader --dry-run` retire bien les paquets de développement (Paratest, Debugbar…). Si vous refaites ensuite du développement, relancez un `composer install` normal. **Ne construisez pas l'envoi dans le dossier de travail habituel sans le savoir** : une copie propre du dépôt évite d'envoyer `vendor/` de développement.

Constituez l'envoi : tout le contenu de `web/` **sans** `.env`, `.git`, `node_modules`, `tests`, `storage/logs/*` ni `storage/framework/*`, **avec** `vendor/` et `public/build/`. Gardez l'archive de chaque envoi : c'est votre retour arrière (§16).

## 5. Envoyer les fichiers

SFTP (FileZilla, WinSCP) ou Gestionnaire de fichiers cPanel (archive ZIP à extraire). Destination : `/home/COMPTE/xomiton/web`. Vérification : dans un navigateur, `https://votre-domaine/.env` et `https://votre-domaine/composer.json` doivent répondre **403 ou 404**, jamais le contenu.

## 6. Le fichier `.env` de production

**Modèle prêt à l'emploi : [`web/.env.production.example`](../web/.env.production.example)** (production complète et commentée en français, sans aucun secret ; sa procédure en tête : copie en `.env`, `key:generate --force`, `xomiton:install`, `config:cache`). Créez le fichier `/home/COMPTE/xomiton/web/.env` (copie de `web/.env.production.example`, ou de `.env.example` puis modifications), puis restreignez-le : `chmod 600 .env`. **Il contient des secrets : ne l'envoyez jamais par e-mail ni sur Git.**

Ne remplissez pas `APP_KEY` à la main : voir §7. Ci-dessous, la liste complète des variables utiles. Les valeurs entre `<...>` sont à remplacer ; tout ce qui n'est pas précisé garde la valeur de `.env.example`.

```ini
# --- Application ---------------------------------------------------------------
APP_NAME=Xomiton
APP_ENV=production                       # les comptes de démonstration ne sont JAMAIS créés en production
APP_KEY=                                 # générée une fois sur le serveur (§7)
APP_DEBUG=false
APP_URL=https://<domaine-de-production>  # exemple du dépôt : https://xomiton.org (à confirmer), sans "/" final
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_TIMEZONE=Africa/Porto-Novo
XOMITON_DISPLAY_TIMEZONE=Africa/Porto-Novo
SEO_ALLOW_INDEXING=true                  # false en recette : l'indexation est alors interdite
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
DEBUGBAR_ENABLED=false

# --- Journaux ------------------------------------------------------------------
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning                        # "debug" seulement pour une enquête ponctuelle

# --- Base de données -----------------------------------------------------------
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<compte_base>
DB_USERNAME=<compte_utilisateur>
DB_PASSWORD=<mot_de_passe_long>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# --- Sessions, file, cache (hébergement mutualisé : ni Redis ni processus permanent)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_DOMAIN=null
SANCTUM_STATEFUL_DOMAINS=<domaine>,www.<domaine>   # hôte d'APP_URL, sinon le SOS flottant perd le lien avec le compte
QUEUE_CONNECTION=database
CACHE_STORE=file
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=null

# --- E-mails (SMTP réel) -------------------------------------------------------
MAIL_MAILER=smtp
MAIL_HOST=<serveur-smtp>
MAIL_PORT=587                            # 465 avec MAIL_SCHEME=smtps ; suivre l'indication du fournisseur
MAIL_SCHEME=null
MAIL_USERNAME=<identifiant-smtp>
MAIL_PASSWORD=<mot_de_passe_smtp>
MAIL_FROM_ADDRESS="<adresse-d-expedition>"
MAIL_FROM_NAME="${APP_NAME}"

# --- Newsletter ----------------------------------------------------------------
XOMITON_NEWSLETTER_KEY=<64 caractères hexadécimaux>   # php -r "echo bin2hex(random_bytes(32));" ; la changer invalide les liens déjà envoyés
XOMITON_NEWSLETTER_FLOOR_MS=600

# --- SOS et alertes (valeurs par défaut sûres) ---------------------------------
XOMITON_ESCALATION_MINUTES=5
XOMITON_PROVIDER_TIMEOUT=3
XOMITON_SOS_MAX_RETRIES=10
# XOMITON_PEER_RECIPIENTS=zone           # zone (défaut) ou all : pair·es prévenu·es à l'envoi d'un SOS

# --- Push navigateur et mobile -------------------------------------------------
WEBPUSH_DRIVER=log                       # vapid exige "composer require minishlink/web-push" (non livré) et des clés VAPID
VAPID_SUBJECT=mailto:<contact>
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
FCM_DRIVER=fcm                           # "log" tant que Firebase n'est pas prêt (§11)
FIREBASE_CREDENTIALS=/home/COMPTE/secrets/firebase-sa.json
FIREBASE_PROJECT_ID=

# --- SMS de secours du SOS -----------------------------------------------------
SMS_DRIVER=vonage                        # log | vonage | twilio ; "log" tant que le compte n'existe pas (§11)
SMS_SENDER=<nom-11-caracteres-ou-numero-E164>
VONAGE_API_KEY=
VONAGE_API_SECRET=
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_MESSAGING_SERVICE_SID=

# --- Assistant : Gemini DESACTIVE ----------------------------------------------
ASSISTANT_PROVIDER=kb
ASSISTANT_LLM_ENABLED=false
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash
GEMINI_TIMEOUT=4
GEMINI_PER_HOUR=10

# --- Réseaux sociaux (sans clés : "publication manuelle") ----------------------
# XOMITON_GATEWAYS_DRIVER=log
FACEBOOK_APP_ID=
FACEBOOK_APP_SECRET=
TELEGRAM_BOT_TOKEN=
TIKTOK_CLIENT_KEY=
TIKTOK_CLIENT_SECRET=

# --- Supervision et sauvegardes ------------------------------------------------
HEARTBEAT_URL=<url-de-la-verification-healthchecks>
HEARTBEAT_REQUIRE_MARKERS=true
BACKUP_DISK=local                        # disque externe chiffré recommandé (décision Q9)
BACKUP_ARCHIVE_PASSWORD=<mot_de_passe_long_a_conserver_ailleurs>

# --- Bibliothèque hors ligne et front ------------------------------------------
XOMITON_OFFLINE_LIBRARY=true
VITE_APP_NAME="${APP_NAME}"

# --- Informations légales (à compléter par le client avant la mise en ligne) ---
XOMITON_LEGAL_PUBLISHER_NAME=
XOMITON_LEGAL_FORM=
XOMITON_LEGAL_REGISTRATION=
XOMITON_LEGAL_ADDRESS=
XOMITON_LEGAL_DIRECTOR=
XOMITON_LEGAL_PHONE=
XOMITON_LEGAL_HOST_NAME=
XOMITON_LEGAL_HOST_ADDRESS=
XOMITON_LEGAL_HOST_COUNTRY=
XOMITON_LEGAL_DPO_NAME=
XOMITON_LEGAL_DPO_EMAIL=
XOMITON_LEGAL_JURISDICTION=
```

Notes importantes :

- **Les commentaires `#` en fin de ligne** ci-dessus servent d'explication ; dans le vrai `.env`, mettez-les sur une ligne à part ou supprimez-les, et mettez entre guillemets les valeurs qui contiennent des espaces ou un `#`.
- **`APP_DEBUG=false` est obligatoire** : en mode débogage le site afficherait ses secrets en cas d'erreur.
- **Pas de variable pour l'e-mail de contact public** : il se règle dans l'application, Administration › Paramètres › Général (`contact_email`, valeur d'origine `contact@xomiton.bj`, **à remplacer**). Il apparaît dans la politique de confidentialité et les e-mails.
- **Informations légales** : laissez vide ce qui n'est pas fourni (la page affiche alors un repère visible « À COMPLÉTER PAR LE CLIENT »). N'écrivez pas de texte provisoire. Détail au §14 ; format et pages concernées en commentaire dans `web/.env.example`.
- **Réglages secondaires** (rarement utiles, valeurs par défaut sûres) : `XOMITON_DISPOSABLE_DOMAINS` (domaines d'e-mail jetables à signaler en plus de la liste intégrée), `XOMITON_REPORTS_DISK`, `XOMITON_EXPORT_MAX_ROWS`, `XOMITON_MYSQLDUMP` et `XOMITON_MYSQLDUMP_BINARY` (sauvegarde), `SMS_PROVIDER` (ancien nom de `SMS_DRIVER`), `APP_PREVIOUS_KEYS` (uniquement pendant une rotation de clé, [RUNBOOK.md](RUNBOOK.md) §8).
- Après toute modification du `.env` : `php artisan config:cache` (voir §10). Sans cela, le changement n'est pas pris en compte.

## 7. Premier déploiement

Dans le terminal cPanel (ou en SSH), depuis `/home/COMPTE/xomiton/web`. On note `PHP=/opt/cpanel/ea-php83/root/usr/bin/php`.

```bash
cd ~/xomiton/web
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan key:generate          # UNE SEULE FOIS, sur un site neuf (jamais sur un site en service : données illisibles)
scripts/deploy.sh --first
$PHP artisan xomiton:install       # PREMIER déploiement : données de base (rôles, réglages, référentiels, modèles d'e-mails), diagnostic, premier super-admin (§7.1)
```

Ce que fait `scripts/deploy.sh --first` : vérifie PHP 8.3, les extensions, `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, la présence de `public/build` et `vendor/` ; applique les migrations (`migrate --force`) ; crée le lien `storage:link` ; construit les caches de configuration, de routes, de vues et d'événements ; lance `xomiton:preflight`. À la main, cela donne : `$PHP artisan migrate --force && $PHP artisan storage:link && $PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache`.

**Pourquoi `xomiton:install` ?** `migrate` crée les tables mais pas les données de base (cinq rôles et leurs permissions, zones et communes du Bénin, 38 réglages, modèles d'e-mails, thématiques…). `php artisan xomiton:install` exécute **uniquement** les seeders de base, tous idempotents : rejouable sans risque, il ne duplique rien et n'écrase pas les réglages déjà modifiés dans l'administration. Il ne crée **jamais** de compte de démonstration, quel que soit `APP_ENV` (contrairement à `db:seed`, qui en sème hors production). Il affiche ensuite un diagnostic : `APP_ENV`, `APP_URL`, base, file d'attente, cache, variables `XOMITON_LEGAL_*` manquantes, FCM / SMS / Gemini désactivés, cron. `php artisan xomiton:install --check` vérifie sans rien écrire.

### 7.1 Créer le premier compte super-administrateur

Aucun compte administrateur n'existe après `xomiton:install` seul (l'inscription publique ne crée que des membres ; le rôle admin ne se donne que depuis le web, par un·e super-administrateur·rice). Créez le premier **une fois**, par la personne technique, avec un vrai e-mail :

```bash
$PHP artisan xomiton:install --admin-email=prenom@exemple.org
```

- Sans `--admin-email`, la commande propose la création en interactif (e-mail, puis mot de passe saisi **masqué** et confirmé, ou généré). Hors saisie interactive (script), le mot de passe est généré.
- `--generate-password` force un mot de passe aléatoire de 24 caractères : il est **affiché une seule fois** dans le terminal. Notez-le dans un coffre de mots de passe, puis changez-le à la première connexion. Le mot de passe n'est **jamais** accepté en argument, ni écrit dans le journal d'audit (`install.first_admin` ne consigne que l'e-mail et les rôles) ni dans `laravel.log`.
- Options : `--admin-name=`, `--admin-pseudo=`, `--no-admin` (données de base seulement).
- Le compte est actif, son e-mail est marqué vérifié, il a les rôles `membre` et `superadmin`.
- **Refus s'il existe déjà un super-administrateur** (code de sortie 1) ; `--force` explicite pour en créer un de plus.
- Les comptes administrateurs exigent un code de vérification par e-mail **à chaque connexion** : **le SMTP doit donc fonctionner avant la première connexion** (en production le code n'est jamais écrit dans le journal). Une fois connecté·e, changez le mot de passe, puis créez les autres comptes depuis Administration › Utilisateurs. Ne laissez aucun mot de passe dans l'historique du terminal partagé.

## 8. Droits des dossiers

```bash
cd ~/xomiton/web
find . -type d -not -path './vendor/*' -exec chmod 755 {} \;
find . -type f -not -path './vendor/*' -exec chmod 644 {} \;
chmod -R ug+rwX storage bootstrap/cache
chmod 600 .env
chmod +x scripts/deploy.sh scripts/backup.sh
```

Dossiers 755, fichiers 644, `storage/` et `bootstrap/cache/` inscriptibles, `.env` en 600, propriétaire = le compte cPanel. (Le script `deploy.sh` refait lui-même le `chmod` de `storage/` et `bootstrap/cache/`.) Contrôle : `$PHP artisan xomiton:preflight` indique « inscriptible » pour chacun.

## 9. Planificateur (cron) et file d'attente

Sans cron, **les SOS sans réponse ne sont pas escaladés** : c'est la tâche la plus importante du serveur. cPanel › Tâches Cron, ajoutez **deux** lignes, chacune « toutes les minutes » (`* * * * *`), avec le binaire PHP 8.3 complet :

```
* * * * * cd /home/COMPTE/xomiton/web && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/COMPTE/xomiton/web && /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

La première lance le planificateur : chaque minute `alerts:escalate`, `notifications:retry`, `mail:flush-outbox`, `newsletter:process`, `gateways:publish`, `heartbeat:ping` ; toutes les 5 minutes la clôture des sondages ; la nuit (02:00 à 04:15) sauvegarde, purges et anonymisations (liste : `$PHP artisan schedule:list`). La seconde traite la **file d'attente stockée en base de données** (`QUEUE_CONNECTION=database`) : exports de données, envois en file, sans processus permanent.

Contrôles : `$PHP artisan schedule:run -v` liste les tâches ; `$PHP artisan xomiton:preflight` (sans `--skip-cron`, après dix minutes) ne doit plus avertir sur `alerts:escalate` et `notifications:retry`. Surveillez « Entry Processes » (cPanel › Resource Usage) : atteindre la limite du forfait impose de passer à un VPS (décision D7).

## 10. Caches de production

À refaire **après chaque modification du `.env` ou mise à jour du code** (le script `deploy.sh` le fait pour vous) :

```bash
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
```

En cas de comportement étrange après un changement : `config:clear`, `route:clear`, `view:clear`, `event:clear`, puis les quatre commandes ci-dessus.

## 11. Courrier, SMS, push, assistant : services externes

Tout démarre éteint (pilote `log`) ; on active un canal seulement quand son compte existe. Procédures détaillées et commandes d'essai : [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §7 et [GUIDE_RECETTE.md](GUIDE_RECETTE.md) §8.

| Service | À faire | Essai |
|---|---|---|
| **E-mail (SMTP)** | `MAIL_*` du §6, ou Administration › Templates e-mails. **Indispensable avant la première connexion admin** (code de vérification) | Administration › Templates e-mails › essai d'envoi |
| **Push mobile (Firebase)** | Projet Firebase, application Android `bj.xomiton.xomiton`, clé de compte de service (JSON) déposée dans `/home/COMPTE/secrets/` en `chmod 600`, `FCM_DRIVER=fcm`, `FIREBASE_CREDENTIALS`, puis `config:cache`. Le fichier `google-services.json` va dans l'application, pas sur le serveur | `$PHP artisan notifications:test-push <jeton FCM> --urgent --yes` |
| **SMS de secours** | Compte Vonage **ou** Twilio, `SMS_DRIVER`, clés, `SMS_SENDER` (selon ce que l'opérateur béninois accepte), solde. Facturé par le fournisseur | `$PHP artisan notifications:test-sms +229… --yes` |
| **Assistant Gemini** | **DESACTIVÉ et à laisser tel quel** : `ASSISTANT_PROVIDER=kb`, `ASSISTANT_LLM_ENABLED=false`. Il enverrait des données sensibles (santé, violences) à un tiers hors de l'Union européenne ; l'activer exige d'abord l'avis juridique ([DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md), Q7). Les messages de crise et de SOS ne lui sont de toute façon jamais envoyés | `$PHP artisan assistant:test-llm --yes` refuse tant que le drapeau est faux |
| **Web Push navigateur** | Optionnel, non livré (paquet Composer et clés VAPID à ajouter) | |

Tant qu'un canal reste en `log`, `xomiton:preflight` affiche un avertissement : c'est attendu, mais **le SOS n'a alors aucun canal réel de secours**. Ne l'ouvrez pas au public dans cet état sans décision écrite de la coordination.

## 12. HTTPS, DNS

1. cPanel › SSL/TLS Status : lancez AutoSSL pour le domaine et `www`. Le certificat doit être valide **avant** la bascule du DNS final si possible.
2. DNS (voir [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §6) : abaissez le TTL à 300 secondes 48 h avant, enregistrement `A` (domaine nu) et `CNAME www` vers l'hébergement, conservez les `MX`, ajoutez SPF, DKIM, DMARC. Gardez l'ancien enregistrement pour pouvoir revenir en arrière.
3. `.htaccess` (déjà dans `public/`) : redirection HTTP vers HTTPS, fichiers cachés refusés, cache d'un an sur `/build`. Le site pose lui-même les en-têtes de sécurité (CSP, HSTS…). Contrôles : `curl -sI https://votre-domaine/` doit montrer `Content-Security-Policy`, `Strict-Transport-Security` et pas de `X-Powered-By` ; `curl -sI https://votre-domaine/build/assets/<fichier>.css` doit montrer `immutable`.
4. Un seul nom de référence (avec ou sans `www`) : redirigez l'autre vers lui. `APP_URL` et `SANCTUM_STATEFUL_DOMAINS` doivent correspondre.

## 13. Supervision, sauvegardes, maintenance

### 13.1 Signal de vie (heartbeat)

1. Créez un compte Healthchecks.io ou UptimeRobot, puis une vérification « cron » de période **1 minute**, délai de grâce **3 minutes**, alerte par e-mail **et** SMS à la personne de garde.
2. Copiez son adresse dans `HEARTBEAT_URL`, puis `$PHP artisan config:cache`.
3. Le signal n'est envoyé que si `alerts:escalate` et `notifications:retry` ont réussi (`HEARTBEAT_REQUIRE_MARKERS=true`) : une alerte veut dire que l'escalade des SOS est en danger.
4. Essai à faire une fois : désactiver le cron 5 minutes, l'alerte doit arriver ; réaction détaillée : [RUNBOOK.md](RUNBOOK.md) §4.
5. Ajoutez des sondes HTTP externes sur `/` et `/sos`.

### 13.2 Sauvegardes

- **Automatique** : `backups:run` chaque nuit à 02:00 (`mysqldump` si le serveur l'autorise, sinon archive ZIP chiffrée), vers `BACKUP_DISK`, chiffrée par `BACKUP_ARCHIVE_PASSWORD`. **Conservez ce mot de passe ailleurs que sur le serveur.**
- **Complément** : planifiez `scripts/backup.sh` à 01:30 (base + fichiers de `storage/app`) avec copie **hors du serveur** (variable `BACKUP_UPLOAD_CMD` du script), et la sauvegarde cPanel vers un stockage externe.
- **Avant chaque mise à jour** : `scripts/backup.sh` (le script `deploy.sh` le fait déjà pour la base).
- **Une sauvegarde jamais restaurée ne compte pas.** Faites un test de restauration en recette, sur le chemin de production, avec la procédure de [RUNBOOK.md](RUNBOOK.md) §9, et notez la date, la perte de données maximale et la durée. Ce test n'a pas encore été fait.

### 13.3 Mode maintenance

Pour une intervention : Administration › Maintenance, ou en ligne de commande :

```bash
$PHP artisan xomiton:maintenance on --minutes=30 --message="Mise à jour en cours." --reason="mise à jour"
$PHP artisan xomiton:maintenance status
$PHP artisan xomiton:maintenance off --reason="terminé"
```

Les urgences, le SOS, le signalement, le suivi et la connexion restent servis ; les admins se connectent. **N'utilisez jamais `artisan down`.** Si `deploy.sh` échoue en cours de route, le site reste en maintenance : corrigez puis relancez, ou levez la maintenance après avoir décidé du retour arrière.

## 14. Pages légales à compléter

Quatre pages publiques, accessibles sans connexion, sont alimentées par les variables `XOMITON_LEGAL_*` du §6 : `/mentions-legales`, `/politique-de-confidentialite`, `/conditions-utilisation` et `/cookies`. Google Play exige en plus une adresse web de **suppression de compte** : `/supprimer-mon-compte` (existe, sans connexion, formulaire pour la personne qui n'a plus accès à son compte). Les pages ne remplacent **pas** un avis juridique : leur texte décrit le fonctionnement réel du code et doit être relu par le conseil du client (droit béninois, RGPD, règles Google Play).

| Variable | Contenu attendu | Pages |
|---|---|---|
| `XOMITON_LEGAL_PUBLISHER_NAME` | nom de l'éditeur | mentions, confidentialité, conditions |
| `XOMITON_LEGAL_FORM` | forme juridique | mentions, confidentialité |
| `XOMITON_LEGAL_REGISTRATION` | numéro d'enregistrement | mentions |
| `XOMITON_LEGAL_ADDRESS` | adresse postale | mentions, confidentialité |
| `XOMITON_LEGAL_DIRECTOR` | directeur·rice de la publication | mentions |
| `XOMITON_LEGAL_PHONE` | téléphone | mentions |
| `XOMITON_LEGAL_HOST_NAME` | hébergeur | mentions, confidentialité |
| `XOMITON_LEGAL_HOST_ADDRESS` | adresse de l'hébergeur | mentions |
| `XOMITON_LEGAL_HOST_COUNTRY` | pays d'hébergement des données | mentions, confidentialité |
| `XOMITON_LEGAL_DPO_NAME` | protection des données : nom | mentions, confidentialité |
| `XOMITON_LEGAL_DPO_EMAIL` | protection des données : e-mail | mentions, confidentialité |
| `XOMITON_LEGAL_JURISDICTION` | juridiction compétente | mentions, conditions |

Marche à suivre : le client fournit les 12 informations ; vous les placez dans le `.env` du serveur (jamais de valeur inventée) ; `config:cache` ; ouvrez les quatre pages et cherchez le texte « À COMPLÉTER » : **il ne doit plus en rester** ; puis relecture juridique des textes. En local, le test `tests/Feature/Public/LegalPagesTest.php` (19 tests) vérifie ces pages. L'adresse de contact des pages vient du réglage `contact_email` (§6), à remplacer. Déclarez ensuite `/politique-de-confidentialite` et `/supprimer-mon-compte` (avec le vrai domaine) dans la Play Console.

## 15. Premier contrôle du site en ligne

```bash
$PHP artisan xomiton:preflight
```

Attendu : **zéro `FAIL`**, et des avertissements justifiés un par un (par exemple pilote SMS en `log` si le compte n'existe pas encore). Puis, à la main : `https://votre-domaine/` s'affiche, `/up` répond 200, `/sos` s'ouvre, `/connexion` fonctionne avec le compte du §7.1, un code de vérification arrive par e-mail. Rejouez ensuite les parcours de [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) sur ce serveur : c'est la recette qui n'a pas encore eu lieu en conditions réelles.

## 16. Mises à jour et retour arrière

### Mise à jour (procédure)

1. Annoncez l'intervention si la maintenance dépasse 5 minutes.
2. Sauvegarde : `scripts/backup.sh`.
3. Sur votre poste : mettez le dépôt à jour, `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build` (§4) ; gardez l'archive de l'envoi **précédent**.
4. Envoyez les fichiers (§5), sans toucher au `.env` ni à `storage/`.
5. Sur le serveur : `scripts/deploy.sh` (maintenance, sauvegarde de la base, migrations, caches, test de fumée, fin de maintenance). Un correctif sans migration ne demande pas de maintenance, mais les caches (§10) restent obligatoires.
6. Contrôles : `$PHP artisan xomiton:preflight`, `/`, `/sos`, une connexion.

### Retour arrière

1. `scripts/deploy.sh --rollback` : maintenance, annulation des migrations du dernier déploiement, recréation des caches.
2. Remettez l'archive du code précédent (même `vendor/` et `public/build/`), puis `scripts/deploy.sh --skip-preflight`, puis `xomiton:preflight`.
3. Si une migration a modifié ou supprimé des données : **restaurez la sauvegarde** faite avant la mise à jour ([RUNBOOK.md](RUNBOOK.md) §9) plutôt que d'annuler les migrations.

Détails : [RUNBOOK.md](RUNBOOK.md) §2 et §3.

## 17. Checklist de sécurité avant ouverture au public

À cocher et signer. Elle complète [SECURITE_CHECKLIST.md](SECURITE_CHECKLIST.md), dont plusieurs lignes « à vérifier à la recette » ne peuvent se cocher que sur le vrai serveur.

- [ ] Hébergement assaini ou compte neuf, rapport d'incident signé ([DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §0).
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` propre à la production, sauvegardée dans un coffre de mots de passe (pas dans le dépôt).
- [ ] `.env` en `chmod 600` ; `https://domaine/.env` et `/composer.json` répondent 403 ou 404.
- [ ] Le site répond en HTTPS avec un certificat valide ; HTTP redirige vers HTTPS ; en-têtes de sécurité présents (§12).
- [ ] Aucun compte de démonstration : `php artisan tinker --execute="echo App\Models\User::where('email','like','%@xomiton.bj')->count();"` ne doit compter que les comptes réels voulus ; aucun mot de passe de démonstration (`Admin123!`…) n'est valide.
- [ ] Mots de passe changés : cPanel (avec double authentification), FTP, SSH, MySQL, SMTP ; clés d'API cPanel révoquées.
- [ ] Assistant Gemini désactivé (`ASSISTANT_LLM_ENABLED=false`).
- [ ] Cron en place, signal de vie reçu, alerte de signal de vie testée, personne de garde et planning renseignés ([RUNBOOK.md](RUNBOOK.md) §12).
- [ ] SOS testé de bout en bout avec les vrais canaux (SMS, push) sur de vrais téléphones, et escalade à 5 minutes constatée.
- [ ] Sauvegarde nocturne observée, copie hors serveur en place, **restauration testée**.
- [ ] Informations légales complètes, plus aucune mention « À COMPLÉTER » sur les quatre pages, relecture juridique faite (§14).
- [ ] `composer audit` et `npm audit` relus avant l'envoi ; alertes restantes connues ([SECURITE_CHECKLIST.md](SECURITE_CHECKLIST.md) §1).
- [ ] Recette humaine terminée et consignée ([CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md)), checklist de [SECURITE_CHECKLIST.md](SECURITE_CHECKLIST.md) signée par la personne responsable.
- [ ] `SEO_ALLOW_INDEXING=true` seulement au moment voulu du lancement public.

## 18. Publication de l'application mobile (Google Play)

La publication dépend du site en ligne : l'application a besoin de l'adresse `https://<domaine>/api/v1`, de la politique de confidentialité et de la page de suppression de compte (§14).

1. **Clé de signature** et **paquet AAB signé** : `flutter build appbundle --release` avec l'adresse de production, selon [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) §7 et §8. Cette clé ne doit jamais être dans le dépôt et doit être sauvegardée ailleurs. Aucun AAB n'a encore été construit.
2. **Firebase** : `google-services.json` dans `mobile/android/app/` sur le poste de build, et clé de compte de service côté serveur (§11).
3. **Console Google Play** : compte développeur, fiche, formulaires (sécurité des données, classification, public cible, permissions, accès de test), pistes de test interne puis fermé (12 testeurs pendant 14 jours pour un compte personnel), puis production progressive. Tout est détaillé, avec la checklist, dans [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §13 et §14.
4. Un seul AAB pour les trois espaces (membre, pair, admin), qui s'affichent selon les rôles du compte : [PLAN_MOBILE_APP_UNIQUE.md](PLAN_MOBILE_APP_UNIQUE.md).
5. Avant tout dépôt : recette sur de vrais appareils (fiches MOB de [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) §10). iOS n'est pas livré.

## 19. Dépannage rapide

| Symptôme | Piste |
|---|---|
| Erreur 500 sans détail | Normal en production. Lisez `storage/logs/laravel.log` (dernière ligne `production.ERROR`). Causes fréquentes : `.env` incomplet, droits de `storage/`, mauvaise version de PHP, `config:cache` oublié après un changement. |
| « Vite manifest not found » | `public/build` n'a pas été envoyé : refaites `npm run build` chez vous et envoyez le dossier. |
| Écran blanc ou « No application encryption key » | `APP_KEY` vide : `key:generate` (premier déploiement seulement). |
| Impossible de se connecter, code non reçu | SMTP non configuré ou en panne : Administration › Templates e-mails › essai ; `mail_outbox` ; [RUNBOOK.md](RUNBOOK.md) §5. |
| Les images ne s'affichent pas | `storage:link` a échoué (`symlink()` désactivée chez l'hébergeur) : voir [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §3. |
| Alerte du signal de vie | Cron arrêté ou en erreur : [RUNBOOK.md](RUNBOOK.md) §4. |
| `deploy.sh` s'arrête sur une extension PHP manquante | cPanel › MultiPHP Manager › Extensions, activez-la pour `ea-php83`, ou définissez `PHP_BIN`. |
| Le site est resté en maintenance après un échec | Corrigez puis relancez `scripts/deploy.sh`, ou `xomiton:maintenance off` après décision de retour arrière. |
| Incident de sécurité | [RUNBOOK.md](RUNBOOK.md) §13. |
