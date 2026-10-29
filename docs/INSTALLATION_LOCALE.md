# Installation locale de Xomiton (site web et application mobile)

Ce guide vous accompagne, pas à pas, pour faire tourner Xomiton **sur votre ordinateur** : d'abord le site, puis l'application mobile. Il est écrit pour Windows (PowerShell) ; les différences pour macOS et Linux sont indiquées à chaque étape. Aucune connaissance technique n'est nécessaire : il suffit de recopier les commandes.

Ce qui est volontairement **simulé en local** : aucun e-mail, SMS ni notification ne part vraiment ; tout est écrit dans un fichier journal (voir §6). Rien n'est envoyé à un service extérieur.

Honnêteté sur ce qui a été vérifié : les commandes du site (`artisan`, `composer`, `pest`) ont été exécutées sous Linux avec PHP 8.3 ; les étapes propres à Windows, à macOS et à Android (émulateur, téléphone, `flutter build apk`) n'ont **pas** pu être rejouées dans l'environnement de rédaction. Elles sont marquées **(non vérifié)** quand c'est le cas. Si une commande ne marche pas chez vous, notez le message exact : le §10 couvre les cas déjà rencontrés.

Documents liés, pour ne pas tout répéter ici :

| Besoin | Document |
|---|---|
| Version « tout automatique » du site (scripts `.sh`) et tests à la main | [GUIDE_RECETTE.md](GUIDE_RECETTE.md) |
| Liste des parcours à cocher pendant la recette | [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) |
| Application Android en détail (SDK, signature, AAB, Firebase) | [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) |
| Mise en ligne sur le serveur | [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) |
| Où en est le projet, ce qui reste à faire | [PLAN_FINALISATION.md](PLAN_FINALISATION.md) |

## 1. Ce qu'il faut installer

| Outil | Version | Pour quoi | Vérifier (PowerShell, macOS ou Linux) |
|---|---|---|---|
| Git | récent | récupérer le code | `git --version` |
| PHP | **8.3** (le projet exige `^8.3`) | le site | `php -v` |
| Extensions PHP | `pdo_sqlite`, `sqlite3`, `pdo_mysql`, `intl`, `gd`, `zip`, `mbstring`, `curl`, `openssl`, `xml`, `dom`, `fileinfo`, `tokenizer`, `ctype`, `sodium` | le site | `php -m` |
| Composer | 2.x | bibliothèques PHP | `composer -V` |
| Node.js et npm | **22** (20 au minimum) | compilation du site | `node -v` et `npm -v` |
| MySQL 8 ou MariaDB 10.6+ | facultatif | seulement si vous ne voulez pas SQLite (§4.2) | `mysql --version` |
| Flutter | **3.47.8**, canal stable (Dart 3.13) | application mobile (facultatif) | `flutter --version` |
| Android Studio | dernière version stable (JDK 17 inclus) | émulateur et SDK Android (facultatif) | `flutter doctor -v` |
| Google Chrome | récent | navigateur de recette (touche F12 pour les erreurs) | |

Pour le site seul, Flutter et Android Studio ne servent pas.

**Windows (non vérifié)** : PHP s'obtient sur <https://windows.php.net/download> (version 8.3, « Thread Safe » x64), ou avec un paquet tout-en-un comme Laragon ou XAMPP (contrôlez que PHP y est bien en 8.3). Décompressez PHP, ajoutez son dossier à la variable d'environnement `Path`, puis ouvrez un **nouveau** PowerShell. Pour activer une extension manquante : copiez `php.ini-development` en `php.ini`, ouvrez-le, retirez le `;` au début de la ligne `extension=pdo_mysql` (idem pour les autres de la liste), enregistrez, et relancez PowerShell. Composer : installateur sur <https://getcomposer.org/download/>. Node : installateur LTS sur <https://nodejs.org/>.

**macOS (non vérifié)** : avec Homebrew, `brew install php@8.3 composer node git` (puis `brew link php@8.3` si besoin). **Linux (Debian, Ubuntu)** : `sudo apt install git php8.3-cli php8.3-sqlite3 php8.3-mysql php8.3-intl php8.3-gd php8.3-zip php8.3-mbstring php8.3-curl php8.3-xml composer`, et Node 22 depuis <https://nodejs.org/> ou `nvm`. Les extensions `ctype`, `fileinfo`, `tokenizer`, `openssl`, `sodium` sont normalement incluses.

Vérification rapide des extensions (doit afficher les 15 noms) :

```powershell
php -m | Select-String -Pattern "pdo_sqlite|sqlite3|pdo_mysql|intl|gd|zip|mbstring|curl|openssl|xml|dom|fileinfo|tokenizer|ctype|sodium"
```

macOS et Linux : `php -m | grep -E "pdo_sqlite|sqlite3|pdo_mysql|intl|gd|zip|mbstring|curl|openssl|xml|dom|fileinfo|tokenizer|ctype|sodium"`.

## 2. Récupérer le code

La branche à utiliser s'appelle `claude/friendly-darwin-abyzn0`. Choisissez un dossier **court, sans espace ni accent** (par exemple `C:\dev`).

```powershell
cd C:\dev
git config --global core.longpaths true
git clone --branch claude/friendly-darwin-abyzn0 https://github.com/massenon/xomiton.git
cd xomiton
git branch --show-current
```

La dernière commande doit afficher `claude/friendly-darwin-abyzn0`. Pour récupérer plus tard les nouveautés : `git pull`.

macOS et Linux : mêmes commandes, sans la ligne `core.longpaths`, par exemple depuis `~/dev`.

Le dépôt contient : `web/` (le site, Laravel), `mobile/` (l'application, Flutter), `docs/` (la documentation), `scripts/` (aides pour la recette).

## 3. Le site : installation

Toutes les commandes de cette partie se lancent dans le dossier `web`.

```powershell
cd C:\dev\xomiton\web
composer install
npm ci
```

`composer install` et `npm ci` téléchargent les bibliothèques : il faut Internet, et compter quelques minutes. **Composer peut afficher des avertissements** du type « Class … does not comply with psr-4 autoloading standard … Skipping » : ils concernent des classes de test et sont **sans gravité** (voir §10).

### 3.1 Le fichier de configuration `.env`

Le fichier `.env.example` est déjà réglé pour la recette locale (base SQLite, journaux à la place des envois réels). Copiez-le :

```powershell
Copy-Item .env.example .env
```

macOS et Linux : `cp .env.example .env`.

N'ouvrez et ne modifiez `.env` que si vous changez de base (§4.2). **Ne le partagez jamais et ne l'envoyez jamais sur Git** : en production il contiendra des mots de passe.

### 3.2 Clé, base de démonstration, site compilé

```powershell
php artisan key:generate
if (-not (Test-Path database\recette.sqlite)) { New-Item -ItemType File database\recette.sqlite | Out-Null }
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
```

macOS et Linux : remplacez la deuxième ligne par `touch database/recette.sqlite`.

- `key:generate` crée la clé de chiffrement locale. Faites-le **une seule fois** (le refaire rend illisibles les données déjà chiffrées de cette base ; en local il suffit alors de refaire `migrate:fresh --seed`).
- `migrate:fresh --seed` crée toutes les tables et les données de démonstration (environ 5 secondes). **Il efface la base de recette** à chaque fois : c'est voulu en local.
- `storage:link` rend les images téléversées visibles. Sous Windows, si la commande échoue avec une erreur de lien symbolique, activez le « Mode développeur » (Paramètres › Confidentialité et sécurité › Pour les développeurs) ou lancez PowerShell « en tant qu'administrateur », puis recommencez **(non vérifié)**.
- `npm run build` compile le site (environ 30 secondes) et se termine par un contrôle de poids des fichiers.

Alternative automatique sous Linux, macOS, WSL ou Git Bash : `scripts/recette-local.sh` fait tout ce qui précède (voir [GUIDE_RECETTE.md](GUIDE_RECETTE.md) §3.1). Sous PowerShell pur, restez sur les commandes ci-dessus.

### 3.3 Lancer le site

Dans le dossier `web`, **ouvrez trois fenêtres PowerShell** et lancez une commande dans chacune :

| Fenêtre | Commande | Rôle |
|---|---|---|
| 1 | `php artisan serve` | le site, sur <http://localhost:8000> |
| 2 | `php artisan schedule:work` | le **planificateur** : joue chaque minute les tâches automatiques (escalade des SOS sans réponse, renvoi des notifications, vidage de la boîte d'envoi, newsletter). Il remplace le « cron » du serveur. |
| 3 | `php artisan queue:work` | la **file d'attente** : traite les envois différés (exports de données, e-mails en file) |

Ouvrez ensuite **<http://localhost:8000>** dans Chrome. Utilisez **exactement** cette adresse (voir §10 : `127.0.0.1` ne convient pas). Pour arrêter : `Ctrl+C` dans chaque fenêtre.

Après toute modification de `.env`, arrêtez puis relancez `php artisan serve` : il ne relit pas le fichier à chaud. Vérification : `curl.exe -s -o NUL -w "%{http_code}" http://localhost:8000/up` doit afficher `200` (macOS et Linux : `curl -s -o /dev/null -w '%{http_code}\n' localhost:8000/up`).

Sans planificateur ni file, le site s'ouvre mais l'escalade d'un SOS après 5 minutes et certains envois ne se produisent pas : pour tester ces points, laissez les fenêtres 2 et 3 ouvertes.

## 4. Choisir la base de données

### 4.1 SQLite (par défaut, recommandé pour la recette)

Rien à faire : le §3.2 l'a créée dans `web\database\recette.sqlite` (fichier ignoré par Git). C'est la configuration utilisée pour les tests automatiques.

### 4.2 MySQL ou MariaDB (si vous voulez être proche de la production)

1. Créez une base vide et un utilisateur (client `mysql`, phpMyAdmin, ou l'outil de votre paquet Laragon/XAMPP) :

   ```sql
   CREATE DATABASE xomiton CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'xomiton'@'localhost' IDENTIFIED BY 'UN_MOT_DE_PASSE_LOCAL';
   GRANT ALL PRIVILEGES ON xomiton.* TO 'xomiton'@'localhost';
   ```

   (Choisissez votre propre mot de passe ; `UN_MOT_DE_PASSE_LOCAL` n'est qu'un repère à remplacer.)

2. Dans `web\.env`, remplacez les lignes de base de données par :

   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=xomiton
   DB_USERNAME=xomiton
   DB_PASSWORD=le_mot_de_passe_choisi
   ```

   Gardez `APP_URL=http://localhost:8000`.

3. Puis :

   ```powershell
   php artisan config:clear
   php artisan migrate:fresh --seed
   php artisan tinker --execute="echo App\Models\User::count();"
   ```

   La migration doit finir **sans ligne rouge** (le dernier seeder, `DemoNewsSeeder`, s'affiche `DONE`) et la dernière commande affiche `8` (les 8 comptes du §5).

Vérifié : MariaDB 10.11. **MySQL 8 : jamais exécuté** (voir [PLAN_FINALISATION.md](PLAN_FINALISATION.md)). Si vous l'essayez, signalez tout écart.

## 5. Comptes de démonstration

Ils n'existent qu'en environnement local, de test ou de préproduction : **jamais en production** (garde-fou dans le code). Le champ « Identifiant » de la page de connexion (`/connexion`) accepte l'e-mail ou le pseudo. Source : `web/database/seeders/DemoAccountsSeeder.php`.

| Rôle | E-mail | Pseudo | Mot de passe |
|---|---|---|---|
| Super-administrateur·rice (et membre) | `admin@xomiton.bj` | `admin` | `Admin123!` |
| Administrateur·rice (et membre) | `moderation@xomiton.bj` | `moderatrice` | `Admin123!` |
| Modérateur·rice (et membre) | `moderateur@xomiton.bj` | `modo_rose` | `Modo123!` |
| Membre | `membre@xomiton.bj` | `awa_s` | `Membre123!` |
| Membre (second compte, pour les tests à deux) | `membre2@xomiton.bj` | `fatou_k` | `Membre123!` |
| Pair éducateur·rice (et membre) | `pair@xomiton.bj` | `pair_chantal` | `Pair123!` |
| Pair en attente de validation (membre) | `pair.attente@xomiton.bj` | `pair_en_attente` | `Pair123!` |
| Admin + pair + membre (les trois espaces) | `equipe@xomiton.bj` | `equipe_xom` | `Equipe123!` |

Ces mots de passe sont publics et ne servent qu'en local. Ce que chaque compte a le droit de voir : [ROLE_ET_PERMISSIONS.md](ROLE_ET_PERMISSIONS.md) §3 et [GUIDE_RECETTE.md](GUIDE_RECETTE.md) §5.

### Le code de vérification (OTP)

La première connexion d'un compte sur un navigateur ou un appareil nouveau demande un **code à 6 chiffres**, et les comptes admin en demandent un à chaque connexion. En local, **aucun e-mail ne part** : après le mot de passe, la page `/code-de-verification` s'affiche, et le code se lit dans le journal, sur une ligne `[otp:log]`.

```powershell
Select-String -Path storage\logs\laravel.log -Pattern "\[otp:log\]" | Select-Object -Last 1
```

macOS et Linux : `grep -a "\[otp:log\]" storage/logs/laravel.log | tail -1`.

Résultat attendu (le code change à chaque fois) : `[otp:log] Code 386776 pour admin@xomiton.bj (login), valable 10 min.` Saisissez le code sur la page : il est valable 10 minutes. Le sujet de l'e-mail ne contient volontairement **jamais** le code : ne le cherchez pas là. Pour suivre le journal en direct : `Get-Content storage\logs\laravel.log -Wait -Tail 20` (macOS et Linux : `tail -f storage/logs/laravel.log`).

## 6. Lire les e-mails, SMS et notifications simulés

Tout est dans `web\storage\logs\laravel.log`. Pour les repères (e-mails, SMS `[sms:log]`, push `fcm:log`, trace d'un SOS), voir [GUIDE_RECETTE.md](GUIDE_RECETTE.md) §7. La boîte d'envoi (confirmation de newsletter, export de données) se vide avec `php artisan mail:flush-outbox`, ou automatiquement par le planificateur.

## 7. L'application mobile (facultatif)

Tout se passe dans le dossier `mobile`. Pour installer Flutter et Android Studio sous Windows (SDK, licences, JDK, émulateur), suivez [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) §2 : ce guide n'en recopie pas le détail.

```powershell
cd C:\dev\xomiton\mobile
flutter doctor -v
flutter pub get
flutter analyze
flutter test
```

`flutter analyze` doit répondre « No issues found » et `flutter test` « All tests passed » (454 tests). S'il y a un échec ici, ne continuez pas : le problème vient de l'installation de Flutter.

### 7.1 Sur un émulateur Android

1. Lancez le site (§3.3, fenêtres 1 à 3).
2. Android Studio › Device Manager › créez un « Pixel 6 » en image système API 34 « Google Play » et démarrez-le.
3. Dans `mobile` : `flutter devices` puis `flutter run`.

Sur l'émulateur, l'adresse `10.0.2.2` désigne **votre ordinateur** : l'application utilise par défaut `http://10.0.2.2:8000/api/v1`, sans rien régler. Pour une autre adresse :

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

Le suffixe `/api/v1` est obligatoire. Le trafic non chiffré (`http`) n'est accepté qu'en mode debug. Connectez-vous avec un compte du §5 ; le code OTP se lit dans le journal du site (§5).

### 7.2 Installer l'application sur un téléphone

1. Sur le téléphone Android 8 ou plus récent : Paramètres › À propos du téléphone, touchez 7 fois « Numéro de build », puis Options pour les développeurs › Débogage USB.
2. Branchez-le en USB et acceptez « Autoriser le débogage USB ». `adb devices` doit lister l'appareil comme `device` (pas `unauthorized`).
3. Deux façons d'atteindre le site de votre ordinateur depuis le téléphone :
   - **USB (le plus simple)** : `adb reverse tcp:8000 tcp:8000`, puis `flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1` ;
   - **Wi-Fi** : lancez le site en écoute large (`php artisan serve --host=0.0.0.0`), ouvrez le port 8000 dans le pare-feu, puis `flutter run --dart-define=API_BASE_URL=http://192.168.X.Y:8000/api/v1` (`192.168.X.Y` = adresse de votre ordinateur, donnée par `ipconfig`).
4. Pour obtenir un **fichier APK de test** à envoyer sur un téléphone :

   ```powershell
   flutter build apk --debug
   ```

   Fichier produit : `mobile\build\app\outputs\flutter-apk\app-debug.apk` (gros, plus de 100 Mo : c'est normal en debug). Installation : `adb install -r build\app\outputs\flutter-apk\app-debug.apk`, ou envoi du fichier au téléphone puis ouverture (autoriser les « sources inconnues »). **(non vérifié : aucun build Android n'a encore été lancé, voir [PLAN_FINALISATION.md](PLAN_FINALISATION.md))**

   Cet APK de debug sert à tester, pas à publier. Le paquet de publication (AAB signé) est décrit dans [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) §7 et §8, et le dépôt sur Google Play dans [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md).

### 7.3 Notifications push et `google-services.json`

Sans Firebase, l'application fonctionne mais **sans notification push** : c'est normal en local. Pour les activer, il faut un projet Firebase (compte du client), puis déposer le fichier `google-services.json` dans `mobile\android\app\`. Nom de paquet à déclarer dans Firebase : `bj.xomiton.xomiton`. Ce fichier est personnel et **n'est jamais envoyé sur Git**. Pas à pas : [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) §10 ; côté serveur : [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §7. Plan de l'application unique (membre, pair, admin) : [PLAN_MOBILE_APP_UNIQUE.md](PLAN_MOBILE_APP_UNIQUE.md).

## 8. Lancer les tests (facultatif, pour l'équipe technique)

| Suite | Commande (dossier) | Nombre indicatif |
|---|---|---|
| PHP (Pest) | `vendor\bin\pest` (`web`) ; macOS et Linux `vendor/bin/pest` | ≈ 2 989 tests |
| Interface Vue (Vitest) | `npm test` (`web`) | 784 tests |
| Mobile (Flutter) | `flutter test` (`mobile`) | 454 tests |

Particularités : Pest a besoin que `npm run build` ait été exécuté une fois (il lit `public/build/manifest.json`). Il utilise sa propre base SQLite en mémoire : il n'abîme **pas** votre base de recette. Pour un seul fichier : `vendor\bin\pest tests\Feature\Public\LegalPagesTest.php`. Contrôles de qualité en plus : `vendor\bin\pint --test` (style), `vendor\bin\phpstan analyse` (analyse statique), `npm run typecheck`.

## 9. Où vérifier que tout va bien

- Le site répond sur <http://localhost:8000> et `/up` renvoie `200`.
- La connexion avec `membre@xomiton.bj` arrive sur le fil, après saisie du code lu dans le journal.
- `php artisan xomiton:preflight --skip-cron` affiche un bilan : en local il signale **normalement** des avertissements (pilotes « log ») et deux échecs (`APP_URL` non https, cookie non sécurisé). Ce n'est pas une anomalie en local ; en production il doit y avoir zéro échec.
- Ensuite, suivez la [checklist de recette](CHECKLIST_RECETTE.md).

## 10. Dépannage

| Symptôme | Cause et remède |
|---|---|
| Page `/connexion` en erreur 500 « **Vite manifest not found** » | Le site n'est pas compilé. Dans `web` : `npm ci` puis `npm run build` (le dossier `public/build` n'est pas dans Git). Si vous aviez lancé `npm run dev` avant, supprimez `public/hot`. |
| `php artisan migrate:fresh --seed` s'arrête avec « **Identifier name … is too long** » ou une erreur d'index MySQL de plus de **64** caractères | MySQL et MariaDB limitent les noms d'index à 64 caractères (SQLite non). La base reste à moitié vide : aucun compte, aucune connexion possible. Récupérez le code à jour (`git pull`), car la migration `2026_10_18_080200_create_indicator_values_table.php` a été corrigée, puis relancez `php artisan migrate:fresh --seed`. Si une autre migration pose le même problème, transmettez le message complet. |
| Je ne trouve pas le **code OTP** | Il n'y a pas d'e-mail réel. Lisez la dernière ligne `[otp:log]` du journal (§5). Vérifiez que `APP_ENV=local` dans `.env` (la ligne n'est écrite qu'en local) et que vous cherchez dans `web\storage\logs\laravel.log`. Si le journal n'existe pas encore, relancez la connexion. Trop d'essais : « 429 Trop de tentatives », attendez une minute. Pour repartir de zéro : `php artisan tinker --execute="App\Models\OtpCode::query()->delete();"` puis `php artisan cache:clear`. |
| « **could not find driver** » ou « Call to undefined function … » | Une extension PHP manque (`pdo_sqlite`, `pdo_mysql`, `intl`, `gd`, `zip`, `mbstring`…). Contrôlez avec `php -m`. Windows : retirez le `;` devant la ligne `extension=…` dans `php.ini` (`php --ini` indique son emplacement), relancez PowerShell. Linux : installez le paquet `php8.3-…` correspondant. |
| « Your PHP version … does not satisfy that requirement » | PHP trop ancien. Installez PHP 8.3 et vérifiez `php -v` dans un **nouveau** terminal (un autre PHP plus ancien peut passer avant dans le `Path`). |
| Images et avatars absents, erreurs « Content Security Policy » dans la console, ou connexion qui « oublie » le cookie | Vous avez ouvert <http://127.0.0.1:8000> au lieu de <http://localhost:8000>. Le cookie de session et la politique de sécurité sont liés à `APP_URL` : ouvrez **exactement** `APP_URL` (`localhost`). Autre port : mettez-le dans `APP_URL` et dans `SANCTUM_STATEFUL_DOMAINS`, puis relancez `php artisan serve`. |
| Page blanche ou non stylée | Compilation oubliée (`npm run build`) ou fichier `public/hot` présent : supprimez-le. |
| **Avertissements Composer « does not comply with psr-4 autoloading standard … Skipping »** | **Sans gravité** : ce sont de petites classes déclarées dans des fichiers de test (`tests/…`). Composer les ignore ; le site et les tests fonctionnent. Aucune action. |
| `composer install` ou `npm ci` échoue | Vérifiez Internet, le proxy de l'entreprise et la version de Node. Pour `npm ci`, supprimez `node_modules` et recommencez. |
| Le port 8000 est déjà pris | `php artisan serve --port=8001`, puis `APP_URL=http://localhost:8001` dans `.env` (et `localhost:8001` dans `SANCTUM_STATEFUL_DOMAINS`), et ouvrez cette adresse. |
| Pas d'e-mail de confirmation de newsletter | `php artisan mail:flush-outbox` (ou laissez tourner le planificateur). |
| L'application mobile affiche « Connexion impossible » | Le site n'est pas lancé, ou l'adresse est mauvaise : émulateur = `http://10.0.2.2:8000/api/v1` ; téléphone en USB = `adb reverse tcp:8000 tcp:8000` puis `http://127.0.0.1:8000/api/v1`. Testez l'adresse du site dans le navigateur du téléphone. |
| `flutter` n'est pas reconnu / `flutter doctor` en rouge | Dossier `flutter\bin` absent du `Path` ou terminal non rouvert ; SDK, licences, JDK : tableau de dépannage de [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) §12. |
| `/sitemap.xml` répond 404 | Normal : l'indexation est interdite hors production. |

Pour signaler une anomalie, utilisez le gabarit de [GUIDE_RECETTE.md](GUIDE_RECETTE.md) §11, sans mot de passe réel ni donnée de personne réelle.
