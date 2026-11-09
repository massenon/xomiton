# Formulaire : informations légales de l'éditeur de Xomiton

> **Pour qui** : la direction de l'organisation qui édite Xomiton. Ce formulaire se remplit **à la main ou dans un traitement de texte**, sans compétence technique.

## ⚠ À lire avant de remplir : ces informations deviennent PUBLIQUES

**Tout ce que vous écrivez dans ce formulaire sera affiché sur le site internet**, lisible par n'importe qui, y compris par des personnes hostiles. Il n'existe pas de version privée de ces champs. Pour un projet qui accompagne des filles, des femmes et des personnes LBTQ+ au Bénin, pesez chaque réponse :

- [ ] **Adresse** : préférez l'adresse **officielle de l'organisation** (siège, local, boîte postale ou domiciliation enregistrée) à une adresse de domicile. Ne mettez jamais l'adresse personnelle d'un·e membre.
- [ ] **Téléphone** : préférez une **ligne de l'organisation**, pas un numéro personnel.
- [ ] **Nom de la direction de la publication** et **nom de la personne chargée de la protection des données** : ces noms seront publics. La personne concernée doit **avoir donné son accord écrit** et avoir mesuré le risque.
- [ ] **Ne remplissez rien d'approximatif.** Si une information n'existe pas encore (par exemple le numéro d'enregistrement), laissez la case « non disponible » cochée et dites-le au conseil : n'inventez rien.
- [ ] **Faites relire ce formulaire par le conseil** (avocat·e ou conseil en protection des données) **avant** de le transmettre : les cases « Confirmé par le conseil » existent pour cela. Aucun texte de loi n'est cité ici : ce qui est dû légalement pour chaque champ est **à confirmer par le conseil** ([DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md)).

## Comment ça marche

1. Vous remplissez les 14 fiches ci-dessous (12 variables `XOMITON_LEGAL_*`, plus l'adresse du site et l'e-mail de contact).
2. Le conseil relit et coche « Confirmé par le conseil ».
3. Vous renvoyez le formulaire à l'équipe technique **par un canal sûr**.
4. L'équipe technique recopie les valeurs dans le fichier de configuration du serveur (`.env`), vide le cache, puis vérifie que **plus aucun marqueur « À COMPLÉTER PAR LE CLIENT » n'apparaît** sur les quatre pages ([GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §14).

Les quatre pages concernées : **Mentions légales** (`/mentions-legales`), **Politique de confidentialité** (`/politique-de-confidentialite`), **Conditions d'utilisation** (`/conditions-utilisation`) et **Cookies** (`/cookies`). Tant qu'un champ est vide, la page affiche en surbrillance « [À COMPLÉTER PAR LE CLIENT : …] » à sa place : l'ouverture au public est alors impossible.

> **Exemples de format** : les exemples ci-dessous montrent seulement **la forme** attendue, avec des mots entre chevrons comme `<nom de l'organisation>`. Ce ne sont jamais des valeurs à recopier.

## Tableau récapitulatif

| N° | Variable | Pages où elle apparaît | À remplir par |
|---|---|---|---|
| 1 | `XOMITON_LEGAL_PUBLISHER_NAME` | Mentions, confidentialité, conditions | Direction |
| 2 | `XOMITON_LEGAL_FORM` | Mentions, confidentialité | Direction / conseil |
| 3 | `XOMITON_LEGAL_REGISTRATION` | Mentions | Direction (document officiel) |
| 4 | `XOMITON_LEGAL_ADDRESS` | Mentions, confidentialité | Direction |
| 5 | `XOMITON_LEGAL_DIRECTOR` | Mentions | Direction |
| 6 | `XOMITON_LEGAL_PHONE` | Mentions | Direction |
| 7 | `XOMITON_LEGAL_HOST_NAME` | Mentions, confidentialité | Équipe technique (contrat d'hébergement) |
| 8 | `XOMITON_LEGAL_HOST_ADDRESS` | Mentions | Équipe technique (contrat d'hébergement) |
| 9 | `XOMITON_LEGAL_HOST_COUNTRY` | Mentions, confidentialité (deux fois) | Équipe technique (contrat d'hébergement) |
| 10 | `XOMITON_LEGAL_DPO_NAME` | Mentions, confidentialité | Direction, avec l'accord de la personne |
| 11 | `XOMITON_LEGAL_DPO_EMAIL` | Mentions, confidentialité (deux fois) | Direction / équipe technique (boîte à créer) |
| 12 | `XOMITON_LEGAL_JURISDICTION` | Mentions, conditions | **Conseil** |
| 13 | `APP_URL` | Plan du site, liens | Équipe technique, d'après le domaine ([DEMARCHE_DOMAINE_XOMITON_APP.md](DEMARCHE_DOMAINE_XOMITON_APP.md)) |
| 14 | Réglage « e-mail de contact » (`contact_email`) | Toutes les pages légales | Direction |

---

## Fiche 1 : nom de l'éditeur (`XOMITON_LEGAL_PUBLISHER_NAME`)

| | |
|---|---|
| **À quoi ça sert** | Dit qui est responsable de Xomiton : « <nom> est responsable du traitement de vos données… », « édité par <nom> » |
| **Pages** | Mentions légales ; politique de confidentialité ; conditions d'utilisation (deux fois) |
| **Format attendu** | Le nom légal **exact**, tel qu'il figure sur l'acte d'enregistrement. Exemple de forme : `<nom légal de l'organisation>` |
| **Qui peut le fournir** | La direction, d'après les statuts ou l'acte d'enregistrement |
| **Attention** | Ce nom sera aussi demandé **à l'identique** pour le numéro D-U-N-S et le compte Apple : une faute de frappe ici crée des refus ailleurs. Il sera **public** : peut-il être associé à Xomiton sans exposer le public ? (à discuter avec le conseil) |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 2 : forme juridique (`XOMITON_LEGAL_FORM`)

| | |
|---|---|
| **À quoi ça sert** | Précise la nature de l'éditeur : « (<forme juridique>) » après le nom |
| **Pages** | Mentions légales ; politique de confidentialité |
| **Format attendu** | Un libellé court. Exemple de forme : `<type de structure : association, société, fondation, autre>`. **Écrivez celui qui figure sur vos documents officiels**, pas celui que vous pensez être le plus avantageux |
| **Qui peut le fournir** | La direction, d'après les statuts ; le conseil pour le libellé exact en droit béninois (**à confirmer par le conseil**) |
| **Si l'organisation n'a pas d'existence légale** | Ne pas inventer de forme. **Prévenir le conseil** : cela change l'éditeur, le compte Apple (voir [DOSSIER_APPLE_DEVELOPER_ORGANISATION.md](DOSSIER_APPLE_DEVELOPER_ORGANISATION.md) §6) et les obligations |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 3 : numéro d'enregistrement (`XOMITON_LEGAL_REGISTRATION`)

| | |
|---|---|
| **À quoi ça sert** | Prouve l'existence légale de l'éditeur et permet à une autorité ou une personne de l'identifier |
| **Pages** | Mentions légales |
| **Format attendu** | Le numéro **tel qu'il est écrit** sur le document officiel (récépissé, certificat, extrait d'immatriculation), avec si besoin l'autorité qui l'a délivré. Exemple de forme : `<intitulé du document> n° <numéro>, délivré le <date> par <autorité>` |
| **Qui peut le fournir** | La direction, depuis le document officiel (à scanner et conserver) |
| **Attention** | Ne jamais recopier de mémoire : copier depuis le document. Ce que la loi béninoise impose de publier pour ce type d'éditeur est **à confirmer par le conseil** |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 4 : adresse postale de l'éditeur (`XOMITON_LEGAL_ADDRESS`)

| | |
|---|---|
| **À quoi ça sert** | Permet de contacter ou d'assigner l'éditeur ; indiquée dans la politique de confidentialité comme adresse du responsable |
| **Pages** | Mentions légales ; politique de confidentialité |
| **Format attendu** | Une adresse postale complète sur une ligne. Exemple de forme : `<numéro et rue ou description du lieu>, <quartier>, <ville>, <pays>` (ou une boîte postale officielle) |
| **Qui peut le fournir** | La direction |
| **Attention : public** | **Pas d'adresse de domicile.** Préférer le siège officiel, une boîte postale ou une domiciliation. Si l'adresse du siège expose des personnes, en parler au conseil **avant** de la saisir |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 5 : directeur ou directrice de la publication (`XOMITON_LEGAL_DIRECTOR`)

| | |
|---|---|
| **À quoi ça sert** | Désigne la personne responsable du contenu publié par l'éditeur |
| **Pages** | Mentions légales |
| **Format attendu** | Prénom et nom, éventuellement la fonction. Exemple de forme : `<prénom> <nom>, <fonction>` |
| **Qui peut le fournir** | La direction, avec **l'accord écrit** de la personne désignée |
| **Attention : public** | Le nom sera visible de tous. Qui doit être désigné (représentant légal ou autre personne) est **à confirmer par le conseil** |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ Accord écrit de la personne obtenu  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 6 : téléphone de l'éditeur (`XOMITON_LEGAL_PHONE`)

| | |
|---|---|
| **À quoi ça sert** | Moyen de joindre l'éditeur |
| **Pages** | Mentions légales |
| **Format attendu** | Un numéro avec l'indicatif du pays, sans espaces superflus. Exemple de forme : `+<indicatif pays> <numéro>` |
| **Qui peut le fournir** | La direction |
| **Attention : public** | Utiliser une **ligne de l'organisation**, jamais un numéro personnel. Se demander qui répond, à quelles heures, et si la personne est prête à recevoir des appels hostiles |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 7 : nom de l'hébergeur (`XOMITON_LEGAL_HOST_NAME`)

| | |
|---|---|
| **À quoi ça sert** | Nomme l'entreprise qui héberge le site, l'API et la base de données. Elle figure aussi dans le tableau des prestataires de la politique de confidentialité |
| **Pages** | Mentions légales ; politique de confidentialité |
| **Format attendu** | Raison sociale de l'hébergeur. Exemple de forme : `<raison sociale de l'hébergeur>` |
| **Qui peut le fournir** | L'équipe technique ou la direction, **d'après le contrat ou la facture** de l'hébergement cPanel. À remplir **seulement quand l'hébergeur est choisi** ([GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §1) |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 8 : adresse de l'hébergeur (`XOMITON_LEGAL_HOST_ADDRESS`)

| | |
|---|---|
| **À quoi ça sert** | Permet de joindre l'hébergeur (par exemple pour un contenu à retirer) |
| **Pages** | Mentions légales |
| **Format attendu** | Adresse postale du siège de l'hébergeur. Exemple de forme : `<rue>, <code postal> <ville>, <pays>` |
| **Qui peut le fournir** | L'équipe technique, depuis les conditions ou les mentions légales du site de l'hébergeur |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 9 : pays d'hébergement des données (`XOMITON_LEGAL_HOST_COUNTRY`)

| | |
|---|---|
| **À quoi ça sert** | Dit **dans quel pays sont stockées les données**. Apparaît deux fois dans la politique de confidentialité (tableau des prestataires : hébergement et service d'e-mails) |
| **Pages** | Mentions légales ; politique de confidentialité |
| **Format attendu** | Un nom de pays. Exemple de forme : `<pays>` |
| **Qui peut le fournir** | L'équipe technique : **le pays du centre de données**, pas forcément celui du siège de l'hébergeur. À demander par écrit à l'hébergeur |
| **Attention** | Cette réponse conditionne les questions de transfert de données du dossier juridique ([DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md) §3, question C). Le point figure déjà comme « à confirmer par l'équipe technique » dans [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §6 |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 10 : personne chargée de la protection des données, nom (`XOMITON_LEGAL_DPO_NAME`)

| | |
|---|---|
| **À quoi ça sert** | Désigne la personne à qui les utilisatrices peuvent s'adresser pour exercer leurs droits (accès, suppression…) |
| **Pages** | Mentions légales ; politique de confidentialité |
| **Format attendu** | Un nom de personne ou une fonction. Exemple de forme : `<prénom nom>` ou `<fonction : délégué·e à la protection des données>` |
| **Qui peut le fournir** | La direction. **Si l'organisation n'a personne, ne mettez pas un nom par défaut** : le conseil vous dira si une désignation est obligatoire ou utile (**à confirmer par le conseil** ; pistes à confirmer : Code du numérique du Bénin et Autorité de protection des données personnelles, APDP) |
| **Attention : public** | Mettre une **fonction** plutôt qu'un nom personnel est possible : à valider avec le conseil |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ Accord écrit de la personne obtenu  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 11 : e-mail de la protection des données (`XOMITON_LEGAL_DPO_EMAIL`)

| | |
|---|---|
| **À quoi ça sert** | Adresse où écrire pour toute question sur les données ; la politique promet une réponse dans un délai d'un mois au plus |
| **Pages** | Mentions légales ; politique de confidentialité (deux fois) |
| **Format attendu** | Une adresse e-mail **qui existe et qui est lue**. Exemple de forme : `<boîte>@<domaine de l'organisation>` |
| **Qui peut le fournir** | La direction ; l'équipe technique crée la boîte dans cPanel si elle est en `@xomiton.app` ([DEMARCHE_DOMAINE_XOMITON_APP.md](DEMARCHE_DOMAINE_XOMITON_APP.md) §7) |
| **Attention** | Une boîte **partagée** (pas personnelle), lue par au moins deux personnes, avec la **promesse de réponse** à tenir. Une adresse qui n'existe pas encore ne doit pas être publiée |

**Votre réponse** : ____________________________________________

☐ Information indisponible pour l'instant  ☐ Boîte créée et testée  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 12 : juridiction compétente (`XOMITON_LEGAL_JURISDICTION`)

| | |
|---|---|
| **À quoi ça sert** | Indique quels tribunaux sont compétents en cas de litige. Cette mention a des **conséquences juridiques** |
| **Pages** | Mentions légales ; conditions d'utilisation |
| **Format attendu** | Un libellé court. Exemple de forme : `<tribunal ou ville de rattachement>, <pays>` |
| **Qui peut le fournir** | **Uniquement le conseil.** Ne pas remplir sans son avis |
| **Attention** | Le choix (Bénin, autre pays, utilisatrices étrangères, droit applicable) fait partie des questions posées dans [DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md) (§3, questions A et E). Toute mention est **à confirmer par le conseil** |

**Votre réponse** : ____________________________________________

☐ En attente du conseil  ☐ **Confirmé par le conseil** (nom, date) : ______________

## Fiche 13 : adresse du site (`APP_URL`)

| | |
|---|---|
| **À quoi ça sert** | Adresse de base de tous les liens du site : plan du site, liens canoniques, e-mails. Doit être **identique** à celle déclarée dans Google Play |
| **Pages** | Plan du site, liens ; adresses à déclarer dans la Play Console |
| **Format attendu** | `https://` suivi du nom de domaine, **sans** barre oblique finale. Exemple de forme : `https://<nom de domaine>` (valeur retenue dans le dépôt : `https://xomiton.app`, **sous réserve** de l'enregistrement effectif) |
| **Qui peut le fournir** | L'équipe technique, une fois le domaine enregistré ([DEMARCHE_DOMAINE_XOMITON_APP.md](DEMARCHE_DOMAINE_XOMITON_APP.md)) |

**Votre réponse** : ____________________________________________

☐ Domaine pas encore enregistré  ☐ **Confirmé par le conseil** (si changement de nom) : ______________

## Fiche 14 : e-mail de contact public (réglage `contact_email`)

| | |
|---|---|
| **À quoi ça sert** | Adresse de contact affichée sur toutes les pages légales (questions, signalement d'un contenu, exercice d'un droit) et dans la fiche Google Play |
| **Pages** | Mentions légales, politique de confidentialité, conditions, cookies |
| **Format attendu** | Une adresse e-mail qui existe. Exemple de forme : `<boîte>@<domaine>` (valeur d'origine dans le dépôt : `contact@xomiton.app`, **à confirmer**) |
| **Où ça se règle** | Pas dans le `.env` : dans l'**Administration › Paramètres › e-mail de contact**, par l'équipe technique |
| **Qui peut le fournir** | La direction ; l'équipe technique crée la boîte dans cPanel |
| **Attention** | Boîte **partagée**, lue chaque jour ; elle recevra aussi des messages de personnes en difficulté : prévoir qui répond et comment ([RUNBOOK.md](RUNBOOK.md)) |

**Votre réponse** : ____________________________________________

☐ Boîte créée et testée  ☐ **Confirmé par le conseil** (nom, date) : ______________

---

## Bloc `.env` prêt à coller

À remplir **uniquement avec des valeurs confirmées**. Règle de format du fichier : une valeur contenant des espaces, une virgule ou un `#` se met **entre guillemets doubles** ([web/.env.production.example](../web/.env.production.example)). Ne laissez aucun champ avec une valeur approximative : un champ vide affiche le marqueur « À COMPLÉTER », ce qui est volontaire et préférable à une information fausse.

```dotenv
XOMITON_LEGAL_PUBLISHER_NAME=""
XOMITON_LEGAL_FORM=""
XOMITON_LEGAL_REGISTRATION=""
XOMITON_LEGAL_ADDRESS=""
XOMITON_LEGAL_DIRECTOR=""
XOMITON_LEGAL_PHONE=""
XOMITON_LEGAL_HOST_NAME=""
XOMITON_LEGAL_HOST_ADDRESS=""
XOMITON_LEGAL_HOST_COUNTRY=""
XOMITON_LEGAL_DPO_NAME=""
XOMITON_LEGAL_DPO_EMAIL=""
XOMITON_LEGAL_JURISDICTION=""
```

Compléments (hors de ces 12 lignes) :

```dotenv
APP_URL=
```

et, dans l'administration (pas dans le `.env`) : Paramètres › **e-mail de contact** = ______________ .

## Après la saisie (équipe technique)

- [ ] Valeurs recopiées dans `web/.env` du serveur ; cache de configuration régénéré (`php artisan config:cache`).
- [ ] Les quatre pages ouvertes en ligne ; **aucune occurrence** du texte « À COMPLÉTER » ([GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §14).
- [ ] La date « Dernière mise à jour » des pages mise à jour si un texte a changé (`xomiton.content.legal_updated_at`, `web/config/xomiton.php`).
- [ ] Relecture juridique des textes par le conseil ([DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md) §3, question A).
- [ ] Adresses de la politique de confidentialité et de la suppression de compte déclarées dans la Play Console ([playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §7).

## Documents liés

[DEMARCHES_CLIENT.md](DEMARCHES_CLIENT.md) · [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §14 · [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §7 · [PLAN_FINALISATION.md](PLAN_FINALISATION.md) chantier C2
