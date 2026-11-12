# Journal des échanges : client et Claude (projet Xomiton)

Résumé compact de toutes les conversations entre le client et Claude, du 15/10/2026 au 12/11/2026.

## 1. En-tête

- **Objet** : passer du prototype Xomiton à la vraie plateforme. Xomiton est un espace sûr pour les filles, les femmes et les personnes LBTQ+ au Bénin.
- **Pile** : Laravel 12 (site public en Blade, administration en Livewire 3, espace membre et pair en Vue 3 + Inertia, API `/api/v1`), une seule application Flutter, hébergement cPanel mutualisé.
- **Période** : du 15/10/2026 au 12/11/2026. Les commits du 15/10 portent sur le prototype ; les messages du client commencent le 17/10.
- **Branche de travail** : `claude/friendly-darwin-abyzn0` (le prototype était sur `claude/tender-darwin-kp4cth`).
- **Source de vérité du plan** : `docs/IMPLEMENTATION_PLAN.md` (phases P0 à P20, points tranchés Q1 à Q99, suivi §8).
- **Pull requests vers `main`** :
  - PR #1 : branche du prototype (`claude/tender-darwin-kp4cth`). On ne sait pas si elle a été fusionnée.
  - PR #2 (fusionnée le 11/11) : toute la branche d'un coup, soit 156 commits environ. Elle contient P0 à P17, l'app mobile, le dossier Play Store, le plan iOS, le README, les corrections de CI et la section « Bientôt disponible ».
  - PR #3 (11/11) : audit SEO automatique des pages publiques, baseline Larastan, CI réparée.
  - PR #4 (11/11) : audit SEO des pages dynamiques, CRUD des BD, inscription en 3 étapes, âge de 18 ans, validation de tous les comptes, CRUD manquants, référentiels administrables.
  - PR #5 (11/11) : CRUD du manuel du pair animateur·rice et lecture des outils CCC sur le web (`/ccc`).
  - PR #6 (11/11) : « pair animateur·rice » partout, test `CreatePollModal` stabilisé.
  - PR #7 (11/11) : lecture des outils CCC dans l'app Flutter.
  - PR #8 (11/11) : charte graphique PDF régénérée avec « pair·es animateur·rice·s ».
  - PR #9 (12/11) : lecture des outils CCC hors ligne, sélecteur d'émojis, plusieurs images, vidéos relues, sondages dans l'app, vault SDLC, PRD de P18 et P19.
  - PR #10 (12/11) : pilote P19 (8 BD), tableau de bord d'administration P18-01 à P18-14, corrections Larastan.
  - PR #11 (12/11) : début du lot 2 des BD, passerelles Facebook et Telegram (P19-11), conversations en BD P20 (lots A et B).

## 2. Chronologie

### 15/10/2026 : prototype (avant les messages de la session)
- Commits sur le prototype : stock retiré, templates e-mails, sondages, newsletter, sortie rapide retirée, favicons, simulateur mobile, passerelles (D4-bis), décision D9 (Vue 3 + Inertia), charte PDF corrigée.
- Fait : la page d'accueil publique devient l'index du prototype.

### 17/10/2026
- Demande : mettre à jour le clone local avec la branche du prototype. Renommages : `landing.php` devient `index.php`, l'ancien fil devient `fil.php`.
  - Fait : branche vérifiée, prototype lancé, consignes de clonage données. Aucune modification de code.
- Demande : « On passe du prototype vers l'implémentation… établir le plan d'implémentation complet et détaillé avec les tâches et lance-toi ».
  - Fait : plan v1.1 relu de façon contradictoire, socle Laravel 12 (P0), dictionnaire de données, schéma complet (P1 : 94 migrations, 57 modèles, 47 enums).
  - Fait : identité et accès (P2 : OTP, API Sanctum, policies, audit), 539 tests au vert.

### 18/10/2026
- Demande : « Try again » trois fois, après des interruptions techniques.
  - Fait : reprise du travail. P3 terminé, puis les vagues A à E (site public P4, alertes, SOS et signalement P5), P6 (socle Vue et API membre), vague H (fil, messagerie, groupes).
- Demande : « on continue, on finalise les tâches », et utiliser le skill Caveman pour économiser les jetons.
  - Fait : les plugins Caveman ont été proposés mais pas installés. Le travail a continué.

### 19/10/2026
- Demande : ajouter un mode maintenance (écran admin, page publique premium) qui bloque le public et laisse l'accès à l'administration.
  - Fait : P13-06 à P13-08 livrés avec la vague K. SOS, signalement et urgences restent ouverts pendant la maintenance.
- Demande : « lets go », phases 9 (espace pair, synchronisation) et 10 (socle admin, tableau de bord).
  - Fait : vague I (événements, forums), vague J (profil, paramètres), vague L lancée.
- Demande : « Pause ».

### 20/10/2026
- Demande : pause (« mes tokens s'épuisent »), puis « tu peux relancer », puis « continue et termine les tâches ».
  - Fait : vagues L et M (dossiers et API pair, synchronisation, socle admin, tableau de bord, alertes, indicateurs, rapports).

### 21/10/2026
- Demande : « continue », puis « PAUSE de toutes les tâches en arrière-plan ».
  - Fait : la vague N (P11, P12, P13-01 à 05) est enregistrée comme travail en cours, puis mise en pause.

### 26/10/2026
- Demande : résumé des tâches restantes, puis une stratégie pour finir à 100 % en économisant environ 80 % des jetons.
  - Fait : stratégie adoptée, un agent par étape, sans revue de routine, avec les tests ciblés puis la suite complète une seule fois.
- Demande : « go étape 1 ».
  - Fait : phases 11 à 13 vérifiées.
- Demande : « go étape 2 ».
  - Fait : phase 14 (modèles d'e-mails, newsletter, envoi par lots, connecteurs).
- Demande : « go étape 3 ».
  - Fait : phase 17 (runbook, déploiement cPanel, scripts).
- Demande : corriger le petit écart du signal de vie, puis « go étape 4 ».
  - Fait : signal de vie lié au succès de l'escalade et du rejeu. Lot mobile A (socle, connexion, accueil).
- Demande : « go lot B », puis « go lot C ».
  - Fait : lot B (SOS, signalement, fil, communauté), lot C (profil, PIN, réglages, push).

### 27/10/2026
- Demande : « corrige le bogue puis go étape 5 ».
  - Fait : le détail d'un événement ne plante plus dans l'app. Phase 16 faite (sécurité, accessibilité, performance, lint des textes).
- Demande : Larastan, mise à jour finale du plan et état des lieux. Réponse : « OUI ».
  - Fait : Larastan dans le fichier de verrou, statuts exacts du plan, `ETAT_DES_LIEUX.md` et `DECISIONS_A_VALIDER.md`.
- Demande : « le projet est terminé ? tout est fonctionnel à 100 % ? et Flutter ? »
  - Fait : réponse honnête. Le projet n'a jamais été testé par un humain : pas de vrais SMS, push, SMTP ni IA, et aucun APK ni IPA compilé.
- Demande : guide de lancement local et de recette. Réponse : « ok ».
  - Fait : `docs/GUIDE_RECETTE.md`.
- Demande : l'assistant répond-il avec Gemini ou OpenAI ? Comment naviguer sans rechargement avec Livewire (texte collé sur `wire:navigate`) ?
  - Fait : spec de la navigation sans rechargement et de l'assistant LLM. L'assistant répond avec la base de connaissances, sans IA branchée.
- Demande : navigation de l'admin sans rechargement. Réponse : « OUI ».
  - Fait : `wire:navigate` dans l'admin (P10-09).
- Demande : corriger d'abord les bogues, en commençant par la recherche sensible aux accents. Réponse : « OUI ».
  - Fait : 4 bogues corrigés (accents, libellés bruts, boîte d'envoi, CSP en dev).

### 28/10/2026
- Demande : quelles sont les décisions en attente ? Puis une feuille de décision courte. Réponse : « OUI ».
  - Fait : `docs/FEUILLE_DE_DECISION.md`, avec une recommandation par question.
- Demande : décisions groupées.
  - Q38 : faire la maquette mobile de l'espace pair, et aussi celle de l'admin. Fait : maquettes dans `prototype_mobile/`.
  - Q16 « OUI », Q61 « 8 secondes », Q89 « OUI », Q56 « on maintient ».
  - Q58/Q64 « déjà validé », Q68 « 15 ans ».
  - Q57 « purger dès l'envoi », avec un design premium des modèles d'e-mail.
  - Fait : CTA « Créer un compte », SOS à 8 s, purge de `mail_outbox` après l'envoi, e-mails premium.
- Demande : que fait l'assistant face à une question hors de la base ? Où se trouve Q54 sur le site ?
  - Fait : explication du repli de l'assistant. Q54 est la section `#alerte` de l'accueil.
- Demande : ajouter un contrôle de l'âge minimal à l'inscription. Q54 « OUI ».
  - Fait : case d'âge minimal à l'inscription, section `#alerte` en rouge très clair.
- Demande : Q7. Push « Firebase, gratuit » ; SMS avec Nexmo (Vonage) et Twilio ; Gemini comme LLM.
  - Fait : push FCM HTTP v1, SMS Vonage et Twilio, Gemini codé mais désactivé par défaut tant qu'il n'y a pas d'avis juridique.
- Demande : commandes complètes pour la recette humaine, puis les commandes pour cloner et lancer sous Windows.
  - Fait : guide réécrit, checklist cochable, scripts de lancement, comptes de démonstration par rôle.
- Demande : sortie collée de `composer install` (avertissements PSR-4).
  - Fait : avertissements sans danger, ce sont des classes d'aide dans des fichiers de test.
- Demande : MySQL au lieu de `database.sqlite`.
  - Fait : `migrate:fresh` sous MySQL, code OTP lisible.
- Demande : textarea de publication mal dimensionné ; règle des 15 ans sur mobile ; menu repliable dans le prototype mobile (membre, pair, admin).
  - Demande aussi : thème clair par défaut, sans bascule de thème ; carrousel de splash plein écran avec les nouvelles images ; comment envoyer le dossier `images`.
  - Fait : spec et prototype mobile v2 (menu repliable, splash plein écran dans le prototype et dans Flutter, case 15 ans, textarea corrigé).
- Demande : push d'images refusé (sortie Git collée).
  - Fait : commits « Ajout des images » intégrés.
- Demande : retirer le texte « Ton vrai nom n'est jamais demandé » ; le bouton ☰ manque ; ruban arc-en-ciel sur la connexion et l'inscription ; tous les rôles passent par la même connexion.
  - Fait : texte retiré, menu ☰ fixe, ruban arc-en-ciel, connexion corrigée.
- Demande : ajouter les images `design-system-1..4` et `xomiton-splash-1..6` à la charte et mettre à jour le PDF. Licence : « j'ai la licence, feu vert ».
  - Fait : charte graphique v1.1 et PDF régénéré.

### 29/10/2026
- Demande : la licence couvre les images IA (« OUI ») ; garder splash-4 et splash-5 (« on les garde dedans ») ; proposer des textes d'onboarding.
  - Demande aussi : plan de l'app mobile pour pair et admin et finalisation membre, « tout en un » ; visuels Play Store premium (texte collé : consignes Google Play sur l'image de présentation, les captures et la vidéo).
  - Fait : plan de l'app mobile unique, dossier Google Play (visuels, générateur, fiche).
- Demande : « NON, une seule appli ». Une connexion pour tous ; inscription réservée aux membres, validée par l'admin ; rôles cumulables ; espace pair dans l'app ; périmètre admin élargi ; l'admin est aussi membre.
  - Fait : maquettes mises à jour, rôles cumulables, invitations à l'équipe, rôle pair attribué, feuille de décision corrigée.
- Demande : conflit 409, « le pair tranche » ; position SOS arrondie, « OK » ; push SOS « admins et pairs ».
  - Fait : commits correspondants (SOS et alertes, conflit 409 tranché par le pair).
- Demande : une seule version Play, maquettes à valider avec corrections ; pages légales et suppression de compte web.
  - Demande aussi : 15 ans, gratuit, tous pays ; tranches « 16-17 » et « 18+ » ; français seul ; fiche en DOCX.
  - Fait : pages légales publiques, suppression de compte web, fiche Play en DOCX.
- Demande : donner les champs « À COMPLÉTER PAR LE CLIENT » et les 12 variables `XOMITON_LEGAL_*` ; convertir la fiche en PDF.
  - Fait : versions PDF (Markdown et DOCX).
- Demande : arbitrages D-MOB-4, 10, 15, 16, 17 et 18. Réponse : « ALLONS Y ». Puis « je valide les maquettes », et les 6 textes d'onboarding sont validés.
  - Fait : recommandation retenue (escalade nationale à 5 min, etc.). Lots mobiles P15-10 à P15-24 (multi-espaces, sécurité, synchronisation, espace pair, espace admin, push d'équipe, release Android).
  - Fait aussi : API admin mobile et effacement à distance.
- Demande : garder la photo « bouche scotchée » (« OUI ») ; valeurs légales dans `.env.example` ; créer `GUIDE_DEPLOIEMENT.md`, `INSTALLATION_LOCALE.md` et `PLAN_FINALISATION.md` ; pousser.
  - Fait : les trois documents, le bloc légal vide dans `.env.example`, la commande `xomiton:install`.
- Demande : erreur collée de `migrate:fresh --seed` sous MySQL (1366 « Incorrect string value »).
  - Fait : `MetaText` ne coupe plus « à ». Seed vérifié sur MariaDB.
- Demande : retirer des sections de la connexion (web et Flutter) ; étape 1 d'inscription trop longue ; retirer « (facultative) ».
  - Fait : connexion sans comptes de démo, étape 1 compacte, stepper sans « facultative ».
- Demande : ruban arc-en-ciel sur le web (« OUI ») ; stepper Flutter sans « facultative » (« OUI »).
  - Fait : `x-auth-decor` avec les jetons `--xm-rainbow-*`, stepper Flutter corrigé.

### 31/10/2026
- Demande : justifier les textes de la fiche Play et corriger les accents ; accueil lent qui se recharge ; commandes et `.env` de production.
  - Fait : documents Play corrigés et PDF reproductibles, modèle `.env` de production.
  - Fait aussi : pages invitées sans session ni cookie, cache HTTP, Speculation Rules, transitions de vue.

### 02/11/2026
- Demande : comment as-tu produit l'image de présentation et les 8 captures, et avec quels skills ? Puis « ok merci ».
  - Fait : explication donnée.
- Demande : inventaire de ce qui reste à faire (local, web, Play Store) dans un fichier md.
  - Fait : `docs/RESTE_A_FAIRE.md`.

### 03/11/2026
- Demande : même question en anglais, pour un autre projet ; « tout documenter et pousser » ; commandes de clonage.
  - Fait : `docs/GUIDE_VISUELS_PLAY_STORE.md`, poussé.

### 09/11/2026
- Demande : extraire un kit générique des visuels Play ; plan iOS ; propositions de domaine.
  - Fait : kit `tools/playstore-kit`, `docs/PLAN_IOS.md`.
- Demande : « JE PRENDS » `xomiton.app` ; corriger les limites du kit ; donner les décisions iOS.
  - Fait : domaine dans les exemples et la doc ; kit avec Inter embarqué, icône 512 et captures tablette.
- Demande : décisions D-IOS-1 à 12, « j'accepte ta recommandation » ; README de présentation sans commandes, avec contact commercial ; remplacer `xomiton.bj` par `xomiton.app` partout.
  - Fait : décisions enregistrées, README et aperçu social, remplacement partout.
- Demande : identifiant `app.xomiton.xomiton` ; aperçu social en vraies captures, thème clair, vrai logo, vrai slogan, vraies fonctionnalités.
  - Fait : identifiant changé, aperçu social refait.
- Demande : visuels Play avec le vrai slogan et le vrai logo ; aligner les marges de l'en-tête et du pied sur le corps.
  - Fait : visuels refaits ; jetons `--xm-container-max` et `--xm-gutter`.
- Demande : corriger l'espace membre en largeur de téléphone (« OUI ») ; décaler la 1re capture (« OUI ») ; garder la photo de deux femmes (« OUI »).
  - Fait : en-tête sur une ligne, avatars neutres en initiales, capture recadrée.
- Demande : garder l'image comme image de présentation et comme aperçu social ; ajouter des puces de fonctionnalités (Alerte VBG, SOS, actualités, communauté…).
  - Demande aussi : « ne pas supprimer les anciennes images », et garder l'ancienne `feature-graphic-1024x500`.
  - Fait : puces ajoutées, anciennes versions archivées, variante fond bleu gardée.
- Demande : corriger le bouton SOS et la carte de l'assistant ; libérer la place quand le clavier est ouvert ; nouvelles captures.
  - Fait : bouton SOS dans une bande réservée, plus aucun recouvrement, saisie libérée avec le clavier ouvert.
- Demande : pousser et donner les commandes de clonage ; « et le projet iOS ? ».
- Demande : « QUOI FAIRE », avec la sortie de `composer install` (lock pas à jour).
  - Fait : `composer.lock` mis à jour (empreinte seule). Conseil : ne pas lancer `composer update`.
- Demande : étape suivante ; finir les étapes 1 et 2 pour vérifier le site ; « ALLONS Y » (domaine, 12 informations légales, Apple Developer D-U-N-S, avis juridique).
  - Fait : dossiers des démarches client (PDF), domaine, informations légales, Apple Developer, avis juridique.

### 10/11/2026
- Demande : le client donne les informations légales, le domaine de production et l'e-mail de contact.
  - Fait : valeurs gardées hors du dépôt (`.env` du serveur seulement). Bloc `.env` préparé. Le numéro de téléphone est à vérifier.
- Demande : « et le projet iOS ».
  - Fait : projet iOS écrit sans Mac, non compilé (iPhone seul, entitlements, manifeste de confidentialité, plugin). Push iOS, liens universels, filtre minimal de contenus.
- Demande : les modérateur·rices gardent le filtre de contenus (« OUI ») ; un lien d'invitation sans l'app ouvre la « page de connexion ».
  - Fait : permission `moderation.filter`, repli web vers `/connexion`.
- Demande : README comme un autre projet de référence ; sans commandes, avec `social-preview.png` et des captures légendées.
  - Fait : README avec bannière et légendes, aucune commande.
- Demande : fusion dans `main` par PR ; surveiller la CI de la PR #2 (« OUI »).
  - Fait : la PR #2 existait déjà. CI corrigée (PHPStan 2, Vitest, Android desugaring, `file_picker` 10.x, manifeste).
- Demande : section « Bientôt disponible sur toutes les plateformes » avec les badges App Store et Google Play (SVG fournis).
  - Fait : section ajoutée sur l'accueil.

### 11/11/2026
- Demande : fusionner la PR ; test SEO complet (Lighthouse > 90, H1-H4, OG/Twitter, alt, robots, sitemap, JSON-LD).
  - Fait : PR #2 fusionnée ; audit SEO automatique, mesures Lighthouse.
- Demande : « oui, ouvre la PR », « surveille et corrige la CI », « fusionne quand c'est vert ».
  - Fait : baseline Larastan, CI verte, PR #3 fusionnée.
- Demande : auditer aussi les pages dynamiques ; ouvrir la PR et surveiller la CI.
  - Fait : hiérarchie H1-H4 stricte, intégrés à la PR #4.
- Demande : l'admin a-t-il le CRUD des BD ? Analyser l'ancien formulaire d'inscription (HTML collé), sans rien faire.
  - Fait : analyse et comparaison données.
- Demande : « FAUT LE FAIRE ». CRUD des BD complet ; inscription comme l'ancien fichier (3 étapes, un seul envoi, compte en attente de validation par l'admin, admin prévenu). Allié·es dès l'inscription « OUI », prénom et nom « OUI », profil obligatoire « OUI ».
  - Fait : CRUD des BD, inscription en 3 étapes (web, API, app), écran d'attente, admins notifiés.
- Demande : d'autres CRUD incomplets ? Âge « C'EST 18 ANS » ; données collectées confirmées ; statut d'allié·e dans la notification « ON GARDE » ; « TOUS VALIDÉS PAR L'ADMIN ».
  - Fait : âge minimal à 18 ans, validation de tous les comptes, fiche Play alignée.
- Demande : « Oui, lance les CRUD manquants » ; tranches d'âge « à partir de 18 ans » ; « on doit saisir l'âge » ; invitations « D'ACCORD » ; référentiels « ADMINISTRABLES, zones non limitées au Bénin ».
  - Fait : boîtes à images, lois, planning de garde, tags newsletter, fiches VBG/VDH en modification, zones multi-pays, écran Référentiels.
- Demande : fusionner la PR.
  - Fait : PR #4 fusionnée.
- Demande : « oui, fais le CRUD du manuel du pair animateur ». Vouvoiement de l'admin « OK », 500 caractères « OK », lecture des chapitres « il faut la créer ».
  - Fait : CRUD des modules du manuel, lecture des outils CCC sur le web (`/ccc`, API `ccc/`).
- Demande : ouvrir la PR, surveiller la CI, fusionner.
  - Fait : PR #5 fusionnée.
- Demande : « garde "pair animateur·rice" partout », puis PR et fusion.
  - Fait : vocabulaire changé partout, PR #6 fusionnée.
- Demande : lecture des outils CCC dans l'app Flutter, puis PR et fusion.
  - Fait : PR #7 fusionnée.
- Demande : « regénère la charte PDF avec pair animateur·rice », puis PR et fusion.
  - Fait : PR #8 fusionnée.

### 12/11/2026
- Demande : lecture hors ligne des outils CCC ; questions sur les sondages, images, vidéos et émojis ; commandes pour récupérer `main` sous Windows.
  - Fait : copie locale par compte, images sur disque, bandeau hors ligne, réponses et commandes données.
- Demande : sélecteur d'émojis (web et app), sondages dans l'app, vidéos relues en modération, plusieurs images. Réponse : « OUI » pour chacun.
  - Fait : `XmEmojiPicker` et `EmojiPickerSheet`, 4 images au plus, vidéo relue avant parution, sondages dans l'app (P7-08 à P7-11).
- Demande : émojis écartés, « garde » 💉 ; PRD de vérification des envois en masse et des modèles d'e-mail (texte collé : 2 pages).
  - Fait : 💉 rétablie. Analyse des écarts : `/admin/newsletter` et `/admin/emails` existent déjà.
- Demande : texte alternatif « FACULTATIF » ; vidéo sans sous-titres « D'ACCORD ».
  - Demande aussi : PRD et plan des contenus éducatifs et BD sur 24 sujets de violences numériques (liste collée), et refonte du tableau de bord admin (5 blocs collés).
  - Fait : vault SDLC, PRD P18 et P19, décisions D-IMGW-5 et D-IMGW-9.
- Demande : réponses Q97 et Q98 (partage public, 3 types de contenu, catégories, pilote de plus de 6 sujets, BD publiques et dans le fil, références légales par l'administration, badges, réponse validée, annonces). Réponse : « FUSIONNE ».
  - Fait : PR #9 fusionnée. Pilote P19 (8 BD, 48 illustrations, bible des personnages). P18-01 à P18-14.
- Demande : quel outil de génération d'images as-tu utilisé ?
  - Fait : connecteur Figma (génération d'images), crédits du plan Figma du client.
- Demande : ouvrir la PR et fusionner quand c'est vert.
  - Fait : PR #10 fusionnée.
- Demande : « génère les BD des 15 sujets restants ».
  - Fait : 21 images sur 90 faites, puis crédits Figma épuisés. Note de reprise dans `design-system/bd/LOT2_REPRISE.md`. Reprise automatique programmée le 13/11 à 00:15 UTC.
- Demande : passerelles Facebook et Telegram (P19-11) ; spec « Conversation en BD » (bulles).
  - Fait : diffusion des BD publiques par API et calendrier de diffusion ; PRD `conversation-en-bd.md`.
- Demande : conversations « PUBLIQUES ET INDEXÉES » ; proposer les 12 modèles ; pas de dessins simples ni de bonshommes bâtons, mais des BD avec bulles ; membres créateur·rices dès la v1 « OUI » ; export « PDF et PNG » ; tutoriel Mangakoaching en référence.
  - Fait : grammaire des bulles, 12 modèles, PRD révisé.
- Demande : personnages « JE DISAIS OUI ET NON » ; créations anonymes par défaut « OUI » ; lancer la première version « OUI ».
  - Fait : casting repris et 4 personnages ajoutés (Q99). P20 lot A (données, rendu SVG, pages publiques) et lot B (éditeur, modération, exports PDF et PNG).
- Demande : ouvrir la PR et fusionner quand c'est vert.
  - Fait : PR #11 fusionnée.
- Demande : commandes pour cloner en local et journal compact des échanges.
  - Fait : commandes PowerShell (clone, `composer install`, `npm install`, `.env`, `migrate --seed`, imports BD et conversations, `npm run build`, `serve`, Flutter) ; ce journal (`docs/JOURNAL_DES_ECHANGES.md`).

## 3. Décisions du client

| Sujet | Décision | Date |
|---|---|---|
| Accueil du prototype | `landing.php` devient `index.php`, le fil devient `fil.php` | 17/10 |
| Mode maintenance | Public bloqué, admin ouvert, page publique premium | 19/10 |
| Q16 CTA de l'accueil | « Créer un compte » | 28/10 |
| Q61 GPS avant SOS | 8 s au total, puis envoi sans position | 28/10 |
| Q89 lien Maps d'une alerte | Gardé, avec l'avertissement | 28/10 |
| Q56 tutoiement | « tu » dans l'assistant et le SOS, « vous » sur le site public | 28/10 |
| Q58/Q64 FAQ et quiz | Maintenus, déjà validés | 28/10 |
| Q57 e-mails en file | Purgés dès l'envoi ; design premium des modèles d'e-mail | 28/10 |
| Q54 bande rouge `#alerte` | Fond rouge très clair avec bordure ; rouge plein réservé au SOS | 28/10 |
| Q7 fournisseurs | Push Firebase (FCM) ; SMS Vonage et Twilio ; Gemini prévu, désactivé avant l'avis juridique | 28/10 |
| Base de données | MySQL, pas SQLite | 28/10 |
| Prototype mobile | Menu repliable, thème clair par défaut, sans bascule de thème, splash plein écran | 28/10 |
| Images et licence | Licence couvre les images IA ; splash-4 et splash-5 gardées ; « bouche scotchée » gardée | 28/10-29/10 |
| Application mobile | Une seule appli, une connexion ; rôles cumulables ; inscription réservée aux membres et validée par l'admin ; espace pair en v1 ; l'admin est aussi membre | 29/10 |
| Conflit 409 | Le pair tranche lui-même | 29/10 |
| Push SOS | Admins et pair·es (zone, puis national à 5 min) ; position arrondie pour les admins | 29/10 |
| Google Play | Une version, gratuite, tous pays, français seul, tranches 16-17 et 18+ (âge 15 ans à ce moment) | 29/10 |
| D-MOB-4, 10, 15, 16, 17, 18 | Recommandations retenues (« ALLONS Y ») | 29/10 |
| Maquettes et onboarding | Maquettes validées ; 6 textes d'onboarding validés | 29/10 |
| Ruban arc-en-ciel | Sur la connexion et l'inscription, web et app | 29/10 |
| Stepper d'inscription | Sans « (facultative) », web et Flutter | 29/10 |
| Domaine | `xomiton.app` ; `xomiton.bj` remplacé partout | 09/11 |
| Identifiant d'app | `app.xomiton.xomiton` | 09/11 |
| D-IOS-1 à 12 | Recommandations retenues (âge et chiffrement soumis au conseil juridique) | 09/11-10/11 |
| Visuels | Vrai logo, vrai slogan, vraies captures en thème clair ; puces de fonctionnalités ; photo de deux femmes gardée | 09/11 |
| Filtre de contenus | Les modérateur·rices gardent sa gestion | 10/11 |
| Lien d'invitation sans app | Repli vers la page de connexion | 10/11 |
| README | Description, aperçu social, captures légendées ; aucune commande | 10/11 |
| Inscription | Ancien formulaire en 3 étapes, envoi unique ; allié·es dès l'inscription ; prénom et nom ; profil obligatoire | 11/11 |
| Q68 âge minimal | 15 ans (28/10), puis **18 ans** ; l'âge est saisi ; tranches du profil à partir de 18 ans | 11/11 |
| Validation des comptes | Tous les nouveaux comptes validés par l'admin ; rôle d'invitation appliqué à la validation | 11/11 |
| Notification aux admins | Le statut d'allié·e reste affiché | 11/11 |
| Référentiels | Administrables ; zones non limitées au Bénin | 11/11 |
| Admin du manuel | Vouvoiement ; description limitée à 500 caractères ; lecture des chapitres à créer | 11/11 |
| Vocabulaire | « pair animateur·rice » partout | 11/11 |
| Publications | Sélecteur d'émojis, sondages dans l'app, vidéos relues, plusieurs images (web et app) | 12/11 |
| Émojis | Liste écartée appliquée, mais 💉 gardée (dépistage, soins) | 12/11 |
| Texte alternatif | Facultatif | 12/11 |
| Vidéos | Sans sous-titres ni vignette ; la description obligatoire sert de transcription courte | 12/11 |
| Q97 rôles | Pas de rôle « bénévole écoutant·e » : ce sont les pair·es approuvé·es | 12/11 |
| Q97 badges | « Membre vérifié » attribué par l'admin ; badge « Pair animateur·rice » (le client a écrit « Pair éducateur », à confirmer) | 12/11 |
| Q97 réponse validée | Par l'auteur·rice du sujet, et la modération | 12/11 |
| Q97 annonces | Uniquement dans l'app et l'espace social | 12/11 |
| Bannissement | Statut définitif, motif obligatoire (appliqué par défaut, à confirmer) | 12/11 |
| Q98 images IA | Générées via Figma, partage public autorisé | 12/11 |
| Q98 contenus | BD, fiche de connaissances, visuel de partage ; 6 catégories, 23 sujets, sans fusion | 12/11 |
| Q98 pilote | Plus de 6 sujets : 8 produits | 12/11 |
| Q98 diffusion | BD publiques, indexées, publiées dans le fil social | 12/11 |
| Q98 références juridiques | Fournies et validées par l'administration de Xomiton | 12/11 |
| Q99 format | BD illustrées avec bulles ; pas de bonshommes bâtons, de symboles ni de code couleur | 12/11 |
| Q99 diffusion | Conversations publiques et indexées | 12/11 |
| Q99 modèles | 12 modèles proposés par Claude et validés | 12/11 |
| Q99 casting | « Oui et non » : personnages des BD repris (Afi, Sena, Kossi, Mariam, Tantie Rosine, Nadia), plus 4 nouveaux (Fifamè, Yasmine, Ablavi, Joël) | 12/11 |
| Q99 créations | Membres créateur·rices dès la v1 ; créations anonymes par défaut | 12/11 |
| Q99 export | PDF et PNG | 12/11 |
| Ordre des images | Les 15 BD restantes d'abord, puis les 60 cases des conversations | 12/11 |

## 4. Livrables par phase

Les statuts viennent du plan (§3 et §8).

- **P0 Socle** : partiel. Projet, gabarits, 24 composants `x-*`, Larastan et CI faits. Restent les jobs e2e et Lighthouse en CI, `APP_PREVIOUS_KEYS` et la relecture du README.
- **P1 Données** : fait (migrations, modèles, enums, seeders, import du prototype, rétention, ré-encryption).
- **P2 Identité et accès** : fait. L'inscription est en 3 étapes depuis le 11/11.
- **P3 Noyau transverse** : partiel. Restent `notifications:digest` et `alerts:anonymize`.
- **P4 Site public** : codé, en recette. Restent la recette visuelle aux 4 largeurs et la validation des textes.
- **P5 Alertes, SOS, signalement** : fait, recette terrain en attente.
- **P6 API et coquille Vue** : partiel (P6-03, P6-04).
- **P7 Fil et sondages** : partiel. Émojis, images multiples, vidéos relues et sondages dans l'app livrés le 12/11.
- **P8 Communauté et profil** : partiel. Les outils CCC en lecture sont livrés le 11/11 (P8-12).
- **P9 Espace pair et synchronisation** : partiel (P9-04, P9-08).
- **P10 Admin, socle et pilotage** : partiel (P10-01, P10-06). Navigation sans rechargement faite.
- **P11 Admin, données** : fait. Modification d'une fiche livrée le 11/11.
- **P12 Admin, plateforme** : fait. CRUD des BD, boîtes, lois, manuel et référentiels ajoutés les 11/11.
- **P13 Admin, système** : fait, mode maintenance compris.
- **P14 Communication** : codé, en recette. Il manque la recette d'une vraie campagne et d'une vraie publication Facebook ou Telegram.
- **P15 Mobile Flutter** : app unique codée (membre, pair, admin d'astreinte, CCC hors ligne, médias du fil). Jamais compilée en APK sur un vrai appareil. iOS écrit mais non compilé.
- **P16 Qualité** : partiel. Restent la couverture, Firefox/WebKit, Lighthouse CI, les appareils et les lecteurs d'écran.
- **P17 Déploiement** : partiel. Documents et scripts prêts. Rien n'a été fait sur le serveur.
- **P18 Tableau de bord admin** : fait (P18-01 à P18-14). Le libellé du badge est à confirmer.
- **P19 Violences numériques** : pilote de 8 BD intégré, et P19-11 (passerelles) codé, en recette. Lot 2 en cours (21/90 images). P19-10 et P19-12 restent à faire.
- **P20 Conversations en BD** : P20-01 et P20-02 faits ; P20-03 à P20-10 codés, en recette. P20-11 (illustrations) et P20-12 (phase 2) restent à faire.

## 5. Règles permanentes et conventions

### Règles demandées par le client
- Jamais de secrets dans le dépôt : mots de passe, clés d'API, jetons, clé APNs, `google-services.json`. Ils ne passent jamais non plus par la conversation.
- Les valeurs juridiques réelles vont seulement dans le `.env` du serveur. `.env.example` garde des champs vides.
- Ne jamais supprimer d'images ni de travail : les anciennes versions sont archivées.
- Pas de pull request ni de fusion sans demande explicite.
- Tout en français. Interface en français seul pour l'instant.
- Dire « pair animateur·rice » partout.
- Tutoiement dans l'assistant, le SOS et l'app ; vouvoiement sur le site public et dans l'admin.
- Thème clair par défaut, sans bascule de thème dans le prototype mobile.
- Toujours le vrai logo, le vrai slogan, et de vraies captures en thème clair.
- README sans commandes de clonage ni d'exécution.
- Coordonnées personnelles du client privées : ne jamais les reprendre.
- Écriture simple et inclusive : on parle de personnes actrices, jamais d'un mot qui les réduit à ce qu'elles ont subi.

### Conventions techniques
- Travailler sur la branche `claude/friendly-darwin-abyzn0`. Messages de commit en français. Toujours commiter avec des chemins explicites.
- Tests web : `env -u APP_ENV -u GEMINI_API_KEY vendor/bin/pest` (copier `.env.example` en `.env` pour la suite complète), puis Pint.
- Larastan niveau 6 : il tourne en CI avec `web/phpstan-baseline.neon`. Une entrée périmée fait échouer la CI.
- Front : Vitest avec `LARAVEL_BYPASS_ENV_CHECK=1`, `vue-tsc`, `npm run build`. Budgets : `public.js` < 60 Ko gzip, pages membres < 150 Ko.
- Textes : `php artisan lang:export` puis `--check` (produit `fr.json` et `app_fr.arb`), `lang:lint`, `npm run emoji:check`. Les textes en attente de l'app utilisent des clés `mobile_*`.
- Mobile : `flutter analyze` et `flutter test`.
- Jobs de CI : « Pint · Larastan · Pest », « Vitest · vue-tsc · build Vite », « Flutter · analyze · test », « Android · APK debug ».
- Images des BD : WebP de 1200 px, 100 Ko au plus ; visuel de partage en 1080 × 1080.
- Langues : `lang/fr` en source unique ; anglais, fon et yoruba prévus plus tard.
- Contraintes cPanel : file d'attente en base, cache fichier, cron chaque minute, pas de Redis ni de WebSocket.

## 6. Ce qui reste à faire et à valider

### Production des contenus
- Reprendre les 15 BD restantes du lot 2 le 13/11 à 00:15 UTC, quand les crédits Figma reviennent. Il manque 69 images ; la procédure est dans `design-system/bd/LOT2_REPRISE.md`.
- Ensuite, produire les 60 illustrations des 12 conversations en BD (P20-11).
- P19-10 : visuels de partage (1080 × 1080 et OG 1200 × 630).
- P19-12 : kit de séance CCC.
- P20-12 : phase 2 (passerelles, app, statistiques).

### À faire valider par l'association
- Relire les textes et les images des BD, ainsi que les points des fiches de connaissances.
- Fournir les références juridiques béninoises (section `legal` encore vide).
- Relire les 12 modèles de conversation.
- Conversations en BD : relecture santé de M11, relecture de sensibilité de M03, M05 et M08 avant publication.
- Libellé du badge : « Pair animateur·rice » ou « Pair éducateur ».
- Règles du bannissement : statut définitif, réservé à l'admin et au superadmin, levée par le superadmin seulement.
- Faire relire les textes légaux par le conseil juridique : âge, données collectées, confidentialité. Il faut aussi l'avis juridique avant de brancher Gemini.
- Informations légales encore manquantes : DPO et juridiction. Le numéro de téléphone est à vérifier.

### À vérifier en vrai
- Déployer sur le serveur cPanel : assainissement, cron, supervision, sauvegardes, HTTPS, domaine `xomiton.app`.
- Tester sur MySQL 8. Les migrations n'ont été rejouées que sur MariaDB.
- Tester sur de vrais téléphones : premier APK Android, Firebase et `google-services.json`, Play Console.
- iOS : premier build sur Mac, compte Apple Developer d'organisation (D-U-N-S), App Store Connect.
- Lecteurs d'écran et recette humaine complète (`docs/GUIDE_RECETTE.md`).
- Jetons Facebook (page) et Telegram (BotFather) à fournir, pour une vraie publication par les passerelles.
- Vrais SMS (Vonage ou Twilio), vrai push, SMTP.
- Restes techniques : `notifications:digest`, `alerts:anonymize`, jobs e2e et Lighthouse en CI, couverture de tests.
