# Vos quatre démarches : tableau de bord

> **Décision client du 11/11/2026 : âge minimal d'inscription 18 ans ; tous les nouveaux comptes sont validés par l'administration.** La question d'âge posée au conseil juridique (démarche D) est reformulée dans `DOSSIER_AVIS_JURIDIQUE.md` §B. Le PDF `DEMARCHES_CLIENT.pdf` n'est pas régénéré par cette mise à jour.

> **À qui s'adresse ce document** : à vous, représentant·e de l'organisation qui édite Xomiton. Il n'y a rien de technique à faire ici. Quatre démarches ne peuvent être menées **que par vous** (ou par l'organisation) : l'équipe technique ne peut ni enregistrer un nom de domaine à votre place, ni demander un numéro D-U-N-S, ni obtenir un avis juridique. Tant qu'elles ne sont pas faites, certaines étapes du projet restent bloquées (tableau du §4).
>
> **Règle d'honnêteté** : ce document ne contient ni prix, ni délai, ni texte de loi que nous n'avons pas pu vérifier. Tout ce qui est marqué **« à vérifier sur le site officiel avant d'agir »** doit l'être : les tarifs, les délais et les règles changent.

Date de rédaction : 9 novembre 2026.

## 1. Les quatre démarches en un coup d'œil

| N° | Démarche | Ce que vous obtenez | Guide détaillé à suivre |
|---|---|---|---|
| **A** | Informations légales de l'éditeur | Les 12 informations qui apparaissent sur les pages légales du site, plus l'adresse de contact | [FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md) |
| **B** | Nom de domaine `xomiton.app` | Le nom de domaine, au nom de l'organisation, correctement protégé | [DEMARCHE_DOMAINE_XOMITON_APP.md](DEMARCHE_DOMAINE_XOMITON_APP.md) |
| **C** | Compte Apple Developer « organisation » (numéro D-U-N-S) | Le droit de publier l'application sur l'App Store | [DOSSIER_APPLE_DEVELOPER_ORGANISATION.md](DOSSIER_APPLE_DEVELOPER_ORGANISATION.md) |
| **D** | Avis juridique écrit | Un avis d'un·e avocat·e ou conseil en protection des données, qui permet d'ouvrir au public | [DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md) |

## 2. Ordre conseillé

Les quatre démarches ont chacune un temps d'attente que vous ne maîtrisez pas (un numéro D-U-N-S, un avis juridique). **Le plus important est de les lancer toutes la même semaine**, puis de les faire avancer en parallèle.

| Ordre | Démarche | Pourquoi à cette place |
|---|---|---|
| **1** | **A. Informations légales** (au moins la partie « identité » : nom, forme juridique, enregistrement, adresse) | Elle ne coûte rien et elle alimente les trois autres : le compte du registraire de domaine, la demande de D-U-N-S et le dossier remis au conseil demandent le **nom légal exact** de l'organisation |
| **2** | **D. Avis juridique** (choisir le conseil et lui envoyer le dossier) | C'est le délai le plus incertain et il conditionne l'ouverture au public. Plus il est lancé tôt, moins il retarde le reste |
| **3** | **C. Numéro D-U-N-S puis compte Apple** | Le D-U-N-S est l'étape lente de la chaîne Apple. Il est gratuit à demander (**à vérifier**) : le lancer tôt ne coûte rien |
| **4** | **B. Domaine `xomiton.app`** | L'achat lui-même est rapide. À faire avant le déploiement sur le serveur et avant de déclarer les liens dans Google Play. À faire **dès qu'un moyen de paiement de l'organisation et une adresse e-mail partagée existent** |

> Si vous préférez commencer par le domaine parce que le paiement est prêt, c'est possible : B ne dépend que du nom de l'organisation et d'une adresse e-mail partagée. L'ordre ci-dessus n'est qu'un conseil ; ce qui compte, c'est de ne pas attendre qu'une démarche soit finie pour commencer les autres.

## 3. Tableau de bord

À cocher au fil de l'avancement. La colonne « Durée » ne contient **aucune promesse** : ce sont des ordres de grandeur à confirmer.

| Démarche | Responsable (vous le désignez) | Durée estimée | Dépend de | Statut |
|---|---|---|---|---|
| **A. Informations légales** | ______________ (direction de l'organisation) | Quelques jours de travail pour réunir les pièces ; la validation par le conseil s'ajoute (**à vérifier**) | Rien pour l'identité ; l'hébergeur doit être choisi pour les champs « hébergeur » ; le conseil pour la case « confirmé » | ☐ à faire ☐ en cours ☐ terminé |
| **B. Domaine** | ______________ (personne qui détient le moyen de paiement de l'organisation) | Enregistrement : de quelques minutes à quelques heures ; propagation des réglages : variable (**à vérifier** auprès du registraire) | Nom légal de l'organisation (A), e-mail de gestion partagé, moyen de paiement de l'organisation | ☐ à faire ☐ en cours ☐ terminé |
| **C. Apple (D-U-N-S + compte)** | ______________ (personne ayant autorité pour engager l'organisation) | D-U-N-S : de quelques jours à plusieurs semaines ; vérification Apple : variable (**à vérifier sur le site officiel avant d'agir**) | Entité juridique existante (A), site web public (B), avis du conseil sur l'âge et le chiffrement avant publication (D) | ☐ à faire ☐ en cours ☐ terminé |
| **D. Avis juridique** | ______________ (direction de l'organisation) | Variable : dépend du conseil retenu et de la charge de son agenda (**à demander au conseil dès le premier contact**) | Informations légales (A) pour le dossier ; la liste des questions est déjà prête | ☐ à faire ☐ en cours ☐ terminé |

## 4. Ce qui reste bloqué tant que ce n'est pas fait

| Démarche non faite | Ce qui est bloqué | Où c'est décrit |
|---|---|---|
| **A. Informations légales** | Les quatre pages légales du site (mentions légales, politique de confidentialité, conditions, cookies) affichent des marqueurs « À COMPLÉTER PAR LE CLIENT » : **on ne peut pas ouvrir au public**. La **politique de confidentialité en ligne**, exigée par Google Play, n'est donc pas utilisable : **la fiche Play ne peut pas être déposée** | [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §14 ; [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §7 |
| **B. Domaine** | Pas de serveur en ligne sous le bon nom ; pas de certificat HTTPS (obligatoire pour `.app`, **avant** toute ouverture) ; pas de boîtes `contact@` et `noreply@` donc **pas de code de vérification par e-mail à l'inscription** ; pas d'adresse web à déclarer à Google pour la politique de confidentialité et la suppression de compte ; pas de build de production de l'application mobile (adresse `https://xomiton.app/api/v1`) ; pas de liens universels | [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §20 ; [PLAN_FINALISATION.md](PLAN_FINALISATION.md) chantier C6 |
| **C. Apple** | Aucune signature de l'application iOS, aucune clé de notifications Apple (APNs, donc pas d'alertes sur iPhone), aucun TestFlight, aucune publication sur l'App Store. **L'application Android n'est pas concernée** : elle peut sortir avant | [PLAN_IOS.md](PLAN_IOS.md) lots IOS-01 et IOS-04 ; [PLAN_FINALISATION.md](PLAN_FINALISATION.md) chantier C22 |
| **D. Avis juridique** | **L'ouverture au public** (le plan de finalisation la déclare impossible sans avis) ; la déclaration d'âge dans Google Play (tranche « 18 ans et plus ») et Apple (classification 18+), l'âge minimal de 18 ans étant décidé depuis le 11/11/2026 (D-MOB-18 remplacée, D-IOS-5) ; la réponse sur le chiffrement à Apple (D-IOS-8) ; toute activation de l'assistant Gemini ; la décision sur les pays de diffusion | [PLAN_FINALISATION.md](PLAN_FINALISATION.md) chantier C3 ; [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md) |

## 5. Les règles qui valent pour les quatre démarches

- [ ] **Au nom de l'organisation, jamais d'une personne.** Comptes, factures, titulaire du domaine, compte Apple : tout est au nom de l'organisation. Ce public (filles, femmes, personnes LBTQ+ au Bénin) peut être mis en danger si le nom d'une personne apparaît publiquement.
- [ ] **Adresses e-mail de gestion partagées** (par exemple une boîte de l'organisation lue par au moins deux personnes), pas des adresses personnelles.
- [ ] **Au moins deux personnes** ont accès à chaque compte important (domaine, Apple, Google Play), pour qu'un départ ou une perte de téléphone ne bloque rien.
- [ ] **Les mots de passe et clés ne passent jamais par e-mail ou messagerie en clair.** Gérez-les dans un coffre de mots de passe de l'organisation.
- [ ] **Double authentification** partout, avec une clé de sécurité physique quand c'est possible.
- [ ] **Rien d'inventé.** Si une information vous manque (par exemple le numéro d'enregistrement), n'en mettez pas une approximative : demandez-la, ou notez « non disponible » dans le formulaire.
- [ ] **Chaque tarif et chaque délai cité par un site se note avec la date de consultation** dans le tableau de suivi de la démarche concernée.

## 6. Quand vous avez terminé

Renvoyez à l'équipe technique, **par un canal sûr** (pas par e-mail en clair pour les secrets) :

- [ ] le formulaire des informations légales rempli et confirmé ([FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md)) ;
- [ ] la confirmation que `xomiton.app` est enregistré, avec le nom du registraire et l'accès à la zone DNS (jamais le mot de passe par e-mail) ;
- [ ] la confirmation du numéro D-U-N-S et de l'ouverture du compte Apple Developer, avec le nom de la personne « titulaire du compte » ;
- [ ] l'avis juridique écrit, et la liste des décisions du tableau de [DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md) §6 remplie.

## 7. Documents liés

[RESTE_A_FAIRE.md](RESTE_A_FAIRE.md) · [PLAN_FINALISATION.md](PLAN_FINALISATION.md) (chantiers C2, C3, C4, C5, C6, C22) · [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) · [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md)
