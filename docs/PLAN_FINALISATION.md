# Plan de finalisation de Xomiton : de l'état réel du dépôt à l'ouverture au public

Document du 29 octobre 2026. Il dit **honnêtement** où en est le projet, ce qui reste à faire, **qui** doit le faire (client ou développeur), dans quel **ordre** et à quelle **condition** une étape est considérée comme terminée. Il s'appuie sur [ETAT_DES_LIEUX.md](ETAT_DES_LIEUX.md) (photographie du 27/10/2026), [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md), [PLAN_MOBILE_APP_UNIQUE.md](PLAN_MOBILE_APP_UNIQUE.md), [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) et [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md), relus le 29/10/2026. Il ne modifie aucun de ces documents : là où ils divergent de ce plan (anciens chiffres de tests, espace pair mobile « sans code »), **ce plan fait foi pour les chiffres et l'avancement du mobile**, parce qu'il est plus récent.

Documents pour agir : [INSTALLATION_LOCALE.md](INSTALLATION_LOCALE.md) (installer chez soi), [GUIDE_RECETTE.md](GUIDE_RECETTE.md) (recette), [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) (mise en ligne), [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md), [RUNBOOK.md](RUNBOOK.md), [SECURITE_CHECKLIST.md](SECURITE_CHECKLIST.md), [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md), [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md).

## 1. En un coup d'œil

- **Le code des fonctions principales existe et est testé automatiquement** : environ 2 989 tests PHP, 784 tests de l'interface, 454 tests de l'application mobile.
- **Mais presque tout ce qui dépend du monde réel n'a jamais été essayé** : aucun SMS ni notification réels, aucun appareil Android, aucun serveur cPanel, aucune base MySQL 8, aucun lecteur d'écran réel. Les tests automatiques prouvent que le code fait ce que ses auteurs ont prévu ; ils ne prouvent pas que la plateforme protégera une personne en danger dans un vrai téléphone, sur un vrai réseau.
- **Le site ne peut pas encore ouvrir au public** : il manque les informations légales du client, l'avis juridique, les comptes (Firebase, SMS, Play), l'hébergement sain, la recette humaine et, pour le SOS, un essai de bout en bout avec de vrais canaux.
- **Le manque de code « premier super-administrateur » est comblé** : `php artisan xomiton:install` (voir §4, chantier C1, fait) sème les données de base et crée le premier compte ([GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §7.1).

## 2. Ce qui est terminé et vérifié

« Vérifié » signifie ici : exécuté par un test automatique ou une commande, pas relu seulement.

### 2.1 Chiffres des suites de tests

| Suite | Résultat | Remarque |
|---|---|---|
| Pest (PHP) | **2 989 tests** (comptés le 29/10/2026 par l'exécution complète) | ETAT_DES_LIEUX indiquait 2 755 ; ce plan met le chiffre à jour |
| Vitest (interface Vue) | **784 tests** (rejoués, 102 fichiers) | ETAT_DES_LIEUX indiquait 771 |
| Flutter (mobile) | **454 tests**, dont 13 scénarios « de bout en bout » contre un serveur simulé | ETAT_DES_LIEUX indiquait 151 ; chiffre du 29/10/2026 dans [PLAN_MOBILE_APP_UNIQUE.md](PLAN_MOBILE_APP_UNIQUE.md) |
| Playwright | 4 spécifications (`a11y`, `parcours`, `offline`, `violences`), Chromium seulement, **hors intégration continue** | |

Pest (2 989) et Vitest (784) ont été rejoués le 29/10/2026 pour ce document, tous verts. Le chiffre Flutter n'a pas été rejoué (pas de SDK Flutter ici) : il vient de la dernière exécution consignée.

### 2.2 Fonctions livrées et testées

- **Fondations** : données, rôles cumulables, authentification avec code de vérification (OTP), API v1 documentée ([API.md](API.md)), journal d'audit, chiffrement des colonnes sensibles, rotation de clé (`xomiton:reencrypt`), rétention et anonymisation.
- **Alertes** : SOS, signalement anonyme, besoins, routage et **escalade à 5 minutes** sans accusé (`alerts:escalate`), priorité d'envoi, accusé par lien signé, mode hors ligne du site.
- **Espace membre** (fil, sondages, messagerie, groupes, pages, événements, forums, profil, paramètres, assistant à base de connaissances, recherche) et **administration** complète (tableau de bord, alertes, indicateurs, rapports PDF/Excel/Word, registres, fiches VBG/VDH, utilisateurs, contenu, modération, passerelles, sauvegardes chiffrées, paramètres, maintenance, journal).
- **Pages légales** (mentions, confidentialité, conditions, cookies) et **suppression de compte** sans connexion (`/supprimer-mon-compte`) : codées, texte alimenté par les 12 variables `XOMITON_LEGAL_*`, 19 tests ; les informations réelles manquent (§4, C2).
- **Newsletter** (double confirmation, suivi, lots de 200 par minute), mode maintenance, supervision par signal de vie, sauvegardes (`backups:run`, `scripts/backup.sh`), `xomiton:preflight`.
- **Application mobile unique** (membre, pair, admin d'astreinte) : onboarding, connexion unique avec sélecteur d'espace, SOS natif, signalement, fil, messages, profil, code PIN, mode discret, synchronisation hors ligne idempotente avec conflits tranchés par le pair, collecte hors ligne, dossiers, alertes d'astreinte, inscriptions à valider, rôles et invitations, modération essentielle, maintenance, effacement à distance, push d'équipe côté code, configuration de release Android. Lots P15-10 à P15-24 « faits côté code » le 29/10/2026, **y compris l'espace pair et l'espace admin** (le plan d'implémentation les donnait encore sans code).
- **Qualité mesurée** : axe-core sans violation sur 12 pages publiques et 3 pages membre (clair, sombre, 360 et 1 280 px) ; `composer audit` sans alerte ; budgets de poids du JavaScript contrôlés à chaque build ; Larastan niveau 6 et Pint en intégration continue.
- **Documentation d'exploitation rédigée** : ce dossier, RUNBOOK, DEPLOIEMENT_CPANEL, SECURITE_CHECKLIST, PERFORMANCE, `scripts/deploy.sh`, `scripts/backup.sh`.

## 3. Ce qui est codé mais JAMAIS exécuté pour de vrai

C'est la zone de risque principale. Chaque ligne a du code et des tests avec simulation, **aucune preuve en conditions réelles**.

| Élément | Ce qui existe | Ce qui manque pour le croire |
|---|---|---|
| **SMS de secours du SOS** (Vonage, Twilio) | Pilotes codés, testés avec réponses HTTP simulées | Un compte, un vrai SMS reçu sur un téléphone béninois, expéditeur accepté par l'opérateur, coût connu. Aucun statut de livraison (accusé du fournisseur) n'est suivi : l'« acceptation » seule est journalisée |
| **Push** (Firebase FCM, mobile) | Envoi codé, enregistrement des appareils, canal Android « Astreinte », contenu neutre | Un projet Firebase, un téléphone réel, application fermée, délai < 30 s sur 4G (MOB-34) |
| **Web Push navigateur** | Pilote `log` seulement | Paquet Composer non installé et clés VAPID non générées : à décider, hors périmètre tant que non demandé |
| **Assistant Gemini (LLM)** | Fournisseur codé, **désactivé** (`ASSISTANT_LLM_ENABLED=false`) | Avis juridique d'abord (données de santé et de violences vers un tiers hors UE). À laisser éteint |
| **Application Android (APK, AAB)** | Configuration de release, icônes, écran de démarrage, intégration continue `mobile.yml` | **Aucun build Android n'a jamais été lancé** (pas de SDK dans l'environnement de rédaction) ; aucune exécution de la CI mobile n'est consignée. Lancement sur émulateur ou téléphone : non vérifié |
| **Recette sur appareil** (MOB-01 à MOB-37) | Tests Flutter et serveur simulé | Un vrai téléphone Android 8 à 15, parcours SOS, hors ligne, PIN, discrétion, notifications |
| **MySQL 8** | Migrations testées sous SQLite et rejouées sous MariaDB 10.11 | Une exécution sur MySQL 8 (le serveur visé : MySQL 8 ou MariaDB 10.6) ; un bogue d'index de plus de 64 caractères a déjà été corrigé (voir [INSTALLATION_LOCALE.md](INSTALLATION_LOCALE.md) §10) |
| **Hébergement cPanel** | `.htaccess`, `deploy.sh`, `backup.sh`, `preflight`, checklists | Rien n'a tourné sur le serveur : extensions, `proc_open`/`mysqldump`, `symlink()`, limite « Entry Processes », cron, temps de réponse |
| **Sauvegarde et restauration** | Commandes et procédure rédigées | Une restauration réussie sur le chemin de production |
| **Passerelles réseaux sociaux** | Pilote `log` et « publication manuelle » | Transport HTTP réel Facebook/Telegram non codé ; TikTok sans connecteur |
| **Lecteurs d'écran et appareils** | Axe-core automatique | NVDA, VoiceOver, TalkBack réels ; Firefox, Safari, 320 px |
| **Charge** | Limiteurs et budgets | Aucun test de charge (objectif : p95 < 600 ms sur `GET feed`, `POST sos`, `GET sync/pull`) |

## 4. Ce qui reste à faire, par ordre de priorité

Légende : **C** = client, **D** = développeur·se, **J** = conseil juridique, **H** = hébergeur. Un chantier est **terminé** quand son critère de sortie est atteint et consigné, pas avant.

### Priorité 1 : bloquants avant toute ouverture

| Id | Chantier | Propriétaire | Dépend de | Critère de sortie |
|---|---|---|---|---|
| **C1** | **Commande de création du premier super-administrateur** : **FAIT** — `php artisan xomiton:install` (seeders de base seulement, diagnostic, `--admin-email=`, mot de passe masqué ou généré, refus d'un second sans `--force`, audit `install.first_admin` ; [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §7.1) | D | — | Fait : 9 tests Pest (`InstallCommandTest`). Reste à rejouer à la recette : connexion complète (SMTP + code de vérification) avec un compte créé ainsi, sur base vierge en `production` |
| **C2** | **Informations légales** : les 12 variables `XOMITON_LEGAL_*` (éditeur, forme, enregistrement, adresse, directeur·rice, téléphone, hébergeur et son adresse et son pays, protection des données nom et e-mail, juridiction) et l'e-mail de contact public (réglage `contact_email` à remplacer ; valeur d'origine `contact@xomiton.bj`) | **C** | — | Les 12 valeurs fournies par écrit ; plus aucune mention « À COMPLÉTER » sur les quatre pages ; aucune valeur inventée |
| **C3** | **Avis juridique** : textes de confidentialité et conditions (Q67), âge minimal 15 ans / tranches Play 16-17 et 18+ (Q68), hébergement des données et transferts, distribution mondiale (pays hostiles aux personnes LGBTQ+), conditions d'emploi éventuel d'un LLM (Q7), conservation des données | **C** + **J** | C2 | Avis écrit ; textes ajustés si besoin ; décisions consignées dans `design-system/DECISIONS.md` |
| **C4** | **Hébergement sain** : compte cPanel neuf ou assainissement de l'existant, rapport d'incident signé ([DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §0), PHP 8.3 et extensions | **C** + **H** + **D** | — | Liste de contrôle cochée ; `php -m` joint au rapport |
| **C5** | **Comptes externes** : Firebase (projet, clé de compte de service, `google-services.json`), Vonage **ou** Twilio, SMTP, Healthchecks.io/UptimeRobot, stockage externe des sauvegardes (Q9), Google Play Console (compte développeur, 12 testeurs si compte personnel) | **C** | — | Identifiants remis par un canal sûr (jamais par e-mail en clair), consignés dans un coffre ; coûts connus. Gemini : **pas de compte** tant que l'avis juridique n'est pas rendu |
| **C6** | **Déploiement en recette sur cPanel** : sous-domaine, `.env`, `deploy.sh --first` rejoué **deux fois** sans étape manuelle non documentée, cron, signal de vie, sondes ; première mesure sur le serveur cible (p95, `proc_open`, `mysqldump`) | **D** + **H** | C4, C5 | `xomiton:preflight` sans échec ; [DEPLOIEMENT_CPANEL.md](DEPLOIEMENT_CPANEL.md) §1 à §5 cochés ; alerte de signal de vie reçue lors de l'essai |
| **C7** | **SOS de bout en bout avec de vrais canaux** (SMS, push, escalade à 5 minutes, accusé), sur de vrais téléphones | **D** + **C** | C5, C6 | Essais consignés : SOS reçu en moins de 30 s en 4G, SMS reçu, escalade constatée, accusé qui l'arrête (CHECKLIST_RECETTE §7 et §9) |
| **C8** | **Recette humaine du site** : toute la [CHECKLIST_RECETTE.md](CHECKLIST_RECETTE.md) (aucune ligne n'est encore cochée), par une personne du client, avec le [GUIDE_RECETTE.md](GUIDE_RECETTE.md) | **C** | C6 (ou version locale pour les premiers parcours) | Synthèse remplie, anomalies bloquantes et majeures corrigées, décision « acceptée / avec réserves / refusée » signée |
| **C9** | **Sauvegarde et restauration testées** sur le chemin de production | **D** + **H** | C6 | Restauration réussie ; RPO et RTO notés dans [RUNBOOK.md](RUNBOOK.md) §9 |
| **C10** | **Décisions bloquantes encore ouvertes** : textes d'accueil, aide, quiz, FAQ et autres lignes « Bloquant recette » de [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md) non marquées « Tranché » | **C** | — | Chaque ligne tranchée et consignée |

### Priorité 2 : avant la mise en production

| Id | Chantier | Propriétaire | Dépend de | Critère de sortie |
|---|---|---|---|---|
| **C11** | **Checklist de sécurité** : lignes « à vérifier à la recette » de [SECURITE_CHECKLIST.md](SECURITE_CHECKLIST.md) (sessions, antivirus des pièces jointes, TLS, IDOR, revue des journaux), test d'intrusion éventuel, `unsafe-eval` de la CSP | **D** + **C** | C6 | Toutes les cases tranchées, signature de la personne responsable |
| **C12** | **Commandes manquantes** `notifications:digest` (résumé quotidien/hebdomadaire : le réglage existe et n'a aucun effet) et `alerts:anonymize` (échéance de rétention des alertes à confirmer d'abord avec le client) | **D** | échéance de rétention à décider par **C**/**J** | Commandes planifiées, testées ; le réglage « résumé » a un effet visible |
| **C13** | **P11-02 : modification d'une fiche VBG/VDH existante** (création, détail, suppression et PDF existent ; l'édition manque) | **D** | Q92 | Édition possible avec contrôles et journal d'audit ; tests Pest |
| **C14** | **Clé de signature Android et premier build** : générer la clé de téléversement (sauvegardée hors dépôt, [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) §7), `flutter build apk --debug`, puis `flutter build appbundle --release` avec l'adresse https de production | **D** (clé gardée par **C**) | C5 | Un APK debug installé et connecté à l'API de recette ; un AAB signé ; taille comparée au budget |
| **C15** | **Recette mobile sur appareils réels** (MOB-01 à MOB-37), dont mode discret, PIN, hors ligne, synchronisation, effacement à distance, push d'équipe ; Android 8 à 15, au moins un appareil d'entrée de gamme | **C** + **D** | C14, C7 | Fiches MOB cochées, anomalies bloquantes corrigées |
| **C16** | **Tests d'accessibilité sur appareils réels, dont TalkBack** (SOS, code OTP, signalement), NVDA et VoiceOver sur le site ; Firefox, Safari, 320 px | **C** (accessibilité) + **D** | C8, C14 | Rapport d'audit, écarts corrigés ([ACCESSIBILITE.md](ACCESSIBILITE.md)) |
| **C17** | **Test de charge léger** sur le serveur cible : `GET feed`, `POST sos`, `GET sync/pull`, messagerie (lectures sans ETag) | **D** | C6 | p95 < 600 ms ou limites du forfait documentées ; décision de passage VPS si « Entry Processes » saturé |
| **C18** | **Intégration continue complétée** : jobs Playwright (avec axe) et Lighthouse (`lighthouserc.json`), contrôle `grep fa-` ; première exécution consignée de la CI mobile sur GitHub ; couverture ≥ 70 % du domaine mesurée (installer pcov) | **D** | accès à un exécuteur CI | Jobs verts sur la branche ; rapports joints |
| **C19** | **Fiche Google Play** : icône 512, formulaires (sécurité des données, classification, public cible, permissions, accès de test), vidéo de démonstration de la localisation, politique de confidentialité et suppression de compte déclarées, **piste de test interne puis fermé (12 testeurs, 14 jours)** | **C** + **D** | C2, C3, C14 | Test fermé de 14 jours terminé ; demande d'accès à la production déposée ([playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §13) |
| **C20** | **Bascule de production** : DNS, HTTPS, nouvelle `APP_KEY`, import éventuel de données réelles (`xomiton:import-prototype`), formation de l'équipe, planning de garde renseigné ([RUNBOOK.md](RUNBOOK.md) §12) | **D** + **C** + **H** | tout ce qui précède de P1 et C11 | Production en ligne, signal de vie supervisé, checklist de [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §17 signée |

### Priorité 3 : peut suivre l'ouverture, ou selon décision du client

| Id | Chantier | Propriétaire | Dépend de | Critère de sortie |
|---|---|---|---|---|
| **C21** | **Connecteurs réseaux sociaux réels** (Facebook Graph API, Telegram Bot API ; TikTok sans connecteur) : écrire le transport HTTP, publier en recette | **D** (jetons : **C**) | Q7 et comptes de l'organisation | Une publication réelle en recette sur chaque réseau retenu ; sinon « publication manuelle » assumée |
| **C22** | **iOS** : projet Apple, `GoogleService-Info.plist`, APNs, icône alternative du mode discret, TestFlight | **D** + **C** (compte Apple) | C5 | Build iOS installé, push reçu ; non commencé |
| **C23** | **Fonctions partielles du web** : PWA pair (précache `/collecte`), indicateurs de l'espace pair, appareils synchronisés, filtres enregistrés, balise de statistiques « Contenu », médiathèque et outils CCC, « Mes signalements », PIN web, filtre de mots (Q74) | **D** | décisions Q | Lot par lot, cf. [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) |
| **C24** | **Comparaison « à l'identique » au prototype** aux 4 largeurs (P16-05) et contrôle visuel des écrans d'administration en navigateur | **C** + **D** | C8 | Captures comparées, écarts consignés |
| **C25** | **Dépendances** : `npm audit` (4 alertes de la chaîne de développement), mise à jour de Vitest ; budget JavaScript serré (≈ 119 Ko gzip pour 120 Ko) | **D** | — | Alertes levées ou justifiées ; marge de budget |
| **C26** | **Mobile : suites** (`sync/pull` étendu, décisions mobiles encore ouvertes en §4 de DECISIONS_A_VALIDER, textes de la fiche) | **D** + **C** | C15 | Selon retours de la recette |

## 5. Échéancier par jalons

Les durées sont des **estimations en semaines à partir du démarrage (S0)**, à réajuster dès que les dépendances du client sont levées. Elles ne tiennent pas compte des délais de tiers (revue Google, ouverture d'un compte Vonage, passage en production d'un DNS), qui ne se contrôlent pas. **Le chemin critique n'est pas le code : ce sont les informations, comptes et avis du client.**

| Jalon | Contenu | Quand (estimé) | Condition de passage |
|---|---|---|---|
| **J0 : lancement** | Validation de ce plan, désignation d'un·e responsable côté client, commande des comptes (C5), envoi de la liste des 12 informations (C2) | S0 | Plan accepté ; responsable nommé·e |
| **J1 : recette locale** | Installation sur 1 à 2 postes du client ([INSTALLATION_LOCALE.md](INSTALLATION_LOCALE.md)), début de la recette (C8) et décisions bloquantes (C10) ; en parallèle côté développement : C1, C12, C13, C18 | S0 à S3 | Premiers parcours cochés, anomalies triées |
| **J2 : serveur de recette** | Hébergement sain (C4), déploiement cPanel de recette (C6), sauvegarde et restauration (C9), mesures serveur ; informations légales saisies (C2) | S2 à S5 | `xomiton:preflight` sans échec ; restauration réussie |
| **J3 : canaux réels et mobile** | SOS réel de bout en bout (C7), clé de signature et premier APK (C14), recette sur appareils (C15) | S4 à S8 | SOS reçu en < 30 s ; APK installé sur 3 appareils réels |
| **J4 : conformité et qualité** | Avis juridique (C3), sécurité (C11), accessibilité dont TalkBack (C16), charge (C17) ; textes ajustés | S5 à S10 | Avis écrit ; checklist de sécurité signée |
| **J5 : Play et pré-production** | Fiche Play (C19) et test fermé de 14 jours incompressibles ; répétition générale du déploiement | S8 à S12 | Test fermé terminé ; déploiement rejoué sans surprise |
| **J6 : ouverture** | Bascule de production (C20), publication progressive de l'application | S12 à S14 | Recette acceptée, avis juridique reçu, checklist de [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §17 signée |
| **J7 : après l'ouverture** | Chantiers de priorité 3 (C21 à C26), iOS, suivi des incidents et des retours | à partir de S14 | Selon priorités du client |

Hypothèses : un·e développeur·se Laravel et un·e développeur·se Flutter disponibles ; le client répond sous une semaine aux demandes d'information ; aucun défaut majeur révélé par la première recette sur appareil (cette dernière hypothèse est la moins sûre, puisqu'aucun appareil n'a encore servi). **Ne planifiez pas de lancement public avant la fin de J5.**

## 6. Risques principaux

| Risque | Gravité | Réponse |
|---|---|---|
| Le SOS n'a jamais été éprouvé avec de vrais canaux | critique | C7 avant toute ouverture ; ne pas communiquer sur le SOS tant que l'essai n'est pas consigné |
| Informations légales ou avis juridique en retard | élevé | C2 et C3 lancés dès J0 ; ouverture impossible sans eux |
| Premier build Android jamais lancé : surprises probables (SDK, Gradle, signature) | élevé | C14 tôt (S2 à S4), sur un poste Windows avec [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) |
| Hébergement mutualisé : limite « Entry Processes », `proc_open`, `symlink()` désactivés | moyen à élevé | Mesures de C6 et C17 ; plan B VPS (décision D7) |
| Hébergement précédent compromis | élevé | C4 d'abord, compte neuf de préférence |
| Premier compte administrateur sans commande dédiée | moyen | C1 ; en attendant, procédure `tinker` surveillée |
| Données de santé et de violences envoyées à un tiers | élevé | Assistant LLM désactivé ; ne rien activer sans avis |
| Couverture de tests non mesurée, Playwright hors CI | moyen | C18 |

## 7. Comment suivre l'avancement

1. Garder ce tableau à jour à chaque chantier terminé (état, date, preuve : lien vers le rapport, la capture ou la sortie de commande).
2. Reporter les anomalies de recette avec le gabarit de [GUIDE_RECETTE.md](GUIDE_RECETTE.md) §11.
3. Consigner chaque décision dans `design-system/DECISIONS.md`, comme le demande [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md).
4. Rejouer, avant J6, l'ensemble des commandes de [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) sur le serveur réel et corriger les guides à chaque écart : la documentation d'exploitation n'a pour l'instant été relue que sur le papier.
