---
name: xomiton-plan-ios
description: Plan pour construire et publier l'application iOS de Xomiton (la même application Flutter unique que l'Android : espaces membre, pair éducateur·rice et admin d'astreinte selon les rôles). Couvre prérequis et coûts (Mac, Apple Developer Program, D-U-N-S, alternatives sans Mac), identité de l'app et signature, conception selon les Human Interface Guidelines (arbitrage « même identité Xomiton, comportements de plateforme respectés », tableau écran par écran), fonctions propres à iOS (APNs, Time Sensitive / Critical Alerts, Keychain, masquage des captures, icône alternative, Face ID, tâches d'arrière-plan, liens universels), conformité App Store Review Guidelines (1.2, 5.1.1(v), âge, vie privée, chiffrement, compte de démonstration, notes de revue), visuels et fiche App Store, architecture technique, lots IOS-00 à IOS-14 avec estimation, échéancier, risques, checklist de publication et décisions à valider. À lire avant tout travail dans `mobile/ios/` et avant toute demande de compte Apple.
---

# Plan iOS : construire et publier l'application Xomiton sur l'App Store

Document du 9 novembre 2026. Il complète [PLAN_MOBILE_APP_UNIQUE.md](PLAN_MOBILE_APP_UNIQUE.md) (architecture et lots P15), [PLAN_FINALISATION.md](PLAN_FINALISATION.md) (chantier C22) et [RESTE_A_FAIRE.md](RESTE_A_FAIRE.md) (§3.3), et suit la même logique que [BUILD_ANDROID_WINDOWS.md](BUILD_ANDROID_WINDOWS.md) et [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md). Légende des responsables : **C** = client, **D** = développeur·se, **M** = tâche qui exige un Mac (ou une machine macOS de CI), **J** = conseil juridique.

## 0. Avertissements et état réel

### 0.1 Limite de ce document (à lire en premier)

- Les compétences (« skills ») `apple-design` et `apple-design-dickwu`, demandées pour cette rédaction, **ne sont pas disponibles dans cet environnement** (recherche faite : aucun résultat). Ce plan s'appuie donc **uniquement sur mes connaissances** des *Human Interface Guidelines* (HIG) et des *App Store Review Guidelines*, arrêtées à mi-2026. **Rien n'a été vérifié en ligne.**
- Apple modifie ces textes plusieurs fois par an, ainsi que les tailles de captures, la classification d'âge (refonte en 2025), la version minimale du SDK exigée à l'envoi et les formulaires de l'App Store Connect. **Avant toute publication, relire les guidelines en vigueur** (developer.apple.com/design/human-interface-guidelines, developer.apple.com/app-store/review/guidelines) et revérifier chaque point marqué **(à vérifier)**. Les numéros de paragraphes cités (1.2, 5.1.1(v)…) sont ceux que je connais ; ils peuvent avoir bougé.
- Les prix et délais (programme développeur, location de Mac, minutes de CI, D-U-N-S) sont des **ordres de grandeur** à confirmer.

### 0.2 Honnêteté sur l'état du projet

**Rien d'iOS n'a jamais été compilé ni exécuté.** Il n'y a pas de Mac dans l'environnement de rédaction : ni `flutter build ios`, ni `pod install`, ni simulateur, ni appareil. Les 454 tests Flutter tournent sous Linux et ne prouvent rien sur iOS. L'application Android elle-même n'a pas encore connu son premier build (chantier C14) : **les défauts du code Dart partagé que ce premier build révélera seront aussi ceux d'iOS**, d'où l'ordre proposé au §8.4.

### 0.3 Ce qui existe réellement dans `mobile/ios/` (constaté le 9/11/2026)

| Élément | État réel | Conséquence |
|---|---|---|
| `Runner.xcodeproj` | Identifiant `bj.xomiton.xomiton` (cible `Runner`) et `bj.xomiton.xomiton.RunnerTests`, signature « Automatic », **aucune équipe (`DEVELOPMENT_TEAM`) renseignée**, cible de déploiement **iOS 15.0**, `TARGETED_DEVICE_FAMILY = "1,2"` (**universel : iPhone et iPad**) | Décider iPhone seul ou universel (D-IOS-1) ; sinon l'App Store exigera des captures iPad |
| `Info.plist` | Existe. Présents : `NSLocationWhenInUseUsageDescription` (en français), `NSCalendarsUsageDescription` et `NSCalendarsFullAccessUsageDescription`, `CFBundleDisplayName = Xomiton`, manifeste de scène, orientations (3 pour iPhone dont paysage, 4 pour iPad). **Absents** : `NSFaceIDUsageDescription`, `UIBackgroundModes`, `ITSAppUsesNonExemptEncryption`, `CFBundleLocalizations`, liste des icônes alternatives, `BGTaskSchedulerPermittedIdentifiers` | §4 et annexe A |
| `AppDelegate.swift` | Gabarit Flutter récent (moteur implicite) ; **aucun code push**, rien pour le délégué de notifications | Vérifier sur Mac ce que `firebase_messaging` et `flutter_local_notifications` exigent encore (§4.1) |
| `SceneDelegate.swift` | **Recouvrement blanc** posé à `sceneWillResignActive`, retiré à `sceneDidBecomeActive` (masque l'aperçu du sélecteur d'applications) | Existant et utile, mais : blanc sans marque, indifférent au mode sombre, déclenché aussi par l'invite Face ID et le centre de contrôle ; aucune détection de capture ni d'enregistrement |
| `Podfile`, `Pods/` | **Absents** (générés par Flutter au premier build sur Mac) | Normal ; à versionner après le premier build |
| Entitlements (`Runner.entitlements`) | **Absent** | Push, Time Sensitive, domaines associés, protection des données : à créer (M) |
| `GoogleService-Info.plist` | **Absent** (compte Firebase non ouvert, C5) | Bloque le push iOS |
| `PrivacyInfo.xcprivacy` | **Absent** | Obligatoire à l'envoi (§5.5) |
| Icône d'application | `AppIcon.appiconset` complet, mais c'est le **logo Flutter par défaut** (1024×1024 sans alpha, mais pas Xomiton) | À remplacer par l'icône Xomiton (§6.4) |
| Écran de lancement | `LaunchScreen.storyboard` blanc avec une image 1×1 transparente | À refaire (couleur de jeton, logo, mode sombre) |
| Icône alternative, extension de notification, plugin natif | **Absents** | §4 |
| `RunnerTests.swift` | Gabarit vide | À remplir seulement si du Swift est ajouté |

### 0.4 Ce que le code Dart sait déjà (et ne sait pas) pour iOS

| Sujet | État | Remarque |
|---|---|---|
| Rôles, espaces, sécurité (PIN, expiration hors contact, effacement, mode discret, hors ligne, SOS) | Logique **100 % Dart**, donc partagée | Rien à réécrire, seulement les points natifs ci-dessous |
| `core/push` (`FcmPushService`) | Initialise `flutter_local_notifications` avec des réglages iOS dont **aucune permission n'est demandée** ; canaux Android seulement ; charge « **données seules** `{type, id}` » | **Insuffisant sur iOS** : un message « données seules » n'est pas livré de façon fiable (§4.1). Changement côté serveur nécessaire |
| `core/platform/discreet_icon.dart` | `supported => Platform.isAndroid` | Brancher `setAlternateIconName` (§4.7) |
| `core/security/screen_security.dart` | `setSecure` sans effet hors Android ; liste des écrans sensibles et `ScreenSecurityGate` déjà là | Réutiliser la liste ; ajouter les signaux natifs de capture (§4.6) |
| `core/storage/secret_store.dart` | `flutter_secure_storage` 9.x (Keychain), **options iOS non précisées** | Fixer l'accessibilité et purger au premier lancement (§4.5) |
| `auth_api`, `push_registrar` | Envoient déjà `os = iOS` | Vérifier que l'API accepte la valeur (`API.md`) |
| Suppression de compte dans l'application | **Existe** (`security_page` → `DELETE me` avec mot de passe) | Exigence Apple 5.1.1(v) déjà couverte (§5.2) |
| Signalement de contenu, blocage | Signalement des publications et commentaires (`feed_pages`), blocage dans les messages privés | Voir §5.1 pour ce qui manque |
| Textes et langue | Français seulement ; chaînes dans `lib/l10n/app_fr.arb` | §3.12 |

### 0.5 Inventaire de ce qui est « Material » dans l'interface (comptage dans `mobile/lib`, 9/11/2026)

Thème `useMaterial3: true`, polices Montserrat (titres) et Arial (corps), jetons dans `app/theme/xm_tokens.dart`, widgets maison `Xm*` dans `core/ui/`. Occurrences relevées par recherche dans le code :

| Composant Material | Occurrences | Ce que fait iOS d'habitude | Traitement proposé |
|---|---|---|---|
| `Scaffold` | 41 | `CupertinoPageScaffold` ; fond groupé gris clair pour les listes | Garder (hybride), fond et barres adaptés |
| `AppBar` | 34 | Barre de navigation, **titre large** sur les écrans racines, chevron de retour avec libellé | Façade `XmAppBar` adaptative |
| `ListTile` | 38 | Rangée de liste groupée avec chevron et séparateurs en retrait | Façade `XmListRow` |
| `SnackBar` | 24 | **N'existe pas sur iOS** | Bandeau non modal `XmToast` ; alerte pour les erreurs bloquantes |
| `showDialog` / `AlertDialog` | 12 / 12 | `UIAlertController` (alerte centrée, boutons empilés ou côte à côte) | `showAdaptiveDialog` ou `CupertinoAlertDialog` |
| `showModalBottomSheet` | 10 | Feuille modale (carte) ou *action sheet* pour les choix | `showCupertinoSheet` / `CupertinoActionSheet` selon le cas |
| `PopupMenuButton` | 3 | Menu contextuel (`UIMenu`) | Menu Cupertino ou *action sheet* |
| `NavigationBar` (2 : membre, pair/admin) | 2 | Barre d'onglets en bas (`UITabBar`) | `CupertinoTabBar` stylée par les jetons |
| `FloatingActionButton` | 2 | **N'existe pas sur iOS** | Bouton d'action dans la barre de navigation ou bouton plein en bas |
| `SegmentedButton` | 8 | Contrôle segmenté (`UISegmentedControl`) | `CupertinoSlidingSegmentedControl` |
| `ChoiceChip` / `FilterChip` | 11 / 2 | Pas d'équivalent standard ; contrôles segmentés ou cellules à coche | Garder les puces (acceptable) mais cibles de 44 pt |
| `SwitchListTile` | 14 | Interrupteur vert iOS dans une rangée groupée | `.adaptive` |
| `CheckboxListTile` / `RadioListTile` | 8 / 5 | Pas de case à cocher native : coche à droite d'une rangée pour un choix, interrupteur pour un réglage | Rangée à coche pour les choix, interrupteur pour les réglages ; cases de consentement : gardées avec libellé cliquable |
| `DropdownButtonFormField` | 20 | Sélecteur en roue (`UIPickerView`) ou menu | `CupertinoPicker` en feuille, ou menu |
| `showDatePicker` | 3 | Sélecteur de date en roue ou compact | `CupertinoDatePicker` en feuille |
| `RefreshIndicator` | 13 | Tirer pour actualiser, roue en haut | `RefreshIndicator.adaptive` |
| `CircularProgressIndicator` | 13 | `UIActivityIndicatorView` | `.adaptive` |
| `ExpansionTile` | 4 | Rangée dépliable (sans équivalent strict) | Garder |
| `Icons.*` (Material) | 223 | Symboles SF | Voir §3.4 |
| `TextField` | 42 | Champ arrondi, bouton d'effacement | Garder le style Xomiton, comportement clavier iOS |
| Boutons `Elevated/Filled/Outlined/TextButton` | 266 | Boutons pleins arrondis ; hauteur 44 pt | Garder, style jeton |
| `Dismissible`, `HapticFeedback`, `PopScope` | 2, 2, 2 | Balayage de rangée, retour haptique, retour interceptable | Vérifier `PopScope` (§3.2) |
| `SafeArea` | 49 | Zones sûres | Bon point de départ |
| Texte : `TextScaler` / `textScale` | 0 | Dynamic Type | Flutter suit l'échelle système ; **à tester** (§3.6) |

## 1. Prérequis et coûts

### 1.1 Le Mac : indispensable en pratique

| Possible **sans** Mac | Impossible **sans** Mac |
|---|---|
| Écrire le code Dart et les tests de widgets (y compris la variante iOS, voir §7.4) | **Compiler** : `flutter build ios`, `pod install`, Xcode et le SDK iOS n'existent que sous macOS |
| Ouvrir le compte Apple Developer, créer l'identifiant d'app, la clé APNs (`.p8`), la clé d'API App Store Connect, remplir toute la fiche (navigateur) | Lancer le **simulateur iOS**, déboguer pas à pas, profiler (Instruments) |
| Compiler et envoyer vers TestFlight depuis une **machine macOS de CI** (GitHub Actions, Codemagic) | Écrire et valider le **Swift natif** (recouvrement, icône alternative, détection de capture) : sans compilation, on écrit à l'aveugle |
| Produire les captures de la fiche avec le générateur à partir du prototype (mais **elles ne seront pas acceptables comme captures de l'app**, voir §6.3) | Capturer de **vraies** captures au format Apple, activer des capacités dans Xcode (contournable par le portail web et édition manuelle des fichiers) |

**Recommandation (D-IOS-2)** : un **Mac mini** (Apple silicon, 16 Go, 256 Go minimum ; Xcode récent exige un macOS récent, **à vérifier**) affecté au développeur Flutter + une CI macOS pour les livraisons reproductibles. Sans Mac, le cycle d'essai passe par la CI (10 à 20 minutes par essai) : tenable pour livrer, **pas pour concevoir l'interface ni le Swift natif**.

### 1.2 Alternatives et coûts (ordres de grandeur, à vérifier)

| Option | Coût indicatif | Avantages | Limites |
|---|---|---|---|
| **Mac mini neuf** (Apple silicon) | ≈ 600 à 700 € une fois ; occasion M1/M2 ≈ 350 à 500 € | Simulateur, appareil branché, captures, Swift ; indépendant des quotas | Achat, un poste de plus à sécuriser (clés de signature) |
| **Mac loué dans le nuage** (MacinCloud, Scaleway Apple silicon, AWS EC2 Mac…) | ≈ 30 à 60 $/mois, ou ≈ 20 à 30 $ pour 24 h (durée minimale de 24 h sur certains) | Pas d'achat ; bien pour 2 à 3 mois | Latence du bureau à distance, **pas d'iPhone branché** (donc pas de test sur appareil), données de signature chez un tiers |
| **CI GitHub Actions macOS** | Gratuit en dépôt public ; en dépôt privé les minutes macOS sont décomptées **×10** (un build de 15 min ≈ 150 min facturées) | Déjà utilisé pour `mobile.yml` et `ci.yml` ; secrets dans GitHub | Ne remplace pas un Mac de développement ; limiter le job iOS aux étiquettes et au déclenchement manuel |
| **Codemagic** | Offre gratuite limitée de minutes macOS (≈ 500 min/mois, à vérifier), puis à la minute | Gestion de la signature par la clé d'API très simple ; publication vers TestFlight intégrée | Un second système de CI |
| **Xcode Cloud** | Quelques heures gratuites par mois avec le programme développeur (à vérifier) | Intégré à App Store Connect | Configuration initiale surtout depuis Xcode ; moins souple pour Flutter |
| **iPhone d'essai** | ≈ 200 à 300 € d'occasion (SE 3, 12) | **Indispensable** : push APNs, Face ID, Keychain, tâches d'arrière-plan, Time Sensitive ne se testent pas (ou mal) sur simulateur | Idéalement deux appareils : un petit écran (SE) pour Dynamic Type, un récent avec Dynamic Island |

### 1.3 Apple Developer Program : organisation ou individuel

Cotisation **99 USD par an** (à vérifier ; renouvellement obligatoire sous peine de retrait de l'app). Une exonération existe pour certaines organisations à but non lucratif, éducatives ou publiques (demande à faire, justificatifs à fournir).

| Critère | Compte **organisation** | Compte **individuel** |
|---|---|---|
| Nom du vendeur affiché sur l'App Store | Nom de l'organisation | **Nom et prénom de la personne** |
| Prérequis | Entité juridique, **numéro D-U-N-S**, site web, adresse e-mail à ce domaine, personne ayant autorité pour engager l'organisation (vérifiée par Apple, parfois par téléphone) | Pièce d'identité |
| Délai | **Plusieurs jours à plusieurs semaines** (le D-U-N-S est le point lent) | Quelques jours |
| Rôles et continuité | Plusieurs utilisateurs avec rôles ; le compte survit au départ d'une personne | Une seule identité : risque de dépendance à une personne |
| Adéquation à Xomiton | **Recommandé** : une plateforme de protection ne doit pas exposer le nom d'une personne comme éditeur | À éviter : nom personnel public, et la plateforme serait attachée à un individu |

**Numéro D-U-N-S** : gratuit pour l'inscription Apple, demandé via la recherche D-U-N-S d'Apple (Dun & Bradstreet). Le **nom légal et l'adresse doivent correspondre exactement** à l'enregistrement de l'organisation ; compter de **quelques jours ouvrés à plusieurs semaines** hors États-Unis. **À lancer dès la décision** : c'est le début du chemin critique (§8.3).

**Points de vigilance à valider avant de payer** :
1. **Pays d'inscription** : vérifier que le pays de l'entité figure dans la liste des régions où Apple ouvre le programme (à vérifier ; en cas de doute, renseigner l'entité qui a la capacité juridique dans un pays supporté).
2. **Statut de professionnel (DSA)** : pour une diffusion dans l'Union européenne, Apple demande de déclarer si l'éditeur est un « professionnel » ; si oui, **adresse, téléphone et e-mail sont affichés publiquement** sur la page de l'app dans les boutiques européennes. Pour une structure qui protège des personnes à risque, c'est un sujet : domiciliation, ligne dédiée, ou **ne pas diffuser dans les boutiques de l'UE** (§5.9).
3. **Identifiant Apple** du titulaire : adresse dédiée de l'organisation (pas une adresse personnelle), double authentification sur un numéro de l'organisation.
4. **Contact pour la revue** (nom, téléphone, e-mail) : Apple peut appeler.

## 2. Identité de l'app

| Élément | Décision | Argument |
|---|---|---|
| **Bundle ID** | `bj.xomiton.xomiton` (déjà dans le projet, identique à l'`applicationId` Android) ; identifiant **explicite** (jamais générique) | **Définitif** après publication ; garder le même sur les deux plateformes simplifie Firebase et la gestion |
| Nom sur l'App Store | « Xomiton : écoute et sécurité » (28 caractères sur 30) | Même nom que Google Play (décision Play, aucun mot d'identité) |
| Nom sous l'icône | `CFBundleDisplayName = Xomiton` | Court, évite la troncature ; voir §4.7 sur le nom neutre |
| Sous-titre | « Écoute, entraide et sécurité » (28/30) | §6.5 |
| SKU | `xomiton-ios` | Interne, jamais affiché |
| Langue principale | Français (France/Bénin selon le formulaire) | App en français seulement |
| Catégorie | Principale **Réseaux sociaux**, secondaire **Style de vie** | Aligné sur Play ; **éviter « Santé et remise en forme »** (examen renforcé des données de santé, §5.4) |
| **Version** | `version: 1.0.0+1` du `pubspec.yaml` : `CFBundleShortVersionString = 1.0.0`, `CFBundleVersion = 1` | Le numéro de build doit **augmenter à chaque envoi** de la même version : `--build-number=$GITHUB_RUN_NUMBER` en CI ; les numéros de build iOS et Android sont indépendants, la version marketing reste commune |
| **Signature** | **Automatique** pour le développement local (Xcode « Automatically manage signing ») ; **en CI, signature par la clé d'API App Store Connect** (Codemagic, ou Fastlane en mode « cloud signing ») | Deux personnes au plus : la gestion automatique est la plus robuste. **Alternative** : Fastlane `match` (certificats chiffrés dans un dépôt privé), à adopter si l'équipe dépasse trois personnes. **Écartée** : signature entièrement manuelle (profils qui expirent, fragile) |
| Certificats | *Apple Development* (essais) et *Apple Distribution* (App Store, limité en nombre par équipe) | Créés par la signature automatique ; **sauvegarder** la clé privée du certificat de distribution hors dépôt |
| Profils | Développement et *App Store* ; **pas d'Ad Hoc** | TestFlight couvre la diffusion de test |
| **Clé APNs** | Une clé d'authentification **`.p8`** (et non un certificat `.p12`) : Key ID et Team ID donnés à Firebase | La clé **n'expire pas** (le certificat expire chaque année) et vaut pour toutes les apps de l'équipe ; **téléchargeable une seule fois : la conserver dans le coffre** |
| **Capacités de l'identifiant** | Push Notifications ; Time Sensitive Notifications ; Associated Domains ; Background Modes (`remote-notification`, plus `fetch`/`processing` si IOS-09 le retient) ; Data Protection ; Critical Alerts **seulement si Apple l'accorde** ; App Groups **seulement** si une extension de notification est ajoutée | Capacités minimales ; pas de localisation en arrière-plan, pas de HealthKit, pas de Sign in with Apple |
| **App Store Connect** | Fiche créée dès l'obtention du compte ; utilisateurs avec le rôle minimal ; **clé d'API** (rôle App Manager) pour la CI ; groupes TestFlight ; contact de revue ; statut de professionnel (DSA) | Rien de personnel dans les rôles ; deux personnes habilitées pour ne pas dépendre d'une seule |

## 3. Conception selon les Human Interface Guidelines

### 3.1 Le principe d'arbitrage

> **Même identité visuelle Xomiton (tokens, logo, Montserrat des titres, illustrations, voix et ton), mais comportements de plateforme respectés** (navigation, retour par balayage, feuilles, menus, contrôles, texte dynamique, gestes, retours haptiques).

| Reste **identique** à Android | S'**adapte** à iOS |
|---|---|
| Couleurs de `xm_tokens.dart` (bleu `#0433C3`, rouge `#E30E0E`, variantes sombres `#7C97FF` et `#FF6B6B`), rubans arc-en-ciel, logo, illustrations | Barre d'onglets, barre de navigation et titres larges, geste de retour |
| Montserrat pour les titres, textes français, tutoiement / vouvoiement par espace | Corps de texte en police **système (SF)** plutôt que Arial (Dynamic Type, rendu optique) ; Montserrat reste sur les titres de marque |
| Parcours, règles métier, ordre des étapes, SOS (maintien de 2 s) | Alertes, feuilles, menus, sélecteurs, interrupteurs, indicateurs d'activité, tirer-pour-actualiser |
| Cibles de 44 pt (déjà `XmSpace.targetMin = 44`) | Zones sûres, île dynamique, indicateur d'accueil, haptiques |

Trois façons de construire l'interface, et le choix :

| Option | Avantages | Inconvénients | Décision |
|---|---|---|---|
| A. Tout Material, thème « iOS-compatible » | Aucun travail | Sensation « application Android portée » ; retour par balayage correct mais barres, alertes et feuilles étrangères ; risque de remarques de revue (4.0, 4.2) | **Écartée** |
| **B. Hybride : `MaterialApp` conservée + couche d'adaptation `Xm*` qui choisit le composant Cupertino sur iOS (`.adaptive`, façades)** | Un seul arbre de widgets, un seul jeu de tests, jetons partagés ; chaque écran garde sa logique | Quelques composants à écrire (barre de navigation, `XmToast`, `XmListRow`) | **Retenue** |
| C. `CupertinoApp` sur iOS, `MaterialApp` sur Android | Fidélité maximale | **Deux arbres d'interface** à maintenir et à tester : coût et risque doublés pour une petite équipe | Écartée |

Règle technique : **toute décision d'adaptation se prend par `Theme.of(context).platform` (ou `defaultTargetPlatform`)**, jamais par `dart:io Platform` dans les widgets, pour pouvoir tester la variante iOS sous Linux (§7.4). Le thème propage les jetons à Cupertino via `cupertinoOverrideTheme`.

### 3.2 Navigation

| Sujet | Règle HIG | Décision Xomiton |
|---|---|---|
| **Barre d'onglets** | **5 onglets au plus** visibles sur iPhone ; au-delà, iOS replie dans « Plus » ; toujours visible, libellés courts, icône pleine pour l'onglet actif, pastille numérique pour les notifications | **Problème** : la barre membre actuelle a 5 onglets **plus** un onglet « Espace pair / Admin » (6). Sur iOS, **retirer le 6ᵉ onglet** : l'accès aux espaces passe par la pastille d'en-tête (« Membre ▾ »), la carte « Mes espaces » du profil et le sélecteur après connexion (déjà prévus, plan mobile §5.3). Barres pair (Accueil · Alertes · SOS · Dossiers · Réseau) et admin (Astreinte · Alertes · Gérer · Notifs · Réseau) : 5 onglets, conformes |
| **SOS en onglet** | Un onglet est une destination, pas une action | **Conservé en onglet** (RG-01 : toujours accessible), avec le bouton SOS aussi présent sur l'accueil. Alternative (bouton flottant central) écartée : non standard sur iOS |
| **Pile de navigation** | Chaque onglet garde sa propre pile ; toucher l'onglet actif revient à la racine | Déjà le comportement d'une `StatefulShellRoute` (`go_router`) ; à vérifier : retour à la racine au second toucher |
| **Retour** | Chevron + libellé de l'écran précédent à gauche ; **geste de balayage depuis le bord gauche** ; jamais de bouton « retour » à la façon Android | `Theme.platform = iOS` donne la transition Cupertino (et le balayage) aux routes Material ; **vérifier** que `PopScope` (2 occurrences) et les écrans à champs de saisie n'invalident pas le geste ; le compte à rebours du SOS doit rester annulable mais **le balayage ne doit pas piéger** |
| **Titres larges** | Titre large sur les écrans racines d'onglet (listes), titre réduit au défilement ; titre en ligne (inline) sur les écrans de détail | `XmAppBar` : `CupertinoSliverNavigationBar` sur Accueil, Fil, Messages, Profil, Alertes, Dossiers, etc. ; titre en ligne ailleurs |
| **Actions de barre** | Texte ou symbole à droite (« OK », « Modifier », « + ») | Remplace les `FloatingActionButton` (2) et les menus à trois points |
| **Geste depuis le bas** | Éviter les contrôles critiques collés au bord inférieur (indicateur d'accueil) | **Bouton SOS** : respecter la zone sûre basse ; tester que le maintien de 2 s près du bas ne déclenche pas le geste « glisser pour accueil » |
| **Modales** | Feuille modale (carte) pour une tâche courte ; *action sheet* pour choisir ; alerte pour une décision grave | Voir §3.3 |

### 3.3 Composants

| Besoin | Android (actuel) | iOS (cible) | Notes |
|---|---|---|---|
| Barre d'onglets | `NavigationBar` | `CupertinoTabBar` aux couleurs des jetons (actif `primary`, inactif `textMuted`) | Contraste ≥ 3:1 des icônes |
| Barre de navigation | `AppBar` | `XmAppBar` (§0.5) | Titre large sur les racines |
| Rangées de liste | `ListTile` | `XmListRow` : fond groupé, séparateurs en retrait, chevron | Rangée entière cliquable (44 pt) |
| Interrupteur | `SwitchListTile` | `SwitchListTile.adaptive` | Vert système remplacé par `success`/`primary` du jeton **seulement si** le contraste reste correct |
| Alerte | `AlertDialog` | `showAdaptiveDialog` / `CupertinoAlertDialog` | Action destructive en rouge (`danger`), annulation en gras à gauche |
| Choix parmi N | `showModalBottomSheet` | `CupertinoActionSheet` (≤ 5 choix) | « Annuler » séparé |
| Feuille de tâche (sélecteur d'espace, filtres, formulaire court) | `showModalBottomSheet` | `showCupertinoSheet` (carte avec fond reculé) ; sinon feuille personnalisée avec poignée | **Les crans de hauteur (« detents »)** ne sont pas natifs en Flutter : à approximer et à tester |
| Menu contextuel | `PopupMenuButton` | menu Cupertino (`CupertinoContextMenu` ou menu ancré, selon la version de Flutter fixée, **à vérifier**) | Actions de modération et de blocage |
| Sélecteur | `DropdownButtonFormField` | `CupertinoPicker` en feuille pour les listes courtes ; page de choix pour les listes longues (zones, communes) | |
| Date | `showDatePicker` | `CupertinoDatePicker` en feuille | |
| Tirer pour actualiser | `RefreshIndicator` | `RefreshIndicator.adaptive` | |
| Indicateur d'activité | `CircularProgressIndicator` | `.adaptive` | |
| Message de retour | `SnackBar` (24) | `XmToast` : bandeau non modal sous la barre de navigation, disparaît seul, **annoncé par VoiceOver** ; erreur bloquante : alerte | **Pas de snackbar en bas** (HIG : pas de toasts façon Android) |
| Formulaires | Champs `OutlinedBorder` | Champs Xomiton conservés ; types de clavier, retour « Suivant / OK », effacement, `AutofillHints` | §3.11 |
| Cases de consentement (inscription) | `CheckboxListTile` | Conservées (aucun équivalent natif) avec libellé cliquable et état annoncé | Jamais pré-cochées |

### 3.4 Icônes : SF Symbols ou icônes existantes

| Option | Avantages | Inconvénients |
|---|---|---|
| **SF Symbols** (jeu d'Apple) | Cohérence parfaite, poids et taille qui suivent Dynamic Type, libellés VoiceOver | **Licence** : réservés aux applications pour plateformes Apple, **interdits** dans le logo, l'icône et les visuels promotionnels ; exigent un pont natif (peu de paquets Flutter, maintenance incertaine) ; ne peuvent pas servir sur Android |
| `CupertinoIcons` (fourni avec Flutter) | Gratuit, intégré, paires contour/plein pour les onglets | Jeu restreint, pas identique aux SF Symbols |
| Icônes Xomiton existantes (SVG de la charte) et Material (223 occurrences) | Déjà là, identité de marque | Les icônes Material dans la **barre d'onglets et la barre de navigation** détonnent sur iOS |

**Décision** : **barre d'onglets et boutons de barre de navigation** : `CupertinoIcons` (contour pour l'inactif, plein pour l'actif) ; **contenu** (cartes, illustrations, pictogrammes de thème) : icônes de la charte inchangées ; icônes Material conservées dans les rangées et formulaires **tant qu'elles restent lisibles** (réexamen à la recette visuelle). SF Symbols réels = **hors v1** (pont natif et licence, bénéfice faible).

### 3.5 Écran « Liquid Glass » (iOS 26) et rendu des barres

Depuis iOS 26 (Xcode 26), les barres et contrôles **natifs** adoptent le style « Liquid Glass ». Flutter dessine ses propres widgets : les composants Flutter ne changent pas d'eux-mêmes ; seuls les éléments système (clavier, alertes système, barre d'état, feuilles de partage, sélecteurs de fichiers) changent. **À vérifier sur un appareil iOS 26** : lisibilité de la barre d'état sur nos fonds, contraste des barres translucides. Il existe une clé d'`Info.plist` d'*atténuation temporaire* (`UIDesignRequiresCompatibility`, **à vérifier**) ; on ne la prévoit pas, sauf défaut constaté. **Décision** : ne pas chercher à imiter le verre dans Flutter pour la v1 ; barres opaques ou à flou léger (`BackdropFilter` avec repli opaque quand « Réduire la transparence » est actif).

### 3.6 Typographie et Dynamic Type

- Les styles actuels (corps 17 / 16 / 14 pt, étiquette 13 pt) sont proches des styles HIG (Body 17, Callout 16, Subhead 15, Footnote 13). **Minimum : 11 pt** (jamais en dessous) ; la charte vise déjà ≥ 13 pt.
- **Dynamic Type** : Flutter relaie l'échelle de texte du système (`MediaQuery.textScaler`), y compris les tailles d'accessibilité (jusqu'à environ ×3). Aucun traitement explicite n'existe dans le code (0 occurrence de `TextScaler`) : **risque de débordement** (hauteurs fixes, rangées de chips, barre d'onglets, bouton SOS). Règles : pas de hauteur fixe sur du texte ; `Wrap` plutôt que `Row` ; troncature interdite sur les libellés porteurs d'action ; **tester aux tailles `XXL` et `AX5`** pour les écrans SOS, connexion, OTP, signalement, alertes d'astreinte. Plafond éventuel (`TextScaler.clamp`) **seulement** sur la barre d'onglets et les titres larges, jamais sur le corps.
- Police : titres Montserrat (déjà embarquée), corps en police système SF (retirer `Arial` sur iOS) ; l'accentuation passe par la graisse, pas par la couleur seule.
- **Texte en gras du système** (`MediaQuery.boldText`) : à honorer (graisse +1).

### 3.7 Mode sombre et couleurs sémantiques

- Les jetons ont déjà une variante sombre ; **le réglage actuel est un choix clair / sombre** (`SegmentedButton<ThemeMode>`) : sur iOS, **ajouter « Système » (par défaut)** pour suivre le réglage de l'appareil et le passage automatique jour/nuit.
- **Couleurs sémantiques** : l'iOS distingue fonds primaire / secondaire / groupé et trois niveaux de texte ; nos jetons `bg`, `surface`, `surfaceMuted`, `text`, `textMuted`, `border` jouent ce rôle : conserver la correspondance dans un tableau de `xm_tokens.dart` (commentaire), sans introduire de couleurs système (SwiftUI/UIKit) qui casseraient la charte.
- Contrastes de la charte à conserver ([ACCESSIBILITE.md](ACCESSIBILITE.md) §1) : bleu `#0433C3` interdit sur fond sombre (utiliser `#7C97FF`), rouge sur fond sombre `#FF6B6B`.
- **Contraste augmenté** (« Augmenter le contraste » d'iOS, exposé par `MediaQuery.highContrast`) : ajouter une variante `XmColors.highContrast` (bordures plus marquées, `textMuted` rapproché de `text`, pas de translucidité). Gain d'accessibilité réel pour peu de code.
- Écran de lancement et recouvrement de confidentialité : **aux couleurs de jeton, en clair et en sombre** (aujourd'hui blanc).

### 3.8 Zones sûres, île dynamique, orientation

- `SafeArea` est déjà utilisé 49 fois : bon point de départ. À vérifier sur iPhone avec île dynamique (en haut) et indicateur d'accueil (en bas) : barre de navigation, barre d'onglets, bouton SOS, bandeaux d'alerte de l'espace admin.
- **Orientation** : le projet autorise le paysage sur iPhone. L'interface est conçue en portrait. **Décision (D-IOS-6)** : **portrait seulement** sur iPhone à la v1 (le SOS et la saisie hors ligne sont pensés à une main ; une surface de test et de captures de moins). **Réserve HIG** : verrouiller le portrait est accepté mais prive les personnes qui utilisent l'appareil en support fixe ; à réexaminer après la recette. Si iPad universel : toutes les orientations sont attendues (§3.13).

### 3.9 Tactile, haptiques, animations

- **Cibles tactiles de 44 × 44 pt** minimum : `XmSpace.targetMin = 44` existe ; vérifier les rangées de puces (`ChoiceChip` : 11), les icônes de barre, les cases de consentement.
- **Haptiques** (2 usages actuellement) : retour **fort** à la fin du maintien du SOS, **succès** à l'envoi, **avertissement** à l'annulation, **sélection** sur les onglets et interrupteurs. Respecter le réglage système (Flutter passe par le moteur haptique). **Pas de vibration en mode discret du pair** : le retour haptique ne trahit pas, mais le son si.
- **Animations** : transitions Cupertino de la plateforme ; **« Réduire les animations »** (`MediaQuery.disableAnimations`, déjà lu dans le carrousel de bienvenue) à étendre : pulsation du SOS remplacée par une bordure fixe, transitions de page réduites à un fondu, aucun clignotement. **« Réduire la transparence »** : flous remplacés par des fonds opaques.

### 3.10 Accessibilité : VoiceOver et le reste

- **VoiceOver** : Flutter traduit l'arbre `Semantics` (101 occurrences dans le code). À contrôler : **ordre de lecture** (titre → contenu → actions, barre d'onglets en dernier), libellés de chaque icône, regroupement des rangées (`MergeSemantics`), états (`selected`, `toggled`, `checked`), **annonces** (`SemanticsService.announce`) pour les `XmToast`, le compte à rebours du SOS et l'arrivée d'une alerte d'astreinte, focus rendu à l'élément déclencheur à la fermeture d'une feuille.
- **SOS accessible** : le maintien de 2 s est difficile avec VoiceOver ; les alternatives de [ACCESSIBILITE.md](ACCESSIBILITE.md) §2 (double activation puis confirmation) doivent exister **et être annoncées** ; à tester avec VoiceOver réel.
- Actions personnalisées (`CustomSemanticsAction`) pour les gestes de balayage de rangée (suppression, accusé) : une action VoiceOver équivalente est exigée.
- **Commande vocale / Contrôle de sélection** : étiquettes visibles identiques aux étiquettes accessibles.
- Réglages à honorer : texte plus grand (§3.6), gras, contraste augmenté (§3.7), réduire animations et transparence (§3.9), « Différencier sans les couleurs » (état jamais porté par la couleur seule : déjà une règle de la charte), inversion intelligente (images protégées).
- **Test** : VoiceOver sur appareil réel (SOS, connexion et OTP, signalement, alertes d'astreinte), cohérent avec la ligne C16 de [PLAN_FINALISATION.md](PLAN_FINALISATION.md).

### 3.11 Formulaires et clavier

- `TextInputAction` (Suivant / OK / Envoyer), types de clavier (e-mail, numérique pour l'OTP), bouton d'effacement, défilement pour que le champ actif ne soit pas masqué par le clavier.
- **OTP** : `AutofillHints.oneTimeCode` ; la suggestion automatique du code reçu fonctionne **surtout pour les SMS** ; notre OTP arrive **par e-mail** (la suggestion depuis Mail existe dans les versions récentes d'iOS, **à vérifier**) : le collage reste le chemin sûr (déjà autorisé, WCAG 3.3.8).
- **Mots de passe** : `AutofillHints.newPassword` / `password` ; le trousseau iCloud peut proposer d'enregistrer le mot de passe (confort, mais **un compte partagé sur un autre appareil Apple** : à rappeler dans l'aide pour les personnes en situation de violence conjugale).
- **Vie privée du clavier** : sur les champs sensibles (récit d'un signalement, messages, notes de suivi), `autocorrect: false`, `enableSuggestions: false`, `enableIMEPersonalizedLearning: false` : le clavier n'apprend pas ces mots.

### 3.12 Localisation

Français seulement (décision Play, reprise). Pour que les éléments **système** soient aussi en français quand l'iPhone est réglé en français : `CFBundleDevelopmentRegion = fr`, `CFBundleLocalizations = [fr]`, ajout du délégué `GlobalCupertinoLocalizations` aux délégués de l'application, `InfoPlist.strings` en français (textes d'autorisation). Si l'iPhone est en anglais, les alertes système resteront en anglais : acceptable. **Notes de revue en anglais** (les relecteurs d'Apple ne lisent pas le français, §5.8).

### 3.13 iPad : iPhone seulement ou universel

| Critère | **iPhone seulement** (`TARGETED_DEVICE_FAMILY = 1`) | **Universel** (iPhone + iPad) |
|---|---|---|
| Public visé (Bénin, mobile d'abord) | Cohérent : très peu d'usage sur tablette | Pas de demande établie |
| Interface | Aucune mise en page à refaire ; sur iPad l'app s'ouvre en mode de compatibilité (fenêtre de la taille d'un iPhone) | Mises en page adaptatives (colonnes, barre latérale), toutes les orientations, multitâche, fenêtres redimensionnables : **≈ 8 à 10 jours** de plus (estimation) |
| Revue Apple | Aucun test iPad exigé | **L'app est aussi examinée sur iPad** : tout défaut de mise en page = rejet |
| Captures | iPhone 6,9″ suffit | **Captures iPad obligatoires** (13″) ; générateur à étendre |
| SOS et sécurité | Écrans connus | Comportement du recouvrement, de la capture d'écran, des fenêtres multiples à traiter (`UIApplicationSupportsMultipleScenes` actuellement `false`) |

**Recommandation (D-IOS-1) : iPhone seulement à la v1.** Le projet est aujourd'hui réglé en universel : le **changer** (`TARGETED_DEVICE_FAMILY`, `Info.plist` iPad) est un prérequis de IOS-03. Un universel pourra suivre dans une version ultérieure sans casser l'identifiant de l'app.

### 3.14 Écran par écran : ce qui change sur iOS

**Charge** : S = ajustement (≈ 0,15 j), M = adaptation (≈ 0,3 j), L = refonte partielle (≈ 0,6 j). Charges indicatives pour IOS-07 ; elles incluent la recette visuelle sur simulateur.

#### Espace membre (31 écrans du simulateur)

| # | Écran | Ce qui change sur iOS | Charge |
|---|---|---|---|
| 01 | Bienvenue (carrousel de 6 diapositives) | Plein écran sous zones sûres ; pagination par points système ; **le geste de retour au bord gauche ne doit pas entrer en conflit avec le balayage du carrousel** ; réduire les animations | M |
| 02 | Connexion (unique) | Champs en `AutofillHints`, bouton « Se connecter » 44 pt ; lien « Mot de passe oublié » ; clavier qui ne masque pas le bouton | S |
| 03 | Inscription (4 phases) | Stepper : titre en ligne + progression ; cases de consentement conservées ; sélecteurs en `CupertinoPicker` ; mot de passe : jauge annoncée | M |
| 04 | Code par e-mail (OTP) | `oneTimeCode`, six cases, collage, renvoi après 30 s ; annonce VoiceOver du résultat | S |
| 05-06 | Mot de passe oublié, nouveau mot de passe | Idem 02 ; `newPassword` | S |
| 07 | Tableau de bord | **Titre large**, rafraîchissement natif, cartes inchangées ; pastille d'espace en barre | M |
| 08 | Thématiques | Titre large, grille adaptée à Dynamic Type | S |
| 09 | Fiche thématique | Titre en ligne, retour, partage via la feuille de partage système | S |
| 10 | Ressources | Titre large, filtres (puces ou contrôle segmenté), ouverture de documents (aperçu) | M |
| 11 | Notifications | Rangées natives, balayage pour marquer lu (+ action VoiceOver) ; demande d'autorisation **en contexte** | M |
| 12 | Signaler et suivre | Rangées, segments à la place des onglets Material | S |
| 13 | **SOS** | Bouton à maintien 2 s : **retour haptique**, hors du bord inférieur, alternative VoiceOver, recouvrement de capture, texte d'avertissement « ne remplace pas les secours » (annexe F) ; compte à rebours annulable | **L** |
| 14 | Alerte envoyée | Haptique succès ; « Je suis en sécurité » ; annonce VoiceOver | S |
| 15 | Signalement (4 étapes) | Barre de progression, clavier sans apprentissage, pièces jointes par sélecteur système (HEIC à tester), brouillon conservé, **recouvrement de capture** | L |
| 16 | Contacts d'urgence | Appel par `tel:` (confirmation système) ; liste groupée | S |
| 17 | Fil d'actualités | Titre large, menu contextuel (signaler, masquer) ; balayage | M |
| 18 | Publication et commentaires | Saisie ancrée au clavier, menu de signalement (UGC 1.2) | M |
| 19 | Créer une publication | Feuille modale « Annuler / Publier » en barre, `Annuler` avec confirmation si brouillon | M |
| 20 | Créer un sondage | Idem 19 ; réordonnancement (si besoin) natif | S |
| 21 | Messages | Titre large, recherche intégrée à la barre, balayage de rangée, aperçus masqués | M |
| 22 | Conversation | Bulles, saisie ancrée, menu (bloquer, signaler), **recouvrement de capture** | M |
| 23 | Groupes | Rangées groupées | S |
| 24 | Événements | Sélecteur de date Cupertino ; ajout au calendrier (écriture seule, §4.9) | M |
| 25 | Assistant | Saisie ancrée, annonces ; mention « ne remplace pas un·e professionnel·le » | S |
| 26 | Mon profil | Titre large ; carte « Mes espaces » **remplace l'onglet 6** | M |
| 27 | Profil progressif | Formulaires groupés ; sélecteurs Cupertino | M |
| 28 | Paramètres | Interrupteurs natifs ; **apparence « Système »** ; icône discrète ; lien « Notifications » vers les réglages iOS | M |
| 29 | Sécurité et confidentialité | PIN, **Face ID** (§4.8), appareils connectés, **suppression de compte** (alerte destructive), export ; recouvrement de capture | L |
| 30 | Newsletter | Formulaire simple | S |
| 31 | Choix de l'espace | Feuille ou écran ; pastille en barre | S |

#### Espace pair (maquette `espace-pair.html`, P1 à P12)

| # | Écran | Ce qui change sur iOS | Charge |
|---|---|---|---|
| P1 | Accueil du pair | Titre large, cartes d'indicateurs, pastille « de garde » | M |
| P2 | Alertes routées | Liste groupée, balayage « Accuser » (+ action VoiceOver), badges | M |
| P3 | Détail d'une alerte SOS | Actions en barre d'outils basse, confirmations en *action sheet* ; recouvrement de capture | M |
| P4 | Mes dossiers | Recherche intégrée à la barre, liste groupée | M |
| P5 | Fiche dossier (5 sections) | Sections repliables ou contrôle segmenté ; **recouvrement de capture** | M |
| P6 | Ajouter un suivi | Feuille modale ; clavier sans apprentissage | S |
| P7 | SOS (espace pair) | Identique à 13 | S |
| P8-P9 | Collecte (étapes, vérification) | Stepper, saisie hors ligne, avertissement de plausibilité en alerte ; sélecteurs Cupertino | L |
| P10 | File de synchronisation | Rangées d'état, tirer pour synchroniser ; pas de promesse d'arrière-plan | M |
| P11 | Conflit 409 (le pair choisit) | Choix en *action sheet* ou deux cartes ; confirmation | M |
| P12 | Notification push SOS reçue | **Notification système** (niveau Time Sensitive), pas un écran : §4.1 | M |

#### Espace admin d'astreinte (maquette `admin-mobile.html`, A1 à A12)

| # | Écran | Ce qui change sur iOS | Charge |
|---|---|---|---|
| A1 | Tableau d'astreinte | Bandeau « SOS actifs » sous la barre, rafraîchissement, compte à rebours (annonce VoiceOver) | M |
| A2 | Liste des alertes | Segments (SOS / Signalements / Besoins), balayage, badges | M |
| A3 | Détail SOS (position arrondie) | « Ouvrir dans Plans » avec avertissement, personnes prévenues, réattribuer en *action sheet*, note interne ; capture | L |
| A4 | Inscriptions à valider | Rangées, valider/refuser avec confirmation (*step-up*) | M |
| A5 | Maintenance | Interrupteur avec confirmation forte (alerte) | S |
| A6 | Notifications | Liste groupée | S |
| A7 | Hors connexion | Rangées d'état | S |
| A8 | Rôles et invitations (recherche de membre) | Recherche intégrée à la barre, partage du lien d'invitation par la feuille de partage | M |
| A9 | Modération | Liste, filtres segmentés | M |
| A10 | Contenu signalé | Décision en *action sheet* (masquer, conserver, avertir) | M |
| A11 | Menu « Gérer » | Liste groupée à chevrons | S |
| A12 | Notification push SOS reçue | Notification système (§4.1) | M |

## 4. Fonctions propres à iOS

### 4.1 Notifications : FCM + APNs

**Chaîne** : l'application demande l'autorisation, iOS donne un jeton APNs, Firebase le convertit en jeton FCM que l'application enregistre (`me/push-subscriptions`, déjà codé), le serveur envoie à FCM, FCM relaie à APNs.

| Sujet | Décision |
|---|---|
| **Clé APNs** | `.p8` créée au portail développeur, importée dans Firebase (Key ID + Team ID) ; coffre hors dépôt (§2) |
| **Capacités** | *Push Notifications* (`aps-environment` dans les entitlements : `development` / `production` posés par la signature) ; *Background Modes* : `remote-notification` |
| **Problème majeur** | Le contrat actuel est « **données seules** `{type, id}` » (plan mobile §5.6), et l'application affiche **elle-même** la notification locale. **Sur iOS, un message sans partie `alert` est un message silencieux (`content-available`) : iOS le retarde, le limite, et ne le livre pas du tout si l'utilisateur a quitté l'app de force.** Un SOS ne peut pas reposer là-dessus |
| **Correctif (serveur, lot IOS-05)** | Pour les appareils `os = iOS`, le serveur envoie un message **avec alerte neutre** : titre « Xomiton », corps générique selon le type (« Nouvelle notification », « Alerte à traiter »), **aucun récit, nom, lieu**, plus `{type, id}` en données, `apns-push-type: alert`, `apns-priority: 10`, `interruption-level: time-sensitive` pour `duty_sos`, `alert_routed` et le SOS (exemple en annexe C). Les autres types : niveau `active` ou `passive` |
| **Aperçus masqués** | Le réglage est local (`kPrefMaskPreviews`, par défaut vrai). Comme le texte iOS est **déjà neutre côté serveur**, le masquage est garanti sans code local. **Plan B** si le client veut des textes plus parlants quand les aperçus sont autorisés : **extension de service de notification** (`mutable-content`) qui compose le texte local ; coûte un second *target*, un groupe d'applications partagé, et des essais sur appareil |
| **Demande d'autorisation** | **En contexte** (HIG) : écran d'explication avant l'invite système, déclenché à l'entrée dans l'espace pair ou admin, ou à l'activation des alertes côté membre ; jamais au premier lancement. Autorisation *provisoire* (livraison discrète) **inadaptée** au SOS |
| **Time Sensitive** | Capacité `Time Sensitive Notifications` (entitlement `com.apple.developer.usernotifications.time-sensitive`), **sans demande à Apple** ; perce les modes de concentration **si** la personne n'a pas désactivé cette option pour l'app ; ne force pas le son quand le mode silencieux est actif |
| **Critical Alerts** | Entitlement `com.apple.developer.usernotifications.critical-alerts`, **soumis à une demande à Apple** (formulaire dédié, justification santé, sécurité ou sécurité publique). Perce le mode silencieux et « Ne pas déranger », son à volume choisi ; en plus, **l'utilisateur doit l'autoriser séparément** (option `criticalAlert` à la demande de permission) |

**Critical Alerts : à demander ou non (D-IOS-3).**

| Arguments pour | Arguments contre |
|---|---|
| L'équipe d'astreinte doit entendre un SOS la nuit, téléphone en silencieux : c'est précisément l'usage prévu (sécurité des personnes) | Octroi **incertain et sans délai garanti** (souvent des semaines) ; refus possible |
| Le SOS réel de bout en bout (C7) exige < 30 s | Pour un·e **pair** discret·e, un son qui perce le silencieux peut **exposer** la personne : contraire au principe de discrétion |
| La demande ne coûte que du temps ; elle peut se faire en parallèle | Une demande refusée ou une entrée à justifier peut aussi attirer l'attention de la revue sur le contenu sensible de l'app |

**Recommandation** : **livrer la v1 en Time Sensitive** (sans dépendance externe) et **déposer la demande de Critical Alerts en parallèle** (justification : alerte à l'astreinte d'une plateforme de protection), à n'activer **que pour l'espace Admin, en option « Astreinte »** ; **jamais par défaut pour le pair**. L'accord, s'il vient, s'ajoute à une version suivante (impact : entitlement, `sound.critical`, option dans Paramètres, 1 jour). Le SOS ne doit **jamais** dépendre de cette autorisation : l'escalade serveur (RG-05, 5 min) et le SMS restent les filets.

**Autres points** : notifications au premier plan : iOS ne les affiche pas sans réglage explicite, et le sondage au premier plan (10 s / 30 s) existe déjà ; **le SOS « données seules » ne doit plus être le seul chemin sur iOS** ; actions de notification (« Accuser ») : **v1 sans**, pour éviter une action sensible depuis l'écran verrouillé (si ajoutée plus tard : `authenticationRequired`).

### 4.2 Localisation

- **« Lorsque l'app est active » seulement** : `NSLocationWhenInUseUsageDescription` existe déjà, en français : « Xomiton utilise ta position, seulement quand l'application est ouverte, pour l'envoyer avec ton alerte SOS ou ton signalement si tu choisis de la partager. » **Le conserver tel quel.** Ne **jamais** déclarer `Always`.
- **Position approximative** : depuis iOS 14, la personne peut accorder une position approximative. Le SOS doit **fonctionner avec** (le serveur arrondit déjà à ≈ 1 km pour les admins) ; afficher « position approximative » dans l'écran 14 si c'est le cas. La demande temporaire de précision complète n'est pas prévue.
- **Avertissement d'envoi possible** (ITMS-90683, manque d'une clé « Always ») : certains paquets de géolocalisation déclenchent un avertissement parce qu'ils *contiennent* le code « toujours ». **À constater au premier envoi** : si Apple l'exige, ajouter la clé avec un texte honnête (« Xomiton n'utilise pas ta position en arrière-plan »), jamais une promesse fausse.
- Écran 13 : le délai d'attente GPS de 8 s existant reste valable.

### 4.3 Caméra, photos, fichiers

- Android a **retiré** `CAMERA` : **pas de capture photo dans la v1 iOS non plus**, donc **pas de `NSCameraUsageDescription`**.
- Pièces jointes (signalement) : `file_picker` ; sur iOS 14+ le sélecteur de photos système ne demande **aucune autorisation**. Ne **pas** ajouter `NSPhotoLibraryUsageDescription` par réflexe ; l'ajouter **seulement** si la validation de build l'exige, avec un texte honnête.
- **HEIC** : les photos d'iPhone sont souvent en HEIC. **À tester** : le paquet convertit-il en JPEG ? le serveur (`MediaUploader`, ré-encodage et retrait EXIF) accepte-t-il HEIC ? Sinon convertir côté application avant envoi.
- Les photos d'iPhone portent des **coordonnées dans les métadonnées** : le serveur les supprime déjà (SECURITE_CHECKLIST V12) ; **vérifier avec une photo d'iPhone réelle**.

### 4.4 Calendrier

`add_2_calendar` (EventKit) ne fait qu'**ajouter** un événement. Les clés actuelles incluent l'accès complet (`NSCalendarsFullAccessUsageDescription`). **Principe du moindre privilège** : passer à l'accès en **écriture seule** (`NSCalendarsWriteOnlyAccessUsageDescription`) **si le paquet le permet** (**à vérifier** sur Mac), pour ne pas demander la lecture d'un calendrier à une application de protection : un relecteur ou une personne méfiante s'en étonnera.

### 4.5 Secrets et données au repos

| Sujet | Décision |
|---|---|
| **Keychain** (`flutter_secure_storage`) | Jeton Sanctum, clé AES de Hive, hachage du PIN. Options iOS à fixer : accessibilité **`first_unlock_this_device`** pour le jeton et la clé Hive (utilisables après le premier déverrouillage, **sans** migration vers un autre appareil ni sauvegarde iCloud) ; **`unlocked_this_device`** pour le hachage du PIN ; **synchronisation iCloud désactivée** |
| **Le trousseau survit à la désinstallation** | **Piège iOS** : les éléments du Keychain ne sont **pas supprimés** quand on désinstalle l'application. Au premier lancement après installation (drapeau absent de `UserDefaults`), **purger tous les éléments Keychain** de l'app, sinon une personne qui réinstalle retrouverait un ancien jeton ou une clé orpheline. À écrire (Dart ou Swift) et à tester sur appareil (désinstaller / réinstaller) |
| **Protection des fichiers** (`NSFileProtection`) | Valeur par défaut d'iOS : *Complete Until First User Authentication*. **Proposition** : *Complete* (illisible tant que l'appareil est verrouillé) pour les boîtes Hive des espaces **pair** et **admin**, via l'attribut `protectionKey` posé à l'ouverture (ou capacité *Data Protection*), et laisser la valeur par défaut pour la boîte d'envoi (SOS) et le cache membre. **Compromis** : avec *Complete*, aucune tâche d'arrière-plan ne peut lire ces données téléphone verrouillé : acceptable, la synchro d'arrière-plan n'étant de toute façon pas garantie (§4.10). À valider en recette (M) |
| **Sauvegardes** | Exclure les dossiers de données (`isExcludedFromBackup`) de la sauvegarde iCloud/Finder : une sauvegarde déposerait une copie des données ailleurs. Clé Hive en `ThisDeviceOnly` : une sauvegarde restaurée sur un autre iPhone ne pourrait de toute façon pas la lire |
| **Presse-papiers** | « Copier » le code de suivi : Presse-papiers universel (Handoff) l'envoie aux autres appareils Apple de la personne. Pour un code de suivi, utiliser une copie **locale et à durée limitée** (option `localOnly` et `expirationDate` de `UIPasteboard`) via un canal natif ; sinon l'afficher et prévenir |
| **Journaux** | Aucun secret dans les journaux (règle SECURITE_CHECKLIST V7) ; pas de `print` du jeton |

### 4.6 Masquer l'aperçu et signaler les captures

**Fait établi** : iOS **ne permet pas de bloquer** les captures d'écran. Ce qu'on peut faire :

| Mesure | Mécanisme | Limite |
|---|---|---|
| **Recouvrement dans le sélecteur d'applications** | Vue opaque posée **avant** la mise en arrière-plan (`sceneWillResignActive`), retirée au retour (existe : blanc) | À refaire : couleur de jeton + logo, mode sombre ; **ne pas se déclencher pendant l'invite Face ID** (qui fait aussi passer l'app « inactive ») ni pour l'invite d'autorisation ; recouvrement aussi lors du centre de contrôle |
| **Détection de capture faite** | Notification `UIApplication.userDidTakeScreenshotNotification` | **Après coup** : la capture existe déjà ; on peut avertir (« Cette capture peut contenir des informations sensibles ») et, pour pair et admin, journaliser (**route serveur à définir**, hors plan actuel) |
| **Détection d'enregistrement / recopie d'écran** | `UIScreen.isCaptured` et `capturedDidChange` (ou l'état de capture de scène, iOS 17+) | Couvre l'enregistrement d'écran **et** la recopie AirPlay ; on affiche un **écran de recouvrement** tant que c'est vrai sur les écrans sensibles (`kSecureLocationPrefixes` existe déjà) |
| Astuce non officielle | Dessiner le contenu dans une vue « saisie sécurisée » rend le contenu absent des captures | **Comportement non documenté**, susceptible de changer d'une version à l'autre ; **à essayer seulement en option**, jamais comme garantie |

**Honnêteté dans l'interface** : l'écran « Sécurité et confidentialité » dit ce qu'iOS **ne** garantit **pas** (« Sur iPhone, nous ne pouvons pas empêcher les captures d'écran : nous masquons l'aperçu et te prévenons »). Pas de promesse « captures impossibles ».

**Réalisation** : un petit plugin Swift `XomitonSecurity` (§7.2), un `EventChannel` pour `capture` et un pour `screenshot`, branchés sur `ScreenSecurityGate` existant ; test **sur appareil** (M).

### 4.7 Mode discret : icône alternative et nom neutre

| Sujet | Réalité iOS | Décision |
|---|---|---|
| **Icône alternative** | Possible : `UIApplication.setAlternateIconName` (iOS 10.3+), jeu d'icônes **livré dans l'app** (clé `CFBundleAlternateIcons` ou réglage de catalogue d'actifs) | Une icône « neutre » (v1) ; pont sur le canal `bj.xomiton/discreet` (même nom que sur Android) |
| **Boîte de dialogue système** | À chaque changement, iOS affiche « Vous avez changé l'icône de “Xomiton” » ; **impossible à supprimer sans API privée** (rejet assuré) | Le dire dans l'écran de réglage : la bascule est visible **au moment** où on la fait |
| **Nom sous l'icône** | **Ne change pas à l'exécution** : `CFBundleDisplayName` est fixe | **Limite documentée** (déjà dans `mobile/README.md`) |
| **Aperçu des notifications et Bibliothèque d'apps** | Affichent le **nom** de l'app | Le mode discret iOS est donc **partiel** : l'icône change, pas le nom |
| **Nom neutre fixe** (alternative) | `CFBundleDisplayName` neutre dès l'installation | Discret dès le premier jour, mais **écart avec le nom de la fiche** (risque de remarque de la revue sur un nom trompeur, à vérifier avec Apple) et perte de repère pour les personnes ; **non retenu pour la v1** (D-IOS-4) |
| **Imitation d'une app Apple** | L'icône ou le nom « Notes » rappellerait l'app d'Apple | **Risque de rejet** (copie d'un produit Apple, icône trompeuse) : **ne pas imiter** l'icône Notes ni son nom. Dessiner **notre** icône utilitaire neutre (forme et couleurs distinctes) |

**Recommandation (D-IOS-4)** : v1 = icône alternative neutre + nom « Xomiton » inchangé, **clairement expliqué** ; ne **pas** nommer l'icône « Notes » (≠ Android, où l'alias s'appelle « Notes » : un écart à documenter, la question D-MOB-13 se pose à nouveau côté Apple). Si le client veut un nom neutre, **demander l'avis d'Apple** avant (formulaire de contact de la revue) plutôt que de le découvrir au rejet.

### 4.8 PIN et Face ID

- Le PIN local (PBKDF2, 5 essais, obligatoire pour pair et admin) est en Dart : **inchangé**.
- **Face ID / Touch ID** : paquet `local_auth` (à ajouter), clé `NSFaceIDUsageDescription` (français : « Xomiton utilise Face ID pour déverrouiller l'application à ta place du code, si tu l'actives. »). **Facultatif et désactivé par défaut**.
- **Pourquoi pas par défaut** : dans un contexte de violence conjugale, un visage ou un doigt peuvent être **imposés** ; un code, non. Un jumeau ou un proche peut aussi passer Face ID.
- **`biometricOnly: true`** : ne **pas** accepter le code de l'appareil en repli (il est souvent connu de l'entourage) ; l'échec biométrique redemande **le PIN Xomiton**.
- Nombre d'essais biométriques limité (3), puis PIN ; le délai de verrouillage (60 s pair, 30 s admin) reste celui du plan mobile.
- Le recouvrement (§4.6) ne doit pas se déclencher à l'ouverture de l'invite biométrique.

### 4.9 Effacement à distance

- Le mécanisme (drapeau `wipe_requested_at`, lecture au `heartbeat`, `sync/*` ou `GET me`, accusé `POST me/devices/{uuid}/wiped`) est serveur + Dart : **inchangé**.
- Particularités iOS : (1) **purger aussi le Keychain** (§4.5) et les préférences ; (2) revenir à l'icône par défaut (`setAlternateIconName(nil)`) ; (3) l'effacement se produit **à la prochaine ouverture de l'app** (ou prochaine tâche d'arrière-plan réussie) ; **un iPhone éteint, sans réseau ou dont l'app a été arrêtée de force ne reçoit pas l'ordre** : l'expiration locale hors contact (14 jours) et le jeton court sont les filets, à dire dans l'interface comme sur Android.
- À tester : un message push silencieux n'est **pas** un moyen fiable de déclencher l'effacement.

### 4.10 Hors ligne et synchronisation en arrière-plan

| Capacité | Android | iOS |
|---|---|---|
| File d'envoi persistante (outbox) | Oui | Oui (Dart) |
| Sondage au premier plan (10 s / 30 s) | Oui | Oui (identique) |
| Réveil par notification | Données seules | **Alerte neutre** (§4.1) ; message silencieux = un indice, jamais une garantie |
| Synchro en arrière-plan | WorkManager possible | **`BGTaskScheduler`** : `BGAppRefreshTask` (≈ 30 s, **moment choisi par iOS**, souvent quelques fois par jour selon l'usage) et `BGProcessingTask` (minutes, plutôt en charge et au repos). **Aucune garantie** ; **rien ne s'exécute si l'utilisateur a quitté l'app de force** ; mode économie d'énergie : suspendu |
| Connexion permanente (WebSocket, long polling) | Non (cPanel) | **Impossible** en arrière-plan |
| SOS envoyé puis app quittée | Reprise possible | **`beginBackgroundTask`** à l'envoi : environ 30 s pour terminer la requête en cours ; au-delà, reprise à la prochaine ouverture ; **ne jamais promettre** « l'alerte repartira toute seule » sans réseau |

**Décision (D-IOS-7)** : v1 = **pas de tâche d'arrière-plan planifiée** (aucune promesse, aucun risque de rejet pour un mode d'arrière-plan injustifié) ; synchronisation à l'ouverture, au retour au premier plan, au retour du réseau, plus `beginBackgroundTask` pour terminer un envoi en cours ; **`BGAppRefreshTask` en option de v1.1** pour l'espace pair, si la recette en montre le besoin. Le message à la personne reste honnête : « La synchronisation se fait à l'ouverture de l'application ».

### 4.11 Liens universels

- Sert à ouvrir `https://<domaine>/signup/<code>` (invitation de pair) dans l'application, et les liens de l'application dans l'e-mail.
- Pièces : capacité *Associated Domains* (`applinks:<domaine>`), fichier **`apple-app-site-association`** servi en **HTTPS**, sans redirection, `Content-Type: application/json`, à `/.well-known/apple-app-site-association` (web, **lot serveur IOS-09** : vérifier que le `.htaccess` de cPanel ne bloque pas `/.well-known/`) ; `FlutterDeepLinkingEnabled` côté Flutter, `go_router` route déjà `/signup/:invitation`.
- **Confidentialité** : le lien d'**accusé de réception signé** envoyé aux admins (e-mail) ne doit pas révéler de contenu ; l'ouverture dans l'application passe **après** le PIN (même règle que pour les notifications).
- Sans fichier valide, le lien s'ouvre dans Safari : dégradation acceptable.
- Pas d'indexation Spotlight, pas de *Handoff*, pas de widget, pas de raccourcis Siri (discrétion).

## 5. Conformité Apple

### 5.1 App Store Review Guidelines applicables

| § (à vérifier) | Sujet | Applicabilité à Xomiton | État | Action |
|---|---|---|---|---|
| **1.2** | **Contenus générés par les utilisateurs** | Oui : fil, commentaires, messages privés, groupes, forums | Partiel (voir tableau ci-dessous) | **Obligatoire avant soumission** |
| 1.1 (1.1.1, 1.1.4) | Contenus choquants, sexuels | L'app aborde violences et sexualité **à titre d'information** | Cadre éditorial | Réponses honnêtes au questionnaire d'âge ; notes de revue |
| 1.4 (1.4.1) | Sécurité physique, santé | SOS, urgences, informations de santé | À formuler | « Ne remplace pas les secours » (§5.3, annexe F) ; pas de promesse médicale |
| 1.5 | Coordonnées du développeur | Oui | Page de contact web | URL d'assistance valide et lisible |
| 2.1 | Complétude, **compte de démonstration** | Oui : connexion obligatoire | À faire | §5.7 |
| 2.3.1 | **Pas de fonction cachée** | **Oui, sensible** : les espaces pair et admin existent mais ne sont pas annoncés au public | À traiter | **Les décrire dans les notes de revue** (§5.8) ; **ne pas** les dissimuler à la revue |
| 2.3.3, 2.3.7 | Captures fidèles, métadonnées | Oui | À faire | Vraies captures de l'app (§6.3) ; pas de mention d'une autre plateforme (« Android », « Google Play ») ni de prix dans le nom ou le sous-titre |
| 4.0, 4.2 | Conception ; fonctionnalité minimale | Risque modéré (application Flutter à interface adaptée) | À faire | Applications natives réelles : notifications, localisation, PIN, hors ligne, SOS ; interface HIG (§3) |
| 4.1 | Imitation | L'icône « Notes » imiterait Apple | À éviter | §4.7 |
| 4.8 | Services de connexion | **Pas de connexion par un tiers** (compte propre) | Sans objet | Sign in with Apple **non requis** ; à reconsidérer si une connexion Google/Facebook apparaissait |
| **5.1.1 (i)** | Politique de confidentialité | Oui | Page web créée (`/politique-de-confidentialite`) | URL dans App Store Connect **et lien dans l'application** (à vérifier dans `security_page`/inscription) |
| 5.1.1 (ii), (iii) | Consentement, minimisation | Consentements RGPD distincts déjà dans l'inscription ; minimisation par conception | OK | Contrôle dans la revue de privacy manifest |
| **5.1.1 (v)** | **Suppression de compte depuis l'app** | **Oui, obligatoire** | **Existe** (`DELETE me`, mot de passe) | §5.2 |
| 5.1.2 | Utilisation et partage des données | Aucune revente, aucun suivi | OK | Étiquettes de confidentialité (§5.4) |
| 5.1.3 | Données de santé | Informations de santé et récits de violences | À cadrer | Pas de HealthKit ; données sensibles chiffrées, consentement distinct (déjà) |
| 5.1.5 | Localisation | SOS, signalement | OK | Texte d'autorisation clair ; position facultative |
| 5.2 | Propriété intellectuelle | Photos d'onboarding, illustrations | Preuves de licence à archiver (RESTE_A_FAIRE §3.2) | Même dossier que pour Play |
| 3.x | Paiements | **Gratuit, aucun achat intégré** | Sans objet | Aucun accord « apps payantes » à signer |

**Guideline 1.2 : ce qu'Apple attend et où en est Xomiton.**

| Exigence | État constaté | Action |
|---|---|---|
| **Filtre** des contenus répréhensibles | **Absent** : Q74 (« filtre de mots ») est à la décision par défaut « **pas de filtre** » ; C23 le liste comme non fait | **À revoir pour iOS** : au minimum un filtre serveur de motifs interdits (insultes, incitations, coordonnées) sur publications, commentaires et messages de groupe, **ou** une justification écrite (pré-modération, messages privés lus seulement s'ils sont signalés). Charge : ≈ 2 j serveur (lot IOS-10). **À trancher par le client (D-IOS-9)** |
| **Signalement** des contenus et utilisateurs | Publications et commentaires : oui. Messages privés et groupes : **à vérifier** écran par écran | Un « Signaler » partout où un contenu d'autrui s'affiche |
| **Blocage** des utilisateurs abusifs | Messages privés : oui. Fil, groupes : **à vérifier** | Blocage accessible depuis profil, fil, groupe |
| **Réponse dans un délai** | Modération « essentielle » dans l'espace admin ; engagement de lecture sous 24 h cité dans la fiche Play | Procédure et délai **écrits** (notes de revue : « modération humaine, signalements traités sous 24 h ») et tenus |
| **Sanction** | Masquer, avertir ; suspension de compte sur le web | Documenté ; l'admin mobile n'a pas la suspension (volontaire) |
| **Coordonnées publiées** | `contact@xomiton.app` (boîte à créer chez l'hébergeur), page de contact | Lien « Aide et contact » **dans l'application** |
| **Conditions d'utilisation** acceptées, tolérance zéro | CGU et consentement à l'inscription | Texte explicite sur le refus des contenus choquants et la possibilité d'exclusion |

### 5.2 Suppression de compte depuis l'application (5.1.1(v))

- Existe : Paramètres › Sécurité › « Supprimer mon compte » (mot de passe demandé), plus export des données ; page web `/supprimer-mon-compte` accessible sans connexion.
- Particularité : **effacement différé de 30 jours puis anonymisation** (dossier Play §3). Apple accepte une confirmation et un délai raisonnables **si le parcours est lancé dans l'app, clairement expliqué, et se termine par la suppression réelle (pas une simple désactivation)**. À écrire dans les notes de revue. Pas de jeton Sign in with Apple à révoquer (aucune connexion tierce).
- À vérifier : le lien vers la suppression est **facile à trouver** (pas enfoui) et fonctionne **pour un compte pair et admin** (que devient le dossier d'un pair supprimé ? règle serveur existante à rappeler).

### 5.3 SOS et applications d'urgence : formulation

L'application **ne remplace pas** les services d'urgence et **n'appelle pas** d'elle-même les secours. Formulation unique à reprendre sur l'écran SOS, la fiche, les notes de revue et les CGU (texte complet en annexe F) :

> « Le SOS prévient tes contacts de confiance et l'équipe Xomiton. Il n'appelle pas les services d'urgence et ne les remplace pas. En danger immédiat, appelle les secours de ton pays. »

Éviter « sauvetage », « intervention garantie », « protection assurée ». Ne pas utiliser le nom « SOS d'urgence » (fonction du système iPhone) ni suggérer une intégration au système. Les numéros d'urgence viennent de l'API (référentiel, NFR-M1), pas du code.

### 5.4 Étiquettes de confidentialité (« App Privacy »)

À remplir dans App Store Connect **d'après le code réel** ; le tableau de sécurité des données Play (déjà rempli, « 3 points à confirmer en interne ») sert de base. **Suivi (« tracking ») : aucun.**

| Type de donnée (catégorie Apple) | Collectée | Liée à l'identité | Suivi | Finalité | Remarque |
|---|---|---|---|---|---|
| Coordonnées : adresse e-mail, numéro de téléphone (facultatif) | Oui | Oui | Non | Fonctionnement de l'app | Compte, OTP, SMS de secours |
| Nom (pseudonyme) | Oui | Oui | Non | Fonctionnement | |
| Localisation précise (SOS, signalement) | Oui, **si la personne choisit de la partager** | Oui | Non | Fonctionnement | Arrondie pour les admins côté serveur |
| **Informations sensibles** (orientation, identité de genre… si saisies au profil progressif ; récits de violences) | Oui, avec **consentement distinct** | Oui | Non | Fonctionnement | **À confirmer champ par champ** dans l'API et le profil |
| Contenu utilisateur : messages, publications, photos et fichiers joints, autre contenu | Oui | Oui | Non | Fonctionnement | |
| Identifiants : identifiant utilisateur, **identifiant d'appareil** (UUID d'appareil, jeton de notification) | Oui | Oui | Non | Fonctionnement | Appareils connectés, effacement à distance, push |
| Données d'utilisation, diagnostics, achats, historique de navigation | **Non** | | | | **Aucun SDK d'analyse ni de plantage** : vérifier la liste des pods au premier build (aucun `FirebaseAnalytics`, pas de `AdSupport`) |

**Transparence de suivi (ATT) : non requise.** Aucun suivi entre apps ou sites, aucun identifiant publicitaire, aucune publicité : pas de `NSUserTrackingUsageDescription`, pas d'invite ATT. Répondre « Non » à « utilisez-vous des données pour suivre ? ».

### 5.5 `PrivacyInfo.xcprivacy` et « required reason APIs »

- Apple exige un **manifeste de confidentialité** à l'envoi ; il déclare le suivi (`NSPrivacyTracking = false`, aucun domaine de suivi), les types de données collectées (cohérents avec §5.4) et les **APIs à « raison requise »** utilisées : typiquement les **préférences utilisateur** (`UserDefaults` : `shared_preferences`, `flutter_local_notifications`, Firebase), **horodatages de fichiers**, **temps de démarrage du système** (moteur Flutter), **espace disque**.
- Le moteur Flutter et la plupart des extensions livrent **leur propre** manifeste ; Xcode les agrège dans un « rapport de confidentialité » (Product › Archive › Generate Privacy Report). **Le manifeste de l'app doit couvrir ce que le code de l'app et les extensions sans manifeste font.** Les kits tiers courants (Firebase) doivent être **signés** (déjà le cas via les paquets officiels).
- À faire sur Mac : générer le rapport, comparer à §5.4, ajouter les raisons manquantes (UserDefaults : raison de l'app seule, etc.). **Chaque raison déclarée doit correspondre à un usage réel.**

### 5.6 Chiffrement et conformité à l'exportation

- À l'envoi, Apple demande si l'app utilise du chiffrement : clé `ITSAppUsesNonExemptEncryption` dans `Info.plist` pour répondre une fois pour toutes.
- Xomiton utilise **HTTPS** (chiffrement du système, exempté) **et** son propre chiffrement local (AES de Hive, PBKDF2 du PIN, SHA, paquet `crypto`). Ce second usage est **au-delà du chiffrement fourni par le système** : il n'est pas certain qu'il soit « exempté » au sens de la réglementation américaine d'exportation.
- **Ne pas** mettre `false` par réflexe. **Décision (D-IOS-8)** : demander l'**avis écrit** du conseil sur la qualification ; en l'attente, répondre à Apple par l'option « utilise le chiffrement… exempté » **si** le conseil le confirme (en pratique : produit grand public, algorithmes standards, avec **auto-classification** déclarée annuellement à l'administration américaine) ; une **pièce justificative** peut être demandée pour certains pays, **dont la France** (à vérifier si la diffusion y est retenue). Clé `ITSAppUsesNonExemptEncryption` posée selon la réponse validée.

### 5.7 Âge, classification et âge minimal de 15 ans

- **Attention à la grille** : Apple a **remplacé en 2025** les anciens niveaux (4+, 9+, 12+, 17+) par **4+, 9+, 13+, 16+, 18+**, avec un questionnaire plus détaillé (contrôles intégrés à l'app, capacités : messagerie, contenu généré par les utilisateurs, accès web non restreint ; sujets médicaux ou de bien-être ; contenus sexuels ou nus ; violence ; sujets matures). Le texte de cadrage de ce projet parlait de « 17+ probable » : **dans la grille actuelle, le résultat probable est 16+ ou 18+**, **à lire dans App Store Connect** après réponse honnête (à vérifier).
- **Réponses honnêtes** : contenu généré par les utilisateurs **oui**, messagerie **oui**, accès web non restreint **non**, publicité **non**, thèmes de violences et de sexualité **abordés à titre d'information et de prévention** (violence réelle, sujets de santé sexuelle) : à déclarer comme tels, **sans minorer**.
- **Articulation avec l'âge minimal de 15 ans** : la classification d'Apple porte sur le **contenu**, elle n'impose pas un âge d'inscription ; l'âge de **15 ans** reste une règle du produit (case « j'ai 15 ans ou plus », RG). Mais une classification **16+ ou 18+** peut **empêcher une personne de 15 ans** d'installer l'app si un contrôle parental s'applique. La décision Play (tranches 16-17 et 18+, point D-MOB-18) est de même nature. **À arbitrer (D-IOS-5) avec le conseil** :
  | Option | Conséquence |
  |---|---|
  | **a. Rester à la classification calculée par Apple (16+ ou 18+) et maintenir 15 ans** | Honnête ; le cas des 15 ans installant malgré la classification relève du contrôle parental ; cohérent avec Play (D-MOB-18) |
  | b. Relever l'âge minimal du produit à 16 ans | Aligne iOS, Play et classification ; **change la règle** du produit et du site (décision du client) |
  | c. Viser une classification plus basse en répondant au minimum | **Écarté** : fausse déclaration, motif de retrait |
- Des lois d'États américains sur la vérification d'âge dans les boutiques peuvent imposer l'interrogation de l'API d'Apple sur la tranche d'âge : **hors périmètre, à faire examiner** si la diffusion aux États-Unis est retenue.

### 5.8 Compte de démonstration et notes de revue

**Comptes** (Guideline 2.1) : la revue doit pouvoir tout voir.

| Compte | Contenu | Remarque |
|---|---|---|
| Membre de démonstration | Données fictives | Connexion sans OTP |
| Pair + membre de démonstration (**statut actif**) | Quelques dossiers fictifs | Espace pair visible |
| Admin + membre de démonstration | Alertes fictives | **L'OTP est imposé** aux admins (Q13 b) : la revue ne peut pas lire un e-mail |

**Deux problèmes à traiter côté serveur (lot IOS-10)** :
1. **OTP des comptes de démonstration** : prévoir un mécanisme **restreint** (code fixe pour ces deux comptes seulement, activable par variable d'environnement, **tracé dans le journal d'audit**, rotation après la revue). Alternative : boîte e-mail surveillée en continu pendant la revue : **non fiable**.
2. **Le SOS de démonstration ne doit alerter personne** : l'examinateur **appuiera** sur SOS. Les alertes émises par ces comptes doivent être **marquées « test »**, acheminées vers aucune personne réelle, **sans SMS ni push d'équipe**. **À tester avant chaque soumission.**

**Notes de revue (en anglais)** : texte-type en annexe D. Contenu : objet de l'app, **existence des trois espaces par rôle** et comptes pour chacun, **pourquoi** les espaces pair et admin ne sont pas décrits publiquement (comptes réservés à des personnes validées ; risque pour la sécurité des personnes), modération et signalement (1.2), suppression de compte, SOS et limite (« ne remplace pas les secours »), notifications (Time Sensitive, pas de Critical Alerts), localisation facultative, absence de suivi.

**Divergence assumée avec le dossier Play** : le dossier Play recommande de ne mentionner les espaces pair et admin que si l'examinateur l'exige. **Pour Apple, c'est l'inverse** : les fonctions non documentées exposent à un rejet (2.3.1). La **fiche publique** reste centrée sur l'espace membre (pas de promotion publique de l'admin) ; les **notes de revue**, visibles d'Apple seul, disent tout. **Plan B** si Apple s'y oppose : construire une **variante « membre »** de l'app avec `--dart-define=SPACES=member` (le mécanisme existe déjà dans le code) et garder pair et admin pour une distribution privée (§8.7).

### 5.9 Pays de diffusion

Choisir les **boutiques** (pays) une par une dans App Store Connect : la décision Play est « tous les pays ». Pour iOS : **vigilance** sur les pays hostiles aux personnes LGBTQ+ (avis juridique C3) ; **Union européenne** : statut de professionnel, adresse et téléphone publics (§1.3). Option raisonnable pour la **première version** : Bénin et pays francophones d'Afrique de l'Ouest, élargissement ensuite.

### 5.10 Risques de rejet et mesures

| Risque | Gravité | Cause probable | Mesure |
|---|---|---|---|
| **1.2** UGC | Élevée | Pas de filtre, signalement/blocage incomplet | §5.1, D-IOS-9 ; procédure de modération écrite |
| **4.2** fonctionnalité minimale | Moyenne | Perçue comme un site web empaqueté | Fonctions natives réelles ; notes de revue ; vidéo d'usage |
| **2.1** compte de démonstration non fonctionnel (OTP, SOS réel) | **Élevée (cause de rejet la plus banale)** | §5.8 | Comptes testés **le jour de la soumission** ; code fixe restreint ; mode test du SOS |
| **2.3.1** fonction cachée | Moyenne | Espaces pair et admin non annoncés | Notes de revue complètes ; plan B variante membre |
| **5.1.1** confidentialité | Moyenne | Lien de politique introuvable dans l'app, manifeste incohérent | Lien dans l'app ; rapport de confidentialité comparé aux étiquettes |
| **1.4 / santé** | Moyenne | SOS perçu comme garantie de secours | Formulation §5.3 partout |
| **4.1 / 5.2** icône « Notes » | Moyenne | Imitation d'Apple | §4.7 |
| **Métadonnées 2.3** | Faible | Mots de superlatif, mention « Android » | Contrôles du générateur étendus |
| Rejet pour mode d'arrière-plan injustifié | Faible | `UIBackgroundModes` inutiles | Seulement `remote-notification` à la v1 |

## 6. Visuels et fiche App Store

### 6.1 Icône de l'app

- **1024 × 1024 px**, PNG, **sans transparence**, **sans coins arrondis** (iOS les arrondit), sRGB ou Display P3, pas de texte lisible superflu. Source : `design-system/logo/icone-appli-bleu.svg`, rendu par `render_png.py` puis **aplati sur fond opaque** (le rendu du dépôt est transparent) ; les SVG `-sombre` et `-blanc` servent aux variantes.
- Xcode accepte une icône à **taille unique** (le jeu actuel liste toutes les tailles avec l'icône Flutter) ; **ajouter les variantes sombre et teintée** des versions récentes d'iOS (facultatif mais soigné) ; la nouvelle chaîne d'icônes en couches d'iOS 26 est facultative (**à vérifier**).
- Remplacer **toutes** les icônes Flutter actuelles et **vérifier l'absence d'alpha** (rejet automatique à l'envoi sinon).
- Icône du mode discret : §4.7 (jeu séparé).

### 6.2 Tailles des captures (à vérifier à la date de dépôt)

| Famille | Tailles acceptées (portrait, pixels) | Règle |
|---|---|---|
| **iPhone 6,9″** (obligatoire) | **1320 × 2868** (référence), 1290 × 2796, 1260 × 2736 | App Store Connect **met à l'échelle** ce jeu pour les tailles inférieures si on n'en fournit pas d'autre |
| iPhone 6,5″ | 1284 × 2778, 1242 × 2688 | Exigé **seulement** si le jeu 6,9″ est absent |
| iPhone 6,3″, 6,1″, 5,5″… | tailles propres à chaque modèle | **Facultatives** (mise à l'échelle depuis la plus grande) |
| **iPad 13″** (seulement si universel) | 2064 × 2752 (ou 2048 × 2732) | Obligatoire pour une app iPad ; **non nécessaire en « iPhone seulement »** |

Format : PNG ou JPEG **sans transparence**, 1 à 10 captures par taille, une au minimum. **Aperçu vidéo** facultatif : 15 à 30 s, jusqu'à trois par taille, contenu de l'app seulement ; non prévu pour la v1 (sauf demande de la revue).

### 6.3 Captures : de vraies captures de l'application

- Le générateur Play (`docs/playstore/build_playstore.py`) capture le **prototype HTML** dans un châssis iPhone 14 (`device_scale 3`, soit 1170 × 2532) : **taille non acceptée** par Apple **et** contenu non réel. Pour Apple (2.3.3), les captures doivent montrer **l'app en usage** ; une composition marketing (fond de marque, titre) est admise, **pas une maquette présentée comme l'app**.
- **Plan** : prendre les **vraies captures sur simulateur** iPhone 17 Pro Max ou 16 Pro Max (6,9″, 1320 × 2868) après IOS-07, barre d'état propre (`xcrun simctl status_bar booted override --time 9:41 --batteryState charged --cellularBars 4`), **comptes de démonstration**, thème clair, puis composer avec le générateur. Pas de texte inventé : mêmes écrans que les captures Play (accueil, SOS, signalement, messages, ressources, fil, mode discret).

### 6.4 Réutiliser le générateur de visuels

`tools/playstore-kit` **n'existe pas** dans le dépôt au 9/11/2026 ; seuls existent `docs/playstore/build_playstore.py`, `visuels.json` et `GUIDE_VISUELS_PLAY_STORE.md`. Ne pas modifier `docs/playstore/` (autres agents) : **dupliquer** vers `docs/appstore/` (IOS-11) et adapter :

| À adapter | Détail |
|---|---|
| Entrées | Source = **captures simulateur** (dossier d'images) au lieu du prototype HTML ; garder le prototype seulement pour prévisualiser |
| Profil de sortie | `iphone-6.9` : 1320 × 2868 ; (option `ipad-13` : 2064 × 2752) ; **pas d'image de présentation** (n'existe pas sur l'App Store) |
| Contrôles | Garder PNG 24 bits sans alpha, poids, contraste AA, aire de texte ; **retirer** la contrainte 1080 × 1920 et le ratio 9:16 ; **ajouter** « android », « google play », « play store » aux mots interdits ; superlatifs conservés |
| Texte | Tutoiement ou vouvoiement comme Play ; titres courts ; jamais de mot d'identité (décision Play) |
| Sortie | `docs/appstore/assets/screenshots/iphone-6.9/*.png`, rapport de contrôles, **sans modifier** la sortie Play |

### 6.5 Textes de la fiche

| Champ | Limite | Proposition (français) |
|---|---|---|
| Nom | 30 | Xomiton : écoute et sécurité (28) |
| Sous-titre | 30 | Écoute, entraide et sécurité (28) |
| Texte promotionnel | 170 | « Un espace sûr pour s’informer, échanger et être soutenu·e, en toute discrétion. Le SOS prévient vos contacts de confiance ; il ne remplace pas les services d’urgence. » (166) |
| Mots-clés | 100 (séparés par des virgules, sans espaces) | `soutien,violences,droits,santé,discret,signalement,femmes,filles,communauté,SOS,aide,confidentiel` (97) : **ne répète pas** les mots du nom ni du sous-titre (déjà indexés) |
| Description | 4 000 | Reprise de la description Play **adaptée** : « Mode discret : l'icône peut devenir neutre ; sur iPhone le nom de l'app ne change pas » ; retirer toute mention d'Android ; **ne parle ni d'espace pair, ni d'administration, ni de rôles** (décision de communication publique) ; ajouter « Xomiton ne remplace ni les services d'urgence, ni les soins… » |
| Nouveautés | 4 000 | « Première version. » |
| URL d'assistance | requis | Page de contact du site (`<domaine>/contact`, à confirmer qu'elle existe) |
| URL marketing | facultatif | `https://<domaine>/` |
| **URL de confidentialité** | requis | `https://<domaine>/politique-de-confidentialite` (en ligne **avant** le dépôt) |
| Droits d'auteur | | « © 2026 <entité légale> » |
| Notes de revue | | Annexe D (en anglais) |

## 7. Architecture technique côté dépôt

### 7.1 Schéma

```
                 APPLICATION FLUTTER UNIQUE (même base de code que l'Android)
 ┌──────────────────────────────────────────────────────────────────────────────────────────────┐
 │ mobile/lib/  (100 % partagé : rôles, espaces, sécurité, synchro, SOS, écrans)                 │
 │   app/theme  : jetons Xomiton ──► ThemeData + cupertinoOverrideTheme (platform = iOS)         │
 │   core/ui    : Xm* façades adaptatives (XmAppBar, XmListRow, XmToast, XmTabBar, XmSheet…)     │
 │   core/platform, core/security, core/push : interfaces Dart ──► canaux MethodChannel/Event     │
 ├───────────────────────────────────┬──────────────────────────────────────────────────────────┤
 │ mobile/android/  (existant)       │ mobile/ios/  (À CONSTRUIRE, rien d'exécuté)               │
 │   MainActivity, alias « Notes »,  │   Runner/ AppDelegate.swift · SceneDelegate.swift         │
 │   FLAG_SECURE, canaux de notif.   │   XomitonSecurityPlugin.swift (capture, icône, fichiers)  │
 │                                   │   Info.plist · Runner.entitlements · PrivacyInfo.xcprivacy│
 │                                   │   Podfile (généré) · GoogleService-Info.plist (hors dépôt)│
 │                                   │   [option] NotificationService/ (extension, plan B)       │
 └───────────────────────────────────┴──────────────────────────────────────────────────────────┘
        │ HTTPS /api/v1 (Bearer, X-Device-Id, Idempotency-Key)           ▲ jeton FCM
        ▼                                                                │
 ┌───────────────────────────────┐   message FCM (v1)     ┌──────────────────────────┐   ┌─────────┐
 │ SERVEUR LARAVEL (cPanel)      │ ─────────────────────► │ Firebase Cloud Messaging │ ─►│  APNs   │─► iPhone
 │  payload par plateforme :     │   apns: {alerte neutre, └──────────────────────────┘   └─────────┘
 │  Android = données seules     │   interruption-level, priorité 10, push-type alert}
 │  iOS = alerte neutre + {type,id}
 │  + comptes de démonstration « test » + AASA (/.well-known) + filtre UGC
 └───────────────────────────────┘
 CI : GitHub Actions (ubuntu : analyse + tests, variante iOS simulée) · macOS (build iOS, TestFlight)
```

### 7.2 Ce qui est partagé, ce qui est spécifique

| Domaine | Partagé avec Android | Spécifique à iOS |
|---|---|---|
| Logique (auth, espaces, PIN, synchro, outbox, SOS, API) | **Tout** (Dart) | — |
| Interface | Écrans, jetons, textes | Façades `Xm*` qui choisissent Cupertino, titres larges, barre d'onglets à 5, `XmToast` |
| Push | Enregistrement du jeton, routage `{type, id}` | Capacités et entitlements, clé APNs, **charge utile serveur iOS**, demande d'autorisation en contexte, niveaux d'interruption |
| Sécurité d'écran | Liste des écrans sensibles, `ScreenSecurityGate` | Recouvrement natif de mise en arrière-plan, détection de capture et d'enregistrement |
| Mode discret | Préférence, interface | `setAlternateIconName`, jeu d'icônes neutre |
| Secrets | `secret_store` | Options Keychain, purge au premier lancement, exclusion des sauvegardes, protection des fichiers |
| Deep links | Routage `go_router` | Associated Domains, fichier AASA |
| Fichiers natifs | — | `Info.plist`, `Runner.entitlements`, `PrivacyInfo.xcprivacy`, `Podfile`, `AppDelegate.swift`, `SceneDelegate.swift`, plugin Swift `XomitonSecurity` |

**Plugin natif** : un seul petit paquet local `ios/Runner/XomitonSecurity.swift` (ou `plugins/xomiton_security/`) exposant : (1) icône alternative, (2) flux d'événements de capture d'écran et d'enregistrement, (3) attribut de protection de fichiers, (4) purge du Keychain au premier lancement, (5) copie locale du presse-papiers, (6) `beginBackgroundTask`. **Moins de 300 lignes de Swift**, à tester sur appareil (M).

### 7.3 Dépendances Flutter à vérifier sur iOS

Versions lues dans `mobile/pubspec.yaml` ; **aucune n'a été résolue ni compilée pour iOS** : tout est « à vérifier au premier `pod install` ».

| Dépendance | Rôle | Point de vigilance iOS |
|---|---|---|
| `flutter_secure_storage ^9.2.4` | Jeton, clés | Keychain : options d'accessibilité, **persistance après désinstallation**, pas de sync iCloud |
| `firebase_core ^5`, `firebase_messaging ^17` | Push | `GoogleService-Info.plist`, clé APNs dans Firebase, *swizzling* actif par défaut ; version minimale d'iOS exigée par le SDK Firebase courant (peut dépasser 15 : voir §7.5) ; **aucun** module d'analyse |
| `flutter_local_notifications ^22` | Notifications locales | Délégué de centre de notifications dans `AppDelegate`, niveau d'interruption, permissions en contexte |
| `geolocator ^14` | Position | `WhenInUse` seulement ; avertissement possible de clé « Always » (§4.2) ; position approximative |
| `hive`, `hive_flutter` | Base locale chiffrée | Dossier de documents : exclusion de la sauvegarde, protection des fichiers |
| `shared_preferences` | Préférences | `UserDefaults` : déclaré au manifeste de confidentialité |
| `connectivity_plus` | Réseau | Le simulateur rapporte mal l'état du réseau : tester sur appareil |
| `url_launcher` | `tel:`, plans, liens | `LSApplicationQueriesSchemes` si `canLaunchUrl` est utilisé |
| `file_picker ^11` | Pièces jointes | Sélecteur de photos système, **HEIC**, aucune autorisation |
| `add_2_calendar` | Ajout d'événement | Écriture seule ; maintenance du paquet |
| `flutter_svg`, `uuid`, `crypto`, `intl`, `go_router`, `dio`, `flutter_riverpod` | Utilitaires | Rien de propre à iOS (Dio : `NSAppTransportSecurity` par défaut = HTTPS ; en développement local, autoriser le réseau local sans désactiver ATS) |
| **À ajouter** : `local_auth` | Face ID | `NSFaceIDUsageDescription` ; `biometricOnly: true` |
| **À ajouter (option)** : tâche d'arrière-plan | Synchro | v1.1 seulement (§4.10) |

### 7.4 Tests : sans Mac, avec Mac, sur appareil

| Niveau | Où | Contenu |
|---|---|---|
| **Widgets, variante iOS** | **Linux** (CI existante `mobile.yml`) | `debugDefaultTargetPlatformOverride = TargetPlatform.iOS` dans des tests ciblés : onglets à 5, titres larges, alertes, `XmToast`, textes à `textScaler` ×2 et ×3, contraste augmenté, réduction des animations. **Aucun Mac requis, et ça protège les adaptations contre les régressions** |
| Tests de logique | Linux | Les 454 existants ; ajout de tests pour la purge Keychain au premier lancement (couche abstraite), la routine d'effacement, la logique du recouvrement |
| `flutter build ios --no-codesign` | macOS (M) | Dans la CI, à chaque étiquette : détecte les erreurs de Pods et de Swift |
| **`integration_test`** | Simulateur iOS (M) | Les 13 scénarios existants rejoués sur simulateur ; **pas de push réel** (le simulateur reçoit des charges `xcrun simctl push` pour tester l'affichage et le routage seulement) |
| **XCTest** | M | Tests du plugin Swift (icône, protection de fichiers, purge) ; `RunnerTests` existe, vide |
| **Appareil réel** | M | **Obligatoire** pour : jeton APNs et push réel (< 30 s), Time Sensitive, Face ID, Keychain après réinstallation, `isCaptured`, capture d'écran, icône alternative, `beginBackgroundTask`, VoiceOver |
| **TestFlight interne** | App Store Connect | Jusqu'à 100 testeurs de l'équipe (rôles du compte), **sans revue Apple** ; le build apparaît après traitement (quelques dizaines de minutes) |
| **TestFlight externe** | App Store Connect | Jusqu'à 10 000 testeurs, **Beta App Review** pour la première version de chaque numéro ; lien public possible ; **les builds expirent après 90 jours** |
| Fastlane / Codemagic | CI | `beta` (build + TestFlight), `release`, `metadata` (envoi des textes), `screenshots` |

**Matrice d'appareils minimale** : iPhone SE (petit écran, iOS le plus ancien supporté), iPhone récent avec île dynamique (iOS 18 ou 26) ; simulateurs iOS 15, 18 et 26.

**Outil de CI recommandé** : **GitHub Actions (macOS) + Fastlane**, cohérent avec `mobile.yml` ; limiter le job iOS aux **étiquettes de version, à la branche principale et au déclenchement manuel** (minutes ×10 en dépôt privé) ; **Codemagic en plan B** si la signature en CI coûte trop de temps.

### 7.5 Version minimale d'iOS

**Recommandation : iOS 15.0** (valeur déjà dans le projet).

| Argument | Détail |
|---|---|
| **Public** | Au Bénin, beaucoup d'iPhone d'occasion et anciens : iOS 15 couvre les iPhone 6s et suivants |
| **Fonctions requises** | *Time Sensitive* exige **iOS 15** (niveaux d'interruption) : plancher fonctionnel pour le SOS ; Face ID, Keychain, BGTask, sélecteur de photos : disponibles |
| **Dépendances** | Les SDK Firebase et Flutter récents peuvent **exiger plus** que 15 : **le constater au premier `pod install`** ; si la contrainte monte, passer à 16 en le justifiant (couverture) |
| **Nouveautés** | `UIScreen.isCaptured` sur iOS 15-16, état de capture de scène sur iOS 17+ : prévoir le repli |
| **Réexamen** | À chaque version majeure d'iOS : on peut relever le minimum quand la part de l'ancienne devient négligeable parmi les testeurs |

L'exigence d'Apple sur le **SDK de compilation** (Xcode récent à l'envoi, resserrée chaque printemps) est indépendante de la version minimale supportée : **à vérifier à la date de soumission** ; la CI doit utiliser le Xcode accepté.

## 8. Plan par lots, échéancier, risques

### 8.1 Lots

Propriétaires : **C** client, **D** développeur·se Flutter, **M** exige un Mac. **S** = côté serveur Laravel (portée très limitée). Les estimations sont en **jours-personne** pour une personne **à l'aise** avec Flutter ; les inconnues d'un premier build iOS (jamais lancé) sont **portées dans IOS-03 et la marge**.

| Lot | Contenu | Dépend de | Qui | j.p | Critères d'acceptation |
|---|---|---|---|---|---|
| **IOS-00** | Décisions du client : D-IOS-1 à D-IOS-10 (§10) ; choix du porteur du compte Apple | — | C | 1 | Tableau de §10 rempli et daté ; avis du conseil sollicité sur âge (D-IOS-5) et chiffrement (D-IOS-8) |
| **IOS-01** | **Compte Apple Developer organisation** : demande du D-U-N-S, inscription, vérification, contacts, rôles, statut de professionnel (DSA), clés (APNs `.p8`, API App Store Connect) ; éventuelle demande d'exonération ; demande de **Critical Alerts** (si D-IOS-3) | IOS-00 | C | 3 (+ délais) | Compte actif ; fiche d'app créée avec l'identifiant `bj.xomiton.xomiton` ; clés dans le coffre ; Apple ID dédié à 2FA |
| **IOS-02** | **Poste et CI macOS** : Mac mini (ou location), Xcode accepté par Apple, Flutter de la CI (3.47.8), CocoaPods, iPhone d'essai ; job `ios` de CI (déclenché à la main) | IOS-00 | D/M | 2 | `flutter doctor` sans rouge côté iOS ; job CI `flutter build ios --no-codesign` vert |
| **IOS-03** | **Premier build iOS** : passer l'appareil en iPhone seul ; `flutter build ios` ; `pod install` ; corriger ce que le build révèle (Pods, versions, Swift) ; lancer sur simulateur ; **versionner `Podfile`** ; icône Xomiton et écran de lancement ; `CFBundleLocalizations` | IOS-02 | D/M | 3 | L'app s'ouvre sur simulateur, connexion, onboarding, SOS jusqu'au compte à rebours (API de recette ou locale) ; cible iPhone seul ; icône Xomiton sans alpha |
| **IOS-04** | **Identité et signature** : `DEVELOPMENT_TEAM`, signature automatique locale, signature par clé d'API en CI, capacités sur l'identifiant, `Runner.entitlements`, Fastlane ou Codemagic jusqu'à TestFlight | IOS-01, IOS-03 | D/M | 2 | Un build signé installé sur l'iPhone d'essai ; un build **traité dans TestFlight interne** |
| **IOS-05** | **Push iOS** : `GoogleService-Info.plist`, clé APNs dans Firebase, capacités Push et Time Sensitive, demande d'autorisation en contexte, délégués de notification, **charge iOS côté serveur** (alerte neutre, niveaux d'interruption, priorité, `push-type`), routage `{type, id}` après PIN | IOS-04 ; C5 (Firebase) | D/M + S | 5 (4 + 1 S) | SOS de test reçu **< 30 s en 4G** sur l'iPhone d'essai, **app fermée et appareil verrouillé**, au niveau *Time Sensitive* ; corps neutre ; ouverture profonde après PIN ; aucun message « données seules » comme seul chemin |
| **IOS-06** | **Socle de design HIG** : `Theme.platform`, `cupertinoOverrideTheme`, `XmAppBar` (titres larges), `XmListRow`, `XmToast`, barre d'onglets à 5, feuilles, alertes, menus, `.adaptive`, `GlobalCupertinoLocalizations`, police système pour le corps, apparence « Système », variante contraste augmenté, haptiques | IOS-03 | D | 10 | Galerie de composants en variante iOS validée sur simulateur ; tests de widgets (variante iOS) au vert ; retour par balayage fonctionnel sur toutes les routes ; 5 onglets au plus |
| **IOS-07** | **Écran par écran** (§3.14) : 31 membres, 12 pair, 12 admin ; Dynamic Type XXL/AX5 ; zones sûres | IOS-06 | D | 14 | Parcours de recette sur iPhone SE et iPhone récent : aucun débordement à XXL ; retour par balayage partout ; plus de `SnackBar`, `FloatingActionButton` ni `PopupMenuButton` Material ; checklist visuelle signée par le client |
| **IOS-08** | **Sécurité et discrétion natives** : plugin `XomitonSecurity` (recouvrement de marque, détection de capture et d'enregistrement, icône alternative, protection de fichiers, purge Keychain au premier lancement, exclusion des sauvegardes), Face ID (`local_auth`), options Keychain, effacement à distance complet | IOS-03 | D/M | 6 | Sur appareil réel : aperçu masqué dans le sélecteur d'applications (clair et sombre) sans clignotement à l'invite Face ID ; capture détectée et signalée ; enregistrement d'écran masqué ; icône alternative basculée ; **réinstallation = aucune ancienne session** ; Face ID facultatif, repli sur le PIN Xomiton, jamais sur le code de l'appareil |
| **IOS-09** | **Hors ligne et liens universels** : `beginBackgroundTask` pour l'envoi du SOS, comportement hors ligne sur appareil, décision sur `BGAppRefreshTask` (D-IOS-7), Associated Domains, **fichier AASA** côté web | IOS-04 | D/M + S | 4 (3 + 1 S) | SOS envoyé puis app quittée : requête terminée ou reprise à l'ouverture ; lien d'invitation ouvre l'app (avec AASA valide) ; message honnête sur la synchro |
| **IOS-10** | **Conformité** : `PrivacyInfo.xcprivacy` et rapport de confidentialité, étiquettes de confidentialité, chiffrement (D-IOS-8), classification d'âge (D-IOS-5), liens de politique et de contact **dans l'app**, signalement et blocage complétés (1.2), **filtre minimal** (D-IOS-9), **comptes de démonstration** (OTP restreint, SOS « test »), notes de revue en anglais, formulation SOS | IOS-07 ; web `/politique-de-confidentialite` en ligne (C2/C6) | D + S | 8 (5 + 3 S) | Rapport de confidentialité sans écart avec les étiquettes ; comptes de démonstration **testés le jour de la soumission** ; SOS de démonstration n'alerte personne ; 1.2 : tableau §5.1 tout « fait » |
| **IOS-11** | **Visuels et fiche** : icône 1024 et variantes, captures 6,9″ réelles composées par le générateur adapté (`docs/appstore/`), textes de §6.5 | IOS-07 | D | 3 | 8 captures conformes aux tailles de §6.2 sans alpha, contrôles verts ; fiche remplie dans App Store Connect (brouillon) |
| **IOS-12** | **Tests et recette** : `integration_test` sur simulateur, XCTest du plugin, recette sur appareils (MOB-xx rejoués sur iPhone), **VoiceOver** (SOS, connexion/OTP, signalement, astreinte), TestFlight **interne** | IOS-05, 07, 08, 09 | D + C | 6 | Checklist MOB rejouée et cochée sur au moins 2 iPhone ; défauts bloquants corrigés ; rapport VoiceOver |
| **IOS-13** | **TestFlight externe** : groupe de testeurs, informations de test, **Beta App Review**, 14 jours de retours | IOS-12, IOS-10 | D + C | 2 | Build externe approuvé ; retours triés |
| **IOS-14** | **Soumission, revue, publication** : soumission, réponse aux questions de la revue, corrections, **libération manuelle**, ouverture par boutiques, suivi | IOS-13 + C3 (avis juridique), C20 (production en ligne) | D + C | 3 (+ attente) | App « Prête à la vente » ; première version disponible dans les boutiques choisies ; suivi des incidents |
| | **Total** | | | **68** | dont ≈ 5 j serveur et ≈ 63 j mobile ou Mac |

**Marge** : +25 % sur ce total = **≈ 85 j.p** (premier build iOS jamais lancé, Swift natif sans expérience préalable dans le dépôt, retours de revue). **Plus ≈ 4 jours de travail côté client** (IOS-00, IOS-01, tests). **Options** : iPad universel **+ 8 à 10 j** (§3.13) ; Critical Alerts accordé **+ 1 j** ; extension de service de notification **+ 3 j** ; synchro `BGAppRefreshTask` **+ 2 j**.

### 8.2 Dépendances amont (hors lots iOS)

| Dépendance | Source | Pourquoi iOS en dépend |
|---|---|---|
| Informations légales, domaine, avis juridique (C2, C3) | [PLAN_FINALISATION.md](PLAN_FINALISATION.md) | URL de politique et d'assistance, classification, pays, chiffrement |
| Projet Firebase (C5) | idem | Clé APNs et `GoogleService-Info.plist` |
| **Premier build Android et recette sur appareil (C14, C15)** | idem | Le code Dart partagé doit être sain avant de chercher des défauts propres à iOS |
| SOS de bout en bout avec de vrais canaux (C7) | idem | Le push iOS fait partie de cet essai |
| Serveur de production en ligne (C6, C20) | idem | Liens universels, comptes de démonstration sur un environnement stable |

### 8.3 Échéancier et chemin critique

Durées en semaines calendaires **à partir de la décision (S0)**, pour **une** personne Flutter avec un Mac ; **deux** personnes ramènent le calendrier à ≈ 10 à 11 semaines hors attentes.

| Semaines | Travail | Attente externe |
|---|---|---|
| S0 | IOS-00 décisions ; **demande de D-U-N-S et d'inscription (IOS-01)** | D-U-N-S : quelques jours à plusieurs semaines |
| S0 à S2 | IOS-02 poste et CI ; IOS-03 premier build sur simulateur | — |
| S2 à S4 | IOS-06 socle de design (démarrage) ; compte Apple en cours | Vérification Apple de l'organisation |
| S3 à S6 | IOS-04 identité et signature (dès que le compte est actif) | **Compte Apple actif** |
| S4 à S10 | IOS-06 puis IOS-07 écrans ; IOS-05 push (S5 à S7) ; IOS-08 sécurité (S6 à S9) | Projet Firebase (C5) |
| S8 à S11 | IOS-09 ; IOS-10 conformité ; IOS-11 visuels | Avis juridique (C3) |
| S10 à S13 | IOS-12 tests et recette ; TestFlight interne | — |
| S13 à S15 | IOS-13 TestFlight externe (≈ 14 jours de retours en parallèle) | Beta App Review (en général un à deux jours, **à vérifier**) |
| S15 à S17 | IOS-14 soumission | **Revue Apple : 24 à 48 h le plus souvent, jusqu'à plusieurs jours ; chaque rejet ajoute un aller-retour de 2 à 5 jours** |

**Chemin critique** : (1) **compte Apple organisation** (D-U-N-S, vérification) ; (2) **avis juridique et pages légales en ligne** (C2, C3) ; (3) **comptes de démonstration fiables** (cause de rejet banale) ; (4) **revue Apple**. Le **calendrier global : ≈ 15 à 17 semaines** pour une personne, ± 4 semaines si le D-U-N-S traîne. **La conception HIG (IOS-06, IOS-07) peut avancer pendant l'attente du compte** : seul le simulateur est nécessaire.

### 8.4 Android d'abord, iOS ensuite : lancement simultané ?

| Option | Conséquences |
|---|---|
| **A. Android d'abord, iOS 6 à 8 semaines plus tard** (**recommandée**, D-IOS-10) | Les défauts du code partagé sont trouvés **une seule fois** (premier build Android, recette, test fermé Play de 14 jours) ; le compte Apple a le temps d'arriver ; l'équipe n'est pas dispersée ; message client : « Android en premier » |
| B. **Simultané** | Possible **seulement** si l'organisation obtient le compte Apple en ≤ 4 semaines et si deux personnes Flutter sont disponibles ; double surface de recette au pire moment ; **un rejet Apple retarde le lancement commun** |
| C. iOS d'abord | Non justifié : pas d'avantage de public connu |

### 8.5 Risques et plan B

| Risque | Probabilité / gravité | Réponse | Plan B |
|---|---|---|---|
| Compte Apple organisation long ou refusé (D-U-N-S, pays non listé, justificatifs) | Moyenne / élevée | Lancer dès S0 ; entité exacte ; prévoir une relance | Compte via une entité partenaire d'un pays supporté (accord écrit, gouvernance claire) ; **éviter** le compte personnel |
| Aucun Mac disponible | Moyenne / élevée | IOS-02 en premier | Mac loué + CI ; **allonger de 30 à 50 %** le calendrier et **réduire le Swift natif** (icône alternative et recouvrement en dernier) |
| Premier build iOS cassé (Pods, versions, Swift) | Haute / moyenne | Marge de 25 %, IOS-03 dédié | Épingler les versions de Flutter, de Firebase et des paquets problématiques |
| Push SOS non livré fiablement | Moyenne / **critique** | Alerte neutre (pas « données seules »), essai sur appareil réel verrouillé, app quittée | Garder l'escalade serveur RG-05 et le SMS ; **ne pas communiquer** sur le SOS iOS tant que l'essai n'est pas consigné |
| Critical Alerts refusé ou long | Moyenne / faible | Time Sensitive en v1 | Aucun : la v1 n'en dépend pas |
| Rejet 1.2 (UGC) | Moyenne / élevée | Filtre minimal, signalement et blocage complets, procédure de modération | Réduire la surface UGC de la première version (messages de groupe désactivés) |
| Rejet 2.1 (démonstration) ou 2.3.1 (fonction cachée) | **Moyenne / élevée** | §5.8 | **Variante « membre » seule** (`SPACES=member`) pour la boutique publique ; pair et admin via distribution privée (TestFlight ou « Apple Business Manager / distribution personnalisée », à vérifier) |
| Rejet à cause de l'icône ou du nom (« Notes ») | Moyenne / faible | Icône originale, nom Xomiton | Retirer l'icône alternative de la v1 |
| Classification 18+ ou 16+ gênante pour les 15 ans | Moyenne / moyenne | D-IOS-5 avec le conseil | Aligner l'âge minimal (16) |
| Confidentialité : données sensibles mal décrites | Moyenne / élevée | Étiquettes et manifeste relus par le conseil | Retarder la soumission plutôt que de sous-déclarer |
| Défaut d'adaptation Dynamic Type (débordements) | Haute / moyenne | Tests XXL/AX5 sur écrans critiques | Plafond de l'échelle sur les écrans critiques |
| Données d'une personne retrouvées après réinstallation (Keychain) | Moyenne / **haute** | Purge au premier lancement, test de réinstallation | — |

## 9. Checklist de publication

### 9.1 Comptes et préparation
- [ ] Décisions D-IOS-1 à D-IOS-10 consignées et datées
- [ ] D-U-N-S obtenu ; compte **Apple Developer organisation** actif ; Apple ID dédié ; 2FA sur numéro de l'organisation
- [ ] Exonération demandée (si éligible) ; cotisation payée ou exonérée
- [ ] Identifiant `bj.xomiton.xomiton` créé ; capacités : Push, Time Sensitive, Associated Domains, Data Protection ; (option) Critical Alerts demandé
- [ ] Clé APNs `.p8` créée, **sauvegardée** hors dépôt, importée dans Firebase ; clé d'API App Store Connect créée
- [ ] Fiche d'app créée dans App Store Connect (nom, SKU, langue, catégories)
- [ ] Statut de professionnel (DSA) renseigné ; choix de la **liste des boutiques** fait
- [ ] Mac, Xcode accepté par Apple, iPhone d'essai, CI macOS opérationnels

### 9.2 Application
- [ ] Cible : iPhone seul (ou universel décidé), iOS 15.0 (ou valeur justifiée)
- [ ] Icône Xomiton 1024 sans alpha ; **toutes les icônes Flutter remplacées** ; variantes sombre et teintée
- [ ] Écran de lancement aux couleurs de jeton, clair et sombre
- [ ] `Info.plist` : textes d'autorisation en français, `NSFaceIDUsageDescription`, `ITSAppUsesNonExemptEncryption` (selon avis), `CFBundleLocalizations`, orientations (portrait), icônes alternatives
- [ ] `Runner.entitlements` : `aps-environment`, Time Sensitive, Associated Domains, protection des données
- [ ] `PrivacyInfo.xcprivacy` + rapport de confidentialité comparé aux étiquettes
- [ ] Aucun pod d'analyse ni de publicité ; aucune clé `NSUserTrackingUsageDescription`
- [ ] Push : SOS de test reçu < 30 s, app fermée, appareil verrouillé, niveau Time Sensitive, texte neutre
- [ ] Recouvrement dans le sélecteur d'applications (clair/sombre) ; capture et enregistrement détectés ; texte honnête dans « Sécurité »
- [ ] Réinstallation = pas d'ancienne session (Keychain purgé)
- [ ] Face ID facultatif, repli sur le PIN Xomiton
- [ ] Mode discret : icône alternative ; limite du nom expliquée
- [ ] Dynamic Type XXL et AX5 : SOS, connexion, OTP, signalement, astreinte lisibles
- [ ] VoiceOver : SOS, connexion/OTP, signalement, astreinte ; actions personnalisées
- [ ] Mode sombre, contraste augmenté, réduire animations et transparence
- [ ] Barre d'onglets à 5 onglets au plus ; retour par balayage partout
- [ ] Lien « Confidentialité » et « Aide et contact » **dans l'app** ; suppression de compte trouvable en 3 touchers

### 9.3 Conformité
- [ ] **1.2** : filtre (ou justification écrite), signalement partout, blocage partout, procédure de modération avec délai, contact publié, CGU
- [ ] **5.1.1(v)** : suppression de compte dans l'app testée sur comptes membre, pair, admin
- [ ] Formulation SOS (annexe F) sur l'écran, la fiche, les notes
- [ ] Étiquettes de confidentialité remplies et relues par le conseil
- [ ] Chiffrement : réponse validée par écrit (D-IOS-8)
- [ ] Classification d'âge répondue honnêtement ; lecture du résultat ; arbitrage 15 ans (D-IOS-5)
- [ ] **Comptes de démonstration** membre, pair, admin testés **le jour de la soumission** ; OTP restreint ; **SOS de démonstration n'alerte personne**
- [ ] Notes de revue en anglais (annexe D), contact de revue (nom, téléphone, e-mail)
- [ ] Politique de confidentialité et suppression de compte **en ligne sous le domaine de production**, lisibles sans connexion

### 9.4 Fiche
- [ ] Nom, sous-titre, texte promotionnel (170), mots-clés (100), description (4 000), nouveautés, URL d'assistance, URL marketing, URL de confidentialité, droits d'auteur
- [ ] Aucune mention d'autre plateforme, de prix ou de superlatif ; aucun mot d'identité (cohérence Play)
- [ ] Captures iPhone 6,9″ **réelles**, sans alpha, 1320 × 2868 (ou taille acceptée), contrôles verts
- [ ] (si universel) captures iPad 13″
- [ ] Preuve de licence des images archivée hors dépôt

### 9.5 TestFlight, revue, publication
- [ ] Builds traités ; TestFlight **interne** : recette cochée
- [ ] TestFlight **externe** : Beta App Review passée ; retours triés (≈ 14 jours)
- [ ] Soumission à la revue ; **libération manuelle** choisie
- [ ] Réponse aux questions de la revue sous 24 h
- [ ] Publication dans les boutiques retenues ; **la « publication progressive » d'Apple ne s'applique qu'aux mises à jour**, donc l'ouverture progressive de la 1.0 se fait **par boutiques** puis élargissement
- [ ] Surveillance des plantages (Organizer et rapports) et des retours ; plan de retrait ou de correctif rapide (une correction demande une nouvelle revue)
- [ ] Mise à jour de [RESTE_A_FAIRE.md](RESTE_A_FAIRE.md) et [PLAN_FINALISATION.md](PLAN_FINALISATION.md) (C22)

## 10. Décisions à valider par le client

| Id | Décision | Options | Recommandation |
|---|---|---|---|
| **D-IOS-1** | iPhone seulement ou universel | iPhone seul / iPhone + iPad | **iPhone seul** à la v1 ; universel plus tard (+ 8 à 10 j, captures et revue iPad) |
| **D-IOS-2** | Mac | Achat / location / CI seule | **Mac mini + CI macOS** ; jamais la CI seule pour concevoir |
| **D-IOS-3** | **Critical Alerts** : demander ou non | Oui / non | **Demander en parallèle, ne pas en dépendre** ; v1 en Time Sensitive ; réservé à l'option « Astreinte » de l'admin, **jamais par défaut pour le pair** |
| **D-IOS-4** | Mode discret iOS : nom neutre ou nom « Xomiton » | Icône neutre + nom inchangé / nom neutre fixe | **Icône neutre, nom inchangé**, limite expliquée ; **ne pas imiter « Notes »** |
| **D-IOS-5** | Classification d'âge et âge minimal de 15 ans | Classification calculée (16+/18+) et 15 ans / relever à 16 ans | **Classification calculée honnêtement ; 15 ans maintenu** si le conseil l'accepte, sinon 16 ; décision avec le conseil (liée à D-MOB-18) |
| **D-IOS-6** | Orientation | Portrait seul / toutes | **Portrait seul** sur iPhone à la v1 |
| **D-IOS-7** | Synchro d'arrière-plan | Aucune / `BGAppRefreshTask` | **Aucune à la v1** ; v1.1 pour l'espace pair si besoin |
| **D-IOS-8** | Chiffrement à l'exportation | « Exempté » / non exempté | **Avis écrit du conseil** avant de répondre ; ne pas poser `false` par défaut |
| **D-IOS-9** | **Filtre de contenus (Guideline 1.2)** : révise Q74 (« pas de filtre ») | Filtre minimal serveur / justification par pré-modération / réduire l'UGC | **Filtre minimal serveur** (≈ 2 j) |
| **D-IOS-10** | Lancement simultané Android et iOS | Android d'abord / simultané | **Android d'abord, iOS 6 à 8 semaines après** |
| D-IOS-11 | Compte Apple : organisation ou individuel | Organisation / individuel | **Organisation** (nom du vendeur public, continuité) |
| D-IOS-12 | Boutiques (pays) de la première version | Tous / liste restreinte | **Liste restreinte** (Bénin et voisins francophones), UE à décider (affichage public de l'adresse) |

---

## Annexe A. `Info.plist` : ajouts et corrections

```xml
<key>CFBundleDevelopmentRegion</key>      <string>fr</string>
<key>CFBundleLocalizations</key>          <array><string>fr</string></array>
<key>CFBundleDisplayName</key>            <string>Xomiton</string>
<key>ITSAppUsesNonExemptEncryption</key>  <!-- selon l'avis écrit : voir §5.6 -->
<key>NSFaceIDUsageDescription</key>
<string>Xomiton utilise Face ID pour déverrouiller l’application à la place de ton code, si tu l’actives.</string>
<key>UIBackgroundModes</key>              <array><string>remote-notification</string></array>
<key>UISupportedInterfaceOrientations</key>
<array><string>UIInterfaceOrientationPortrait</string></array>
<!-- supprimer UISupportedInterfaceOrientations~ipad si « iPhone seulement » -->
<!-- NSLocationWhenInUseUsageDescription : conserver le texte actuel -->
<!-- NSCalendarsWriteOnlyAccessUsageDescription : à la place de l'accès complet si le paquet le permet -->
<!-- CFBundleIcons / CFBundleAlternateIcons : icône neutre du mode discret -->
<!-- ne PAS ajouter : NSCameraUsageDescription, NSPhotoLibraryUsageDescription (sauf exigence constatée),
     NSLocationAlways*, NSUserTrackingUsageDescription -->
```

## Annexe B. Entitlements (`Runner.entitlements`)

```xml
<key>aps-environment</key>                                          <string>production</string>
<key>com.apple.developer.usernotifications.time-sensitive</key>     <true/>
<key>com.apple.developer.associated-domains</key>
<array><string>applinks:DOMAINE-DE-PRODUCTION</string></array>
<key>com.apple.developer.default-data-protection</key>              <string>NSFileProtectionComplete</string>
<!-- seulement si accordé par Apple :
<key>com.apple.developer.usernotifications.critical-alerts</key>    <true/> -->
```

(`aps-environment` vaut `development` pour les builds de développement ; Xcode le pose selon le profil. La valeur de protection des données se discute en IOS-08, §4.5.)

## Annexe C. Charge utile FCM v1 pour un appareil iOS (exemple, serveur)

```json
{
  "message": {
    "token": "<jeton FCM>",
    "data": { "type": "duty_sos", "id": "<ulid>" },
    "apns": {
      "headers": { "apns-push-type": "alert", "apns-priority": "10" },
      "payload": {
        "aps": {
          "alert": { "title": "Xomiton", "body": "Alerte à traiter" },
          "sound": "default",
          "interruption-level": "time-sensitive",
          "thread-id": "astreinte"
        }
      }
    }
  }
}
```

Texte **neutre** : aucun récit, nom, lieu. Taille totale < 4 Ko. Pour un message de communauté : `interruption-level: active`, texte générique.

## Annexe D. Notes de revue (anglais, brouillon)

> Xomiton is a safety and support app (information, secure messaging, reporting) for girls, women and LGBTQ+ people in Benin. The public listing describes the **member** experience. The same app also contains two role-based areas that are **not promoted publicly** because their access is restricted to vetted staff and volunteers, and exposing them in public marketing could put people at risk: a **peer educator** area (case collection and follow-up, offline-first) and an **on-call administrator** area (alert handling, registration validation, moderation). They appear only for accounts that hold those roles. We provide demo accounts for all three (see below).
>
> **Demo accounts** (fictitious data only; SOS alerts from demo accounts are flagged as tests and notify nobody): member `…` / `…`; peer + member `…` / `…`; administrator + member `…` / `…` (one-time code for review: `…`).
>
> **User-generated content (1.2):** posts, comments and messages can be reported from each item; users can be blocked; reports are reviewed by trained moderators within 24 hours; accounts can be warned or suspended; contact: `…`; terms of use forbid objectionable content. A filter blocks `…`.
>
> **Emergency (1.4):** the SOS button alerts the user's trusted contacts and our team. It does **not** call emergency services and the app says so on the SOS screen and in the description.
>
> **Account deletion (5.1.1(v)):** Settings › Security › “Supprimer mon compte” (password required). Data are erased after a 30-day grace period, then anonymised. A public web page also exists: `…/supprimer-mon-compte`.
>
> **Location:** only while the app is open, only when the user chooses to share it with an alert. **Notifications:** Time Sensitive for alerts; no Critical Alerts. **Tracking:** none; no advertising, no analytics SDK. The app is in French only.

## Annexe E. Textes d'autorisation en français (à relire par le client)

| Clé | Texte |
|---|---|
| `NSLocationWhenInUseUsageDescription` | « Xomiton utilise ta position, seulement quand l’application est ouverte, pour l’envoyer avec ton alerte SOS ou ton signalement si tu choisis de la partager. » (existant) |
| `NSFaceIDUsageDescription` | « Xomiton utilise Face ID pour déverrouiller l’application à la place de ton code, si tu l’actives. » |
| `NSCalendarsWriteOnlyAccessUsageDescription` | « Ajouter un événement auquel tu participes à ton calendrier. » (même texte que l'actuel) |

## Annexe F. Formulation SOS

- **Écran SOS** : « Le SOS prévient tes contacts de confiance et l’équipe Xomiton. Il n’appelle pas les services d’urgence et ne les remplace pas. En danger immédiat, appelle les secours de ton pays. »
- **Fiche (description)** : « Xomiton ne remplace ni les services d’urgence, ni les soins médicaux, ni l’aide juridique. En cas de danger immédiat, contactez les services d’urgence de votre pays. »
- **Espaces pair et admin** : même phrase au vouvoiement ou au tutoiement selon l'espace (plan mobile).
- **Interdits** : « sauvetage », « intervention garantie », « disponible 24 h/24 » (sauf si c'est vrai et tenu), « protection assurée ».
