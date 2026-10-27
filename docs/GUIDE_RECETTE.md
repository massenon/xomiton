# Guide de recette — lancer Xomiton en local et le tester à la main

Pour toi qui possèdes le projet. Tout ce qui est écrit ici a été exécuté le 27/10/2026 sur une copie propre (PHP 8.3.6, Node 22, Flutter 3.47.8) ; ce qui n'a pas pu l'être est marqué **(non vérifié)**. Coche les cases au fur et à mesure (`- [x]`). Chemins : `xomiton/` = racine du dépôt, `web/` = Laravel, `mobile/` = Flutter.

## 1. Prérequis

| Outil | Version | Pour quoi |
|---|---|---|
| PHP | **8.3** (`php -v`) | Laravel 12 |
| Extensions PHP | `ctype dom fileinfo filter gd hash iconv json libxml mbstring openssl pcre phar session simplexml tokenizer xml xmlreader xmlwriter zip` (exigées par `composer.lock`), plus **`pdo_sqlite` + `sqlite3`** (base locale) et **`intl`** (formats de langue). `bcmath` est installé par la CI mais pas exigé. | `php -m` doit les lister |
| Composer | 2.x | `composer install` |
| Node + npm | **22** / 10 | compilation Vite |
| Flutter SDK | **3.47.8** stable (Dart 3.13.7, comme la CI) | application mobile |
| Chromium | celui de Playwright (`npx playwright install chromium`) ou ton Chrome | seulement pour `npm run e2e` ; la recette manuelle se fait dans ton navigateur |
| Android Studio / SDK + émulateur, ou un téléphone | Android 8+ (`minSdk 26`) | lancer l'app mobile **(non vérifié ici : pas d'Android SDK)** |

Vérifie les extensions : `php -m | grep -iE 'pdo_sqlite|sqlite3|intl|gd|zip|mbstring'` doit afficher les six.

## 2. Lancement web, pas à pas

```bash
cd xomiton/web
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

**Piège n° 1 — `.env.example` vise MySQL et SMTP, pas ton poste.** Édite `.env` (garde `APP_ENV=local` : les comptes de démonstration ne sont semés qu'en `local`, `testing` ou `staging`) :

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
DB_DATABASE=/chemin/absolu/vers/xomiton/web/database/recette.sqlite
MAIL_MAILER=failover
DEBUGBAR_ENABLED=false
```
Commente aussi `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` (inutiles en SQLite). Pourquoi ces choix :
- **Base dédiée** : `touch database/recette.sqlite`. N'utilise pas `database/database.sqlite` : `migrate:fresh` l'efface. Les `*.sqlite` sont ignorés par git. Si tu laisses `DB_DATABASE=xomiton` avec sqlite, Laravel cherche un fichier nommé `xomiton` et échoue.
- **`MAIL_MAILER=failover`** (smtp puis log) plutôt que `log` : voir piège n° 3.
- **`DEBUGBAR_ENABLED=false`** : sinon la console du navigateur affiche ~80 erreurs CSP « Refused to execute inline script » venant de `/_debugbar/...` sur chaque page connectée. Sans effet sur le site.
- **`APP_URL=http://localhost:8000` et on ouvre toujours `http://localhost:8000`** (pas `127.0.0.1`, pas un autre port) : les avatars et le CSP s'appuient sur `APP_URL` ; sur un autre hôte/port les images `storage/` sont bloquées.

```bash
touch database/recette.sqlite
php artisan migrate:fresh --seed        # ≈ 5 s ; dit « DONE » pour DemoAccountsSeeder, DemoResourcesSeeder, DemoNewsSeeder
npm run build                           # ≈ 30 s ; finit par le contrôle de budgets (doit afficher « ok »)
php artisan serve                       # http://localhost:8000
```
Vérifie : `curl -s -o /dev/null -w '%{http_code}\n' localhost:8000/up` → `200`.

### Terminaux à garder ouverts
| Terminal | Commande | Rôle |
|---|---|---|
| 1 | `php artisan serve` | le site |
| 2 | `php artisan schedule:work` | planificateur (chaque minute : `alerts:escalate`, `notifications:retry`, `mail:flush-outbox`, `newsletter:process`, `polls:close`…) ; liste : `php artisan schedule:list` |
| 3 | `php artisan queue:work --stop-when-empty` | **à relancer à la main** après une action qui met un job en file (export de données, etc.) ; la file est en base (`QUEUE_CONNECTION=database`) |
| 4 | `tail -f storage/logs/laravel.log` | e-mails, SMS, push, OTP |

`npm run dev` est possible mais **piège n° 2** : en mode dev, le CSP bloque les polices et les icônes (`font-src 'self'` sans `127.0.0.1:5173`) → icônes manquantes, 5 erreurs console. De plus le service worker (hors connexion) **n'est enregistré qu'en build de production**. Pour la recette : `npm run build`, pas `npm run dev`. Après un build, rien d'autre à faire ; si tu modifies du JS, relance le build.

### Où lire quoi (tout dans `web/storage/logs/laravel.log`)
- **Code OTP** (connexion, inscription, mot de passe oublié) : `grep -a "Subject: Ton code Xomiton" storage/logs/laravel.log | tail -1` → le code est dans le sujet (`Ton code Xomiton : 123456`) et dans le corps `<h1>`. Valable 10 min. Seul son hachage est en base : on ne peut pas le lire autrement. **Une connexion sur un appareil inconnu exige un OTP ; les comptes admin/superadmin en exigent un à chaque connexion.**
- **E-mails** : même fichier (`To: …`, `Subject: …`). Les sujets accentués sont encodés (`=?utf-8?Q?…`) ; les liens longs peuvent être coupés par `=` en fin de ligne (quoted-printable) : recolle-les.
- **SMS** : lignes `[sms:log] SMS consigné (aucun envoi)` (numéro masqué). **Push** : `[webpush:log]` / `[fcm:log]` `Notification push consignée`. Rien ne part réellement (§6).
- **Tracé SOS** : `alerts.sos_created`, `alerts.dispatched … "purpose":"sos_triggered"` ; sans accusé au bout de 5 min, `alerts.dispatched … "purpose":"sos_unacknowledged"` (c'est `alerts:escalate`, donc `schedule:work` doit tourner).
- **Table ou ligne précise** : `php artisan tinker --execute='echo App\Models\Alert::count();'`.

### Pièges rencontrés en testant
3. **E-mails « en file » invisibles avec `MAIL_MAILER=log`.** L'OTP, la bienvenue, etc. partent directement et apparaissent dans le log. Mais la **confirmation de newsletter**, l'**e-mail de test de campagne**, l'**export RGPD** passent par la table `mail_outbox`, et `mail:flush-outbox` répond « Aucun transport SMTP disponible (MAIL_MAILER=log) : rien n'est envoyé ». Avec `MAIL_MAILER=failover` (SMTP injoignable → repli log), `php artisan mail:flush-outbox` (ou `schedule:work`) les vide dans `laravel.log`. Sans cela : `php artisan tinker --execute='echo App\Models\NewsletterSubscriber::latest("id")->value("token");'` donne le jeton (lien `/newsletter/confirmer/<jeton>`).
4. **Limites volontaires** (réponse « 429 Trop de tentatives » ou « Trop de codes demandés pour cette adresse ») : 5 connexions par minute et par IP, **5 codes OTP par heure et par adresse**, 10 publications du fil par minute. Après une série de tests : `php artisan tinker --execute='App\Models\OtpCode::query()->delete();'` puis `php artisan cache:clear`.
5. **Mot de passe « compromis »** : à l'inscription l'application interroge un service externe ; hors ligne ou derrière un proxy, une ligne `local.ERROR: HTTP request returned status code 403` apparaît dans le log mais l'inscription réussit.
6. **`/sitemap.xml` répond 404** en local : normal (indexation interdite hors production).
7. **`php artisan serve` ne relit pas `.env` à chaud** : redémarre-le après chaque changement.
8. Données de départ : 5 comptes, 18 ressources, 29 thématiques, 28 FAQ, 5 forums ; **fil, groupes, événements, messages et sujets de forum sont vides** : tu les crées pendant la recette.

## 3. Comptes de démonstration (`DemoAccountsSeeder`)

| Rôle | Identifiant | Mot de passe | Accès |
|---|---|---|---|
| Super-admin | `admin@xomiton.bj` (pseudo `admin`) | `Admin123!` | `/admin` complet + espace membre |
| Admin (modération/contenu) | `moderation@xomiton.bj` (`moderatrice`) | `Admin123!` | `/admin` |
| Membre | `membre@xomiton.bj` (`awa_s`) | `Membre123!` | espace membre ; `/admin`, `/espace-pair`, `/collecte` → 403 |
| Pair éducatrice | `pair@xomiton.bj` (`pair_chantal`) | `Pair123!` | espace membre + `/espace-pair`, `/collecte` ; `/admin` → 403 |

Le champ « identifiant » de `/connexion` accepte l'e-mail ou le pseudo. Chaque connexion d'un nouvel appareil/navigateur : lis le code dans le log (§2) et saisis-le sur `/code-de-verification` (le dernier chiffre valide tout seul). Astuce : une fenêtre de navigation privée par rôle.

## 4. Recette manuelle, parcours par parcours

Convention : **URL** — étapes — ✅ attendu — ⚠ si ça échoue. Ouvre la console (F12) : aucune erreur rouge n'est attendue (sauf celles listées en §6).

### A. Site public (sans compte)
- [ ] **Accueil** `/` : page « Xomiton – Espace safe LBTQ+… », menu, bouton **SOS** visible. ✅ aucune erreur console. ⚠ page blanche/non stylée : `npm run build` oublié, ou `public/hot` présent (supprime-le).
- [ ] **Thématiques** `/thematiques` → ouvre une fiche (`/thematiques/<slug>`). ✅ 29 fiches, fiche lisible, FAQ en bas.
- [ ] **Ressources** `/ressources` (+ `/ressources/<slug>`, `/actualites`) : liste, recherche, détail, badge « Hors ligne » sur certaines. ✅ 17 ressources publiées.
- [ ] **Aide** `/aide` (FAQ, 28 questions) et **Urgences** `/urgences` : numéros 117, 118, ligne d'écoute `+229 01 97 38 88 47`. ✅ liens `tel:`.
- [ ] **Newsletter double opt-in** `/newsletter` : e-mail `nl1@exemple.bj`, un centre d'intérêt, case de consentement, « S'inscrire ». ✅ page « Merci ! Un e-mail de confirmation… ». Puis `php artisan mail:flush-outbox` et cherche `Confirmez votre inscription` dans le log (ou jeton, §2 piège 3), ouvre `/newsletter/confirmer/<jeton>`, clique **Confirmer mon inscription**. ✅ statut `actif` dans `/admin/newsletter` › Abonné·es. Lien de désinscription `/newsletter/desinscription/<jeton>` : désabonne en un clic. ⚠ rien dans le log : `MAIL_MAILER=log` (piège 3).
- [ ] Pages légales `/confidentialite`, `/lois`, `/violences`, `/do-glegbe`, `/mot-de-passe-oublie` : elles s'affichent.

### B. Signalement anonyme + suivi par code
- [ ] `/signaler` (6 étapes, sans compte) : type « Signaler une violence » (`#f-type-0`), catégorie, **Suivant** ; description ≥ quelques phrases ; département ; étapes 4 et 5 facultatives ; étape 6 récapitulatif « Rien n'est encore parti », **Envoyer**. ✅ `/signaler/confirmation` : « Ton signalement est envoyé » + code `SIG-XXXXXXXXXX` **affiché une seule fois** (copie-le). ⚠ si l'envoi reste bloqué : onglet Réseau, requête `POST /api/v1/signalements` ; ligne d'erreur dans `laravel.log`.
- [ ] `/suivi` : saisis le code. ✅ « Statut : Nouveau », « Reçu. Une personne formée va le lire ». Avec un code inexistant : message neutre, pas de fuite.
- [ ] `/admin/alertes` (super-admin) : la ligne apparaît (type Signalement, anonyme). Change son statut (Pris en charge) puis recharge `/suivi` : le statut suit.

### C. SOS
- [ ] `/sos` : **maintiens le bouton rouge 2 secondes** (souris ou doigt). ✅ décompte « Envoi de ton alerte dans 5… Tu peux encore annuler ».
- [ ] **Annulation** : pendant le décompte, **Annuler l'alerte**. ✅ « Alerte annulée » ; `php artisan tinker --execute='echo App\Models\Alert::count();'` inchangé ; aucune ligne `alerts.dispatched`.
- [ ] **Envoi** : recommence et ne touche à rien 5 s. ✅ « Ton alerte est partie », « Fausse alerte ? Tu peux encore annuler pendant 58 s », boutons « Appeler le 117 », « Je suis en sécurité ». Autorise ou refuse la position (case « Partager ma position »). Sans maintien : double clic ou Entrée sur le bouton, puis confirmation.
- [ ] **Journal** : `grep -a "alerts\.\|sms:log" storage/logs/laravel.log | tail` → `alerts.sos_created`, `alerts.dispatched … sos_triggered … "sent":N`, `[sms:log]`. Après 5 min sans accusé (avec `schedule:work`), `sos_unacknowledged`.
- [ ] **Admin** : `/admin` (bandeau rouge « 1 alerte SOS active », **Accuser réception**, tuile « SOS actifs ») et `/admin/alertes` (onglet SOS, filtre Statut). ✅ après accusé, le bandeau disparaît. Le bouton rond SOS flottant existe aussi sur les pages membre.

### D. Inscription → OTP → fil
- [ ] `/inscription` : pseudo `recette1`, e-mail `recette1@exemple.bj`, mot de passe `Mot-de-passe-solide-2026!`, cases CGU et confidentialité, **Créer mon compte**. ✅ arrivée sur `/inscription/profil` ; étapes facultatives profil, sécurité, consentements (**Passer**) ; e-mail « Bienvenue » dans le log.
- [ ] `/code-de-verification` : lis le code (§2), saisis-le. ✅ compte vérifié ; arrive sur `/fil`. Un code faux : message d'erreur et compteur d'essais ; **Renvoyer** respecte un délai.
- [ ] Déconnexion puis `/connexion` avec ce compte, et « Mot de passe oublié » (`/mot-de-passe-oublie` → code dans le log → `/nouveau-mot-de-passe`). ⚠ « Trop de codes » : piège 4.

### E. Fil : publier, sondage, commentaire (connecté en `membre@xomiton.bj`)
- [ ] `/fil` → « Partage tes pensées… » : texte, thème (Témoignage/Question…), visibilité, **Publier**. ✅ la carte apparaît en haut, compteur « Publications » +1.
- [ ] **Sondage** : question, deux réponses (+ « Ajouter une réponse »), durée, **Publier le sondage** ; vote (**Voter**), résultat affiché. ✅ après l'échéance (`schedule:work`, `polls:close`) le vote est refusé.
- [ ] **Commentaire** sous une publication ; **J'aime** ; **Partager** ; enregistrement (`/fil/enregistres`) ; détail `/fil/<id>`.
- [ ] Publier avec « Anonyme » coché : le pseudo n'apparaît pas. Signaler une publication (menu ⋯) puis la retrouver dans `/admin/moderation`.

### F. Messagerie
- [ ] `/messages` → **Nouvelle conversation** › Message privé › cherche `pair` › `pair_chantal` ; écris et envoie. ✅ message affiché ; dans une autre session (`pair@xomiton.bj`) la conversation arrive (cloche + liste). Onglet « Non lues », recherche de conversation, conversation de groupe (onglet Groupe).

### G. Groupes, événements, forums
- [ ] **Groupes** `/groupes` → **Créer un groupe** (nom, description, Privé/Public, zone). ✅ redirection `/groupes/<id>` + « Groupe créé ». Depuis le compte pair : rejoindre (si public).
- [ ] **Événements** `/evenements` → **Créer un événement** : titre, **date obligatoire**, heure, lieu ou « en ligne ». ✅ listé sous « À venir » ; **Participer** depuis un autre compte ; rappel de la veille (`events:remind`) dans `laravel.log`.
- [ ] **Forums** `/forums` → **Nouveau sujet** (forum, titre, message, option anonyme). ✅ « Ton sujet est publié » ; réponse depuis un autre compte.

### H. Profil progressif et confidentialité
- [ ] `/profil` : la fenêtre « Complète ton profil, c'est facultatif » pose **un groupe de questions à la fois** (« Question 2 sur 8 »), avec « Je préfère ne pas le dire », « Passer ce groupe », « Plus tard ». ✅ la jauge « Profil complété à X % » monte. Change avatar/couverture, onglets À propos, Connexions, Médias, Événements, Activité.
- [ ] `/parametres` › **Confidentialité** : visibilité du profil (Tout le monde / Communauté / …), « Afficher ma zone », « Trouvable dans la recherche », partage GPS du SOS. Vérifie avec l'autre compte (`/membres/<pseudo>`, `/recherche`) que le réglage est respecté.

### I. Paramètres
- [ ] **Mot de passe** : actuel + nouveau ×2 → **Changer le mot de passe** ; reconnecte-toi avec le nouveau.
- [ ] **Sécurité › Mode discret** : titre d'onglet neutre, aperçus masqués. **Appareils connectés** → « Déconnecter tous les appareils ».
- [ ] **Notifications** (grille par thème × canal), **Langue**, **Apparence** (Clair/Sombre/Automatique), **Newsletter**.
- [ ] **Mes données › Télécharger mes données** : toast « Ton export est en préparation… ». Lance `php artisan queue:work --stop-when-empty` puis `php artisan mail:flush-outbox` ; cherche `mes-donnees/export/` dans le log et ouvre le lien (48 h). ✅ JSON téléchargé.
- [ ] **Supprimer mon compte** (sur un compte jetable comme `recette1`) : taper `SUPPRIMER` + mot de passe. ✅ déconnexion, connexion impossible ; anonymisation réelle après 30 j (`php artisan accounts:anonymize --dry-run`).

### J. Recherche et assistant
- [ ] `/recherche?q=santé` (champ de l'en-tête) : onglets Tout / Ressources / Thématiques / Contacts / Membres. ✅ « 3 résultats ». Note : `sante` sans accent donne 0 résultat (§6).
- [ ] `/assistant` : clique une suggestion (« Comment porter plainte ? ») → réponse de la base de connaissances (`ASSISTANT_PROVIDER=kb`). **Message de crise** : écris « je veux mourir, je ne vois plus d'issue ». ✅ réponse de crise avec boutons **SOS avec position GPS**, **Appeler le 117 / 118**, ligne d'écoute, « Faire un signalement », « Parler à un humain ». « Garder cette conversation » est désactivé par défaut.

### K. Espace pair et collecte hors ligne (compte `pair@xomiton.bj`)
- [ ] `/espace-pair` : tableau de bord (dossiers, alertes routées), `/espace-pair/dossiers`.
- [ ] `/collecte` en ligne : type de dossier, type de violence, département, population, sexe, âge, **Enregistrer le dossier**. ✅ bandeau « En ligne / Aucune fiche en attente ».
- [ ] **Hors ligne** : sur `/collecte`, DevTools › Réseau › **Hors connexion** (le service worker de la page publique `/urgences` n'est enregistré qu'avec `npm run build`). Remplis et enregistre. ✅ bandeau « Hors ligne · 1 fiche en attente ». Remets « En ligne » : ✅ en quelques secondes « Aucune fiche en attente » et le dossier est en base (`DB::table('cases')->count()` +1 ; visible dans `/admin/registres`). Idem `/signaler` hors ligne : à l'envoi il part au retour du réseau et affiche son code.

### L. Administration (super-admin `admin@xomiton.bj`, code OTP à chaque connexion)
- [ ] **Tableau de bord** `/admin` : tuiles (Nouvelles alertes, SOS actifs, Comptes…), graphiques, bandeau SOS (parcours C). Menu : une entrée par écran ci-dessous ; Ctrl K = recherche globale.
- [ ] **Alertes** `/admin/alertes` : filtres, actualisation auto toutes les 10 s, accuser réception, notes, **Exporter CSV**.
- [ ] **Registres** `/admin/registres` (dossiers VBG/VDH, **Nouveau dossier**, le récit n'est jamais dans la liste) ; **Fiches** `/admin/fiches` (édition d'une fiche déjà créée : voir §6).
- [ ] **Utilisateurs** `/admin/utilisateurs` : filtre par rôle, **Créer un compte**, suspendre/réactiver, valider un pair, matrice des permissions (lecture seule).
- [ ] **Modération** `/admin/moderation` : file « À traiter », décision (confirmer/rétablir), mots surveillés (7).
- [ ] **Contenu** `/admin/contenu` : onglets Ressources / Thématiques / FAQ / Catégories ; **Nouvelle ressource** ; « Passer en brouillon » retire la fiche du public (vérifie `/ressources`).
- [ ] **Newsletter** `/admin/newsletter` › Campagnes › **Nouvelle campagne** : objet, corps (variables `{{user_first_name}}`, `{{unsubscribe_link}}`), segment, e-mail dans « Envoyer le test » (`#xmNlTestTo`) → **Envoyer le test**. ✅ toast « Test mis en file d'envoi : il part dans la minute » ; `php artisan mail:flush-outbox` ; sujet `[TEST] …` dans le log. Planifier/envoyer : `newsletter:process` (par lots de 200, via `schedule:work`). Statistiques : taux d'ouverture/clic quand le pixel est chargé.
- [ ] **Sauvegardes** `/admin/sauvegardes` : **Sauvegarder maintenant** ; archive chiffrée listée et téléchargeable (avertissement normal : `BACKUP_ARCHIVE_PASSWORD` non défini).
- [ ] **Paramètres** `/admin/parametres` (Général, Sécurité, Conservation, Messagerie SMTP, Système) : une valeur hors bornes est refusée. **Journal d'audit** `/admin/journal` : tes actions récentes (`auth.otp_verified`, `alerts.sos_triggered`…), filtre, export CSV.
- [ ] **Mode maintenance** `/admin/maintenance` : **Activer la maintenance** › saisis un **Motif interne**, durée, coche « Je confirme », **Activer maintenant**. ✅ bandeau rouge « Mode maintenance actif… » et décompte. **Vérifie en navigation privée (visiteur)** :
  - ouverts (200) : `/sos` (envoie un SOS complet : il part), `/signaler` (envoie un signalement : il part), `/suivi`, `/urgences`, `/connexion`, `/newsletter`, `/up`, `/sw.js` ;
  - fermés (503, page « Maintenance en cours — Une pause pour mieux vous accueillir », rappel SOS/Signaler/Urgences, compte à rebours) : `/`, `/inscription`, `/thematiques`, `/aide`, `/fil`, `/admin` ;
  - les membres connectés sont déconnectés ; l'admin garde `/admin` et `/fil`. « Voir comme un visiteur » montre le rendu.
  - **Désactive** ensuite (bouton vert « Désactiver la maintenance ») et vérifie que `/` redevient 200. Planifie aussi une fenêtre (début/fin/annonce préalable).

### M. Pages d'erreur
- [ ] **404** : `/n-importe-quoi` ou `/thematiques/x`. ✅ « Erreur 404 – Xomiton » avec rappel SOS/urgences.
- [ ] **403** : connecté en `membre@xomiton.bj`, ouvre `/admin` (ou `/espace-pair`). ✅ « Erreur 403 ». (`/admin/export` répond aussi 403 sans lien signé : normal.)
- [ ] **419** : ouvre `/connexion`, supprime les cookies du site (DevTools › Application), envoie le formulaire. ✅ « Erreur 419 — Votre session a expiré… les informations saisies n'ont pas été envoyées ».
- [ ] **503** : maintenance active (ci-dessus). **429** : enchaîne 6 connexions fausses en une minute. ✅ « Trop de tentatives… Nouvelle tentative possible dans N secondes ».

## 5. Mobile Flutter

```bash
# Installer le SDK (Linux) : version identique à la CI (.github/workflows/mobile.yml)
curl -O https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_3.47.8-stable.tar.xz
tar xf flutter_linux_3.47.8-stable.tar.xz && export PATH="$PWD/flutter/bin:$PATH"   # macOS/Windows : flutter.dev/install
cd xomiton/mobile
flutter pub get      # ≈ 30 s
flutter analyze      # ≈ 30 s : « No issues found! »
flutter test         # ≈ 1 min 15 : « All tests passed! » (151 tests)
```
Les trois commandes ci-dessus ont été exécutées avec Flutter 3.47.8 : OK. `flutter doctor` doit être vert pour Android (SDK + licences) avant de lancer sur un appareil.

**URL de l'API** : `--dart-define=API_BASE_URL=<racine>/api/v1` (le suffixe **`/api/v1` est obligatoire**). Défaut : `http://10.0.2.2:8000/api/v1`.
- Émulateur Android, build **debug** : `flutter run` suffit (10.0.2.2 = le `localhost` de ton PC ; le trafic http en clair n'est autorisé qu'en debug/profile).
- Téléphone réel, debug : `php artisan serve --host=0.0.0.0`, puis `flutter run --dart-define=API_BASE_URL=http://192.168.X.Y:8000/api/v1` (même Wi-Fi, pare-feu ouvert).
- **Release** : `flutter build apk --release --dart-define=API_BASE_URL=https://ton-domaine/api/v1`. En release une URL non https fait **refuser le démarrage** (`API_BASE_URL doit être une URL https en production`).
- Les codes OTP du mobile arrivent aussi dans `web/storage/logs/laravel.log` (§2). Mêmes limites 429 qu'au web.

Parcours (compte `membre@xomiton.bj`) :
- [ ] **Onboarding** : carrousel de présentation (Suivant, Passer, « J'ai déjà un compte »).
- [ ] **Inscription** `/signup` → code OTP (log) → arrivée sur l'accueil. **Mot de passe oublié** → `/reset`.
- [ ] **Connexion** : identifiant + mot de passe → OTP si nouvel appareil → accueil (tuiles Apprendre, Signaler, Urgences, Assistant). Déconnexion puis reconnexion.
- [ ] **SOS** (onglet SOS) : maintien du bouton, décompte annulable, envoi ; vérifie `alerts.sos_created` dans le log et la ligne dans `/admin/alertes`. Position GPS : autorise la permission, ou refuse (l'alerte part quand même).
- [ ] **Signalement** (`/signaler`) anonyme ou connecté, code de suivi, puis `/suivi`. Sans réseau : l'envoi est mis en file (Hive) et part au retour.
- [ ] **Fil** : lire, publier (`/feed/new`), commenter, J'aime. **Messages** : nouvelle conversation, envoi, réception depuis le web.
- [ ] **Profil** : `/mon-profil`, profil progressif, `/parametres`, `/securite` (**mode discret**, masquage des notifications, appareils), suppression de compte.
- [ ] **Code PIN** (`/securite` › activer) : 4 chiffres ; ferme et rouvre l'app (le PIN est demandé) ; 5 erreurs → session fermée (reconnexion mot de passe + OTP). SOS, urgences et signalement restent accessibles sans PIN.

**Ne peut PAS marcher sans configuration** :
- **Push (Firebase)** : sans `mobile/android/app/google-services.json` (jamais versionné), l'app démarre en `NoopPushService` : aucune permission demandée, aucune notification. Côté serveur `FCM_DRIVER=log` : même avec Firebase, rien n'est envoyé (§6). Procédure : `mobile/README.md`.
- **iOS** : `GoogleService-Info.plist`, capacité Push, clé APNs non livrés ; l'icône alternative du mode discret n'existe pas (le nom ne peut pas changer). Build iOS **(non vérifié, nécessite macOS)**.
- Les tuiles du tableau de bord dont l'écran n'est pas livré ouvrent `/soon/<page>` (« bientôt ») ; l'**espace pair n'existe pas côté mobile**.
- Le SDK Android n'était pas disponible dans mon environnement : `flutter run`/`build apk` et les parcours ci-dessus sont **(non vérifiés)** ; seuls `pub get`, `analyze`, `test` le sont.

## 6. Ce qui ne marche volontairement pas encore (et défauts constatés)

Volontaire (voir `docs/ETAT_DES_LIEUX.md`) :
- **SMS** : `SMS_PROVIDER=log` — consignés, jamais envoyés. **Push** : `WEBPUSH_DRIVER=log` / `FCM_DRIVER=log` — consignés, jamais envoyés (paquets et clés VAPID/Firebase absents).
- **SMTP** : aucun envoi réel ; réglable dans Administration › Paramètres › Messagerie (SMTP) ou `MAIL_*`.
- **Réseaux sociaux** : passerelles `/admin/passerelles` sans clés (`FACEBOOK_*`, `TELEGRAM_BOT_TOKEN`, `TIKTOK_*`) : « publication manuelle ».
- **`notifications:digest`** n'existe pas : le réglage de synthèse quotidienne/hebdomadaire n'a aucun effet (idem `alerts:anonymize`).
- **P11-02** : éditer une fiche VBG/VDH **déjà créée** n'est pas possible (`/admin/fiches`).
- **Espace pair mobile** absent ; assistant en base de connaissances seulement (pas de LLM : `ASSISTANT_API_KEY` ignorée).

Défauts constatés pendant mon test du lancement (aucun ne bloque un parcours, rien n'a été corrigé) :
- **B1 — e-mails de la boîte d'envoi muets avec `MAIL_MAILER=log`** : confirmation newsletter, test de campagne, export RGPD restent dans `mail_outbox` (`mail:flush-outbox` inactif pour `log`/`array`). Contournement : `MAIL_MAILER=failover` (§2). Le README de `web/` laisse croire que `log` suffit.
- **B2 — `npm run dev` : polices et icônes bloquées** par le CSP (`font-src 'self'` sans l'hôte Vite) ; le service worker n'y est pas enregistré. Utilise `npm run build`.
- **B3 — Debugbar (`APP_DEBUG=true`)** : ~80 violations CSP dans la console de chaque page connectée. `DEBUGBAR_ENABLED=false`.
- **B4 — libellés bruts dans l'admin** : `/admin/newsletter` affiche `{0} Aucune confirmation en attente|{1} …|[2,*] …` (clé de pluriel non traitée, `lang/fr/admin_newsletter.php:43`) ; `/admin/contenu` affiche la clé `admin_content.ressources.title`.
- **B5 — recherche sensible aux accents** : `/recherche?q=sante` → 0 résultat, `santé` → 3.
- **B6 — mélange d'hôtes** : si tu ouvres le site sur un autre hôte/port que `APP_URL`, les avatars `storage/` sont bloqués par le CSP (pas un bogue, mais déroutant).

## 7. Gabarit de rapport de bug

Copie ceci dans un ticket (une anomalie = un rapport) :

```markdown
**Titre** : [zone] phrase courte (ex. « [SOS] le décompte ne s'annule pas sur mobile »)
**Parcours du guide** : § … (ex. 4.C SOS)   **Gravité** : bloquant / majeur / mineur / cosmétique
**Environnement** : web (navigateur + version) ou mobile (appareil, Android/iOS, version de l'app) ;
  commit (`git rev-parse --short HEAD`) ; `APP_ENV`, `MAIL_MAILER` ; `npm run build` ou `dev` ; compte utilisé
**Étapes** :
1. …
2. …
**Résultat attendu** : (copie la ligne « ✅ » du guide)
**Résultat obtenu** :
**Capture** : (écran complet avec l'URL visible ; pour un flux, courte vidéo)
**Console du navigateur** : (F12 › Console, texte des erreurs rouges ; onglet Réseau : requête en échec, statut HTTP)
**Ligne de laravel.log** : (`tail -n 50 web/storage/logs/laravel.log` ; garde la ligne `local.ERROR` et sa première ligne de pile)
**Reproductible** : toujours / parfois / une fois     **Contournement** : …
```
Ne colle jamais de vrai mot de passe, de vrai code de suivi ou de données de personnes réelles dans un rapport ; les comptes de démonstration du §3 sont sans risque.
