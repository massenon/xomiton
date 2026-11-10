# Reste à faire : en local, en production sur le web, sur le Play Store

Inventaire au 2 novembre 2026, établi sur l'état de la branche `claude/friendly-darwin-abyzn0`. Il complète le [plan de finalisation](PLAN_FINALISATION.md) (priorités, échéancier, risques) avec une vue **par lieu d'exécution**, en cases à cocher. Légende des responsables : **C** = client, **D** = développeur·se, **J** = conseil juridique, **H** = hébergeur.

**Où en est le code** : serveur, site public, espace membre, espace pair et espace admin d'astreinte sont codés et testés (environ 3 040 tests Pest, 790 Vitest, 450 Flutter, tous verts au dernier passage). Ce qui reste n'est presque plus du code : ce sont des **vérifications en conditions réelles**, des **informations et comptes du client** et des **avis**. Rien n'a encore tourné sur un vrai serveur ni sur un vrai téléphone.

---

## 1. En local, sur votre poste

Guide pas à pas : [INSTALLATION_LOCALE.md](INSTALLATION_LOCALE.md). Parcours de test : [GUIDE_RECETTE.md](GUIDE_RECETTE.md) et [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) (112 lignes, **aucune n'est encore cochée**).

### 1.1 Mettre en route le site
- [ ] **C/D** `git pull --rebase origin claude/friendly-darwin-abyzn0`, puis `composer install`, `npm ci`.
- [ ] **C/D** Créer la base MySQL (`utf8mb4_unicode_ci`), renseigner `DB_*` dans `.env`, lancer `php artisan migrate:fresh --seed` : **vérifier que cela passe jusqu'au bout sur votre MySQL 8**. Il a été validé sur SQLite et MariaDB 10.11 ; deux erreurs propres à MySQL ont déjà été corrigées (index de plus de 64 caractères, texte coupé au milieu d'un « à »). Toute nouvelle erreur : la copier telle quelle.
- [ ] **C/D** `APP_DEBUG=false` dans `.env`, puis `php artisan optimize` et `npm run build`. Sans cela, le site local est lent (Debugbar actif, `php artisan serve` mono-processus : voir [PERFORMANCE.md](PERFORMANCE.md) §11). Pour des mesures de vitesse crédibles, passer par Laragon ou XAMPP (Apache).
- [ ] **C** Se connecter avec chacun des 8 comptes de démonstration (identifiants dans le guide de recette, à saisir à la main ; code OTP lu dans `storage\logs\laravel.log`, ligne `[otp:log]`).

### 1.2 Recette humaine du site
- [ ] **C** Dérouler [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) : visiteur·se, membre, pair éducateur·rice, administrateur·rice, SOS et alertes, e-mails, notifications, accessibilité, sécurité visible.
- [ ] **C** Valider ou corriger visuellement : accueil, inscription compacte (étape 1 sans défilement à 1366×768), ruban arc-en-ciel de la connexion et de l'inscription, bandeau d'alerte de l'accueil, modèles d'e-mail (`/admin/emails`).
- [ ] **C** Trancher la question du **formulaire de newsletter du pied de page** : aujourd'hui un bouton vers `/newsletter` (sûr) à la place du formulaire inline, parce que les pages sans session n'ont pas de jeton CSRF. Un formulaire inline demanderait une exemption avec contrôle d'origine, à valider explicitement.
- [ ] **C** Lire les pages légales et signaler tout ce qui est faux : confidentialité, conditions, mentions légales, cookies, suppression de compte.

### 1.3 Mobile en local (jamais compilé jusqu'ici)
Guide : [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md).
- [ ] **D/C** Installer Flutter, Android Studio, le SDK Android et ses licences ; `flutter doctor` sans erreur.
- [ ] **D/C** `flutter pub get`, `flutter run` sur émulateur (API locale à `10.0.2.2:8000`) puis sur un vrai téléphone (`php artisan serve --host=0.0.0.0` et l'IP du poste).
- [ ] **D** `flutter build apk --debug` : **premier build Android jamais lancé**. Corriger ce qu'il révèle (manifeste fusionné, R8/proguard, icônes, permissions).
- [ ] **C/D** Placer `google-services.json` (non versionné) et tester le push avec `php artisan notifications:test-push`.
- [ ] **C/D** Dérouler les lignes MOB-01 à MOB-37 de la checklist sur un téléphone réel : connexion unique, sélecteur d'espace, PIN, mode discret, SOS avec attente GPS de 8 s, hors ligne et synchronisation, conflit 409 tranché par le pair, effacement à distance.

### 1.4 Canaux externes, en essai
- [ ] **C** Comptes Vonage **ou** Twilio ; `php artisan notifications:test-sms` vers un numéro béninois ; confirmer l'expéditeur accepté et le coût. Seule l'acceptation par le fournisseur est journalisée : aucun statut de livraison.
- [ ] **C** Projet Firebase : clé de compte de service (hors dépôt) et `google-services.json`.
- [ ] **C** Assistant Gemini : **laisser `ASSISTANT_LLM_ENABLED=false`** tant que l'avis juridique n'est pas rendu. `assistant:test-llm` ne sert qu'à vérifier la clé.

### 1.5 Code qui manque encore (aucun n'est bloquant pour tester)
- [ ] **D** `notifications:digest` : le réglage « résumé » existe et n'a aucun effet.
- [ ] **D** `alerts:anonymize` : à lancer après décision sur l'échéance de conservation (**C/J**).
- [ ] **D** P11-02 : modification d'une fiche VBG/VDH existante (création, détail, suppression, PDF existent).
- [ ] **D** Connecteurs réseaux sociaux réels (Facebook, Telegram) : aujourd'hui pilote `log` et publication manuelle.
- [ ] **D** Mobile : écran annuaire des membres (absent des 31 écrans validés), tâches de suite (`sync/pull` étendu).
- [ ] **D** Dépendances : `npm audit` (alertes de la chaîne de développement), budget JavaScript serré.

---

## 2. Déploiement web (cPanel)

Guides : [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md), [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md), [RUNBOOK.md](RUNBOOK.md). Modèle de configuration : `web/.env.production.example` (120 variables, tous les secrets vides).

### 2.1 Avant de toucher au serveur
- [ ] **C** Fournir les **12 variables légales** `XOMITON_LEGAL_*` (éditeur, forme juridique, enregistrement, adresse, directeur·rice de la publication, téléphone, hébergeur, adresse et pays de l'hébergeur, personne chargée des données et son e-mail, juridiction), le **domaine de production** et l'**e-mail de contact public** (réglage admin `contact_email`, `contact@xomiton.app` par défaut). Tant qu'elles sont vides, les pages légales affichent « À COMPLÉTER PAR LE CLIENT ». Aucune valeur n'est inventée.
- [ ] **C/J** **Avis juridique** : confidentialité et conditions (Q67), âge de 15 ans, transferts hors Bénin, hébergement, conservation, distribution dans tous les pays (vigilance pour les pays hostiles aux personnes LGBTQ+), conditions d'un éventuel assistant à base de LLM.
- [ ] **C/H/D** **Hébergement sain** : compte cPanel neuf ou assainissement de l'existant, rapport d'incident signé, PHP 8.3 et extensions requises (`php -m` joint au rapport).
- [ ] **C** Comptes à ouvrir : SMTP réel, supervision (Healthchecks.io ou UptimeRobot), stockage externe des sauvegardes.
- [ ] **C** Trancher les lignes « Bloquant recette » restantes de [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md). Par défaut, les décisions D-MOB-6, 7, 8, 9 et 13 sont « recommandation retenue », à confirmer.

### 2.2 Mise en place sur le serveur
- [ ] **D/H** Sous-domaine de recette, base MySQL, `.env` copié depuis `.env.production.example` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` générée, `SESSION_SECURE_COOKIE=true`, `queue=database`, `cache=file`).
- [ ] **D** `deploy.sh --first` rejoué **deux fois** sans étape manuelle non documentée ; il lance déjà `php artisan optimize`.
- [ ] **D** `php artisan migrate --force` puis `php artisan xomiton:install --admin-email=…` (données de base **sans** comptes de démonstration + premier super-administrateur). **À rejouer à la recette** : connexion complète avec ce compte (e-mail SMTP réel et code de vérification), car elle n'a jamais été éprouvée de bout en bout.
- [ ] **D/H** Cron à la minute (planificateur) et signal de vie supervisé ; alerte de signal de vie reçue lors d'un essai.
- [ ] **D** `php artisan xomiton:preflight` sans échec.
- [ ] **D/H** Vérifier sur le serveur réel : `proc_open`, `mysqldump`, `symlink()`, limite « Entry Processes », OPcache actif, en-têtes de cache et compression Brotli/gzip des assets (`.htaccess` seulement testé par son contenu), `Cache-Control` des pages invitées (`public, max-age=60`, aucun cookie posé), `SEO_ALLOW_INDEXING` à `true` **le jour de l'ouverture seulement**.
- [ ] **D** Sauvegarde et **restauration réussie** sur le chemin de production ; RPO et RTO notés dans le runbook.

### 2.3 Essais en conditions réelles
- [ ] **D/C** **SOS de bout en bout** avec de vrais canaux : push reçu en moins de 30 s en 4G, SMS reçu, escalade nationale à 5 minutes constatée, accusé de réception qui l'arrête.
- [ ] **C** Recette humaine complète de la checklist sur la recette hébergée.
- [ ] **D/C** Revue de sécurité ([SECURITE_CHECKLIST.md](SECURITE_CHECKLIST.md)) : sessions, antivirus des pièces jointes, TLS, accès par identifiant, journaux ; test d'intrusion éventuel ; `unsafe-eval` de la CSP.
- [ ] **D** Test de charge léger (`GET feed`, `POST sos`, `GET sync/pull`) : objectif p95 sous 600 ms ; sinon documenter les limites du forfait et décider d'un VPS.
- [ ] **C/D** Audit d'accessibilité réel : NVDA et VoiceOver sur le site, Firefox, Safari, 320 px.
- [ ] **D** Optimisations non faites : police Bootstrap Icons complète (131 Ko), 3 GIF animés de l'accueil (240 à 510 Ko), 2 requêtes de maintenance par page.
- [ ] **D** Intégration continue : jobs Playwright (avec axe) et Lighthouse ; **première exécution** de la CI mobile sur GitHub (jamais lancée).

### 2.4 Bascule
- [ ] **D/C/H** DNS, HTTPS, nouvelle `APP_KEY`, import éventuel de données réelles (`xomiton:import-prototype`), planning de garde renseigné, formation de l'équipe, checklist de [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §17 signée.

---

## 3. Publication sur le Play Store

Dossier complet : [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) (version Word : `playstore/Fiche-publication-Play-Store-Xomiton.docx`). Visuels déjà produits dans `playstore/assets/` (image de présentation 1024×500, 8 captures 1080×1920, 130 contrôles automatiques verts).

### 3.1 Décisions prises (rien à refaire)
Une seule application avec espaces par rôle (membre, pair, admin d'astreinte) ; un seul AAB ; âge minimal de 15 ans ; **gratuite**, **tous les pays**, **français uniquement** ; tranches d'âge Play « 16-17 » et « 18+ » seulement ; nom « Xomiton : écoute et sécurité », sans drapeau ni mot d'identité sur la fiche.

### 3.2 À faire
- [ ] **C** Valider **juridiquement** la cohérence de l'âge : l'application exige 15 ans, la fiche Play ne déclare que 16-17 et 18+ (point signalé D-MOB-18).
- [ ] **C** Ouvrir le **compte développeur Google Play** (frais uniques). S'il est personnel et récent : **12 testeurs pendant 14 jours** en test fermé avant la production.
- [ ] **D/C** Générer la **clé de téléversement**, sauvegardée hors dépôt ([BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) §7) ; activer Play App Signing.
- [ ] **D** Premier `flutter build appbundle --release` signé, avec l'adresse https de production ; comparer la taille au budget (plafonds de CI à resserrer).
- [ ] **C** Fournir l'**icône 512×512** (à exporter depuis `design-system/logo/icone-appli-bleu.svg`) et faire valider le logo vectoriel.
- [ ] **C** **URL de la politique de confidentialité** et **URL web de suppression de compte** : les pages existent (`/politique-de-confidentialite`, `/supprimer-mon-compte`) mais doivent être en ligne sous le domaine de production et sans connexion.
- [ ] **C/D** Remplir les formulaires de la Play Console : sécurité des données (rempli d'après le code, **3 points à confirmer en interne**), classification du contenu, public cible, permissions (localisation pour le SOS, notifications, photos), déclaration « application de santé ».
- [ ] **D/C** Préparer l'**accès de test** pour la revue Google : compte **membre** de démonstration ; ne mentionner les espaces pair et admin que si l'examinateur l'exige, en expliquant qu'ils sont réservés à des comptes à rôle.
- [ ] **D** Enregistrer une **vidéo** montrant l'usage de la localisation (SOS) pour la revue des permissions sensibles.
- [ ] **D** **Remplacer les captures du prototype par de vraies captures** de l'application dès qu'elle tourne (le générateur `playstore/build_playstore.py` les recompose en une commande ; barre d'état propre).
- [ ] **C** **Droits des images** : archiver hors dépôt la preuve de licence (photos de l'onboarding et des illustrations, dont celles générées par IA et la photo « bouche scotchée » autorisée en onboarding mais exclue de la fiche).
- [ ] **D/C** Pistes de test : **interne**, puis **fermé** (12 testeurs, 14 jours), puis demande d'accès à la **production**.
- [ ] **C** Prévoir une version anglaise **plus tard seulement** (aujourd'hui interdite par le générateur tant que les blocs `en` sont vides).
- [ ] **D** Après publication : surveiller les rapports de plantage et la revue de politique sur les fonctions sensibles (localisation, services d'urgence), et prévoir un plan de retrait rapide d'une version défectueuse.

### 3.3 Plus tard
- [ ] **D/C** iOS : projet Apple, `GoogleService-Info.plist`, APNs, icône alternative du mode discret, TestFlight (non commencé ; compte Apple requis ; plan : [docs/PLAN_IOS.md](PLAN_IOS.md), lots IOS-00 à IOS-14, ≈ 68 j.p hors marge ; rien d'iOS n'a jamais été compilé, pas de Mac).
  - **C22, avancement du 10/11/2026 (sans Mac, NON COMPILÉ)** : projet iPhone seul, entitlements, `PrivacyInfo.xcprivacy`, `Podfile`, icônes et icône discrète, écran de lancement, plugin Swift `XomitonSecurity`, `AppDelegate` écrits ; Dart (5 onglets, widgets adaptatifs, capture d'écran, liens légaux) testé. Détail par lot : [PLAN_IOS.md §0.6](PLAN_IOS.md) ; procédure au Mac : [IOS_PREMIER_BUILD_SUR_MAC.md](IOS_PREMIER_BUILD_SUR_MAC.md). Reste : tout ce qui exige un Mac et un compte Apple, `ITSAppUsesNonExemptEncryption` (avis du conseil), **blocage d'une personne depuis le fil (endpoint serveur manquant, règle 1.2)**.
  - Décisions D-IOS-1 à D-IOS-12 : **Tranché 2026-11-10 (recommandation retenue par le client)**. IOS-00 fait côté client ; D-IOS-5 (âge) et D-IOS-8 (chiffrement) restent conditionnées à l'avis écrit du conseil.
- [x] **D** **Chantier serveur « filtre minimal de contenus »** : fait (IOS-10) : mots (catégories, gravités bloquer / masquer / signaler), coordonnées et liens suspects, liste blanche, écran d'administration (`moderation.filter`), 422 `content_rejected`, journal sans contenu, jamais sur les messages privés 1-à-1 ni les alertes. **Reste (équipe)** : compléter la liste de départ (courte et neutre) par la modération et la faire valider par le conseil.
- [ ] **D** Tablettes 7 et 10 pouces (captures non obligatoires, à produire plus tard).

---

## 4. Ce qui bloque le plus, dans l'ordre

Guides pas à pas pour les quatre démarches que seul le client peut mener : [DEMARCHES_CLIENT.md](DEMARCHES_CLIENT.md) (tableau de bord) ; **C2** informations légales : [FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md) ; **C3** avis juridique : [DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md) ; **C4** domaine (avant l'hébergement en ligne) : [DEMARCHE_DOMAINE_XOMITON_APP.md](DEMARCHE_DOMAINE_XOMITON_APP.md) ; **C5** comptes externes, dont le compte Apple : [DOSSIER_APPLE_DEVELOPER_ORGANISATION.md](DOSSIER_APPLE_DEVELOPER_ORGANISATION.md).

1. Les **12 informations légales**, le **domaine** (`xomiton.app` retenu : enregistrement, DNS, HTTPS avant ouverture, boîtes `contact@` et `noreply@xomiton.app`, voir [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §20) et l'**avis juridique** (c'est sur le chemin critique de tout le reste).
2. Un **hébergement cPanel sain** et les **comptes externes** (Firebase, SMS, SMTP, Play Console).
3. Le **premier build Android** : c'est lui qui dira ce que la compilation et un vrai téléphone révèlent.
4. La **recette humaine** (site puis mobile) : elle est la seule preuve que tout fonctionne ensemble.
