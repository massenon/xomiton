# Dossier : inscription au programme Apple Developer en tant qu'ORGANISATION

> **Pour qui** : la personne qui a **autorité pour engager l'organisation** (direction). Sans ce compte, l'application ne peut pas être publiée sur l'App Store (l'application Android n'est pas concernée).
>
> **Honnêteté** : les règles d'Apple (pays admis, pièces demandées, tarifs, délais, vérification) changent et n'ont pas pu être vérifiées en direct pour ce dossier. Tout ce qui est marqué **« à vérifier sur le site officiel avant d'agir »** doit l'être sur le site de développeur d'Apple avant de payer ou de déposer un document. Ce dossier complète [PLAN_IOS.md](PLAN_IOS.md) §1.3 (lot **IOS-01**) ; le plan a été rédigé sans accès aux outils Apple et est à relire avant publication.
>
> **Décision déjà prise** : compte Apple **organisation** (D-IOS-11, tranché le 2026-11-10 : [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md)). Raison : une plateforme de protection ne doit pas exposer le nom d'une personne comme éditeur.

## 1. Prérequis : ce qu'il faut réunir avant de commencer

| Prérequis | Pourquoi | Prêt ? |
|---|---|---|
| **Entité juridique existante** (association, société, fondation… reconnue légalement) | Apple vérifie que l'organisation existe légalement (pas une simple équipe informelle) | ☐ |
| **Nom légal et adresse exacts**, identiques sur tous les documents | Le **numéro D-U-N-S** (§2) et le compte Apple doivent correspondre **lettre pour lettre** à l'enregistrement officiel | ☐ |
| **Numéro D-U-N-S** de l'organisation | Exigé par Apple pour un compte organisation (§2) | ☐ |
| **Site web public** dont le contenu correspond au nom de l'organisation | Apple vérifie que l'organisation est réelle et identifiable. Le site `xomiton.app` doit donc être **en ligne**, avec ses pages légales **complètes** ([DEMARCHE_DOMAINE_XOMITON_APP.md](DEMARCHE_DOMAINE_XOMITON_APP.md), [FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md)). **À vérifier** : Apple peut aussi demander une adresse e-mail du même domaine que le site | ☐ |
| **Personne ayant autorité pour engager l'organisation** (représentant légal ou personne habilitée) | Apple vérifie que la personne qui s'inscrit a le pouvoir de signer les contrats au nom de l'organisation | ☐ |
| **Adresse e-mail et téléphone de contact de l'organisation** (joignables, partagés si possible) | Apple peut **appeler** pour vérifier (§5) ; ce numéro doit être répondu | ☐ |
| **Apple ID dédié à l'organisation**, avec **double authentification** | Le compte développeur se rattache à un identifiant Apple ; il ne doit pas être personnel (§3) | ☐ |
| **Moyen de paiement de l'organisation** | Pour la cotisation annuelle (§7) | ☐ |
| **Appareil Apple** (iPhone ou autre) pour la double authentification et la validation | Apple demande souvent de valider depuis un appareil Apple (**à vérifier** : exigences actuelles du site d'Apple) | ☐ |
| **Pays de l'entité** admis par le programme | Le programme n'est pas ouvert partout (**à vérifier sur le site officiel avant d'agir** : liste des pays et régions). En cas de doute, voir §6 | ☐ |

## 2. Numéro D-U-N-S

Un **numéro D-U-N-S** est un identifiant d'entreprise à 9 chiffres géré par la société Dun & Bradstreet. Apple l'utilise pour vérifier qu'une organisation existe.

### 2.1 Vérifier si l'organisation en a déjà un

- [ ] Utiliser **l'outil de recherche D-U-N-S proposé par Apple** (sur son site de développeur : chercher « D-U-N-S lookup » ; **à vérifier** : adresse exacte de l'outil sur le site officiel d'Apple).
- [ ] Saisir le **nom légal exact** et l'**adresse** de l'organisation, tels qu'ils figurent sur les documents officiels.
- [ ] Si une fiche est trouvée : **noter le numéro**, vérifier que le nom, l'adresse et le pays sont **corrects**. Une fiche inexacte doit être corrigée **avant** l'inscription (via Dun & Bradstreet, voir 2.2).
- [ ] Si rien n'est trouvé : passer à 2.2.

### 2.2 Demander un numéro D-U-N-S s'il n'existe pas

- [ ] Passer par la **demande gratuite pour Apple** auprès de Dun & Bradstreet (la demande se lance depuis la page Apple dédiée au D-U-N-S ; **à vérifier** : le caractère gratuit de la demande et la page à utiliser, sur le site officiel d'Apple).
- [ ] Préparer : nom légal, forme juridique, adresse, téléphone, nom du représentant, site web, et le **document d'enregistrement officiel**.
- [ ] **Se méfier** : des intermédiaires proposent de « s'en occuper » contre paiement. Pour l'usage Apple, ne payer qu'après avoir lu les conditions sur le site officiel d'Apple.
- [ ] **Délai** : variable ; de quelques jours ouvrés à plusieurs semaines selon le pays (ordre de grandeur repris de [PLAN_IOS.md](PLAN_IOS.md) §1.3, **à vérifier**). C'est le **début du chemin critique** de l'application iOS : à lancer tôt.
- [ ] Après obtention, Apple met **un certain temps** à voir le numéro dans son outil (**à vérifier** : délai de prise en compte) : ne pas lancer l'inscription le jour même sans vérifier.

| Suivi | |
|---|---|
| Date de la recherche dans l'outil Apple | ______________ |
| Numéro D-U-N-S trouvé ou obtenu | ______________ |
| Nom exact enregistré chez D-U-N-S | ______________ |
| Date de la demande à Dun & Bradstreet (si besoin) | ______________ |
| Délai annoncé par la réponse (noter la source) | ______________ |

## 3. Préparer l'identifiant Apple de l'organisation

- [ ] Créer un **Apple ID dédié**, avec une adresse e-mail **de gestion de l'organisation** (boîte partagée), jamais l'adresse personnelle d'un·e membre.
- [ ] Activer la **double authentification** et l'attacher à **un numéro de téléphone de l'organisation** et à un appareil Apple de l'organisation.
- [ ] Noter les **codes ou méthodes de récupération** dans le coffre de l'organisation.
- [ ] **Deux personnes** de confiance savent comment accéder à cet Apple ID (perte d'un téléphone, départ d'une personne).
- [ ] **Ne pas** utiliser un Apple ID déjà employé pour un usage personnel (achats, iCloud).

## 4. Inscription pas à pas

Les étapes exactes sont données par Apple et peuvent changer : suivez celles du site (**à vérifier sur le site officiel avant d'agir**). En substance :

- [ ] **1. Connexion** à la page d'inscription du programme avec l'Apple ID de l'organisation.
- [ ] **2. Type d'entité** : choisir **« Organisation »** (pas « Individu »).
- [ ] **3. Informations de l'organisation** : nom légal **exact**, numéro D-U-N-S, adresse, téléphone, site web.
- [ ] **4. Informations de la personne qui s'inscrit** : nom, fonction, preuve de son pouvoir d'engager l'organisation.
- [ ] **5. Contrats** : lire et accepter le contrat du programme (le texte fait foi ; **en cas de doute, le montrer au conseil**).
- [ ] **6. Paiement** de la cotisation annuelle (§7) avec le moyen de paiement de l'organisation.
- [ ] **7. Attente de la vérification** (§5). Noter la date d'envoi : ______________ .
- [ ] **8. Activation** : réception de l'e-mail de confirmation, puis accès au portail développeur et à App Store Connect.

## 5. Vérification par appel d'Apple

- [ ] Prévoir qu'Apple peut **appeler le numéro de téléphone** renseigné (parfois en anglais) pour confirmer l'identité de la personne et l'existence de l'organisation.
- [ ] La personne qui s'inscrit doit être **joignable** sur ce numéro, avec les documents sous la main (preuve d'enregistrement, pouvoir d'engager l'organisation).
- [ ] Apple peut demander **des pièces complémentaires** (par exemple un document prouvant le lien avec le site web). Répondre vite et avec des documents **cohérents** avec ce qui a été saisi.
- [ ] Un décalage de nom ou d'adresse entre le D-U-N-S, le site et les documents est la **cause classique de blocage** : vérifier la cohérence **avant** d'envoyer.
- [ ] **Délais de vérification** : variables, **à vérifier**. Ne rien promettre à l'équipe avant d'avoir la confirmation d'activation.

## 6. Si l'organisation n'a pas d'entité légale

Sans entité juridique reconnue, un compte **organisation** n'est pas possible. Il existe une alternative, **à peser avec le conseil** ([DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md)) :

| | Compte organisation | Compte **individuel** |
|---|---|---|
| **Nom du vendeur affiché sur l'App Store** | Nom de l'organisation | **Prénom et nom de la personne**, visible publiquement |
| Prérequis | Entité juridique, D-U-N-S, site web, autorité pour engager | Pièce d'identité de la personne (**à vérifier** : pièces demandées) |
| **Transfert** | Le compte survit au départ d'une personne ; rôles multiples | **Un compte individuel ne se transfère pas simplement** : le passer plus tard à une organisation est une opération incertaine, possiblement sans retour (**à vérifier** auprès d'Apple avant de choisir). Il y a un risque de **dépendance à une seule personne** |
| Exposition | L'organisation | **Une personne physique** est publiquement associée à l'application et en porte la responsabilité |
| Adéquation à Xomiton | **Recommandé** (D-IOS-11) | **À éviter** : une plateforme qui protège des personnes à risque ne doit pas exposer le nom d'une personne |

**Pistes à discuter avec le conseil** (rien n'est conseillé ici avant avis) :

- [ ] Créer ou faire reconnaître une entité légale **avant** de s'inscrire (c'est la voie recommandée).
- [ ] Faire porter le compte par une **entité partenaire** existante, avec un accord écrit clair sur la propriété de l'application et les droits d'accès (**à confirmer par le conseil** : risques et formes possibles).
- [ ] Publier d'abord **Android seul**, le temps de régulariser l'entité (la décision D-IOS-10 prévoit déjà Android d'abord, iOS 6 à 8 semaines après).

Décision de la direction : ☐ compte organisation ☐ attendre l'entité ☐ compte individuel (en connaissance des conséquences ci-dessus). Date : ______________ .

## 7. Frais annuels

- **Cotisation annuelle** du programme : environ **99 USD** d'après [PLAN_IOS.md](PLAN_IOS.md) §1.3, **à vérifier sur le site officiel avant d'agir** (montant, devise, taxes, conditions de renouvellement).
- **Renouvellement** obligatoire chaque année : en cas d'oubli, l'application est retirée de l'App Store (**à vérifier**). Mettre le renouvellement dans l'agenda de l'organisation, avec carte à jour.
- **Exonération** : Apple propose une exonération pour certaines organisations à but non lucratif, éducatives ou publiques, sur demande avec justificatifs (**à vérifier** : conditions, justificatifs, délai). À demander **avant** de payer si l'organisation y est éligible.
- Coûts hors cotisation (poste Mac, CI macOS, appareil d'essai) : voir [PLAN_IOS.md](PLAN_IOS.md) §1.1 et §1.2, aussi à vérifier.

| Coût | Montant constaté sur le site officiel | Date de consultation |
|---|---|---|
| Cotisation annuelle | ______ | ______ |
| Exonération possible ? | ☐ oui ☐ non | ______ |

## 8. Rôles dans App Store Connect

Une fois le compte actif, **ne laissez pas tous les droits à une seule personne**. Rôles utiles (noms des rôles **à vérifier** dans App Store Connect) :

| Rôle | À quoi il sert | Qui, pour Xomiton |
|---|---|---|
| **Account Holder** (titulaire du compte) | Un seul par compte : signe les contrats, gère le paiement, peut tout faire. **Ne pas le confier à un prestataire** | La personne ayant autorité pour engager l'organisation : ______________ |
| **Admin** | Gère les utilisateurs et les réglages | Une deuxième personne de confiance de l'organisation : ______________ |
| **App Manager** | Gère la fiche, les versions, TestFlight, les envois en revue | Personne pilotant la publication (équipe technique) : ______________ |
| Autres rôles (développeur, marketing…) | Accès plus restreints, à donner seulement si utile | À définir |

- [ ] **Au moins deux personnes** avec un rôle de gestion (Account Holder + Admin).
- [ ] Chaque personne a **son propre Apple ID** avec double authentification : pas de compte partagé entre humains.
- [ ] Le titulaire du compte est **connu de la direction** et son départ éventuel est prévu (procédure de transfert du rôle : **à vérifier** auprès d'Apple).

## 9. Clés à créer plus tard, et où les ranger

Ces clés sont créées **après** l'ouverture du compte, par l'équipe technique, dans le cadre des lots [PLAN_IOS.md](PLAN_IOS.md) IOS-01 et IOS-04. **Aucune de ces clés ne va dans le dépôt de code.**

| Clé | À quoi elle sert | Où la ranger | Fait |
|---|---|---|---|
| **Clé APNs (fichier `.p8`)** | Permet à Firebase d'envoyer les notifications sur iPhone (alertes, SOS d'équipe). Téléchargeable **une seule fois** à la création : à sauvegarder immédiatement | **Coffre de mots de passe de l'organisation**, avec le *Key ID* et le *Team ID* notés à côté ; une copie dans un second emplacement sûr | ☐ |
| **Clé d'API App Store Connect** (fichier `.p8` + *Key ID* + *Issuer ID*) | Permet à la livraison automatique (CI) d'envoyer les versions vers TestFlight sans mot de passe personnel | Même coffre ; accès limité à l'équipe technique | ☐ |
| Certificats et profils de signature | Signent l'application | Gérés par l'outil de livraison, secrets dans le coffre de la CI | ☐ |

- [ ] **Jamais** par e-mail en clair, jamais dans un document partagé ouvert, jamais dans le dépôt.
- [ ] Une **liste des clés** (nom, usage, date de création, personne responsable, date de renouvellement) tenue dans le coffre.
- [ ] En cas de départ d'une personne ayant accès : **révoquer et recréer** les clés qu'elle connaissait.

## 10. Statut de « professionnel » (règlement européen sur les services numériques, DSA)

Pour diffuser dans l'**Union européenne**, Apple demande à l'éditeur de déclarer s'il est un « **professionnel** » (« trader »). Si oui, d'après [PLAN_IOS.md](PLAN_IOS.md) §1.3, **l'adresse, le téléphone et l'e-mail** de l'éditeur sont **affichés publiquement** sur la page de l'application dans les boutiques européennes. **Les règles exactes (qui est concerné, quelles informations sont montrées, dans quels pays) sont à vérifier sur le site officiel d'Apple avant d'agir.**

Pour une organisation qui protège des personnes à risque, c'est un sujet à décider **avant** de choisir les pays de diffusion :

- [ ] Savoir si l'organisation relève du statut de professionnel (**à confirmer par le conseil**).
- [ ] Si oui : prévoir une **adresse** publique qui n'expose personne (siège officiel, domiciliation), une **ligne de téléphone dédiée** de l'organisation, une **adresse e-mail de contact** partagée.
- [ ] Ou : **ne pas diffuser dans les boutiques de l'UE** au départ (option raisonnable de [PLAN_IOS.md](PLAN_IOS.md) §5.9 : Bénin et pays francophones d'Afrique de l'Ouest d'abord, élargissement ensuite ; D-IOS-12).
- [ ] Question posée au conseil : [DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md) §3, question E.

## 11. Lien avec le plan iOS

| Élément | Où | Dépend de ce dossier |
|---|---|---|
| Lot **IOS-01** : compte organisation, D-U-N-S, vérification, rôles, DSA, clés | [PLAN_IOS.md](PLAN_IOS.md) §8 (lots) | **C'est ce dossier** |
| Lot IOS-04 : `DEVELOPMENT_TEAM`, signature, capacités, TestFlight | [PLAN_IOS.md](PLAN_IOS.md) | Compte actif (IOS-01) |
| Lot IOS-05 : notifications sur iPhone (clé APNs) | [PLAN_IOS.md](PLAN_IOS.md) §4.1 | Clé APNs (§9) |
| Lot IOS-10 : conformité (âge, chiffrement) | [PLAN_IOS.md](PLAN_IOS.md) §5 | **Avis écrit du conseil**, D-IOS-5 et D-IOS-8 |
| Chantier **C22** (iOS) | [PLAN_FINALISATION.md](PLAN_FINALISATION.md) | Compte Apple |
| Critical Alerts (option « Astreinte » de l'admin) | [PLAN_IOS.md](PLAN_IOS.md) §4.1, D-IOS-3 | Demande à Apple à déposer **après** l'ouverture du compte, en parallèle |

Identifiant d'application prévu : `app.xomiton.xomiton` (à saisir à la création de la fiche).

## 12. Checklist finale

- [ ] Entité juridique confirmée ; nom légal et adresse **identiques** partout
- [ ] **Numéro D-U-N-S** trouvé ou obtenu, nom exact vérifié
- [ ] **Site web public** en ligne, pages légales complètes
- [ ] **Apple ID dédié**, double authentification, accès de secours
- [ ] Personne ayant autorité identifiée, **joignable** pour l'appel de vérification
- [ ] **Tarif et exonération** vérifiés sur le site officiel, date notée
- [ ] Inscription en **« Organisation »** envoyée ; date : ______________
- [ ] Vérification passée ; compte **actif** ; date : ______________
- [ ] Rôles distribués (**Account Holder**, **Admin**, **App Manager**) ; au moins deux personnes de gestion
- [ ] Statut de professionnel (DSA) décidé avec le conseil ; pays de diffusion choisis
- [ ] Clés APNs et API App Store Connect créées et rangées dans le **coffre** (par l'équipe technique)
- [ ] Fiche d'application créée avec l'identifiant `app.xomiton.xomiton`
- [ ] Avis écrit du conseil reçu sur l'âge et le chiffrement avant tout envoi en revue

## 13. Documents liés

[DEMARCHES_CLIENT.md](DEMARCHES_CLIENT.md) · [PLAN_IOS.md](PLAN_IOS.md) (§1.3, §4.1, §5, lot IOS-01) · [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md) (D-IOS-1 à D-IOS-12) · [PLAN_FINALISATION.md](PLAN_FINALISATION.md) chantier C22
