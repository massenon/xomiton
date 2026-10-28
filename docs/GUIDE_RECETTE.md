# Guide de recette : lancer Xomiton sur votre ordinateur et le tester à la main

Ce guide s'adresse à la personne qui réceptionne Xomiton : aucune connaissance technique n'est requise, il suffit de recopier les commandes. Les parcours à vérifier se trouvent dans la **[checklist de recette](CHECKLIST_RECETTE.md)**, à cocher au fur et à mesure.

Les commandes de ce guide ont été exécutées le 28/10/2026 (PHP 8.3.6, Node 22, base SQLite de démonstration, sans accès à Internet). Ce qui n'a pas pu être vérifié est marqué **(non vérifié)**.

Repères : `xomiton/` est le dossier du projet, `web/` le site (Laravel), `mobile/` l'application (Flutter).

## 1. En bref

```bash
cd xomiton
scripts/recette-local.sh        # installe, crée la base de démonstration, compile le site (≈ 5 min la première fois)
scripts/recette-lancer.sh       # démarre le site, le planificateur et la file (Ctrl+C pour tout arrêter)
```

Ouvrez ensuite **http://localhost:8000** (exactement cette adresse) et connectez-vous avec un compte du §5. Les e-mails, SMS et notifications ne partent pas vraiment : ils sont écrits dans un fichier journal (§6).

## 2. Ce qu'il faut installer d'abord

| Outil | Version | Pour quoi | Vérifier |
|---|---|---|---|
| PHP | **8.3** | le site | `php -v` |
| Extensions PHP | `pdo_sqlite`, `sqlite3`, `intl`, `gd`, `zip`, `mbstring`, `curl`, `openssl`, `xml`, `dom`, `fileinfo`, `tokenizer`, `ctype` | le site | `php -m` |
| Composer | 2.x | dépendances PHP | `composer -V` |
| Node.js + npm | **22** (20 minimum) | compilation du site | `node -v` |
| Google Chrome | récent | navigateur de recette conseillé (outils de développement F12) | |
| Flutter SDK | **3.47.8** stable (Dart 3.13) | application mobile (facultatif) | `flutter --version` |
| Android Studio + émulateur, ou un téléphone Android 8+ | | lancer l'application mobile (facultatif) | `flutter doctor` |

Pour la partie web seule, Flutter et Android ne sont pas nécessaires. Sous Windows, utilisez WSL (Ubuntu) ou Git Bash pour les scripts `.sh`.

## 3. Installation pas à pas

### 3.1 Méthode automatique (recommandée)

```bash
cd xomiton
scripts/recette-local.sh
```

Le script, rejouable à volonté : vérifie les prérequis, lance `composer install` et `npm ci`, crée `web/.env` depuis `web/.env.example` s'il n'existe pas (sans jamais modifier un `.env` existant), génère la clé, **crée la base de recette** `web/database/recette.sqlite` avec les données de démonstration, compile le site, puis affiche les adresses et les comptes.

Options : `--yes` (aucune question), `--no-reset` (conserve une base existante et la met à jour sans rien effacer), `--skip-install`, `--skip-build`, `--db CHEMIN`. **Si une base existe déjà, le script demande confirmation avant de l'effacer** (`--yes` répond oui d'avance).

### 3.2 Méthode manuelle (équivalente)

```bash
cd xomiton/web
composer install
npm ci
cp .env.example .env                  # déjà réglé pour la recette locale : SQLite, journaux, rien d'externe
php artisan key:generate
touch database/recette.sqlite
php artisan migrate:fresh --seed      # ≈ 5 s ; ATTENTION : efface la base de recette
php artisan storage:link
npm run build                         # ≈ 30 s ; se termine par le contrôle des budgets (« ok »)
```

## 4. Lancement

### 4.1 Tout en un

```bash
cd xomiton
scripts/recette-lancer.sh             # options : --port 8001, --host 0.0.0.0, --no-queue, --no-schedule
```

Trois processus démarrent, leurs messages portent un préfixe `[site]`, `[planif]`, `[file]`. **Ctrl+C** les arrête proprement. Le script refuse de démarrer si le port est déjà pris. Vérification : `curl -s -o /dev/null -w '%{http_code}\n' localhost:8000/up` affiche `200`.

### 4.2 À la main (un terminal par ligne), depuis `xomiton/web`

| Terminal | Commande | Rôle |
|---|---|---|
| 1 | `php artisan serve` | le site, sur http://localhost:8000 |
| 2 | `php artisan schedule:work` | planificateur « cron » simulé : chaque minute `alerts:escalate` (escalade des SOS), `notifications:retry`, `mail:flush-outbox`, `newsletter:process`, `gateways:publish` ; toutes les 5 min `polls:close` ; liste : `php artisan schedule:list` |
| 3 | `php artisan queue:work` | traite la file (exports de données, envois en file). Variante ponctuelle : `queue:work --stop-when-empty` |
| 4 | `tail -f storage/logs/laravel.log` | e-mails, SMS, push, codes OTP (§6) |

Après toute modification de `.env`, **redémarrez** `php artisan serve` : il ne relit pas `.env` à chaud.

### 4.3 Compiler le site : `build` plutôt que `dev`

Pour la recette, utilisez `npm run build` (déjà fait par le script). `npm run dev` fonctionne mais le mode hors ligne (service worker) n'y est **pas** enregistré, et il faut supprimer `public/hot` pour revenir au build. Si vous modifiez du JavaScript, relancez le build.

### 4.4 Application mobile

```bash
cd xomiton/mobile
flutter pub get
flutter analyze && flutter test       # 151 tests, vérifiés le 27/10/2026
flutter run                           # émulateur Android (API http://10.0.2.2:8000/api/v1 par défaut)
```

L'adresse de l'API se règle avec `--dart-define=API_BASE_URL=<racine>/api/v1` (le suffixe **`/api/v1` est obligatoire**) :

- **Émulateur Android** : `flutter run` suffit (`10.0.2.2` désigne votre ordinateur). Le trafic http en clair n'est accepté qu'en debug.
- **Téléphone réel** (même Wi-Fi, pare-feu ouvert) : `scripts/recette-lancer.sh --host 0.0.0.0`, puis `flutter run --dart-define=API_BASE_URL=http://192.168.X.Y:8000/api/v1` (192.168.X.Y = adresse de votre ordinateur).
- **Release** : `flutter build apk --release --dart-define=API_BASE_URL=https://votre-domaine/api/v1` ; en release, une URL non https empêche le démarrage.
- **`flutter run -d chrome` n'est pas prévu** : le projet n'a pas de dossier `web/` Flutter et le serveur n'autorise pas les appels d'un autre site (CORS). Utilisez un émulateur Android ou un téléphone.
- Les codes de vérification de l'application arrivent dans le même fichier journal que ceux du site (§6).

Seuls `pub get`, `analyze` et `test` ont été exécutés ; le lancement sur appareil est **(non vérifié)** faute de SDK Android dans l'environnement de préparation.

## 5. Comptes de démonstration

Créés uniquement en environnement `local`, `testing` ou `staging` (jamais en production : garde-fou dans `DatabaseSeeder` et test automatisé). Le champ « identifiant » de `/connexion` accepte l'e-mail ou le pseudo. La page de connexion propose aussi trois boutons de remplissage automatique.

| Rôle | Identifiant | Pseudo | Mot de passe | Accès attendu |
|---|---|---|---|---|
| Super-administrateur·rice | `admin@xomiton.bj` | `admin` | `Admin123!` | `/admin` complet, espace membre |
| Administrateur·rice | `moderation@xomiton.bj` | `moderatrice` | `Admin123!` | `/admin` (sans les droits réservés au super-admin) |
| Modérateur·rice | `moderateur@xomiton.bj` | `modo_rose` | `Modo123!` | arrive sur `/admin` : modération, utilisateurs (suspension) ; l'espace membre reste accessible ; `/espace-pair` refusé (403) |
| Membre | `membre@xomiton.bj` | `awa_s` | `Membre123!` | espace membre ; `/admin`, `/espace-pair`, `/collecte` refusés (403) |
| Membre (second compte) | `membre2@xomiton.bj` | `fatou_k` | `Membre123!` | pour les tests à deux : messages, groupes, événements |
| Pair éducateur·rice | `pair@xomiton.bj` | `pair_chantal` | `Pair123!` | espace membre, `/espace-pair`, `/collecte` ; `/admin` refusé |
| Pair en attente de validation | `pair.attente@xomiton.bj` | `pair_en_attente` | `Pair123!` | se connecte avec un bandeau « en attente » ; à valider dans Administration › Utilisateurs (règle RG-03) |

Les permissions détaillées de chaque rôle sont dans [ROLE_ET_PERMISSIONS.md](ROLE_ET_PERMISSIONS.md) §3.

**Page d'arrivée après la connexion.** Super-admin, administrateur·rice et modérateur·rice : `/admin`. Membre, second membre, pair éducateur·rice et pair en attente : `/fil` (le pair ouvre ensuite `/espace-pair` depuis le menu ; le pair en attente voit un bandeau « en attente » et `/espace-pair` lui est refusé tant qu'il n'est pas validé). Ces parcours sont vérifiés par `tests/Feature/Auth/Web/DemoAccountsLoginTest.php` et ont été rejoués par de vraies requêtes HTTP (cookies, jeton CSRF, redirections) sur MariaDB 10.11, avec `localhost` et `127.0.0.1`.

**Code de vérification (OTP) en mode journal.** Une connexion depuis un appareil ou un navigateur inconnu exige un code à 6 chiffres (donc **la toute première connexion de chaque compte, membre compris**), et les comptes admin et super-admin en exigent un à **chaque** connexion. Aucun e-mail ne part : l'écran vous envoie sur `/code-de-verification` et le code se lit dans le journal `web/storage/logs/laravel.log`. Le sujet de l'e-mail ne contient volontairement jamais le code (« Ton code de vérification Xomiton ») : ne cherchez pas le code dans le sujet. Chaque émission écrit une ligne dédiée `[otp:log]` (environnement `local` seulement) :

```bash
grep -a "\[otp:log\]" web/storage/logs/laravel.log | tail -1
# [otp:log] Code 386776 pour admin@xomiton.bj (login), valable 10 min.
```

Sous Windows (PowerShell) : `Select-String "\[otp:log\]" web\storage\logs\laravel.log | Select-Object -Last 1`. Le code figure aussi dans le corps HTML de l'e-mail consigné (bloc en gros chiffres espacés).

Saisissez-le sur `/code-de-verification` (le dernier chiffre valide tout seul). Il est valable 10 minutes. Astuce : une fenêtre de navigation privée par rôle. Limites volontaires : 5 connexions par minute et par adresse IP, 5 codes par heure et par adresse, 5 essais par code. Après une série d'essais, pour repartir de zéro : `php artisan tinker --execute='App\Models\OtpCode::query()->delete();'` puis `php artisan cache:clear`.

**Avec MySQL ou MariaDB au lieu de SQLite** (par exemple XAMPP, Laragon, WAMP). Créez une base vide en `utf8mb4_unicode_ci`, puis dans `web/.env` : `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=xomiton`, `DB_USERNAME=…`, `DB_PASSWORD=…`. Gardez `APP_URL=http://localhost:8000` et ouvrez exactement cette adresse. Relancez `php artisan config:clear`, puis `php artisan migrate:fresh --seed` : **la commande doit finir sans ligne rouge et le dernier seeder (`DemoNewsSeeder`) doit s'afficher `DONE`**. Vérification : `php artisan tinker --execute='echo App\Models\User::count();'` affiche `7`. Si `migrate:fresh` s'arrête sur « Identifier name … is too long », la base est restée à moitié vide : aucun compte n'existe et aucune connexion ne peut réussir (corrigé dans `2026_10_18_080200_create_indicator_values_table.php`).

**Si la page `/connexion` affiche une erreur 500 « Vite manifest not found »** : le site n'est pas compilé. Lancez `npm ci` puis `npm run build` dans `web/` (le dossier `web/public/build` n'est pas versionné).

## 6. Données de démonstration

Après `migrate:fresh --seed` : 7 comptes (§5), 12 zones et 29 communes du Bénin, 18 ressources (dont les actualités), 29 thématiques, 28 questions d'aide, 5 forums, 12 modèles d'e-mails, 38 réglages. **Le fil, les groupes, les événements, les messages, les sujets de forum et l'annuaire sont vides** : vous les créez pendant la recette. Pour tout recommencer : `scripts/recette-local.sh` (avec confirmation) ou `php artisan migrate:fresh --seed`. Aucune donnée réelle, aucun appel à un service externe.

## 7. Où lire les e-mails, SMS et notifications (mode journal)

Tout est dans **`web/storage/logs/laravel.log`** (`tail -f` pour suivre en direct).

| Quoi | Comment le retrouver |
|---|---|
| Code OTP | `grep -a "\[otp:log\]" web/storage/logs/laravel.log \| tail -1` (§5) |
| E-mails | lignes `To: …` et `Subject: …`. Les sujets accentués sont encodés (`=?utf-8?Q?…`) ; les liens longs sont coupés par `=` en fin de ligne : recollez-les |
| SMS de secours du SOS | `grep -a "sms:log" …` : `[sms:log] SMS consigné (aucun envoi)` (numéro masqué) |
| Notifications push | `grep -a "webpush:log\|fcm:log" …` : `Notification push consignée (aucun envoi)` |
| Trace d'un SOS | `grep -a "alerts\." …` : `alerts.sos_created`, `alerts.dispatched … sos_triggered`, et après 5 minutes sans accusé `sos_unacknowledged` (le planificateur doit tourner) |
| Une table précise | `php artisan tinker --execute='echo App\Models\Alert::count();'` |

**Boîte d'envoi.** La confirmation de newsletter, le test de campagne et l'export de données passent par une boîte d'envoi : `php artisan mail:flush-outbox` (ou le planificateur) la vide dans le journal. `MAIL_MAILER=failover`, réglage par défaut, tente le SMTP puis retombe sur le journal.

## 8. Tester les canaux réels (désactivés par défaut)

Tout est éteint par défaut (`log`). N'activez un canal que pour un essai ponctuel, avec vos propres clés, dans `web/.env` ; **ne commitez jamais `.env`**. Redémarrez le serveur après modification. Les commandes d'essai n'envoient qu'un texte fixe, sans donnée réelle, et demandent confirmation (sauf `--yes`).

| Canal | Variables `.env` à renseigner | Commande d'essai |
|---|---|---|
| Push mobile (Firebase FCM, API HTTP v1) | `FCM_DRIVER=fcm`, `FIREBASE_CREDENTIALS=` chemin **absolu** du JSON du compte de service (hors `public/`), `FIREBASE_PROJECT_ID=` (facultatif) | `php artisan notifications:test-push <jeton FCM de l'appareil> --urgent --yes` |
| SMS Vonage | `SMS_DRIVER=vonage`, `VONAGE_API_KEY=`, `VONAGE_API_SECRET=`, `SMS_SENDER=` | `php artisan notifications:test-sms +229XXXXXXXX --yes` (facturé par le fournisseur) |
| SMS Twilio | `SMS_DRIVER=twilio`, `TWILIO_ACCOUNT_SID=`, `TWILIO_AUTH_TOKEN=`, `TWILIO_MESSAGING_SERVICE_SID=` (facultatif) | idem |
| Assistant Gemini | `ASSISTANT_PROVIDER=gemini`, `ASSISTANT_LLM_ENABLED=true`, `GEMINI_API_KEY=` | `php artisan assistant:test-llm --yes` |
| E-mail réel | `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` (ou Administration › Paramètres › Messagerie) | Administration › Templates e-mails › essai d'envoi |

Résultat attendu sans clé, vérifié : `notifications:test-sms` et `notifications:test-push` répondent « Pilote … : log » puis « Envoi accepté par le pilote » (rien ne part, une ligne apparaît dans le journal) ; `assistant:test-llm` répond que `ASSISTANT_LLM_ENABLED` est faux et n'appelle rien. Avec les clés, l'appareil ou le téléphone doit recevoir le message dans la minute.

À savoir : **Gemini ne doit pas être activé en production avant l'avis juridique** (données de santé et de violences envoyées à un tiers : [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md), Q7) ; les messages de crise et de SOS ne lui sont jamais envoyés. Le push navigateur (Web Push VAPID) demande en plus `composer require minishlink/web-push` et des clés VAPID (non livrés). La commande `php artisan xomiton:preflight --skip-cron` résume l'état des canaux ; en local elle signale normalement des avertissements (pilotes `log`) et deux échecs (`APP_URL` non https, cookie non sécurisé) sans gravité hors production.

## 9. Fonctions particulières à connaître avant de tester

### Mode maintenance
Administration › **Maintenance** : « Activer la maintenance », motif interne, durée, case de confirmation. En ligne de commande : `php artisan xomiton:maintenance on --minutes=30 --message="…" --reason="…"`, `off --reason="…"`, `status` (n'utilisez jamais `artisan down`). Pour les visiteur·ses : restent ouverts `/sos`, `/signaler`, `/suivi`, `/urgences`, `/connexion`, `/newsletter`, `/up` ; le reste affiche la page « Maintenance en cours » (503). Les membres sont déconnectés ; l'administration garde l'accès. **N'oubliez pas de désactiver la maintenance** après l'essai.

### Hors ligne et synchronisation
Le service worker (build uniquement) met en cache les pages d'urgence et la bibliothèque. Test : ouvrez `/urgences`, puis dans F12 › Réseau choisissez **Hors connexion** ; `/collecte` (compte pair) enregistre alors la fiche dans l'appareil (« Hors ligne · 1 fiche en attente ») et la transmet au retour du réseau. `/signaler` se comporte de même. Mobile : file Hive, rejouée au retour du réseau.

### SOS : maintien, décompte, attente de la position
Sur `/sos` : maintenir le bouton **2 secondes**, puis décompte annulable de **5 secondes**. Le navigateur attend la position GPS **8 secondes au total** ; passé ce délai, ou si vous refusez le partage, l'alerte part **sans position** (décision Q61). Le bouton rond flottant des pages connectées envoie immédiatement, sans position. Aucun SMS ni push réel en mode journal.

### Inscription : case d'âge minimal
`/inscription` comporte une case obligatoire de confirmation de l'âge minimal (15 ans, décision Q68), en plus des cases CGU et confidentialité. Sans elle, le compte n'est pas créé. L'application mobile applique la même règle.

### Accueil : bandeau d'alerte
La page d'accueil `/` comporte la section rouge très clair « alerte » (ancre `/#alerte`, décision Q54) avec les boutons « Signaler » et « Numéros d'urgence ». Vérifiez le texte, les boutons et le contraste.

### E-mails au design premium
Administration › **Templates e-mails** (`/admin/emails`, super-admin ou admin) : liste des 12 modèles, aperçu rendu dans le gabarit premium, essai d'envoi. Le rendu final se vérifie aussi en lisant un vrai message dans le journal (§7) ou, avec un SMTP réel, dans une boîte de réception.

### Maquettes du prototype mobile
Ouvrez par double-clic (aucun serveur nécessaire) dans `prototype_mobile/` : `index.html` (simulateur des 30 écrans), **`espace-pair.html`** (espace pair mobile) et **`admin-mobile.html`** (administration d'astreinte). Ce sont des **maquettes à valider** : l'espace pair et l'administration n'existent pas encore dans l'application Flutter. La police Montserrat demande Internet ; sans réseau, une police de secours s'affiche.

## 10. Dépannage

| Symptôme | Cause et remède |
|---|---|
| Page blanche ou non stylée | build oublié (`npm run build`) ou fichier `web/public/hot` présent : supprimez-le |
| `could not find driver` / base introuvable | extension `pdo_sqlite` absente (`php -m`), ou `DB_DATABASE` ne pointe pas sur un fichier existant |
| Images et avatars bloqués, erreurs CSP | ouvrez exactement `APP_URL` (`http://localhost:8000`), pas `127.0.0.1` ni un autre port |
| 429 « Trop de tentatives » | limites volontaires (§5) : attendez une minute ou videz les codes |
| Pas d'e-mail pour une confirmation de newsletter | exécutez `php artisan mail:flush-outbox` |
| ~80 erreurs CSP venant de `/_debugbar/` | `DEBUGBAR_ENABLED=false` (déjà le cas dans `.env.example`) |
| `/sitemap.xml` répond 404 | normal : l'indexation est interdite hors production |
| « Mot de passe compromis » : erreur 403 dans le journal à l'inscription | le service externe de vérification est injoignable hors ligne ; l'inscription réussit |
| `composer install` ou `npm ci` échoue | accès réseau nécessaire pour ces deux commandes uniquement |
| Le port 8000 est occupé | `scripts/recette-lancer.sh --port 8001`, puis mettez `APP_URL=http://localhost:8001` dans `.env` et ouvrez cette adresse |

## 11. Signaler une anomalie

Une anomalie = un rapport. Copiez ce gabarit dans votre outil de suivi :

```markdown
**Titre** : [zone] phrase courte (ex. « [SOS] le décompte ne s'annule pas sur mobile »)
**Ligne de la checklist** : (ex. SOS-03)   **Gravité** : bloquant / majeur / mineur / cosmétique
**Environnement** : web (navigateur + version) ou mobile (appareil, Android/iOS, version) ;
  commit (`git rev-parse --short HEAD`) ; build ou dev ; compte utilisé
**Étapes** :
1. …
**Résultat attendu** : (copie de la colonne « Résultat attendu »)
**Résultat obtenu** :
**Capture** : (écran complet, URL visible ; courte vidéo pour un flux)
**Console du navigateur** : (F12 › Console : erreurs rouges ; onglet Réseau : requête en échec et statut HTTP)
**Journal** : (`tail -n 50 web/storage/logs/laravel.log` : ligne `local.ERROR` et sa première ligne de pile)
**Reproductible** : toujours / parfois / une fois     **Contournement** : …
```

Ne collez jamais de vrai mot de passe, de vrai code de suivi ni de donnée de personne réelle dans un rapport : les comptes du §5 sont sans risque.

## 12. Pour aller plus loin

- Parcours à cocher, par rôle : [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) (avec la liste de ce qui n'est pas encore fonctionnel).
- Mise en ligne : [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) et [RUNBOOK.md](RUNBOOK.md) (procédures d'exploitation).
- État d'avancement : [ETAT_DES_LIEUX.md](ETAT_DES_LIEUX.md) et [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) ; décisions en attente : [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md).
