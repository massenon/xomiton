# Dossier à remettre à un·e avocat·e ou conseil en protection des données

> **Deux lecteurs** : (1) **vous**, représentant·e de l'organisation, qui choisissez le conseil et lui remettez ce dossier ; (2) **le conseil**, qui y trouve le contexte, les questions précises et les pièces. Les §1 à §5 s'adressent surtout au conseil ; le §6 sert à transformer l'avis en modifications du produit ; le §7 aide à choisir le conseil.
>
> **Honnêteté** : ce dossier ne contient **aucun texte de loi**, aucun délai légal et aucune conclusion juridique. Les textes de Xomiton ont été **écrits d'après le fonctionnement réel du code** par l'équipe technique, **sans validation juridique**. Pour le Bénin, les seules pistes mentionnées sont celles à confirmer par le conseil : le **Code du numérique du Bénin** et l'**Autorité de protection des données personnelles (APDP)**. Tout point de droit est **à confirmer par le conseil**.
>
> Ce dossier prépare le chantier **C3** du [PLAN_FINALISATION.md](PLAN_FINALISATION.md) (« Avis juridique ») ; sans avis, l'ouverture au public est impossible selon ce plan.

Date de rédaction : 9 novembre 2026. État du dépôt : voir l'historique `git`.

---

## 1. Contexte du projet

### 1.1 Ce qu'est Xomiton

Xomiton est une plateforme numérique **sûre** (site web et **application mobile unique** pour Android, puis iOS) destinée aux **filles, femmes et personnes LBTQ+ au Bénin**. Elle propose : information (ressources, thématiques), communauté et messagerie, assistant de questions-réponses, signalement de violences et de besoins (avec ou sans compte), annuaire de contacts utiles, **bouton SOS** qui prévient des contacts de confiance et le point focal de la zone, et une newsletter. Elle est gratuite, en français, sans publicité, sans mesure d'audience externe, sans rapport de plantage externe. Trois espaces selon le rôle : membre, pair éducateur·rice, administration d'astreinte. Descriptif : [BUSINESS_ANALYSIS_PRODUCT_SPECIFICATION.md](BUSINESS_ANALYSIS_PRODUCT_SPECIFICATION.md), [MODULE_SPECIFICATIONS.md](MODULE_SPECIFICATIONS.md).

### 1.2 Pourquoi ce dossier est sensible

| Facteur | Détail |
|---|---|
| **Public vulnérable** | Personnes exposées à des violences, à la stigmatisation et, selon les pays, à des poursuites. Une fuite de données ou une identification peut mettre en danger des personnes |
| **Âge** | Âge minimal produit : **15 ans** (case à cocher à l'inscription, aucune date de naissance demandée). Décision client D-MOB-18 : tranches Google Play « 16-17 » et « 18+ » uniquement, **à valider par le conseil** |
| **Données sensibles** | **Santé sexuelle** et santé en général (texte libre de signalements et de besoins) ; **violences subies** (récits, notes de suivi) ; **position géographique** au moment d'une alerte SOS ; **orientation sexuelle et identité de genre**, saisies **uniquement si la personne y consent** (consentement distinct, retirable) mais aussi **potentiellement déduites** de l'usage du service lui-même (existence d'un compte, contenus consultés, échanges) |
| **Contacts de tiers** | Noms et numéros de **contacts de confiance** de la personne (données de personnes qui n'ont pas consenti elles-mêmes) |
| **Hébergement mutualisé** | Serveur cPanel d'un hébergeur à choisir ; pays à confirmer |
| **Prestataires hors Bénin** | Google (Firebase Cloud Messaging), Vonage ou Twilio pour les SMS de secours, éventuellement Gemini (désactivé) |
| **Distribution mondiale** | Décision du client : **tous les pays** sur Google Play, y compris des pays où la loi est hostile aux personnes LGBTQ+ |
| **Discrétion par conception** | Nom et icône neutres, aucun drapeau ni mot d'identité sur la fiche, mode discret, aperçus de notification masqués, PIN |

### 1.3 Ce qui est déjà en place (d'après le code)

| Sujet | Constat | Source |
|---|---|---|
| Données minimales | Aucun nom réel ni date de naissance exigé ; pseudonyme + e-mail + mot de passe haché | [web/lang/fr/pages/legal.php](../web/lang/fr/pages/legal.php) (politique, section « Les données que nous collectons ») |
| Chiffrement en base | Récits de signalement, notes, coordonnées et position d'une alerte, corps de messages : **chiffrés dans la base** ; position **non arrondie** | idem ; [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §6-7 |
| Orientation, identité de genre | **Chiffrement au repos non constaté dans le modèle** : à confirmer techniquement **avant** que la politique ne l'affirme | [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §6 |
| Consentements | Consentement distinct pour les données sensibles, les notifications, la newsletter (double confirmation) ; version des consentements : `web/app/Domain/Auth/ConsentVersion.php` | politique de confidentialité |
| Droits | Export des données (Paramètres › Mes données, lien valable 48 h) ; suppression du compte (dans l'application ou `/supprimer-mon-compte`, sans accès au compte) ; retrait des consentements | idem |
| Pas de pistage | Aucune bibliothèque de publicité, d'analyse d'audience ou de rapport de plantage (vérifié par l'équipe technique) | [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §6 |
| Filtre de contenus | **Filtre minimal serveur décidé** (D-IOS-9, révise Q74) ; **chantier non commencé** | [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md) ; [RESTE_A_FAIRE.md](RESTE_A_FAIRE.md) §3.3 |
| Assistant | Base de connaissances interne ; le service Gemini est **branché mais désactivé** (`ASSISTANT_LLM_ENABLED=false`) | `web/config/xomiton.php` (section `assistant`) |
| SMS de secours | Pilotes Vonage et Twilio codés, **non éprouvés avec de vrais canaux** ; défaut : aucun SMS n'est envoyé | [PLAN_FINALISATION.md](PLAN_FINALISATION.md) §3, chantier C7 |

### 1.4 Durées de conservation réellement configurées

Source : `web/config/xomiton.php` (sections `retention`, `accounts`, `reports`, `content_events`) et tableau « Combien de temps nous les conservons » de la politique ([web/lang/fr/pages/legal.php](../web/lang/fr/pages/legal.php)). **Le conseil est prié de dire si chacune est adaptée**, pas seulement conforme.

| Donnée | Durée configurée | Réglage |
|---|---|---|
| Compte supprimé : effacement différé avant anonymisation | **30 jours** | `accounts.deletion_grace_days` |
| Demande publique de suppression : trace (empreinte de l'adresse, jamais l'adresse) | **90 jours** | `accounts.deletion_request_retention_days` |
| Lien d'export de données | **48 heures**, puis fichier effacé | `accounts.export_link_hours` |
| Codes de vérification (OTP) | **24 heures** | `retention.otp_hours` |
| Journal des actions sensibles (adresse IP, navigateur) | **90 jours** | `retention.audit_log_days` (`XOMITON_AUDIT_RETENTION_DAYS`) |
| Journaux d'envoi d'e-mails | **180 jours** ; contenu vidé dès l'envoi, ou après **7 jours** en cas d'échec | `retention.mail_log_days`, `mail_outbox_failed_days` |
| Dossiers de signalement et alertes | Durée du dossier, puis **5 ans** après clôture, puis anonymisation | `retention.sensitive_years_after_case` ; **attention : la commande d'anonymisation des alertes `alerts:anonymize` n'existe pas encore** ([PLAN_FINALISATION.md](PLAN_FINALISATION.md) C12) et l'échéance de conservation des alertes est **à confirmer avec le client** |
| Appareils connectés inactifs | Révoqués après **180 jours** | `retention.device_inactive_days` |
| Accusés de synchronisation hors connexion | **90 jours** | `retention.sync_receipt_days` |
| Fichiers de rapports générés | **30 jours** | `reports.retention_days` |
| Compteurs de consultation des pages (sans donnée personnelle) | **30 jours** | `content_events.retention_days` |
| Origine, orientation, identité de genre | Jusqu'au retrait du consentement | politique |

---

## 2. Comment utiliser ce dossier

1. Le client choisit le conseil (§7) et lui remet ce dossier avec les pièces du §4.
2. Le conseil répond **question par question** (§3), en indiquant pour chacune : décision, niveau de certitude, texte de référence **à citer par lui** s'il y en a un, et conséquence pratique.
3. Le client reporte les décisions dans le tableau du §6, qui indique **quel fichier du dépôt** change. L'équipe technique applique les modifications.
4. Les décisions sont consignées dans [design-system/DECISIONS.md](../design-system/DECISIONS.md) et [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md) (D-MOB-18, D-IOS-5, D-IOS-8, Q7, Q67, Q68).

---

## 3. Questions précises à trancher

Pour chaque question, les références du dépôt sont indiquées pour que le conseil retrouve le texte exact. **Les textes de loi applicables sont à indiquer par le conseil.**

### A. Textes légaux du site

Pages : [web/resources/views/public/legal/page.blade.php](../web/resources/views/public/legal/page.blade.php) (vue commune) alimentée par [web/lang/fr/pages/legal.php](../web/lang/fr/pages/legal.php) et [web/lang/fr/pages/deletion.php](../web/lang/fr/pages/deletion.php) : politique de confidentialité, conditions d'utilisation, mentions légales, cookies, suppression de compte.

- [ ] A1. La **politique de confidentialité** est-elle conforme au droit béninois applicable et, si le conseil le juge pertinent, au droit européen (utilisatrices de l'Union européenne en cas de distribution mondiale) ? Quelles mentions manquent ou sont à corriger ?
- [ ] A2. Les **bases légales** annoncées (exécution du service, consentement, intérêt légitime, intérêt vital, obligation légale) sont-elles correctes pour chaque donnée du tableau de la section « Les données que nous collectons » ? Le **consentement explicite** pour l'orientation et l'identité de genre est-il suffisant ?
- [ ] A3. Les **durées de conservation** du §1.4 sont-elles acceptables ? Faut-il en raccourcir, en allonger, en justifier ? Cas particuliers : **5 ans après clôture** pour les dossiers de signalement ; **90 jours** pour le journal d'audit (adresse IP) ; **180 jours** pour les journaux d'e-mail ; **30 jours** avant anonymisation d'un compte supprimé.
- [ ] A4. Les **conditions d'utilisation** (section « Disponibilité et responsabilité », propriété des contenus, signalement) sont-elles valables ? Quelle clause de **limitation de responsabilité** est opposable ?
- [ ] A5. Les **mentions légales** : quelles informations l'éditeur doit-il obligatoirement publier (voir [FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md)) ? Quelle **juridiction compétente** (`XOMITON_LEGAL_JURISDICTION`) indiquer ?
- [ ] A6. **Cookies** : seuls des cookies indispensables sont déposés (session, jeton CSRF, appareil de confiance) ; la **absence de bandeau** est-elle acceptable ?
- [ ] A7. **Suppression de compte** : la procédure (accès coupé immédiatement, effacement après 30 jours, formulaire par e-mail pour une personne sans accès) répond-elle aux exigences ? La **trace de 90 jours** (empreinte de l'adresse) est-elle justifiée ?
- [ ] A8. Les textes sont rédigés **en français uniquement** : la validité vis-à-vis d'utilisatrices étrangères est-elle affectée ?

### B. Âge minimal de 15 ans et tranches Google Play / Apple

Références : D-MOB-18 (Play : tranches 16-17 et 18+, âge produit 15 ans), D-IOS-5 (Apple : classification honnête, 15 ans maintenu si le conseil l'accepte), Q68. Détail : [PLAN_IOS.md](PLAN_IOS.md) §5.7, [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §5.

- [ ] B1. Le **seuil de 15 ans** pour consentir seul·e au traitement de données (y compris sensibles) est-il valable en droit béninois ? Faut-il un accord parental, et sous quelle forme ?
- [ ] B2. Une **simple case « J'ai 15 ans ou plus »**, sans vérification, est-elle suffisante ? Le client veut éviter de collecter une date de naissance (minimisation).
- [ ] B3. Le **décalage** entre l'âge produit (15 ans) et les tranches déclarées dans Google Play (16-17 et 18+) est-il acceptable ou trompeur ? Faut-il **relever l'âge à 16 ans** ?
- [ ] B4. **Apple** : la classification d'âge calculée (16+ ou 18+ selon les réponses) peut empêcher des personnes de 15 ans d'installer l'application ; le conseil confirme-t-il de **maintenir 15 ans** ou d'**aligner à 16 ans** ?
- [ ] B5. Si un signalement révèle qu'une **personne mineure est en danger**, le texte prévoit une orientation vers les services de protection compétents : la **base et les limites** de cette démarche sont-elles correctes (**secret, obligation de signalement éventuelle**) ?

### C. Hébergement et transferts de données hors du Bénin

Références : politique, section « Prestataires et transferts hors du Bénin » ; [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §6 (« pays d'hébergement du serveur et des fournisseurs SMS et e-mail à confirmer »).

| Prestataire | Rôle | Données | Lieu |
|---|---|---|---|
| Hébergeur (cPanel, **à choisir**) | Site, API, base de données | Toutes | **À confirmer** (`XOMITON_LEGAL_HOST_COUNTRY`) |
| Google (Firebase Cloud Messaging) | Notifications Android et iOS | Jeton d'appareil, contenu minimal | Hors Bénin, hors UE |
| Vonage **ou** Twilio | SMS de secours d'un SOS | Numéro du contact de confiance, texte | Hors Bénin |
| Service d'e-mails | Codes, notifications, newsletter | Adresse e-mail, contenu | **À confirmer** |
| Google (Gemini) | Assistant (**désactivé**) | Texte des questions | Hors Bénin |

- [ ] C1. Les **transferts hors du Bénin** vers ces prestataires sont-ils licites ? Quelles **garanties** exiger (contrat de sous-traitance, clauses types, autorisation ou déclaration préalable à l'autorité) ?
- [ ] C2. L'**hébergement dans un pays donné** (à choisir) est-il à privilégier ou à écarter, compte tenu des **risques d'accès par des autorités** ?
- [ ] C3. Les prestataires sont-ils des **sous-traitants** ? Quel **contrat** signer avec chacun ? Faut-il en informer les utilisatrices autrement que dans la politique ?
- [ ] C4. Le **SMS de secours** transmet à un prestataire le numéro d'un contact de confiance qui **n'a pas consenti personnellement** : base légale, information du contact ?
- [ ] C5. La phrase de la politique sur les garanties de transfert (« engagement contractuel, clauses contractuelles types ou pays reconnu comme offrant une protection suffisante ») est-elle **exacte** ?

### D. Chiffrement et exportation (D-IOS-8)

Référence : [PLAN_IOS.md](PLAN_IOS.md) §5.6. Xomiton utilise HTTPS (chiffrement du système) **et** son propre chiffrement local (AES de Hive, PBKDF2 du PIN, SHA, paquet `crypto`).

- [ ] D1. Quelle réponse donner à **Apple** sur le chiffrement (clé `ITSAppUsesNonExemptEncryption`, option « exempté ») ? Le chiffrement propre de l'application est-il **exempté** au sens de la réglementation américaine d'exportation ?
- [ ] D2. Faut-il une **auto-classification** ou un rapport annuel à une administration américaine ? Qui le dépose ?
- [ ] D3. Apple peut demander une **pièce justificative pour certains pays**, dont la France (à vérifier) : en cas de diffusion dans ces pays, laquelle ?
- [ ] D4. Existe-t-il, au **Bénin** ou ailleurs, des **restrictions d'usage ou d'importation** du chiffrement qui affectent les utilisatrices ou l'organisation ?
- [ ] D5. Réponse analogue à donner dans la **console Google Play**, si une déclaration est demandée.

### E. Distribution mondiale et pays hostiles aux personnes LGBTQ+

Références : [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §3 (« Distribution dans tous les pays : points de vigilance »), [PLAN_IOS.md](PLAN_IOS.md) §5.9 (pays de diffusion), D-IOS-12.

- [ ] E1. **Risques pour les utilisatrices et utilisateurs** dans les pays où l'homosexualité, l'expression de genre ou les associations LGBTQ+ sont réprimées : détenir ou utiliser l'application peut-il constituer une infraction ? Faut-il **exclure certains pays** ?
- [ ] E2. **Risques pour l'organisation** (poursuites, demandes de retrait, blocage, mise en cause de ses membres) selon les pays de distribution et selon la **juridiction de l'éditeur**.
- [ ] E3. **Options de restriction par pays** : Google Play et App Store Connect permettent de choisir les pays (retrait ou ajout sans nouvelle version). Quelle **règle** adopter : liste blanche (Bénin et pays francophones d'Afrique de l'Ouest d'abord) ou liste noire ?
- [ ] E4. **Statut de professionnel (règlement européen sur les services numériques)** : l'éditeur est-il concerné ? Ses adresse, téléphone et e-mail seront-ils **publics** dans les boutiques de l'Union européenne ? Faut-il ne pas diffuser dans l'UE ? ([DOSSIER_APPLE_DEVELOPER_ORGANISATION.md](DOSSIER_APPLE_DEVELOPER_ORGANISATION.md) §10)
- [ ] E5. **Formulation de la fiche** : le choix actuel (« espace d'écoute, de sécurité et de ressources, ouvert à tout le monde », sans mot d'identité, sans promesse d'anonymat absolu) est-il **juridiquement sûr**, et suffisamment honnête vis-à-vis des utilisatrices ?
- [ ] E6. **Support et retrait** : quelle procédure suivre si un pays exige le retrait de l'application ou d'un contenu ?

### F. Responsabilité de l'éditeur et de l'hébergeur pour les contenus des utilisatrices

Références : conditions d'utilisation (section « Contenus » et « Disponibilité et responsabilité »), Apple Guideline 1.2 ([PLAN_IOS.md](PLAN_IOS.md) §5.1), Q74, D-IOS-9, [ROLE_ET_PERMISSIONS.md](ROLE_ET_PERMISSIONS.md).

- [ ] F1. Quel est le **régime de responsabilité** de l'éditeur pour les contenus publiés par les membres (publications, commentaires, messages de groupe) ? Quelle est celle de l'**hébergeur** ?
- [ ] F2. Quelles obligations de **modération** et de **retrait après signalement** s'imposent, avec quels délais ?
- [ ] F3. Le **filtre minimal de contenus** décidé (insultes, incitations, coordonnées) suffit-il ? Un filtre peut-il engager plus de responsabilité qu'une absence de filtre ?
- [ ] F4. **Lecture des messages privés** : la modération ne lit que le **message signalé** (Q74) : est-ce licite, proportionné, et comment l'annoncer aux utilisatrices ?
- [ ] F5. **Signalement et blocage** : quelles garanties de procédure (information de la personne signalée, recours) ?
- [ ] F6. Responsabilité des **pairs éducateur·rices** et de l'équipe pour les conseils donnés ; mention « informations éducatives, pas un conseil juridique » des ressources du domaine du droit ([web/config/xomiton.php](../web/config/xomiton.php), `legal_notice_categories`).

### G. Déclaration à l'autorité, délégué·e à la protection des données, registre

Pistes **à confirmer par le conseil** : Code du numérique du Bénin ; Autorité de protection des données personnelles (APDP).

- [ ] G1. Une **déclaration ou autorisation préalable** auprès de l'autorité est-elle exigée pour les traitements de Xomiton, notamment les **données sensibles** et les **transferts hors du Bénin** ? Quelles pièces, quel calendrier ?
- [ ] G2. La **désignation d'un·e délégué·e à la protection des données (DPO)** est-elle obligatoire ? Qui peut l'être (une personne de l'organisation, un prestataire externe) ? Ses nom et e-mail seront **publics** (`XOMITON_LEGAL_DPO_NAME`, `XOMITON_LEGAL_DPO_EMAIL`) : peut-on indiquer une **fonction** plutôt qu'un nom ?
- [ ] G3. Faut-il tenir un **registre des traitements** et réaliser une **analyse d'impact** ? Un modèle de départ peut être tiré de [DATABASE_DICTIONARY.md](DATABASE_DICTIONARY.md) et de la politique.
- [ ] G4. **Violation de données** : quelles obligations de **notification** (à l'autorité, aux personnes) et quels délais ? La politique promet d'informer « dans les délais prévus par la loi ».
- [ ] G5. Conditions d'exercice des **droits** (accès, rectification, suppression, opposition, portabilité) : le délai d'un mois annoncé est-il compatible ?

### H. Assistant à base de LLM (Gemini désactivé)

Références : politique et clauses `assistant_kb` / `assistant_llm` ([web/lang/fr/pages/legal.php](../web/lang/fr/pages/legal.php)), Q7, [SPEC_NAVIGATION_ET_ASSISTANT_LLM.md](SPEC_NAVIGATION_ET_ASSISTANT_LLM.md), [PLAN_FINALISATION.md](PLAN_FINALISATION.md) C5 (« Gemini : pas de compte tant que l'avis juridique n'est pas rendu »).

- [ ] H1. **À quelles conditions** l'assistant peut-il envoyer le texte de questions à un service externe (Gemini de Google) ? Données personnelles en jeu même si l'identité n'est pas transmise (une question peut décrire une situation de violence ou une orientation) ?
- [ ] H2. **Base légale et information** : un **consentement séparé** et activable est-il requis ? Quelle formulation ?
- [ ] H3. **Contrat** avec le fournisseur : exclusion de l'**entraînement** sur les données, **durée de conservation** côté fournisseur, lieu de traitement ?
- [ ] H4. **Limites de contenu** : l'assistant ne donne ni diagnostic ni avis médical ou juridique ; faut-il des **avertissements** supplémentaires ou une **interdiction de répondre** sur certains sujets ?
- [ ] H5. Si la réponse est « pas avant plus tard » : confirmation que la mention actuelle (« branché mais désactivé ») est exacte.

### I. SOS : promesses et limites de responsabilité

Références : conditions et politique (section « Ce que Xomiton ne remplace pas »), [PLAN_IOS.md](PLAN_IOS.md) §5.3 et annexe F (formulation SOS), [PLAN_FINALISATION.md](PLAN_FINALISATION.md) C7.

- [ ] I1. La formulation actuelle (**aucune garantie** d'intervention ni de délai de réponse ; ne remplace ni services d'urgence ni soins) est-elle **suffisante et opposable** ? Que faut-il ajouter ?
- [ ] I2. **Responsabilité** de l'éditeur si une alerte SOS n'est pas reçue (panne, SMS non délivré, téléphone éteint) ou si un faux positif alerte des contacts ?
- [ ] I3. **Contacts de confiance** : leur information et leur **consentement** ; peuvent-ils refuser, être retirés, être informés de leur désignation ?
- [ ] I4. **Position** : relevée seulement au moment de l'alerte, premier plan, si le partage est actif, et **non arrondie**. Est-ce acceptable ? Faut-il arrondir ou limiter la durée de conservation ?
- [ ] I5. **Numéros d'urgence** affichés (117 pour le Bénin) : leur présentation à des utilisatrices hors Bénin crée-t-elle un risque ? Faut-il une mention par pays ?
- [ ] I6. **Mise en conformité Apple/Google** pour les applications d'urgence ou de santé : la formulation validée sera reprise dans les fiches.

### J. Newsletter et consentements

Références : politique (newsletter, double confirmation), [web/app/Domain/Auth/ConsentVersion.php](../web/app/Domain/Auth/ConsentVersion.php), `web/config/xomiton.php` (section `newsletter`).

- [ ] J1. Le **double consentement** (inscription puis confirmation par e-mail) est-il conforme ? Faut-il conserver la **preuve** (date, version du texte) et combien de temps ?
- [ ] J2. **Désinscription** : un lien dans chaque message suffit-il ?
- [ ] J3. **Version des consentements** : en cas de changement de texte, faut-il **re-recueillir** le consentement ? (la version est modifiée avec `legal_updated_at`)
- [ ] J4. **Consentements distincts** (données sensibles, notifications, newsletter) : leur **séparation actuelle** est-elle suffisante ?

### K. Conservation et demandes d'accès de l'État ou de tiers (réquisitions)

Aujourd'hui, la politique indique seulement : « **Les autorités, uniquement si la loi nous y oblige.** » Aucune procédure interne n'est écrite.

- [ ] K1. Quelle **politique de réponse** adopter face à une **demande d'une autorité** (police, justice, administration) ou d'un tiers : qui reçoit, qui décide, quelles **pièces exiger**, quels recours ?
- [ ] K2. **Peut-on informer** la personne concernée, et sous quelles conditions ? Existe-t-il une interdiction légale d'informer ?
- [ ] K3. **Journalisation** des demandes et des réponses : que consigner (sans créer une nouvelle base de données sensible) ?
- [ ] K4. **Minimisation** : quelles données **ne pas conserver** pour qu'elles ne puissent pas être demandées (adresses IP du journal d'audit, journaux d'e-mail, position) ? Les durées du §1.4 vont-elles dans ce sens ?
- [ ] K5. **Hébergement et prestataires hors Bénin** : une autorité étrangère peut-elle les contraindre ? Quelle information donner aux utilisatrices ?
- [ ] K6. **Effacement à distance** de l'appareil et **suppression de compte** : ces mécanismes peuvent-ils être qualifiés de destruction de preuves en cas de procédure ?
- [ ] K7. Faut-il publier un **rapport de transparence** ou une **procédure** à destination des autorités ?

---

## 4. Documents à joindre

Remettre les fichiers suivants (l'équipe technique peut produire un dossier compressé). **Aucun mot de passe ni secret** : le dépôt n'en contient pas.

| Pièce | Chemin dans le dépôt | Pour répondre à |
|---|---|---|
| Pages légales (vue commune) | `web/resources/views/public/legal/*` (`page.blade.php`) | A |
| Textes des pages légales | `web/lang/fr/pages/legal.php`, `web/lang/fr/pages/deletion.php`, `web/lang/fr/pages/confidentialite.php` | A, B, G, J |
| Remplissage des champs légaux | `web/app/Application/Legal/LegalTexts.php` | A |
| Réglages (rétention, âge, assistant, légal) | `web/config/xomiton.php` | A, B, H |
| Dossier de publication Google Play, §6 « sécurité des données », §7 « politique et suppression » | `docs/playstore/PUBLICATION_PLAY_STORE.md` | A, B, C, E |
| Checklist de sécurité | `docs/SECURITE_CHECKLIST.md` | C, D, G |
| Dictionnaire de la base de données | `docs/DATABASE_DICTIONARY.md` | A, G, K |
| Rôles et permissions | `docs/ROLE_ET_PERMISSIONS.md` | F, G, K |
| Plan iOS, §5 « Conformité Apple » | `docs/PLAN_IOS.md` | B, D, E, F |
| Décisions à valider (Q7, Q67, Q68, Q74, D-MOB-18, D-IOS-5, D-IOS-8, D-IOS-9, D-IOS-12) | `docs/DECISIONS_A_VALIDER.md` | toutes |
| Formulaire d'informations légales rempli | `docs/FORMULAIRE_INFORMATIONS_LEGALES.md` | A, G |
| Spécification de l'assistant | `docs/SPEC_NAVIGATION_ET_ASSISTANT_LLM.md` | H |
| Plan de finalisation (chantiers C2, C3, C7) | `docs/PLAN_FINALISATION.md` | contexte |

> **Limite de ces pièces** : elles décrivent le code **tel qu'il est écrit**. Plusieurs fonctions n'ont **jamais été exécutées avec de vrais canaux** (SMS, push, SOS de bout en bout). Le conseil doit en tenir compte et ne pas supposer qu'une fonction fonctionne comme décrit tant qu'elle n'a pas été testée.

---

## 5. Ce que le client demande au conseil de livrer

- [ ] Un **avis écrit**, question par question (A à K), daté et signé.
- [ ] Une **liste de textes de référence** (lois, règlements) sur lesquels l'avis s'appuie, **citée par le conseil**.
- [ ] Pour chaque point, la **réserve** éventuelle (« à confirmer auprès de l'autorité », « dépend d'un fait à vérifier »).
- [ ] Une **version corrigée des textes légaux** ou la liste de leurs corrections.
- [ ] **Les décisions** à reporter dans le tableau du §6.
- [ ] **Les formalités** à accomplir (déclaration, désignation, contrats) avec leur ordre.

---

## 6. Tableau « question → décision attendue → impact sur le produit »

À remplir à la réception de l'avis. La colonne « Fichier à changer » indique où l'équipe technique agit ; elle n'anticipe pas la réponse.

| Question | Décision attendue du conseil | Impact sur le produit (fichier à changer) | Réponse reçue |
|---|---|---|---|
| A1, A2 : conformité de la politique, bases légales | Textes validés ou corrections | `web/lang/fr/pages/legal.php` (section `privacy`) ; mettre à jour `xomiton.content.legal_updated_at` dans `web/config/xomiton.php` ; version des consentements : `web/app/Domain/Auth/ConsentVersion.php` | ☐ |
| A3 : durées de conservation | Durée acceptable par donnée | `web/config/xomiton.php` (`retention.*`, `accounts.deletion_grace_days`, `accounts.deletion_request_retention_days`, `reports.retention_days`, `content_events.retention_days`) ; commandes de purge ; tableau de la politique | ☐ |
| A3 : alertes (5 ans après clôture) | Échéance de conservation des alertes | Commande `alerts:anonymize` à écrire (C12) ; `retention.sensitive_years_after_case` | ☐ |
| A4 : conditions, responsabilité | Clauses valides | `web/lang/fr/pages/legal.php` (section `terms`) | ☐ |
| A5 : mentions et juridiction | Libellés exacts, juridiction | `.env` du serveur (`XOMITON_LEGAL_*`, voir [FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md)) ; `web/lang/fr/pages/legal.php` (section `notice`) | ☐ |
| A6 : cookies sans bandeau | Accepté ou bandeau requis | `web/lang/fr/pages/legal.php` (section `cookies`) ; ajout éventuel d'un composant de consentement | ☐ |
| A7 : suppression de compte | Procédure et trace acceptées | `web/lang/fr/pages/deletion.php` ; `web/config/xomiton.php` (`accounts`) | ☐ |
| B1 à B4 : âge minimal, tranches Play et Apple | 15 ans maintenu ou 16 ans ; accord parental | `web/config/xomiton.php` (`accounts.min_age`) ; textes (`{min_age}` repris automatiquement) ; web `lang/fr` et formulaire d'inscription ; mobile (case d'âge) ; fiche Play : [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §5 ; classification Apple : [PLAN_IOS.md](PLAN_IOS.md) §5.7 | ☐ |
| B5 : mineure en danger | Limites de l'orientation | `web/lang/fr/pages/legal.php` (section âge) ; procédure interne ([RUNBOOK.md](RUNBOOK.md)) | ☐ |
| C1 à C5 : hébergement et transferts | Garanties, pays d'hébergement, contrats | `XOMITON_LEGAL_HOST_*` ; `web/lang/fr/pages/legal.php` (section prestataires) ; choix de l'hébergeur ([GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §1) ; `SMS_DRIVER` du `.env` | ☐ |
| D1 à D5 : chiffrement et exportation | Réponse à Apple et Google | `mobile/ios/Runner/Info.plist` (`ITSAppUsesNonExemptEncryption`) ; [PLAN_IOS.md](PLAN_IOS.md) §5.6 | ☐ |
| E1 à E3 : pays de distribution | Liste de pays | Réglage dans la **Play Console** et **App Store Connect** (aucun code) ; [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §3 ; [PLAN_IOS.md](PLAN_IOS.md) §5.9 | ☐ |
| E4 : statut de professionnel (UE) | Déclaration à faire ou pas ; UE incluse ou non | App Store Connect ; [DOSSIER_APPLE_DEVELOPER_ORGANISATION.md](DOSSIER_APPLE_DEVELOPER_ORGANISATION.md) §10 | ☐ |
| E5 : formulation de la fiche | Texte validé | [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §4 | ☐ |
| F1 à F3 : responsabilité, modération, filtre | Obligations et délais de retrait | `web/lang/fr/pages/legal.php` (section `terms`) ; **filtre minimal serveur** (D-IOS-9, chantier non commencé) ; procédure de modération écrite ([RUNBOOK.md](RUNBOOK.md)) | ☐ |
| F4, F5 : lecture du message signalé, signalement, blocage | Licéité, procédure | Services de messagerie et de modération du site ; textes de la politique ; Q74 dans [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md) | ☐ |
| G1 à G3 : déclaration, DPO, registre | Formalités à faire, personne à désigner | `XOMITON_LEGAL_DPO_NAME` et `XOMITON_LEGAL_DPO_EMAIL` ; textes (`privacy`, `notice`) ; registre : nouveau document dans `docs/` | ☐ |
| G4 : violation de données | Délais et personnes à informer | [RUNBOOK.md](RUNBOOK.md) (procédure d'incident) ; texte « Sécurité » de la politique | ☐ |
| H1 à H5 : assistant LLM | Conditions d'activation, consentement séparé | `ASSISTANT_LLM_ENABLED` (`.env`, `web/config/xomiton.php` section `assistant`) ; clauses `assistant_kb` / `assistant_llm` de `web/lang/fr/pages/legal.php` ; consentement à créer s'il est exigé ([DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md) Q7) | ☐ |
| I1 à I6 : SOS | Formulation, contacts, position | `web/lang/fr/pages/legal.php` (sections « limites » et SOS) ; arrondi de position (changement de code si demandé : [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §7) ; [PLAN_IOS.md](PLAN_IOS.md) annexe F | ☐ |
| J1 à J4 : newsletter, consentements | Double consentement, preuve, re-consentement | `web/config/xomiton.php` (`newsletter`) ; `web/app/Domain/Auth/ConsentVersion.php` ; textes | ☐ |
| K1 à K7 : réquisitions, conservation | Politique de réponse, minimisation, journalisation | Nouvelle procédure écrite dans [RUNBOOK.md](RUNBOOK.md) ; durées de `retention.*` (`web/config/xomiton.php`) ; ligne « Les autorités… » de `web/lang/fr/pages/legal.php` (section destinataires) | ☐ |

---

## 7. Choisir le conseil

Critères à vérifier avant de choisir (**sans nommer de cabinet** : la direction décide). Demander un devis et un calendrier écrits (**les honoraires et délais sont à demander, aucun n'est annoncé ici**).

| Critère | Pourquoi | À vérifier |
|---|---|---|
| **Expérience en protection des données personnelles** (données de santé, données sensibles, transferts internationaux) | Le cœur du dossier est la donnée sensible d'une population vulnérable | ☐ |
| **Expérience des droits des minorités**, en particulier des **personnes LGBTQ+**, et de la sécurité des militant·es | Le risque principal est l'exposition des personnes, pas la sanction administrative | ☐ |
| **Connaissance du droit béninois** (numérique, données personnelles, associations, presse en ligne) | L'éditeur et le public principal sont au Bénin | ☐ |
| **Connaissance du droit européen** de la protection des données, si une diffusion dans l'UE est retenue | Distribution mondiale ; statut de professionnel dans les boutiques européennes | ☐ |
| Connaissance des **règles des boutiques d'applications** (Google Play, Apple) ou capacité à travailler avec un second avis | Âge, chiffrement, fiches, applications d'urgence et de santé | ☐ |
| **Discrétion et confidentialité** : engagement écrit, secret professionnel, stockage sécurisé du dossier | Le dossier décrit des risques pour des personnes | ☐ |
| **Indépendance** : pas de conflit avec l'hébergeur, un prestataire ou une autorité visée | Avis crédible | ☐ |
| Capacité à **rédiger en français**, en langage compréhensible, et à **chiffrer** délais et honoraires | Exploitabilité par une organisation non juridique | ☐ |
| **Disponibilité** pour des échanges de suivi après l'avis (corrections de textes, formalités) | L'avis se transforme en modifications | ☐ |
| Possibilité de **deux conseils** (un local, un international) si aucun ne couvre tout | Couverture complète | ☐ |

Choix du conseil : ______________ (nom, date, devis reçu : ☐ oui ☐ non, calendrier annoncé : ______________ )

## 8. Documents liés

[DEMARCHES_CLIENT.md](DEMARCHES_CLIENT.md) · [FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md) · [DOSSIER_APPLE_DEVELOPER_ORGANISATION.md](DOSSIER_APPLE_DEVELOPER_ORGANISATION.md) · [PLAN_FINALISATION.md](PLAN_FINALISATION.md) chantier C3 · [DECISIONS_A_VALIDER.md](DECISIONS_A_VALIDER.md)
