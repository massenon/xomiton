# Xomiton : dossier de publication Google Play

Version du dossier 1.1 · 29 octobre 2026 · application `bj.xomiton.xomiton`, version 1.0.0 (code 1) · langue de la fiche : français (France), avec gabarit anglais prévu.

Ce dossier réunit tout ce qu'il faut pour remplir la Play Console : textes de la fiche, visuels, formulaire « Sécurité des données », déclarations, checklist de publication et liste de ce qui reste à fournir. Les visuels sont **produits par script** (`build_playstore.py`) à partir de `visuels.json`, du design system et du prototype mobile, puis **vérifiés automatiquement** (§10). Sources de vérité : `design-system/DECISIONS.md` > `design-system/xomiton/MASTER.md` > prototype > `docs/`.

> **Avertissement interne (ne figure pas sur les pages publiques).** Les pages légales du site (politique de confidentialité, conditions d'utilisation, mentions légales, cookies, suppression de compte) et ce dossier **ne remplacent pas un avis juridique**. Elles décrivent le fonctionnement réel du code ; elles doivent être relues et validées par le conseil juridique du client (droit béninois, RGPD pour les personnes concernées, règles Google Play) avant le dépôt.

> Règle qui prime, comme dans la charte : la sécurité de la personne passe avant l'esthétique. Une fiche publique ne doit jamais désigner à un tiers une personne qui l'a dans son historique d'installations (§2).

## 1. Où en est-on

| Élément | État |
|---|---|
| Image de présentation 1024 × 500, 8 captures 1080 × 1920 (français) | **Produits et vérifiés** (`assets/`, copie dans `design-system/assets/playstore/`) |
| Textes de la fiche (nom, descriptions) | Rédigés ci-dessous, longueurs contrôlées par le script |
| Formulaire « Sécurité des données » | Rempli d'après le code réel (§6) ; trois points à confirmer par l'équipe technique |
| Icône 512 × 512 | **À exporter** depuis `design-system/logo/icone-appli-bleu.svg` (carré plein, sans coins arrondis : Google applique son masque) |
| Captures tablette 7 et 10 pouces | Non requises ; à produire plus tard (§12) |
| Politique de confidentialité et URL web de suppression de compte | **Créées dans le site** (§7) : `/politique-de-confidentialite` et `/supprimer-mon-compte`, accessibles sans connexion ; domaine de production et champs « À COMPLÉTER PAR LE CLIENT » à fournir |
| Décisions du client du 29 octobre 2026 (âge, tarif, pays, langue, version unique) | **Reportées** dans ce dossier (§3, §5) |
| Fiche de publication au format Word | `Fiche-publication-Play-Store-Xomiton.docx` (§15) |
| Fichier AAB signé, compte développeur | **À fournir** (§14) |

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
| Site web | `https://<domaine>/` (domaine de production à fournir) |
| Téléphone | facultatif ; laisser vide |
| Politique de confidentialité (URL) | `https://<domaine>/politique-de-confidentialite` (route `legal.privacy`) ; **à remplacer par le domaine de production et à publier avant le dépôt** (§7) |
| URL web de suppression de compte | `https://<domaine>/supprimer-mon-compte` (route `account-deletion.show`) ; accessible sans connexion ; **domaine à remplacer** (§7) |
| Pays de distribution | **Tous les pays** (décision du client, 29 octobre 2026) ; points de vigilance ci-dessous |
| Tarif | **Pas de tarif : application gratuite** (décision du client). Console : « Gratuite » ; la fiche ne mentionne aucun prix ni le mot « gratuit » |
| Publicités | Aucune |
| Achats intégrés | Aucun |
| Langue de la fiche | **Français uniquement** (décision du client) ; le gabarit anglais du §11 est conservé mais **non utilisé** |
| Versions publiées | **Une seule version Play** : un seul AAB (`bj.xomiton.xomiton`) avec des espaces selon le rôle du compte (membre, pair éducateur·rice, admin d'astreinte), voir ci-dessous |

### Décisions du client (29 octobre 2026)

| Sujet | Décision | Conséquence dans le dossier |
|---|---|---|
| Âge | Âge minimal du produit **15 ans** ; tranches Play **« 16-17 » et « 18+ » uniquement** | §5 |
| Tarif | Aucun, application gratuite | Console : gratuite ; aucun prix dans la fiche |
| Pays | Tous les pays | Points de vigilance ci-dessous |
| Langue | Français uniquement | Pas de traduction dans la console |
| Version | **Un seul AAB**, trois espaces selon le rôle (membre : tous les comptes ; pair : rôle `pair` validé ; admin d'astreinte : permission dédiée) | La fiche présente l'espace membre ; **ni l'admin ni le pair ne sont promus publiquement** (§2.6). Les espaces pair et admin ne s'affichent que si le serveur déclare le rôle ou la permission ; ils ne sont décrits ni dans la description, ni dans les captures, ni dans les réponses publiques de la console. Les examinateurs Google reçoivent, dans « Instructions d'accès », un compte membre et, si besoin, un compte de test à rôle (§5) |

### Distribution dans tous les pays : points de vigilance

La décision du client est prise et ne bloque rien ; voici ce qu'il faut savoir avant le dépôt.

- **Conformité locale** : la politique de confidentialité vise le Bénin et le RGPD. Hors Bénin et hors Union européenne, d'autres lois de protection des données, de consommation ou de services d'urgence peuvent s'appliquer. Les numéros d'urgence affichés (117 pour le Bénin) et les contacts de l'annuaire ne valent que pour le Bénin : la fiche et l'application disent déjà que les contacts utiles varient selon la zone et que le service ne remplace pas les secours locaux.
- **Pays où la loi est hostile aux personnes LGBTQ+** : dans certains pays, utiliser ou détenir une telle application peut exposer la personne à des poursuites ou à des violences. Le choix de la discrétion (§2 : nom neutre, aucun drapeau, mode discret) est la principale protection ; il ne la supprime pas. Formulation prudente à garder partout : l'application est un espace d'écoute, de sécurité et de ressources, **ouvert à tout le monde**, sans promesse d'anonymat absolu ni de protection hors du cadre décrit. **Recommandation : faire relire la liste des pays et les risques pour les utilisatrices et utilisateurs par un conseil juridique et par des organisations locales avant la mise en production.** Cette recommandation n'empêche pas la distribution mondiale décidée par le client ; Google Play permet de restreindre ou d'ajouter des pays à tout moment, sans nouvelle version.
- **Contenus et lois locales** : un pays peut exiger le retrait d'un contenu ou d'une application ; prévoir un contact réactif et la possibilité de retirer un pays sans toucher aux autres.
- **Données** : plus de pays signifie plus de personnes concernées par le RGPD (Union européenne) ; la page de suppression de compte et l'export des données (§7) répondent déjà aux droits d'accès et d'effacement.
- **Santé et déclarations** : les réponses de la console (classification, sécurité des données, santé) valent pour tous les pays ; ne pas les nuancer par pays.
- **Support** : l'équipe répond en français et dans les heures de travail du Bénin ; ne promettre aucun délai de réponse à l'étranger.

## 4. Textes de la fiche (français)

### Description courte (80 caractères max)

<!--DEBUT:court-->Un espace sûr pour s'informer, échanger et être soutenu·e, en toute discrétion.<!--FIN:court-->

### Description longue (4 000 caractères max)

<!--DEBUT:long-->
Xomiton est un espace sûr pour s'informer, échanger et être soutenu·e, avec une attention particulière aux filles et aux femmes, et un accueil sans jugement quelle que soit votre identité ou votre orientation.

CE QUE VOUS POUVEZ FAIRE

• Alerter les personnes de confiance : le bouton SOS, à maintenir deux secondes, prévient vos contacts de confiance et le point focal de votre zone. Vous choisissez de partager ou non votre position, et vous pouvez annuler l'alerte pendant quelques secondes.
• Signaler en confiance : un parcours guidé en quatre étapes pour déclarer une situation vécue ou observée, anonymement si vous le souhaitez, puis en suivre l'avancement avec un code.
• Échanger : messages privés avec des personnes formées à l'écoute et groupes de discussion modérés. Une personne formée lit votre message sous 24 h.
• S'orienter : un assistant propose des ressources, des contacts et des démarches. Il ne remplace pas un·e professionnel·le.
• Se renseigner : fiches pratiques, textes de loi, bandes dessinées et vidéos sur les droits, la santé, la sécurité numérique et le bien-être. Plusieurs ressources se lisent hors ligne.
• Faire partie d'une communauté bienveillante : publications, sondages, événements et ateliers, avec des règles de modération claires.

PENSÉE POUR LA DISCRÉTION

• Mode discret : l'icône et le nom de l'application peuvent devenir neutres sur Android.
• Aperçus masqués : les notifications et les messages n'affichent pas leur contenu à l'écran de verrouillage.
• Verrouillage par code à l'ouverture de l'application.
• Inscription simple : un pseudonyme suffit. Aucune date de naissance n'est demandée ; vous confirmez avoir 15 ans ou plus.
• Vos données vous appartiennent : vous pouvez les exporter, ou supprimer votre compte depuis l'application ou depuis notre site, même sans accès à votre compte.

À SAVOIR

Xomiton ne remplace ni les services d'urgence, ni les soins médicaux, ni l'aide juridique. En cas de danger immédiat, contactez les services d'urgence de votre pays. L'application affiche des contacts utiles, qui peuvent varier selon votre zone. Le partage de votre position est facultatif et ne concerne que les personnes alertées.

Une question ? Écrivez-nous : contact@xomiton.bj
<!--FIN:long-->

Rédaction (mise à jour du 29 octobre 2026) : la description présente uniquement l'espace membre ; elle ne parle ni d'espace pair, ni d'administration, ni de rôles, ni de tarif. Voix Xomiton (chaleureuse, claire, rassurante), phrases courtes, écriture inclusive au point médian, aucune promesse de disponibilité permanente (formule de référence « Une personne formée lit votre message sous 24 h »), aucun mot de la liste interdite (meilleur, n° 1, nouveau, gratuit, remise, millions de téléchargements, télécharger, installer, essayer). Le script le contrôle (§10).

### Notes de version (500 caractères max)

« Première version : SOS, signalement guidé, messages, assistant, ressources, communauté, mode discret. »

## 5. Classification du contenu, public cible, âge

| Sujet | Réponse proposée | Remarque |
|---|---|---|
| Questionnaire de classification (IARC) | Catégorie « Réseaux sociaux / communication ». Contenu généré par les utilisateurs : **oui** (modéré, avec signalement et blocage). Partage de position : **oui** (SOS, facultatif). Violence : aucune représentation ; **sujets de violence traités à titre d'information et de soutien**. Sexualité, drogue, jeu d'argent, langage grossier : non. Achats : non | Répondre honnêtement ; la note finale (probablement 12 ans et plus ou Teen) est calculée par le questionnaire, ne pas la présumer |
| Public cible et âge | **16-17 ans et 18 ans et plus uniquement** (décision du client, 29 octobre 2026). Âge minimal produit : **15 ans** (Q68, case « J'ai 15 ans ou plus », aucune date de naissance) | **Ne pas cocher « 13-15 »** : la tranche Play commence à 13 ans et classerait l'application comme visant des mineurs (politique Familles, règles durcies) alors que le produit exclut les moins de 15 ans. Les personnes de 15 ans peuvent s'inscrire selon les conditions d'utilisation, mais la fiche ne les cible pas. Cette position est cohérente avec les pages légales (15 ans minimum). Point à faire valider par le conseil juridique (Q67, Q68) |
| Application destinée aux enfants (programme Familles) | Non | Ne pas rejoindre le programme |
| Contenu sensible | Mentionner violences basées sur le genre, santé sexuelle, soutien psychologique : c'est du contenu **informatif et de soutien** | Voir §9 |
| Déclaration « Applications de santé » | Répondre que l'application ne propose pas de suivi médical, de diagnostic ni de dispositif médical ; elle oriente vers des ressources | Formulaire obligatoire pour toutes les applications |
| Déclaration de sécurité financière, applications gouvernementales | Non concerné | |
| Accès à l'application (identifiants de test) | Fournir un compte de démonstration d'évaluation **membre**, sans donnée réelle. Les espaces pair et admin d'astreinte sont réservés à des comptes à rôle : ne les mentionner que si l'examen l'exige, en expliquant qu'ils ne sont pas accessibles au public | Le prototype en liste un ; créer un compte dédié à Google sur l'environnement de préproduction |

## 6. Formulaire « Sécurité des données » (data safety)

Établi d'après le code : `mobile/pubspec.yaml`, `AndroidManifest.xml`, `platform_services.dart`, `web/app/Models`, `web/config/xomiton.php`, `docs/SECURITE_CHECKLIST.md`. Aucune bibliothèque de publicité, d'analyse d'audience ou de rapport de plantage n'est présente (vérifié : ni Firebase Analytics, ni Crashlytics, ni Sentry).

**Questions générales**

| Question | Réponse |
|---|---|
| L'application collecte-t-elle ou partage-t-elle des données utilisateur ? | Oui |
| Toutes les données sont-elles chiffrées en transit ? | **Oui** (HTTPS uniquement : `usesCleartextTraffic="false"`) |
| Les utilisateurs peuvent-ils demander la suppression de leurs données ? | **Oui** : suppression du compte depuis l'application (Paramètres > Sécurité > « Supprimer mon compte »), effacement différé de 30 jours puis anonymisation ; export des données (lien valable 48 h). **URL web de suppression de compte** : `/supprimer-mon-compte`, créée (§7), à déclarer dans la console |
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

## 7. Politique de confidentialité et suppression de compte (pages du site)

### URL à déclarer dans la Play Console

Les pages sont dans le site public (Blade), **accessibles sans connexion**, indexables et liées dans le pied de page de l'accueil. Remplacer `<domaine>` par le domaine de production (la variable `APP_URL` du site doit être la même).

| Page | Chemin | Route | Où la déclarer |
|---|---|---|---|
| Politique de confidentialité | `https://<domaine>/politique-de-confidentialite` | `legal.privacy` | Fiche > Contenu de l'application > Politique de confidentialité |
| Suppression de compte et de données | `https://<domaine>/supprimer-mon-compte` | `account-deletion.show` | Contenu de l'application > Sécurité des données > « Supprimer le compte » (URL web) |
| Conditions d'utilisation | `https://<domaine>/conditions-utilisation` | `legal.terms` | Lien dans l'application, sur la fiche si souhaité |
| Mentions légales | `https://<domaine>/mentions-legales` | `legal.notice` | Site |
| Cookies et stockage local | `https://<domaine>/cookies` | `legal.cookies` | Site |
| Charte, confidentialité et conditions résumées (page historique) | `https://<domaine>/confidentialite` | `confidentialite` | Liens de l'inscription et de la newsletter ; renvoie vers les pages complètes |

La page de suppression explique la procédure dans l'application (Paramètres > Sécurité > Supprimer mon compte) et sur le web, les délais (accès coupé tout de suite, données effacées sous 30 jours puis anonymisation), ce qui est effacé, ce qui est conservé et pourquoi. Elle propose un **formulaire pour les personnes qui n'ont plus accès à leur compte** : saisie de l'adresse e-mail, envoi d'un lien de confirmation valable 24 h, puis bouton de confirmation qui lance la **même suppression que l'application** (`RequestAccountDeletion`). La réponse est identique que le compte existe ou non (aucune donnée exposée). La demande est tracée par une empreinte de l'adresse, jamais l'adresse (table `account_deletion_requests`, purgée après 90 jours). Un compte de l'équipe d'administration n'est pas supprimable par ce formulaire.

### Contenu de la politique (écrit d'après le code)

La politique couvre : responsable et personne chargée des données ; données collectées (compte, pseudonyme, e-mail, téléphone, profil, données sensibles avec consentement distinct, contacts de confiance, position au moment d'un SOS, signalements, publications et messages, journaux techniques, jeton de notification, newsletter) avec finalité, base légale et caractère facultatif ; destinataires ; prestataires et transferts hors du Bénin (Firebase Cloud Messaging de Google, prestataire SMS Vonage ou Twilio selon la configuration, hébergeur) ; durées de conservation **lues dans la configuration** (compte supprimé 30 jours, journal d'audit 90 jours, journaux d'e-mails 180 jours, codes 24 h, dossiers 5 ans après clôture, appareils inactifs 180 jours) ; droits et manière de les exercer ; sécurité ; âge minimal 15 ans et mineur·es ; cookies ; modifications et contact.

- **Assistant et Gemini** : le service Gemini est branché mais **désactivé par défaut** (`ASSISTANT_LLM_ENABLED=false`). La politique le dit tant qu'il est désactivé ; si l'équipe l'active, la phrase change automatiquement (transmission du texte de la question à Google, hors du Bénin) et l'activation exige l'avis juridique prévu (DECISIONS_A_VALIDER.md).
- **Prestataire SMS** : la phrase suit `SMS_DRIVER` (`vonage`, `twilio`, sinon « tant que ce service n'est pas activé, aucun SMS n'est envoyé »).
- **Position du SOS** : le code la **chiffre** en base (`Alert.lat`, `lng`) mais ne l'**arrondit pas** ; la politique parle donc d'une position relevée au moment de l'alerte et chiffrée, sans promettre d'arrondi. Si le client veut un arrondi, c'est un changement de code.
- **Mentions à valider** : l'Autorité de protection des données personnelles du Bénin (APDP) et le droit béninois sont cités comme autorité et cadre de référence ; à faire confirmer par le conseil.

### Champs à compléter par le client

Tant qu'un champ est vide, la page affiche en surbrillance « [À COMPLÉTER PAR LE CLIENT : …] ». Les renseigner dans le fichier `.env` du serveur (aucune modification de code), puis vider le cache de configuration.

| Variable `.env` | Information attendue | Pages concernées |
|---|---|---|
| `XOMITON_LEGAL_PUBLISHER_NAME` | Nom de l'association ou de l'entité éditrice | Confidentialité, conditions, mentions |
| `XOMITON_LEGAL_FORM` | Forme juridique | Confidentialité, mentions |
| `XOMITON_LEGAL_REGISTRATION` | Numéro d'enregistrement ou de déclaration | Mentions |
| `XOMITON_LEGAL_ADDRESS` | Adresse postale | Confidentialité, mentions |
| `XOMITON_LEGAL_DIRECTOR` | Directeur ou directrice de la publication | Mentions |
| `XOMITON_LEGAL_PHONE` | Téléphone | Mentions |
| `XOMITON_LEGAL_HOST_NAME` | Nom de l'hébergeur | Confidentialité, mentions |
| `XOMITON_LEGAL_HOST_ADDRESS` | Adresse de l'hébergeur | Mentions |
| `XOMITON_LEGAL_HOST_COUNTRY` | Pays où sont hébergées les données | Confidentialité, mentions |
| `XOMITON_LEGAL_DPO_NAME` | Personne chargée de la protection des données | Confidentialité, mentions |
| `XOMITON_LEGAL_DPO_EMAIL` | Adresse e-mail de cette personne | Confidentialité, mentions |
| `XOMITON_LEGAL_JURISDICTION` | Juridiction compétente | Conditions, mentions |
| Réglage « E-mail de contact » (administration) | Adresse publique de contact (par défaut `contact@xomiton.bj`, à confirmer) | Toutes les pages |
| `APP_URL` | Domaine de production | Plan du site, liens |

Dernière mise à jour affichée sur les pages : `xomiton.content.legal_updated_at` (à changer avec chaque modification de texte). Textes : `web/lang/fr/pages/legal.php` et `deletion.php`.

### Inventaire des pages légales (état au 29 octobre 2026)

| Besoin | Avant | Maintenant |
|---|---|---|
| Charte, confidentialité et conditions résumées, signalement | Existait (`/confidentialite`) | Conservée, renvoie vers les pages complètes |
| Politique de confidentialité complète | Absente | **Créée** : `/politique-de-confidentialite` |
| Conditions d'utilisation | Résumé de 5 lignes | **Créées** : `/conditions-utilisation` |
| Mentions légales | Absentes (clé de pied de page prévue, sans page) | **Créées** : `/mentions-legales` |
| Cookies | Absents | **Créée** : `/cookies` ; seuls des cookies indispensables (session, jeton CSRF, appareil de confiance), donc **pas de bandeau** |
| Suppression de compte et de données | Seulement dans l'application et l'API (`DELETE /api/v1/me`) | **Créée** : `/supprimer-mon-compte`, avec formulaire par e-mail |
| Contact de la personne chargée des données | Absent | Champs `XOMITON_LEGAL_DPO_*` dans la politique et les mentions |
| Accessibilité | Aucune déclaration | Section « Accessibilité » des mentions légales |

La politique reste accessible aussi **dans l'application** (écran Sécurité et confidentialité) et **avant** l'inscription (liens déjà présents sur l'inscription et la newsletter, qui mènent à `/confidentialite`, laquelle renvoie vers la politique complète).

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
| Suppression de compte | Lien web de suppression obligatoire | Page publique `/supprimer-mon-compte` créée (§7) ; à déclarer dans la console |
| Métadonnées | Mots promotionnels, classements, répétition, témoignages | Aucun de ces éléments dans les visuels ni les textes (contrôlé par le script) |
| Âge et mineurs | Tranches d'âge incohérentes avec le produit | Tranches 16-17 et 18+ uniquement (décision du client), voir §5 |
| Distribution mondiale | Lois locales, pays hostiles aux personnes LGBTQ+ | Voir §3 (points de vigilance) ; revue juridique recommandée |

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
| `assets/screenshots/04-messages.png` | 1080 × 1920 | idem | Capture 4 | Conversation écrite avec une intervenante formée : messages reçus et envoyés, champ de saisie en bas de l'écran. |
| `assets/screenshots/05-assistant.png` | 1080 × 1920 | idem | Capture 5 | Écran Assistant : message d'accueil, rappel qu'il ne remplace pas un professionnel et suggestions de questions. |
| `assets/screenshots/06-ressources.png` | 1080 × 1920 | idem | Capture 6 | Écran Ressources : recherche et filtres, fiches pratiques, textes de loi, bande dessinée et vidéo, avec la taille de chaque fichier. |
| `assets/screenshots/07-communaute.png` | 1080 × 1920 | idem | Capture 7 | Fil de la communauté : zone pour partager une pensée, filtres par thème et publication d'une intervenante formée sur un atelier. |
| `assets/screenshots/08-confidentialite.png` | 1080 × 1920 | idem | Capture 8 | Écran Sécurité : mode discret, masquage des aperçus, verrouillage par code, appareils connectés, téléchargement et suppression des données. |
| Icône 512 × 512 | 512 × 512 | PNG 32 bits (alpha autorisé pour l'icône) | Icône, **à exporter** | Logo Xomiton : rosace rouge à quatre formes sur fond bleu. |

Titres incrustés (français) : 01 « Un espace sûr, tout près » · 02 « Alerter les personnes de confiance » · 03 « Signaler, même anonymement » · 04 « Des échanges à son rythme » · 05 « Un assistant pour s'orienter » · 06 « Des ressources fiables à portée de main » · 07 « Une communauté bienveillante » · 08 « Vie privée et mode discret ». Chaque capture porte une pastille courte (Confidentiel, Position partagée avec accord, Anonyme si besoin, Écoute sans jugement, Oriente, ne remplace pas, Lisibles hors ligne, Échanges modérés, Icône et nom neutres), dont le texte reste fidèle au produit.

Ordre : les **trois premières captures sont centrées sur l'interface** (accueil, SOS, signalement), comme demandé. Adaptations du prototype faites pour la fiche et assumées : barre d'état figée, notch retiré (cadre générique), illustrations à drapeau masquées (accueil et fil), noms de famille raccourcis.

## 11. Version anglaise (gabarit, non utilisé)

Décision du client (29 octobre 2026) : **français uniquement**. Ce gabarit est conservé pour plus tard ; ne pas le produire maintenant.

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
- [ ] Politique de confidentialité en ligne (`/politique-de-confidentialite`), lien de suppression de compte en ligne (`/supprimer-mon-compte`), champs `XOMITON_LEGAL_*` renseignés, relecture juridique faite
- [ ] Formulaires de la console : contenu de l'application, sécurité des données, classification, public cible, application de santé, permissions (localisation), accès à l'application (compte de test)
- [ ] Fiche : nom, descriptions, icône 512, image de présentation, 8 captures, catégorie, contact
- [ ] Piste de **test interne** (un seul AAB pour les trois espaces ; tester aussi un compte pair et un compte admin) (jusqu'à 100 testeurs, immédiat) : valider installation, SOS, notifications, mode discret, localisation sur des appareils Android 8 à 15
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
| **Domaine de production** et **champs `XOMITON_LEGAL_*`** (§7), puis validation juridique des pages | Les pages existent (`/politique-de-confidentialite`, `/supprimer-mon-compte`) ; elles doivent être publiées et complétées |
| Déclaration des deux URL dans la console | Exigées par Google, en plus de la suppression dans l'application (§7) |
| Preuve de licence des images (archivée hors dépôt) | Licence d'usage commercial des dix visuels du dossier `assets/` confirmée le 28 octobre 2026 : la preuve n'est pas dans le dépôt ; indispensable si une photographie est un jour ajoutée à la fiche ou à l'onboarding. Les captures de la fiche **n'en contiennent pas** |
| Validation du **logo vectoriel** | Reconstitution à partir du seul fichier de 240 × 66 px, à faire valider ou remplacer par le fichier source de la personne qui l'a dessiné |
| Adresse de contact publique, site web | Champs de la fiche |
| Validation juridique de l'âge (Q67, Q68) | Décision prise (16-17 et 18+, âge produit 15 ans) ; confirmer avec le conseil |
| Compte de test pour l'examen et vidéo de démonstration de la localisation | Revue Google |
| Réponses de l'équipe technique aux trois points du §6 | Exactitude de la déclaration |
| Validation du sigle, des textes et du choix de ne montrer ni admin ni espace pair | Discrétion, §2 |
| Clé de signature d'envoi et fichier AAB | Dépôt |
| Revue juridique de la distribution mondiale | Pays hostiles aux personnes LGBTQ+, lois locales (§3) |
| Tarif et pays de distribution | **Décidés** : gratuit, tous les pays (§3) |


## 15. Fiche de publication (Word)

[`Fiche-publication-Play-Store-Xomiton.docx`](Fiche-publication-Play-Store-Xomiton.docx) (dans ce dossier) reprend ce document dans une mise en page remise au client : page de titre, sommaire, tableaux, image de présentation et miniatures des 8 captures avec leur texte alternatif. Elle est **générée** à partir de ce Markdown par `python3 docs/playstore/build_fiche_docx.py` (python-docx) : modifier le Markdown, puis relancer le script ; ne pas corriger le Word à la main.

**Les trois fichiers (ce PDF, la fiche Word et son PDF) sont produits d'un seul geste** par `python3 docs/playstore/build_pdf.py` : environnement UTF-8 forcé, titre passé par un fichier de métadonnées (jamais en argument de commande), police Montserrat embarquée, paragraphes justifiés avec césure française, et **échec si un caractère corrompu (losange U+FFFD ou lettres accentuées lues deux fois en UTF-8 puis Latin-1) est détecté** dans les sources, le DOCX ou les PDF. `--verifier` ne fait que les contrôles.

*Ce dossier ne constitue pas un avis juridique. Les exigences de Google évoluent : relire les pages officielles de la Play Console avant le dépôt (exigences d'API cible, règles de test, classification et sécurité des données).*
