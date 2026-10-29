# Xomiton : dossier de publication Google Play

Version du dossier 1.0 · 29 octobre 2026 · application `bj.xomiton.xomiton`, version 1.0.0 (code 1) · langue de la fiche : français (France), avec gabarit anglais prévu.

Ce dossier réunit tout ce qu'il faut pour remplir la Play Console : textes de la fiche, visuels, formulaire « Sécurité des données », déclarations, checklist de publication et liste de ce qui reste à fournir. Les visuels sont **produits par script** (`build_playstore.py`) à partir de `visuels.json`, du design system et du prototype mobile, puis **vérifiés automatiquement** (§10). Sources de vérité : `design-system/DECISIONS.md` > `design-system/xomiton/MASTER.md` > prototype > `docs/`.

> Règle qui prime, comme dans la charte : la sécurité de la personne passe avant l'esthétique. Une fiche publique ne doit jamais désigner à un tiers une personne qui l'a dans son historique d'installations (§2).

## 1. Où en est-on

| Élément | État |
|---|---|
| Image de présentation 1024 × 500, 8 captures 1080 × 1920 (français) | **Produits et vérifiés** (`assets/`, copie dans `design-system/assets/playstore/`) |
| Textes de la fiche (nom, descriptions) | Rédigés ci-dessous, longueurs contrôlées par le script |
| Formulaire « Sécurité des données » | Rempli d'après le code réel (§6) ; trois points à confirmer par l'équipe technique |
| Icône 512 × 512 | **À exporter** depuis `design-system/logo/icone-appli-bleu.svg` (carré plein, sans coins arrondis : Google applique son masque) |
| Captures tablette 7 et 10 pouces | Non requises ; à produire plus tard (§12) |
| Fichier AAB signé, compte développeur, URL de confidentialité | **À fournir** (§14) |

## 2. Choix de discrétion (à lire avant de valider les visuels)

Le public est vulnérable : une fiche Play reste dans l'historique du compte Google et s'affiche à côté de l'icône sur l'écran d'accueil. Choix retenus :

1. **Nom visible neutre** : « Xomiton : écoute et sécurité ». Aucun mot stigmatisant, aucun sigle d'identité dans le nom, la description courte ou le premier paragraphe. La description longue accueille « quelle que soit votre identité ou votre orientation », sans désigner de groupe.
2. **Aucun drapeau arc-en-ciel envahissant** : fonds de marque en dégradés bleu, violet et ciel (jetons du design system) ; aucune photographie de personnes dans la fiche (les photographies du dossier `assets/` sont réservées à l'onboarding dans l'application). Les illustrations à drapeau de l'accueil et du fil sont **masquées dans les captures** (`visuels.json`, clé `css`), les autres écrans n'en contiennent pas.
3. **Valeur annoncée = écoute, sécurité, communauté, ressources** ; jamais de formulation du type « application pour… » une catégorie de personnes.
4. **Mode discret mis en avant** (capture 08) : icône et nom neutres, aperçus masqués, verrouillage par code. La fiche dit que cela existe ; elle ne dit pas ce que devient le nom de l'icône.
5. **Image de présentation sans personne**, logo blanc sur fond bleu (fond autorisé par la charte), slogan « Écoute, sécurité, communauté ». Le logo reste en blanc monochrome : la variante « sombre » a une rosace claire dont le contraste sur ce bleu n'a pas été calculé.
6. **Ni l'admin ni l'espace pair ne sont promus.** L'admin d'astreinte est un outil interne, sans intérêt pour le public et dont la présence dans la fiche expose des fonctions de modération. L'espace pair est réservé à des comptes à rôle : le montrer ferait croire qu'il est accessible à tous. Le pair éducateur apparaît quand même, comme **interlocuteur**, dans la capture « Messages » (c'est ce que voit une personne membre). Aucune capture dédiée n'a donc été produite ; à rediscuter seulement si le client veut recruter des pair·es via le Store (mieux vaut le faire hors Store).
7. **Données de démonstration** : pseudonymes fictifs (Awa, Chantal, Membre anonyme). Les noms de famille du prototype sont raccourcis à l'initiale par le script (`remplacements`). Aucune donnée réelle.
8. **Écran d'ouverture (splash) non montré** : il contient une photographie avec drapeau ; la capture d'accueil est préférée.
9. **Tutoiement ou vouvoiement** : les captures montrent l'interface réelle (tutoiement, règle de l'espace connecté) ; les titres ajoutés sont écrits à la forme impersonnelle pour ne mélanger aucun registre ; la description publique vouvoie (site public, règle Q36).

Images du client : les deux contre-exemples de `design-system/assets/splash/references-a-eviter/` sont autorisés **dans l'onboarding** à la demande du client. Le premier (slogans anglais, buste dénudé) n'est utilisable qu'en recadrage : `splash-symbole-venus-recadre.webp` (480 × 392, haut du visage et main, texte et buste retirés). Le second (bouches scotchées) n'a besoin d'aucun recadrage mais **n'est pas utilisé dans la fiche Play** : image anxiogène, hors discrétion et hors des écrans de signalement et de SOS.

## 3. Identité de la fiche

| Champ | Valeur |
|---|---|
| Nom de l'application (30 caractères max) | <!--DEBUT:nom-->Xomiton : écoute et sécurité<!--FIN:nom--> |
| Identifiant | `bj.xomiton.xomiton` |
| Catégorie | **Réseaux sociaux** (repli : Style de vie). Éviter « Santé et remise en forme » : elle impose une déclaration de santé plus lourde alors que l'application oriente sans soigner |
| Étiquettes (tags) | Communauté, Sécurité, Entraide (3 à 5 selon la console) |
| E-mail de contact (public) | contact@xomiton.bj (valeur par défaut des réglages ; à confirmer) |
| Site web | à fournir (domaine de production) |
| Téléphone | facultatif ; laisser vide |
| Politique de confidentialité | `https://<domaine>/confidentialite` (page déjà prévue dans le site, route `confidentialite`) ; **à publier avant le dépôt** (§7) |
| Pays de distribution | Bénin d'abord ; élargir ensuite. Les pays francophones d'Afrique de l'Ouest peuvent suivre |
| Tarif | à définir par le client ; la fiche ne parle pas de prix |
| Publicités | Aucune |
| Achats intégrés | Aucun |

## 4. Textes de la fiche (français)

### Description courte (80 caractères max)

<!--DEBUT:court-->Un espace sûr pour s'informer, échanger et être soutenu·e, en toute discrétion.<!--FIN:court-->

### Description longue (4 000 caractères max)

<!--DEBUT:long-->
Xomiton est un espace sûr pour s'informer, échanger et être soutenu·e, avec une attention particulière aux filles et aux femmes, et un accueil sans jugement quelle que soit votre identité ou votre orientation.

CE QUE VOUS POUVEZ FAIRE

• Alerter les personnes de confiance : le bouton SOS, à maintenir deux secondes, prévient vos contacts de confiance et le point focal de votre zone. Vous choisissez de partager ou non votre position, et vous pouvez annuler l'alerte pendant quelques secondes.
• Signaler en confiance : un parcours guidé en quatre étapes pour déclarer une situation vécue ou observée, anonymement si vous le souhaitez, puis en suivre l'avancement avec un code.
• Échanger : messages privés avec des pair·es éducateur·rices formé·es et groupes de discussion modérés. Une personne formée lit votre message sous 24 h.
• S'orienter : un assistant propose des ressources, des contacts et des démarches. Il ne remplace pas un·e professionnel·le.
• Se renseigner : fiches pratiques, textes de loi, bandes dessinées et vidéos sur les droits, la santé, la sécurité numérique et le bien-être. Plusieurs ressources se lisent hors ligne.
• Faire partie d'une communauté bienveillante : publications, sondages, événements et ateliers, avec des règles de modération claires.

PENSÉE POUR LA DISCRÉTION

• Mode discret : l'icône et le nom de l'application peuvent devenir neutres sur Android.
• Aperçus masqués : les notifications et les messages n'affichent pas leur contenu à l'écran de verrouillage.
• Verrouillage par code à l'ouverture de l'application.
• Inscription simple : un pseudonyme suffit. Aucune date de naissance n'est demandée ; vous confirmez avoir 15 ans ou plus.
• Vos données vous appartiennent : vous pouvez les exporter ou supprimer votre compte depuis l'application.

À SAVOIR

Xomiton ne remplace ni les services d'urgence, ni les soins médicaux, ni l'aide juridique. En cas de danger immédiat, contactez les services d'urgence de votre pays. L'application affiche des contacts utiles, qui peuvent varier selon votre zone. Le partage de votre position est facultatif et ne concerne que les personnes alertées.

Une question ? Écrivez-nous : contact@xomiton.bj
<!--FIN:long-->

Rédaction : voix Xomiton (chaleureuse, claire, rassurante), phrases courtes, écriture inclusive au point médian, aucune promesse de disponibilité permanente (formule de référence « Une personne formée lit votre message sous 24 h »), aucun mot de la liste interdite (meilleur, n° 1, nouveau, gratuit, remise, millions de téléchargements, télécharger, installer, essayer). Le script le contrôle (§10).

### Notes de version (500 caractères max)

« Première version : SOS, signalement guidé, messages, assistant, ressources, communauté, mode discret. »

## 5. Classification du contenu, public cible, âge

| Sujet | Réponse proposée | Remarque |
|---|---|---|
| Questionnaire de classification (IARC) | Catégorie « Réseaux sociaux / communication ». Contenu généré par les utilisateurs : **oui** (modéré, avec signalement et blocage). Partage de position : **oui** (SOS, facultatif). Violence : aucune représentation ; **sujets de violence traités à titre d'information et de soutien**. Sexualité, drogue, jeu d'argent, langage grossier : non. Achats : non | Répondre honnêtement ; la note finale (probablement 12 ans et plus ou Teen) est calculée par le questionnaire, ne pas la présumer |
| Public cible et âge | **16-17 ans et 18 ans et plus** dans la console. Âge minimal produit : **15 ans** (Q68, tranchée le 28 octobre 2026, case « J'ai 15 ans ou plus », aucune date de naissance) | **Écart à trancher** : la tranche Play « 13-15 » commence à 13 ans ; la cocher classe l'application comme visant des mineurs (politique Familles, durcissement des règles) alors que le produit exclut les moins de 15 ans. Recommandation : ne cocher que 16-17 et 18+ ; les personnes de 15 ans peuvent utiliser l'application selon les conditions d'utilisation, mais la fiche ne les cible pas. À faire valider par le conseil juridique (Q67, Q68) |
| Application destinée aux enfants (programme Familles) | Non | Ne pas rejoindre le programme |
| Contenu sensible | Mentionner violences basées sur le genre, santé sexuelle, soutien psychologique : c'est du contenu **informatif et de soutien** | Voir §9 |
| Déclaration « Applications de santé » | Répondre que l'application ne propose pas de suivi médical, de diagnostic ni de dispositif médical ; elle oriente vers des ressources | Formulaire obligatoire pour toutes les applications |
| Déclaration de sécurité financière, applications gouvernementales | Non concerné | |
| Accès à l'application (identifiants de test) | Fournir un compte de démonstration d'évaluation (ex. membre), sans donnée réelle | Le prototype en liste un ; créer un compte dédié à Google sur l'environnement de préproduction |

## 6. Formulaire « Sécurité des données » (data safety)

Établi d'après le code : `mobile/pubspec.yaml`, `AndroidManifest.xml`, `platform_services.dart`, `web/app/Models`, `web/config/xomiton.php`, `docs/SECURITE_CHECKLIST.md`. Aucune bibliothèque de publicité, d'analyse d'audience ou de rapport de plantage n'est présente (vérifié : ni Firebase Analytics, ni Crashlytics, ni Sentry).

**Questions générales**

| Question | Réponse |
|---|---|
| L'application collecte-t-elle ou partage-t-elle des données utilisateur ? | Oui |
| Toutes les données sont-elles chiffrées en transit ? | **Oui** (HTTPS uniquement : `usesCleartextTraffic="false"`) |
| Les utilisateurs peuvent-ils demander la suppression de leurs données ? | **Oui** : suppression du compte depuis l'application (Paramètres > Sécurité > « Supprimer mon compte »), effacement différé de 30 jours puis anonymisation ; export des données (lien valable 48 h). **URL web de suppression de compte requise par Google : à créer** (§14) |
| Données sauvegardées par Android ? | Non (`allowBackup="false"`, règles d'extraction excluant tout) |

**Types de données**

| Catégorie Play | Donnée | Collectée | Partagée | Finalité | Facultative | Chiffrement et remarques |
|---|---|---|---|---|---|---|
| Informations personnelles | Pseudonyme (nom) | Oui | Non | Fonctionnement, compte | Non | Aucun vrai nom demandé |
| Informations personnelles | Adresse e-mail | Oui | Non | Compte, code de vérification, notifications | Non | Code OTP envoyé par e-mail, pas par SMS |
| Informations personnelles | Numéro de téléphone | Oui, uniquement si saisi pour un contact de confiance ou le profil | Oui, avec un prestataire d'envoi SMS (**fournisseur de service**, traitement pour notre compte) | Alerte SOS de secours par SMS | Oui | À confirmer : liste exacte des champs téléphone du profil |
| Informations personnelles | Origine, orientation ou identité de genre (« autres informations sensibles ») | Oui, si la personne y consent | Non | Personnalisation du soutien | **Oui**, consentement distinct, retirable | **Chiffrement au repos de ce champ non constaté dans le modèle : à confirmer avant de cocher « chiffré »** |
| Localisation | Position précise (et approximative) | Oui, **au moment de l'alerte SOS seulement**, premier plan uniquement, si la personne active le partage | Non à des tiers ; visible des personnes alertées | Fonction SOS | Oui | Latitude et longitude chiffrées en base (`Alert.lat`, `lng`) ; pas de localisation en arrière-plan |
| Messages | Messages dans l'application (messagerie, assistant) | Oui | Non | Fonctionnement | Non (si la fonction est utilisée) | Corps de message chiffré en base (`Message.body`, `AssistantMessage.body`) |
| Photos et vidéos / Fichiers et documents | Pièces jointes d'un signalement (sélecteur système) | Oui, si la personne en ajoute | Non | Preuves d'un signalement | Oui | Pas d'accès à la galerie : sélecteur de fichiers du système, **aucune permission média** |
| Santé et remise en forme | Contenu libre d'un signalement ou d'un besoin pouvant décrire une situation de santé | Oui (saisie libre) | Non | Prise en charge | Oui | Par prudence, déclarer « informations de santé » comme **collectées, facultatives, non partagées**, ou à défaut le justifier par le champ libre |
| Activité dans l'application | Contenus publiés, commentaires, sondages, « j'aime » | Oui | Non | Fonctionnement | Oui | Modération par l'équipe |
| Identifiants d'appareil ou autres | Jeton de notification (FCM), identifiant d'appareil de synchronisation, adresse IP et agent utilisateur du journal d'audit et des consentements | Oui | Jeton FCM : traité par **Google (Firebase Cloud Messaging)** comme fournisseur de service | Notifications, sécurité, preuve de consentement | Non | Journal d'audit conservé 90 jours ; appareil inactif révoqué après 180 jours |
| Infos et performances de l'application | Plantages, diagnostics | **Non collectés** | | | | |
| Finances, contacts du carnet, calendrier | Non collectés | | | | | « Ajouter à l'agenda » écrit un événement local, sans lire l'agenda |

À répondre dans la console : la **finalité** « Publicité ou marketing » est **non** ; la **newsletter** (consentement distinct) utilise l'e-mail pour « communications des développeurs ». Aucune donnée n'est vendue.

Points à confirmer par l'équipe technique avant de soumettre : (1) liste exacte des champs téléphone, (2) chiffrement au repos du profil sensible et des pièces jointes, (3) pays d'hébergement du serveur et des fournisseurs SMS et e-mail, à citer dans la politique de confidentialité.

## 7. Politique de confidentialité

À publier à une URL publique stable (non protégée, non PDF si possible), accessible aussi **dans l'application** (écran Sécurité et confidentialité, déjà prévu) et **avant** l'inscription. Contenu minimal attendu par Google et par le RGPD/la loi béninoise :

1. Qui est responsable (nom de l'association ou de l'entité, adresse, contact de la personne chargée des données).
2. Données collectées (celles du §6), pour chacune : finalité, base légale (consentement ou exécution du service), caractère facultatif.
3. Données sensibles : consentement distinct, retirable, sans incidence sur l'accès aux autres fonctions.
4. Destinataires : équipe habilitée (rôles), personnes alertées (position), fournisseurs (hébergement, e-mail, SMS, notifications Google Firebase) ; aucune vente, aucune publicité, aucun profilage.
5. Transferts hors du Bénin et garanties.
6. Durées de conservation (compte supprimé : 30 jours puis anonymisation ; journal d'audit 90 jours ; e-mails de la file vidés après envoi ; dossiers sensibles 5 ans après clôture du cas ; liens d'export 48 h).
7. Droits : accès, export, rectification, suppression, retrait du consentement, réclamation auprès de l'autorité de protection des données ; **comment suppression depuis l'application et depuis le web**.
8. Sécurité : HTTPS, chiffrement des champs sensibles, accès par rôles, journal d'audit, mode discret.
9. Âge minimal (15 ans), absence de date de naissance.
10. Mineur·es et signalements : ce qui peut être transmis à des services de protection de l'enfance, **à faire valider juridiquement**.
11. Date de dernière mise à jour et historique des versions.

Les mots employés doivent être ceux de l'application (pas de contradiction avec les écrans de consentement).

## 8. Déclaration des permissions

| Permission (AndroidManifest) | Usage réel | Justification à donner | Risque |
|---|---|---|---|
| `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION` | Une seule position, relevée à l'envoi d'une alerte SOS si la personne a laissé le partage actif (délai de 8 s, sinon l'alerte part sans position) | Fonction centrale de sécurité ; premier plan uniquement ; explication affichée **avant** la demande système (divulgation bien visible exigée par la politique Données utilisateur) | Moyen : revue possible ; ne **pas** demander la localisation en arrière-plan |
| `POST_NOTIFICATIONS` (Android 13+) | Alertes et messages ; demandée après la première connexion | Notifications utiles ; aperçus masqués possibles ; le refus ne bloque rien | Faible |
| Photos et vidéos | **Aucune permission** : sélecteur de fichiers du système pour joindre une preuve | Pas de déclaration « autorisations photos et vidéos » à remplir | Faible |
| `INTERNET`, `ACCESS_NETWORK_STATE`, `VIBRATE` | Réseau, état hors ligne, retour tactile du SOS | Permissions normales | Aucun |

Le rédacteur de la console demandera aussi la **vidéo de démonstration** de la fonction de localisation : prévoir un enregistrement de l'écran SOS (demande, partage, envoi).

## 9. Politiques Google à risque pour ce type d'application

Formulations prudentes à reprendre dans la fiche et dans les réponses aux revues.

| Politique | Risque | Mesure |
|---|---|---|
| Contenu généré par les utilisateurs | Obligation de modération, de signalement et de blocage | Modération par l'équipe, signalement de contenu et règles visibles ; le décrire dans « Informations de l'application » |
| Contenu sensible et violences | Contenu abordant violences, santé sexuelle | Contenu **informatif et de soutien**, sans scène explicite, sans incitation ; avertissements de contenu sur les fiches |
| Services d'urgence et sécurité | Laisser croire que l'application remplace les secours ou garantit une intervention | Ne jamais écrire « intervention immédiate », « nous venons vous chercher », « secours garantis ». Dire « prévient vos contacts de confiance et le point focal » et « ne remplace pas les services d'urgence ». Pas de promesse de réponse humaine en continu |
| Santé | Allégations médicales, conseils non encadrés | L'assistant **oriente** ; mention « ne remplace pas un·e professionnel·le » ; pas de diagnostic |
| Localisation | Collecte sans consentement clair, arrière-plan | Premier plan, facultative, une position par alerte, divulgation préalable, politique de confidentialité à jour |
| Comportement trompeur | Le mode discret change le **nom et l'icône affichés au lancement** (alias d'activité « Notes ») ; un examinateur peut y voir une usurpation | Fonction activée uniquement par la personne, pour sa sécurité ; l'expliquer dans les notes aux examinateurs (« Instructions d'accès ») ; la fiche, le nom du paquet et la page d'information gardent « Xomiton » ; ne pas imiter l'icône ni le nom d'une application existante de tiers |
| Suppression de compte | Lien web de suppression obligatoire | Créer une page publique (§14) |
| Métadonnées | Mots promotionnels, classements, répétition, témoignages | Aucun de ces éléments dans les visuels ni les textes (contrôlé par le script) |
| Âge et mineurs | Tranches d'âge incohérentes avec le produit | Voir §5 |

## 10. Visuels : spécifications, contrôles et texte alternatif

Générateur : `python3 docs/playstore/build_playstore.py` (Playwright et Pillow, Chromium `/opt/pw-browsers/chromium-1194/chrome-linux/chrome` ou variable `XM_CHROMIUM`). Il capture les écrans du prototype à **390 × 844 avec un facteur d'échelle 3** (1170 × 2532 réels), fige la barre d'état (9:41, signal et Wi-Fi pleins, batterie pleine, aucun opérateur, aucune notification), compose sur dégradés issus des jetons, exporte en PNG RVB 24 bits sans canal alpha et écrit `assets/rapport-controles.json`. Options : `--verifier`, `--seulement 02-sos`, `--langue en`.

Contrôles automatiques (130 passés à la livraison, fiche comprise) : dimensions exactes, PNG 24 bits (profondeur 8, type RVB, aucun bloc de transparence), poids (< 8 Mo ; mesuré 0,3 à 0,6 Mo), ratio 9:16, aire du texte ajouté (titre et pastille) ≤ 20 % (mesurée de 6 à 10 %), contraste du texte ajouté ≥ 4,5:1 mesuré sur le fond réel au pixel le plus défavorable (5,65:1 au pire), couleurs vives de l'image de présentation, fonds de captures en jetons saturés, barre d'état propre, mots interdits, longueur du texte alternatif, absence de « image de », longueur du nom (30), de la description courte (80) et de la longue (4 000).

Typographie : Montserrat 800 (titres) et 700 (pastilles), auto-hébergée depuis `design-system/fonts/`. Titres de 76 px sur 1080 px de large, bien au-delà du minimum lisible. Aucun appel à l'action, aucun badge de magasin, aucun logo tiers, cadre d'appareil générique (ni encoche ni marque).

### Tableau des assets

| Fichier | Dimensions | Format | Rôle | Texte alternatif (≤ 140 caractères) |
|---|---|---|---|---|
| `assets/feature-graphic-1024x500.png` | 1024 × 500 | PNG 24 bits, sans alpha | Image de présentation | Logo Xomiton et mots « écoute, sécurité, communauté » sur fond bleu, entre deux écrans de l'application. |
| `assets/screenshots/01-accueil.png` | 1080 × 1920 | PNG 24 bits, sans alpha | Capture 1, interface | Accueil de l'application : accès rapide au signalement, aux urgences, à l'assistant et aux thématiques, avec la touche SOS en bas. |
| `assets/screenshots/02-sos.png` | 1080 × 1920 | idem | Capture 2, interface | Écran SOS : grand bouton rouge à maintenir deux secondes, option pour partager sa position, lien vers les numéros d'urgence. |
| `assets/screenshots/03-signaler.png` | 1080 × 1920 | idem | Capture 3, interface | Écran Signaler et suivre : alerte SOS, faire un signalement en quatre étapes, exprimer un besoin et suivre un dossier avec un code. |
| `assets/screenshots/04-messages.png` | 1080 × 1920 | idem | Capture 4 | Conversation écrite avec une pair éducatrice : messages reçus et envoyés, champ de saisie en bas de l'écran. |
| `assets/screenshots/05-assistant.png` | 1080 × 1920 | idem | Capture 5 | Écran Assistant : message d'accueil, rappel qu'il ne remplace pas un professionnel et suggestions de questions. |
| `assets/screenshots/06-ressources.png` | 1080 × 1920 | idem | Capture 6 | Écran Ressources : recherche et filtres, fiches pratiques, textes de loi, bande dessinée et vidéo, avec la taille de chaque fichier. |
| `assets/screenshots/07-communaute.png` | 1080 × 1920 | idem | Capture 7 | Fil de la communauté : zone pour partager une pensée, filtres par thème et publication d'une pair éducatrice sur un atelier. |
| `assets/screenshots/08-confidentialite.png` | 1080 × 1920 | idem | Capture 8 | Écran Sécurité : mode discret, masquage des aperçus, verrouillage par code, appareils connectés, téléchargement et suppression des données. |
| Icône 512 × 512 | 512 × 512 | PNG 32 bits (alpha autorisé pour l'icône) | Icône, **à exporter** | Logo Xomiton : rosace rouge à quatre formes sur fond bleu. |

Titres incrustés (français) : 01 « Un espace sûr, tout près » · 02 « Alerter les personnes de confiance » · 03 « Signaler, même anonymement » · 04 « Des échanges à son rythme » · 05 « Un assistant pour s'orienter » · 06 « Des ressources fiables à portée de main » · 07 « Une communauté bienveillante » · 08 « Vie privée et mode discret ». Chaque capture porte une pastille courte (Confidentiel, Position partagée avec accord, Anonyme si besoin, Écoute sans jugement, Oriente, ne remplace pas, Lisibles hors ligne, Échanges modérés, Icône et nom neutres), dont le texte reste fidèle au produit.

Ordre : les **trois premières captures sont centrées sur l'interface** (accueil, SOS, signalement), comme demandé. Adaptations du prototype faites pour la fiche et assumées : barre d'état figée, notch retiré (cadre générique), illustrations à drapeau masquées (accueil et fil), noms de famille raccourcis.

## 11. Version anglaise (gabarit, non générée)

`visuels.json` contient, pour chaque visuel, un bloc `textes.en` **vide** (`titre`, `puces`, `alt`, `slogan`). Pour produire la version anglaise : (1) remplir ces blocs, (2) fournir une version anglaise du prototype (les captures montrent aujourd'hui l'interface française ; changer seulement les titres donnerait des captures incohérentes), (3) lancer `python3 docs/playstore/build_playstore.py --langue en` : le script refuse de produire tant qu'un texte est vide et écrit dans `assets/en/`. Traduire le nom, les descriptions et le §4 dans la console (« Gérer les traductions »).

## 12. Captures tablette 7 et 10 pouces (plus tard)

Non requises pour publier. Elles améliorent la mise en avant sur grands écrans (recommandées si l'application est installable sur tablette). Spécifications Google : 2 à 8 captures par format, PNG ou JPEG 24 bits sans alpha, côtés de 320 à 3 840 px, rapport 16:9 ou 9:16 au plus. Pour les produire : ajouter dans `visuels.json` un bloc `tablette_7` et `tablette_10`, un viewport du prototype adapté (`1280 × 800` en paysage, la mise en page est responsive) et un nouveau gabarit de composition. Hors périmètre de cette livraison.

## 13. Version et publication

| Élément | Valeur |
|---|---|
| Nom de version / code | 1.0.0 / 1 (`mobile/pubspec.yaml`, `1.0.0+1`) ; incrémenter le code à chaque envoi |
| `minSdk` | 26 (Android 8.0) |
| `targetSdk` | `flutter.targetSdkVersion` : **vérifier que la valeur atteint l'exigence Google en vigueur au moment du dépôt** (page « Target API level requirements ») |
| Format | **AAB** (Android App Bundle), pas d'APK |

### Checklist de publication

- [ ] Compte développeur créé et vérifié (identité, adresse, téléphone ; organisation : numéro D-U-N-S si compte d'organisation)
- [ ] Clé d'envoi (upload key) générée, **sauvegardée hors dépôt** ; fichier `key.properties` non versionné
- [ ] **Play App Signing** activé (Google garde la clé de signature ; recommandé et obligatoire pour les AAB)
- [ ] `flutter build appbundle --release` avec `--obfuscate --split-debug-info` ; symboles de débogage conservés
- [ ] Vérifier `applicationId`, version, icônes adaptatives, nom Android « Xomiton », alias du mode discret
- [ ] Politique de confidentialité en ligne, lien de suppression de compte en ligne
- [ ] Formulaires de la console : contenu de l'application, sécurité des données, classification, public cible, application de santé, permissions (localisation), accès à l'application (compte de test)
- [ ] Fiche : nom, descriptions, icône 512, image de présentation, 8 captures, catégorie, contact
- [ ] Piste de **test interne** (jusqu'à 100 testeurs, immédiat) : valider installation, SOS, notifications, mode discret, localisation sur des appareils Android 8 à 15
- [ ] Piste de **test fermé** : **comptes personnels créés après novembre 2023 : au moins 12 testeurs inscrits en continu pendant 14 jours avant de pouvoir demander l'accès à la production** (compter large, 20 recommandé, pour absorber les départs) ; ne s'applique pas aux comptes d'organisation
- [ ] Demande d'accès à la production avec les réponses aux questions de test (retours reçus, corrections faites)
- [ ] Déploiement de production **progressif** (par exemple 20 % puis 100 %), surveillance des plantages et des retours
- [ ] Tests sur appareils réels (Arial devient Roboto sur Android : la mise en page doit rester correcte, charte §5)
- [ ] Revue de discrétion par l'association avant chaque changement de visuel

Délais : la revue d'une nouvelle application peut prendre plusieurs jours, voire plus longtemps pour une première soumission ; ne pas planifier de lancement public sans marge.

## 14. Ce que le client doit encore fournir

| À fournir | Pourquoi |
|---|---|
| Compte développeur Google Play (type personnel ou organisation) et frais d'inscription | Condition de tout dépôt ; le type de compte décide de l'obligation des 12 testeurs |
| 12 à 20 testeur·ses volontaires (adresses Google) pour 14 jours, si compte personnel | Condition d'accès à la production |
| **URL de la politique de confidentialité** (domaine de production) et sa validation juridique | Obligatoire ; la page web existe dans le code, elle doit être publiée et complétée (§7) |
| **URL web de suppression de compte** | Exigée par Google, en plus de la suppression dans l'application |
| Preuve de licence des images (archivée hors dépôt) | Licence d'usage commercial des dix visuels du dossier `assets/` confirmée le 28 octobre 2026 : la preuve n'est pas dans le dépôt ; indispensable si une photographie est un jour ajoutée à la fiche ou à l'onboarding. Les captures de la fiche **n'en contiennent pas** |
| Validation du **logo vectoriel** | Reconstitution à partir du seul fichier de 240 × 66 px, à faire valider ou remplacer par le fichier source de la personne qui l'a dessiné |
| Adresse de contact publique, site web | Champs de la fiche |
| Décision sur la tranche d'âge (Q67, Q68) | §5 : 16+ ou 13-15 |
| Compte de test pour l'examen et vidéo de démonstration de la localisation | Revue Google |
| Réponses de l'équipe technique aux trois points du §6 | Exactitude de la déclaration |
| Validation du sigle, des textes et du choix de ne montrer ni admin ni espace pair | Discrétion, §2 |
| Clé de signature d'envoi et fichier AAB | Dépôt |
| Traduction anglaise et prototype en anglais | Version anglaise de la fiche (§11) |
| Tarif et pays de distribution | Champs de la console |

*Ce dossier ne constitue pas un avis juridique. Les exigences de Google évoluent : relire les pages officielles de la Play Console avant le dépôt (exigences d'API cible, règles de test, classification et sécurité des données).*
