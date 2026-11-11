# Xomiton : dossier de publication Google Play

> **Décision client du 11/11/2026 : âge minimal 18 ans ; tous les nouveaux comptes sont validés par l'administration.** L'âge minimal du produit passe de 15 à **18 ans** ; la seule tranche d'âge Play à déclarer est **« 18 ans et plus »** (plus de « 16-17 »). L'incohérence 15 ans / 16-17 / 18+ qui figurait dans ce dossier disparaît. Les textes ci-dessous sont mis à jour en conséquence.

Version du dossier 1.2 · 9 novembre 2026 · application `app.xomiton.xomiton`, version 1.0.0 (code 1) · langue de la fiche : français (France), avec gabarit anglais prévu.

Ce dossier réunit tout ce qu'il faut pour remplir la Play Console : textes de la fiche, visuels, formulaire « Sécurité des données », déclarations, checklist de publication et liste de ce qui reste à fournir. Les visuels sont **produits par script** (`build_playstore.py`) à partir de `visuels.json`, du design system et de captures du site réel (thème clair), puis **vérifiés automatiquement** (§10). Sources de vérité : `design-system/DECISIONS.md` > `design-system/xomiton/MASTER.md` > prototype > `docs/`.

> **Avertissement interne (ne figure pas sur les pages publiques).** Les pages légales du site (politique de confidentialité, conditions d'utilisation, mentions légales, cookies, suppression de compte) et ce dossier **ne remplacent pas un avis juridique**. Elles décrivent le fonctionnement réel du code ; elles doivent être relues et validées par le conseil juridique du client (droit béninois, RGPD pour les personnes concernées, règles Google Play) avant le dépôt.

> Règle qui prime, comme dans la charte : la sécurité de la personne passe avant l'esthétique. Une fiche publique ne doit jamais désigner à un tiers une personne qui l'a dans son historique d'installations (§2).

## 1. Où en est-on

| Élément | État |
|---|---|
| Image de présentation 1024 × 500, 8 captures 1080 × 1920 (français) | **Produits et vérifiés** (`assets/`, copie dans `design-system/assets/playstore/`). **Captures provisoires : à remplacer par de vraies captures de l'application avant soumission à Google, qui exige des captures de l'application elle-même** (voir §10) |
| Textes de la fiche (nom, descriptions) | Rédigés ci-dessous, longueurs contrôlées par le script |
| Formulaire « Sécurité des données » | Rempli d'après le code réel (§6) ; trois points à confirmer par l'équipe technique |
| Icône 512 × 512 | **Produite** (`assets/icon-512.png`) depuis `design-system/logo/icone-appli-bleu.svg` (carré plein, sans coins arrondis : Google applique son masque) ; **à faire valider** par le client |
| Captures tablette 7 et 10 pouces | Non requises ; le kit sait les produire (§12), à faire avec l'application réelle |
| Politique de confidentialité et URL web de suppression de compte | **Créées dans le site** (§7) : `/politique-de-confidentialite` et `/supprimer-mon-compte`, accessibles sans connexion ; domaine `xomiton.app` (choix du client, HTTPS obligatoire) à déployer, champs « À COMPLÉTER PAR LE CLIENT » à fournir |
| Décisions du client du 29 octobre 2026 (âge, tarif, pays, langue, version unique) | **Reportées** dans ce dossier (§3, §5) |
| Fiche de publication au format Word | `Fiche-publication-Play-Store-Xomiton.docx` (§15) |
| Fichier AAB signé, compte développeur | **À fournir** (§14) |

## 2. Choix de discrétion (à lire avant de valider les visuels)

Le public est vulnérable : une fiche Play reste dans l'historique du compte Google et s'affiche à côté de l'icône sur l'écran d'accueil. Choix retenus :

1. **Nom visible neutre** : « Xomiton : espace safe », tiré du slogan officiel du site. Aucun mot stigmatisant, aucun sigle d'identité dans le nom, la description courte ou le premier paragraphe. La description longue accueille « quelle que soit votre identité ou votre orientation », sans désigner de groupe.
2. **Aucun drapeau arc-en-ciel envahissant** : fonds clairs légèrement teintés de bleu (jetons du design system `blue-50` et `blue-100`, Google déconseille le blanc pur et le noir dominant) ; aucune photographie de personnes dans l'image de présentation ni dans les captures (la page d'accueil n'y apparaît que par son haut, sans visage). Les pages d'espace membre, qui affichent des avatars photo, ne sont pas utilisées.
3. **Valeur annoncée = le slogan officiel du site, « Espace safe d'apprentissage et d'appui holistique »**, avec l'accroche « Espace sûr · confidentiel · bienveillant » (page d'accueil du site) ; jamais de formulation du type « application pour… » une catégorie de personnes.
4. **Mode discret** : la fiche écrite dit qu'il existe (icône et nom neutres, aperçus masqués, verrouillage par code) ; elle ne dit pas ce que devient le nom de l'icône. Aucune capture ne le montre tant que les captures de l'application réelle n'existent pas.
5. **Image de présentation sans personne**, **vrai logo en couleur** (`design-system/logo/logo-couleur.svg` : lettres bleues, rosace et symbole rouges) sur fond clair teinté, vrai slogan « Espace safe d'apprentissage et d'appui holistique » et accroche « Espace sûr · confidentiel · bienveillant », avec une page web et un écran mobile réels du site. Aucun thème sombre.
6. **Ni l'admin ni l'espace pair ne sont promus.** L'admin d'astreinte est un outil interne, sans intérêt pour le public et dont la présence dans la fiche expose des fonctions de modération. L'espace pair est réservé à des comptes à rôle : le montrer ferait croire qu'il est accessible à tous. Le pair animateur apparaît quand même, comme **interlocuteur**, dans la capture « Messages » (c'est ce que voit une personne membre). Aucune capture dédiée n'a donc été produite ; à rediscuter seulement si le client veut recruter des pair·es via le Store (mieux vaut le faire hors Store).
7. **Données de démonstration** : le site est lancé en local sur une base temporaire (jeu de données de démonstration), sans identifiant affiché ni donnée réelle ; le numéro fictif de l'annuaire de démonstration est masqué sur la capture des urgences.
8. **Écran d'ouverture (splash) non montré** : il contient une photographie avec drapeau ; la capture d'accueil est préférée.
9. **Tutoiement ou vouvoiement** : les captures montrent l'interface réelle (tutoiement, règle de l'espace connecté) ; les titres ajoutés sont écrits à la forme impersonnelle pour ne mélanger aucun registre ; la description publique vouvoie (site public, règle Q36).

Images du client : les deux contre-exemples de `design-system/assets/splash/references-a-eviter/` sont autorisés **dans l'onboarding** à la demande du client. Le premier (slogans anglais, buste dénudé) n'est utilisable qu'en recadrage : `splash-symbole-venus-recadre.webp` (480 × 392, haut du visage et main, texte et buste retirés). Le second (bouches scotchées) n'a besoin d'aucun recadrage mais **n'est pas utilisé dans la fiche Play** : image anxiogène, hors discrétion et hors des écrans de signalement et de SOS.

## 3. Identité de la fiche

| Champ | Valeur |
|---|---|
| Nom de l'application (30 caractères max) | <!--DEBUT:nom-->Xomiton : espace safe<!--FIN:nom--> |
| Identifiant | `app.xomiton.xomiton` |
| Catégorie | **Réseaux sociaux** (repli : Style de vie). Éviter « Santé et remise en forme » : elle impose une déclaration de santé plus lourde alors que l'application oriente sans soigner |
| Étiquettes (tags) | Communauté, Sécurité, Entraide (3 à 5 selon la console) |
| E-mail de contact (public) | contact@xomiton.app (domaine choisi par le client ; à créer et à confirmer) |
| Site web | `https://xomiton.app/` (domaine choisi par le client ; le suffixe `.app` impose HTTPS) |
| Téléphone | facultatif ; laisser vide |
| Politique de confidentialité (URL) | `https://xomiton.app/politique-de-confidentialite` (route `legal.privacy`) ; **à publier avant le dépôt** (§7) |
| URL web de suppression de compte | `https://xomiton.app/supprimer-mon-compte` (route `account-deletion.show`) ; accessible sans connexion ; **à publier avant le dépôt** (§7) |
| Pays de distribution | **Tous les pays** (décision du client, 29 octobre 2026) ; points de vigilance ci-dessous |
| Tarif | **Pas de tarif : application gratuite** (décision du client). Console : « Gratuite » ; la fiche ne mentionne aucun prix ni le mot « gratuit » |
| Publicités | Aucune |
| Achats intégrés | Aucun |
| Langue de la fiche | **Français uniquement** (décision du client) ; le gabarit anglais du §11 est conservé mais **non utilisé** |
| Versions publiées | **Une seule version Play** : un seul AAB (`app.xomiton.xomiton`) avec des espaces selon le rôle du compte (membre, pair animateur·rice, admin d'astreinte), voir ci-dessous |

### Décisions du client (29 octobre 2026)

| Sujet | Décision | Conséquence dans le dossier |
|---|---|---|
| Âge | Âge minimal du produit **18 ans** (décision du 11/11/2026, remplace 15 ans) ; tranche Play **« 18 ans et plus » uniquement** | §5 |
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

<!--DEBUT:court-->Espace safe d'apprentissage et d'appui holistique, sûr et confidentiel.<!--FIN:court-->

### Description longue (4 000 caractères max)

<!--DEBUT:long-->
Xomiton est un espace safe d'apprentissage et d'appui holistique : un espace sûr, confidentiel et bienveillant pour s'informer, échanger et être soutenu·e, avec une attention particulière aux filles et aux femmes, et un accueil sans jugement quelle que soit votre identité ou votre orientation.

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
• Inscription en trois étapes : prénom, nom, pseudonyme, e-mail, âge (18 ans minimum), pays et profil. Aucune date de naissance n'est demandée ; votre compte est validé par l'équipe avant d'accéder à tout l'espace.
• Vos données vous appartiennent : vous pouvez les exporter, ou supprimer votre compte depuis l'application ou depuis notre site, même sans accès à votre compte.

À SAVOIR

Xomiton ne remplace ni les services d'urgence, ni les soins médicaux, ni l'aide juridique. En cas de danger immédiat, contactez les services d'urgence de votre pays. L'application affiche des contacts utiles, qui peuvent varier selon votre zone. Le partage de votre position est facultatif et ne concerne que les personnes alertées.

Une question ? Écrivez-nous : contact@xomiton.app
<!--FIN:long-->

Rédaction (mise à jour du 29 octobre 2026) : la description présente uniquement l'espace membre ; elle ne parle ni d'espace pair, ni d'administration, ni de rôles, ni de tarif. Voix Xomiton (chaleureuse, claire, rassurante), phrases courtes, écriture inclusive au point médian, aucune promesse de disponibilité permanente (formule de référence « Une personne formée lit votre message sous 24 h »), aucun mot de la liste interdite (meilleur, n° 1, nouveau, gratuit, remise, millions de téléchargements, télécharger, installer, essayer). Le script le contrôle (§10).

### Notes de version (500 caractères max)

« Première version : SOS, signalement guidé, messages, assistant, ressources, communauté, mode discret. »

## 5. Classification du contenu, public cible, âge

| Sujet | Réponse proposée | Remarque |
|---|---|---|
| Questionnaire de classification (IARC) | Catégorie « Réseaux sociaux / communication ». Contenu généré par les utilisateurs : **oui** (modéré, avec signalement et blocage). Partage de position : **oui** (SOS, facultatif). Violence : aucune représentation ; **sujets de violence traités à titre d'information et de soutien**. Sexualité, drogue, jeu d'argent, langage grossier : non. Achats : non | Répondre honnêtement ; la note finale (probablement 12 ans et plus ou Teen) est calculée par le questionnaire, ne pas la présumer |
| Public cible et âge | **18 ans et plus uniquement** (décision du client du 11/11/2026, qui remplace celle du 29 octobre : 16-17 et 18+). Âge minimal produit : **18 ans** (l'inscription demande l'âge, refusé sous 18 ans, aucune date de naissance) | **Ne cocher ni « 13-15 » ni « 16-17 »** : ces tranches classeraient l'application comme visant des mineurs (politique Familles, règles durcies) alors que le produit exclut les moins de 18 ans. La fiche, les conditions d'utilisation et les pages légales disent la même chose (18 ans minimum, `{min_age}` lu dans la configuration). Point à faire valider par le conseil juridique (Q67, Q68) |
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

Les pages sont dans le site public (Blade), **accessibles sans connexion**, indexables et liées dans le pied de page de l'accueil. Le domaine de production est `https://xomiton.app` (choix du client ; le suffixe `.app` impose HTTPS) : la variable `APP_URL` du site doit être la même.

| Page | Chemin | Route | Où la déclarer |
|---|---|---|---|
| Politique de confidentialité | `https://xomiton.app/politique-de-confidentialite` | `legal.privacy` | Fiche > Contenu de l'application > Politique de confidentialité |
| Suppression de compte et de données | `https://xomiton.app/supprimer-mon-compte` | `account-deletion.show` | Contenu de l'application > Sécurité des données > « Supprimer le compte » (URL web) |
| Conditions d'utilisation | `https://xomiton.app/conditions-utilisation` | `legal.terms` | Lien dans l'application, sur la fiche si souhaité |
| Mentions légales | `https://xomiton.app/mentions-legales` | `legal.notice` | Site |
| Cookies et stockage local | `https://xomiton.app/cookies` | `legal.cookies` | Site |
| Charte, confidentialité et conditions résumées (page historique) | `https://xomiton.app/confidentialite` | `confidentialite` | Liens de l'inscription et de la newsletter ; renvoie vers les pages complètes |

La page de suppression explique la procédure dans l'application (Paramètres > Sécurité > Supprimer mon compte) et sur le web, les délais (accès coupé tout de suite, données effacées sous 30 jours puis anonymisation), ce qui est effacé, ce qui est conservé et pourquoi. Elle propose un **formulaire pour les personnes qui n'ont plus accès à leur compte** : saisie de l'adresse e-mail, envoi d'un lien de confirmation valable 24 h, puis bouton de confirmation qui lance la **même suppression que l'application** (`RequestAccountDeletion`). La réponse est identique que le compte existe ou non (aucune donnée exposée). La demande est tracée par une empreinte de l'adresse, jamais l'adresse (table `account_deletion_requests`, purgée après 90 jours). Un compte de l'équipe d'administration n'est pas supprimable par ce formulaire.

### Contenu de la politique (écrit d'après le code)

La politique couvre : responsable et personne chargée des données ; données collectées (compte, pseudonyme, e-mail, téléphone, profil, données sensibles avec consentement distinct, contacts de confiance, position au moment d'un SOS, signalements, publications et messages, journaux techniques, jeton de notification, newsletter) avec finalité, base légale et caractère facultatif ; destinataires ; prestataires et transferts hors du Bénin (Firebase Cloud Messaging de Google, prestataire SMS Vonage ou Twilio selon la configuration, hébergeur) ; durées de conservation **lues dans la configuration** (compte supprimé 30 jours, journal d'audit 90 jours, journaux d'e-mails 180 jours, codes 24 h, dossiers 5 ans après clôture, appareils inactifs 180 jours) ; droits et manière de les exercer ; sécurité ; âge minimal 18 ans (`{min_age}`) ; cookies ; modifications et contact.

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
| Réglage « E-mail de contact » (administration) | Adresse publique de contact (par défaut `contact@xomiton.app`, à confirmer) | Toutes les pages |
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
| Âge et mineurs | Tranches d'âge incohérentes avec le produit | Tranche « 18 ans et plus » seule, alignée sur l'âge minimal de 18 ans (décision du client du 11/11/2026), voir §5 |
| Distribution mondiale | Lois locales, pays hostiles aux personnes LGBTQ+ | Voir §3 (points de vigilance) ; revue juridique recommandée |

## 10. Visuels : spécifications, contrôles et texte alternatif

> **Captures provisoires : à remplacer par de vraies captures de l'application avant soumission à Google, qui exige des captures de l'application elle-même.** Les huit captures ci-dessous montrent le **site web** Xomiton (Laravel, thème clair) à largeur mobile 390 px, et non l'application Flutter, qui n'est pas compilable dans l'environnement de production de ces visuels. Elles servent à valider la composition, les textes et les contrôles ; l'image de présentation montre aussi une page du site. Pour la soumission : lancer l'application sur émulateur Android, capturer les mêmes écrans, les placer à la place des fichiers de `docs/playstore/sources/` (mêmes noms) et relancer le générateur ; la barre d'état propre se règle dans `visuels.json` (`barre_etat.mode` : `recouvrir`).

Générateur : `python3 docs/playstore/build_playstore.py`, qui délègue au **kit** `tools/playstore-kit/` (Playwright et Pillow, Chromium via `PW_CHROMIUM` ou recherche automatique). La configuration est `visuels.json` : sources = images réelles `sources/*-390x844.png` (1170 × 2532, échelle ×3), vrai logo couleur, jetons de `design-system/tokens/tokens.css`, police Montserrat du design system. Les captures sont refaites par `python3 docs/playstore/capturer_site.py` sur le site Laravel lancé en local, **sans toucher au dépôt ni au `.env`** : variables d'environnement `APP_ENV=local`, `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:8000`, base SQLite temporaire hors dépôt (`DB_DATABASE`), `php artisan migrate:fresh --seed`, `php artisan serve`, navigateur en `colorScheme: 'light'` ; aucun bandeau de débogage ni identifiant de démonstration n'apparaît ; le serveur est arrêté ensuite. Options du générateur : `--verifier`, `--seulement 03-sos feature-graphic icone`, `--regles`.

Contrôles automatiques (133 passés à la livraison, fiche et icône comprises) : dimensions exactes, PNG 24 bits (profondeur 8, type RVB, aucun bloc de transparence), poids (< 8 Mo), ratio 9:16, aire du texte ajouté ≤ 20 %, contraste du texte ajouté ≥ 4,5:1 mesuré sur le fond réel au pixel le plus défavorable, couleurs, mots interdits, longueur du texte alternatif, absence de « image de », longueur du nom (30), de la description courte (80) et de la longue (4 000), et pour l'icône : 512 × 512, PNG 32 bits, 1 024 Ko au plus, carré plein sans coin arrondi, motif dans la zone de sécurité. Les règles numériques de Google sont dans `tools/playstore-kit/google_rules.json`, datées du 9 novembre 2026 (relecture humaine, le kit n'interroge jamais Google) : à relire avant dépôt.

**Seuil de couleurs ajusté, et pourquoi.** Le contrôle d'origine exigeait des fonds saturés (saturation > 0,5, valeur ≤ 0,95) ; il contredisait le fond clair de marque voulu par le client. La règle Google est d'éviter le **blanc pur, le noir et le gris foncé** dominants. Le seuil devient : saturation ≥ 0,05 (exclut le blanc et tous les gris, y compris le gris foncé), valeur de 0,25 à 0,99 (exclut le noir et le blanc pur), saturation moyenne de l'image de présentation ≥ 0,12 au lieu de 0,45 ; limites de blanc (< 25 %) et de noir (< 3 %) inchangées. Les fonds employés sont `blue-50` (#EBEFFA) et `blue-100` (#D7DEF5).

Typographie : Montserrat (titres 72 px sur 1080 px de large), auto-hébergée depuis `design-system/fonts/`. Aucun appel à l'action, aucun badge de magasin, aucun logo tiers, cadre générique clair (ni encoche ni marque), aucune pastille.

### Tableau des assets

| Fichier | Dimensions | Format | Rôle | Texte alternatif (≤ 140 caractères) |
|---|---|---|---|---|
| `assets/feature-graphic-1024x500.png` | 1024 × 500 | PNG 24 bits, sans alpha | Image de présentation | Logo Xomiton en couleur, slogan « Espace safe d'apprentissage et d'appui holistique », une page du site et un écran mobile. |
| `assets/screenshots/01-accueil.png` | 1080 × 1920 | PNG 24 bits, sans alpha | Capture 1, interface (provisoire : site web) | Accueil du site Xomiton : titre de l'espace, boutons Créer un compte, Se connecter et Signaler, mot d'une membre. |
| `assets/screenshots/02-signaler.png` | 1080 × 1920 | idem | Capture 2, interface (provisoire) | Page Signaler : signalement sans compte et anonyme, explication de la confidentialité et première étape du formulaire. |
| `assets/screenshots/03-sos.png` | 1080 × 1920 | idem | Capture 3, interface (provisoire) | Page SOS : grand bouton rouge à maintenir deux secondes, choix de partager sa position, consignes d'utilisation. |
| `assets/screenshots/04-ressources.png` | 1080 × 1920 | idem | Capture 4 (provisoire) | Liste des ressources : filtres par type et par thème, fiches et textes avec leur format et leur taille. |
| `assets/screenshots/05-thematiques.png` | 1080 × 1920 | idem | Capture 5 (provisoire) | Page Thématiques : recherche, filtres par sujet et fiches pédagogiques avec leur temps de lecture. |
| `assets/screenshots/06-urgences.png` | 1080 × 1920 | idem | Capture 6 (provisoire) | Page Contacts d'urgence : bandeau SOS rouge et boutons d'appel direct vers les numéros d'urgence. |
| `assets/screenshots/07-actualites.png` | 1080 × 1920 | idem | Capture 7 (provisoire) | Page Actualités : filtres par catégorie et article avec son thème, son résumé, sa date et son temps de lecture. |
| `assets/screenshots/08-aide.png` | 1080 × 1920 | idem | Capture 8 (provisoire) | Centre d'aide : questions fréquentes sur le compte, le signalement et le soutien, avec réponses dépliables. |
| `assets/icon-512.png` | 512 × 512 | PNG 32 bits (alpha autorisé pour l'icône) | Icône, **à faire valider** | Logo Xomiton : rosace à quatre formes blanches sur fond bleu. |

Titres incrustés (français) : 01 « Espace sûr, confidentiel, bienveillant » · 02 « Signaler en toute sécurité » · 03 « Alerte d'urgence SOS » · 04 « Ressources à lire et à partager » · 05 « Thématiques pour comprendre et agir » · 06 « Contacts d'urgence à appeler » · 07 « Actualités et guides » · 08 « Centre d'aide et questions ».

Ordre : les **trois premières captures sont centrées sur l'interface** (accueil, signalement, SOS). **Assistant non montré** : l'assistant est dans l'espace membre, dont le gabarit mobile web montre des avatars photo et n'est pas fait pour cette largeur ; il sera à capturer dans l'application réelle. Adaptations assumées : captures du site tel qu'il est (aucun montage de l'interface), numéro fictif de l'annuaire de démonstration masqué.

## 11. Version anglaise (gabarit, non utilisé)

Décision du client (29 octobre 2026) : **français uniquement**. Ce gabarit est conservé pour plus tard ; ne pas le produire maintenant.

`visuels.json` ne contient aujourd'hui que le français. Pour produire la version anglaise : (1) ajouter à chaque visuel un bloc `textes.en` (`titre`, `alt`, et `slogan` / `accroche` pour l'image de présentation) ; une chaîne vide est refusée, (2) fournir une version anglaise du prototype (les captures montrent aujourd'hui l'interface française ; changer seulement les titres donnerait des captures incohérentes), (3) lancer `python3 docs/playstore/build_playstore.py --langue en` : le script refuse de produire tant qu'un texte est vide et écrit dans `assets/en/`. Traduire le nom, les descriptions et le §4 dans la console (« Gérer les traductions »).

## 12. Captures tablette 7 et 10 pouces (plus tard)

Non requises pour publier. Elles améliorent la mise en avant sur grands écrans (recommandées si l'application est installable sur tablette). Spécifications Google (relues le 9 novembre 2026, voir `tools/playstore-kit/google_rules.json`) : 4 à 8 captures par jeu quand elles sont fournies, PNG ou JPEG 24 bits sans alpha, côtés de 1 080 à 7 680 px, portrait 9:16 ou paysage 16:9. Le kit sait les produire : ajouter à `visuels.json` un bloc `tablettes` (voir `tools/playstore-kit/README.md` et `example/visuels.json`), à faire avec l'application réelle sur émulateur de tablette. Hors périmètre de cette livraison.

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
- [ ] Fiche : nom, descriptions, icône 512, image de présentation, 8 captures **de l'application** (remplacer les captures provisoires du site web), catégorie, contact
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
| **Déploiement sur `xomiton.app`** (HTTPS obligatoire pour `.app`) et **champs `XOMITON_LEGAL_*`** (§7), puis validation juridique des pages | Les pages existent (`/politique-de-confidentialite`, `/supprimer-mon-compte`) ; elles doivent être publiées et complétées |
| Déclaration des deux URL dans la console | Exigées par Google, en plus de la suppression dans l'application (§7) |
| Preuve de licence des images (archivée hors dépôt) | Licence d'usage commercial des dix visuels du dossier `assets/` confirmée le 28 octobre 2026 : la preuve n'est pas dans le dépôt ; indispensable si une photographie est un jour ajoutée à la fiche ou à l'onboarding. Les captures de la fiche **n'en contiennent pas** |
| Validation du **logo vectoriel** | Reconstitution à partir du seul fichier de 240 × 66 px, à faire valider ou remplacer par le fichier source de la personne qui l'a dessiné |
| Création de la boîte `contact@xomiton.app` | Adresse de contact publique de la fiche |
| **Vraies captures de l'application** (émulateur Android, 4 à 8) | Les captures actuelles montrent le site web : provisoires, Google exige des captures de l'application |
| Validation de l'icône 512 (`assets/icon-512.png`) | Produite depuis `icone-appli-bleu.svg`, à faire valider |
| Validation juridique de l'âge (Q67, Q68) | Décision prise le 11/11/2026 (18 ans et plus, âge produit 18 ans : plus de décalage) ; confirmer avec le conseil la valeur d'un âge déclaré sans vérification |
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
