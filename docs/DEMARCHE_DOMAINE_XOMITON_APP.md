# Démarche : enregistrer le nom de domaine `xomiton.app`

> **Pour qui** : la personne de l'organisation qui détient le moyen de paiement et la responsabilité du compte. Aucune compétence technique n'est demandée pour l'enregistrement. Les réglages DNS (§7) peuvent être faits par vous ou transmis à l'équipe technique, qui vous guidera.
>
> **Honnêteté** : les prix, délais et fonctions de chaque registraire changent. Tout ce qui est marqué **« à vérifier sur le site officiel avant d'agir »** doit l'être. Ce guide ne recommande aucune marque : les noms cités sont des **exemples** de ce qui existe, pas un choix.
>
> **Référence technique** : [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §20 (domaine) et §12 (HTTPS, DNS).

## 1. Ce qu'il faut savoir avant de commencer

| Point | Explication simple |
|---|---|
| Un nom de domaine se **loue**, il ne s'achète pas définitivement | Il faut le renouveler. Un oubli de renouvellement peut le faire perdre, et quelqu'un d'autre pourrait le racheter : d'où le renouvellement automatique (§4) |
| Le domaine appartient à celui ou celle dont le nom figure comme **titulaire** | Ce doit être **l'organisation**, jamais une personne. Sinon, l'organisation dépend d'un individu |
| Le compte chez le registraire est la « clé » du domaine | Qui contrôle le compte contrôle le site, les e-mails et le nom. Il faut le protéger comme un coffre |
| Le suffixe `.app` impose HTTPS | Voir §6 : il faut un certificat **avant** d'ouvrir le site |

## 2. Choisir un registraire (hors du Bénin)

Le **registraire** est l'entreprise qui vous loue le domaine. Il doit être **hors du Bénin**, pour que le domaine ne dépende pas d'un registre ou d'une autorité locale qui pourrait exiger l'identification du titulaire (voir aussi §9 pour le `.bj`).

### Critères de choix

| Critère | À vérifier | Fait |
|---|---|---|
| Établi hors du Bénin, avec des conditions d'utilisation lisibles | Page « Conditions » du registraire | ☐ |
| Prend en charge l'extension **`.app`** | Barre de recherche du site | ☐ |
| **Masquage de l'identité du titulaire** (WHOIS) possible, de préférence gratuit et activé par défaut | Page tarifs et options ; « à vérifier sur le site officiel avant d'agir » | ☐ |
| **Double authentification** avec **clé de sécurité** (FIDO2/WebAuthn) si possible, sinon application d'authentification | Paramètres de sécurité du compte | ☐ |
| **Verrouillage du transfert** (« registrar lock ») | Page du domaine | ☐ |
| **DNSSEC** pris en charge | Page DNS | ☐ |
| **Gestion DNS** complète et simple (A, AAAA, CNAME, MX, TXT) | Page DNS | ☐ |
| Renouvellement automatique et enregistrement **de plusieurs années** | Page de commande | ☐ |
| Paiement par **un moyen de l'organisation** accepté | Page de paiement | ☐ |
| **Support** joignable et délai de réponse annoncé | Page support | ☐ |
| Politique claire sur les **demandes de tiers ou d'autorités** (plainte, suspension) | Conditions d'utilisation ; en cas de doute, la question est à poser au conseil ([DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md)) | ☐ |
| Prix du **premier achat ET du renouvellement** (ils diffèrent souvent) | **Comparer les prix** : noter la date de consultation | ☐ |

Exemples seulement (non exhaustifs, aucune recommandation) : Namecheap, Gandi, Porkbun, Cloudflare Registrar, OVHcloud, Google/Squarespace Domains. **Comparez les prix et les conditions de plusieurs registraires** avant de choisir, et vérifiez qu'ils proposent bien `.app`.

| Registraire comparé | Prix 1re année | Prix renouvellement | WHOIS masqué inclus ? | Clé de sécurité ? | Date de consultation |
|---|---|---|---|---|---|
| ______________ | ______ | ______ | ☐ oui ☐ non | ☐ oui ☐ non | ______ |
| ______________ | ______ | ______ | ☐ oui ☐ non | ☐ oui ☐ non | ______ |
| ______________ | ______ | ______ | ☐ oui ☐ non | ☐ oui ☐ non | ______ |

## 3. Vérifier la disponibilité de `xomiton.app`

- [ ] Chercher `xomiton.app` dans la barre de recherche du registraire retenu.
- [ ] **Disponible** : passer au §4.
- [ ] **Non disponible** : ne rien acheter à la hâte, passer au §10 (variantes).
- [ ] Noter la date et l'heure de la vérification : ______________ .

## 4. Ouvrir le compte et acheter

### 4.1 Ouvrir le compte au nom de l'organisation

- [ ] **Nom du compte / de l'entreprise** : le nom légal exact de l'organisation (voir [FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md), champ `PUBLISHER_NAME`), pas votre nom.
- [ ] **Adresse e-mail du compte** : une **adresse de gestion partagée**, lue par au moins deux personnes (exemple de forme : `domaine@` suivi d'un nom de domaine que vous maîtrisez déjà, ou une boîte partagée d'un fournisseur de messagerie). **Pas une adresse personnelle.**
  - Attention : ne **pas** utiliser une adresse en `@xomiton.app` pour ce compte, car elle dépendrait du domaine lui-même (si le domaine tombe, vous ne pouvez plus recevoir les e-mails de récupération).
- [ ] **Mot de passe** unique et long, créé dans le coffre de mots de passe de l'organisation.
- [ ] **Double authentification** activée **immédiatement**, avec une **clé de sécurité** (deux clés : une principale, une de secours rangée ailleurs) si le registraire le permet ; sinon application d'authentification. **Éviter le SMS** quand une autre méthode existe.
- [ ] **Codes de secours** de la double authentification imprimés ou enregistrés dans le coffre, et **pas** sur le téléphone seul.
- [ ] Un **second accès** (deuxième personne de confiance) créé si le registraire le permet, ou accès partagé documenté.

### 4.2 Réglages à la commande

| Réglage | Valeur attendue | Fait |
|---|---|---|
| **Durée** | **2 ans au minimum** (plus si le prix le permet ; **à vérifier** : durée maximale proposée) | ☐ |
| **Renouvellement automatique** | **Activé**, avec une carte de l'organisation dont la date d'expiration est suivie | ☐ |
| **Masquage de l'identité du titulaire** (WHOIS privacy / redaction) | **Activé**. Ce site protège des personnes exposées à des risques : le nom, l'adresse, l'e-mail et le téléphone du titulaire ne doivent pas être publics | ☐ |
| **Coordonnées du titulaire** | Celles de l'organisation, **pas** d'une personne ; l'e-mail est l'adresse partagée du §4.1 | ☐ |
| **Verrouillage du transfert** (registrar lock) | **Activé** (empêche un transfert non voulu vers un autre registraire) | ☐ |
| **DNSSEC** | **Activé** (voir §5) | ☐ |
| **Paiement** | Par un **moyen de paiement de l'organisation** (carte ou compte de l'organisation) ; **jamais** une carte personnelle | ☐ |
| **Options payantes inutiles** (hébergement, site, e-mail du registraire) | Refusées : l'hébergement est un compte cPanel séparé ([GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §1) | ☐ |

- [ ] **Facture** téléchargée et archivée au nom de l'organisation.
- [ ] **Date d'expiration** du domaine notée dans l'agenda de l'organisation, avec un rappel **60 jours avant** : ______________ .

## 5. Sécurité du domaine, en clair

| Protection | À quoi elle sert | Comment vérifier |
|---|---|---|
| **Identité masquée** (WHOIS) | Les annuaires publics du domaine ne montrent ni votre nom ni votre adresse | Chercher `xomiton.app` dans un outil « whois » public (par exemple celui du registre ou d'un site de recherche WHOIS) : les champs du titulaire doivent apparaître masqués ou « redacted » |
| **Verrouillage du transfert** | Personne ne peut déplacer le domaine sans déverrouiller d'abord | L'état « locked » ou équivalent s'affiche sur la page du domaine ; dans le résultat WHOIS, un statut de type `clientTransferProhibited` est attendu (**à vérifier** : le libellé exact dépend du registraire) |
| **DNSSEC** | Signe les réponses DNS pour empêcher qu'on vous redirige vers un faux site | Interrupteur « DNSSEC » du registraire ; si la zone DNS est chez un autre fournisseur (par exemple l'hébergeur), il faut y copier un enregistrement « DS » fourni par le fournisseur DNS dans l'interface du registraire : **ne l'activer qu'avec l'équipe technique** si ce n'est pas un simple interrupteur. Test : `dig +dnssec xomiton.app` ou un vérificateur DNSSEC en ligne |
| **Renouvellement automatique + 2 ans** | Évite la perte du nom par oubli | Page du domaine : renouvellement « activé », date d'expiration à plus de 2 ans |
| **Double authentification + clé de sécurité** | Empêche le vol du compte, donc du domaine | Tentative de connexion depuis un navigateur privé : le deuxième facteur est demandé |

## 6. Pourquoi il faut un certificat HTTPS avant d'ouvrir le site

Le suffixe **`.app`** figure dans la liste « **HSTS preload** » des navigateurs (point repris de [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §20.1). Concrètement :

- les navigateurs **refusent toute connexion non sécurisée** (HTTP) à un site `.app`, même pour un simple test, et sans possibilité de « continuer quand même » ;
- tant que le **certificat HTTPS** n'est pas **émis et actif**, le site est **inaccessible**, ce n'est pas un affichage dégradé ;
- l'ordre est donc : **1.** enregistrer le domaine, **2.** créer les enregistrements DNS (§7), **3.** faire émettre le certificat sur l'hébergement (cPanel › SSL/TLS Status › AutoSSL), **4.** seulement ensuite, ouvrir `https://xomiton.app` à quelqu'un d'autre que l'équipe technique.

> Le certificat ne s'émet que lorsque les enregistrements DNS pointent vers l'hébergement. Si le certificat n'est pas encore là, ne communiquez pas l'adresse.

## 7. Enregistrements DNS à créer

**Les valeurs ci-dessous sont des exemples de FORME.** Les vraies valeurs viennent de l'hébergeur cPanel, de l'équipe technique et du fournisseur d'envoi d'e-mails. N'en copiez aucune telle quelle. Les adresses `203.0.113.10` et `2001:db8::10` sont des adresses **réservées à la documentation** : elles ne mènent nulle part.

Référence complète : [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §20.3 (tableau), §20.4 (sous-domaines et redirection).

| Type | Nom | Exemple de valeur (À REMPLACER) | Rôle | Fait |
|---|---|---|---|---|
| A | `@` | `203.0.113.10` (adresse IPv4 de l'hébergement cPanel) | Le domaine nu mène au site | ☐ |
| AAAA | `@` | `2001:db8::10` (IPv6 de l'hébergement, seulement si fournie) | Idem en IPv6 | ☐ |
| CNAME | `www` | `xomiton.app.` | `www.xomiton.app` mène au même site | ☐ |
| MX | `@` | serveur de messagerie indiqué par l'hébergeur, priorité 0 ou 10 | Réception des e-mails `contact@` et autres | ☐ |
| TXT | `@` | `v=spf1 +a +mx include:<fournisseur d'envoi> ~all` | **SPF** : liste des serveurs autorisés à envoyer des e-mails au nom du domaine | ☐ |
| TXT | `default._domainkey` | `v=DKIM1; k=rsa; p=<clé publique fournie par cPanel ou le fournisseur>` | **DKIM** : signature des e-mails (la clé est **publique**, ce n'est pas un secret) | ☐ |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:dmarc@xomiton.app; adkim=s; aspf=s` | **DMARC** : consigne en cas d'échec SPF/DKIM ; passer ensuite à `p=quarantine` après quelques semaines d'observation | ☐ |

- [ ] **Abaisser le TTL à 300 secondes 48 h avant** toute bascule d'hébergement.
- [ ] **Une seule** redirection de `www` vers `https://xomiton.app` (adresse canonique), jamais deux versions du site ([GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §20.4).
- [ ] Créer dans cPanel les boîtes `contact@xomiton.app` et `noreply@xomiton.app` (utilisées par le site : sans elles, les codes de vérification à l'inscription ne partent pas), puis `dmarc@xomiton.app` si vous gardez la ligne DMARC ci-dessus, et la boîte de la protection des données si vous utilisez une adresse en `@xomiton.app` pour `DPO_EMAIL`.

## 8. Réservations défensives

Objectif : empêcher qu'un tiers enregistre un nom proche pour tromper les utilisatrices. À **comparer en prix** ; ce n'est pas obligatoire pour ouvrir.

| Nom | Intérêt | Décision (à cocher) |
|---|---|---|
| `xomiton.org`, `xomiton.com`, `xomiton.net` | Noms les plus faciles à confondre. À réserver puis **rediriger** vers `https://xomiton.app` | ☐ oui ☐ non ☐ plus tard |
| `.care` ou `.help` | Facultatif | ☐ oui ☐ non ☐ plus tard |
| `.bj` | **Seulement en redirection, jamais pour héberger le service ou les e-mails.** Un domaine national peut être suspendu ou exiger l'identification du titulaire auprès d'un registre local, ce qui exposerait l'organisation. **À faire valider par le conseil** avant tout achat | ☐ oui ☐ non ☐ plus tard |

Mêmes règles pour chaque réservation : au nom de l'organisation, WHOIS masqué, 2FA, renouvellement automatique, verrouillage du transfert.

## 9. Ne jamais faire

- [ ] Ne pas enregistrer le domaine au nom d'une personne, ni avec une adresse personnelle.
- [ ] Ne pas héberger le service ni les e-mails sur un domaine national béninois (`.bj`).
- [ ] Ne pas partager le mot de passe du compte par e-mail ou messagerie.
- [ ] Ne pas laisser le compte sans double authentification, même « une journée ».
- [ ] Ne pas ouvrir `https://xomiton.app` au public avant que le certificat soit actif.

## 10. Si `xomiton.app` n'est pas disponible

### 10.1 Variantes à proposer (à faire valider par la direction)

Critères d'un bon nom : court, sans accent, proche de « Xomiton », **sans mot d'identité** (la discrétion fait partie du produit : [playstore/PUBLICATION_PLAY_STORE.md](playstore/PUBLICATION_PLAY_STORE.md) §2), facile à dicter au téléphone, extension qui impose aussi HTTPS si possible.

| Variante (exemples de forme) | Remarque | Choix |
|---|---|---|
| `xomiton.org` | Extension classique pour une association ; n'impose pas HTTPS par défaut (le site l'impose quand même) | ☐ |
| `xomiton.care` ou `xomiton.help` | Évoque l'aide ; **à vérifier** : disponibilité et prix | ☐ |
| `getxomiton.app`, `xomiton-app.org` ou équivalent | À éviter si possible : plus facile à imiter | ☐ |
| Autre : ______________ | | ☐ |

Avant de choisir : chercher la marque « Xomiton » sur Internet pour vérifier qu'elle n'est pas utilisée par un tiers (le conseil peut le confirmer, voir [DOSSIER_AVIS_JURIDIQUE.md](DOSSIER_AVIS_JURIDIQUE.md)).

### 10.2 Liste exacte des endroits du dépôt à changer si le domaine change

Source : [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §20.8. **C'est le travail de l'équipe technique**, vous n'avez qu'à lui communiquer le nouveau nom. Pour mémoire, tout ce qui suit cite l'ancien domaine :

| Où | Quoi |
|---|---|
| `web/.env` du serveur | `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `MAIL_FROM_ADDRESS`, `VAPID_SUBJECT`, `MAIL_EHLO_DOMAIN` |
| Modèles d'environnement | `web/.env.example` et `web/.env.production.example` |
| Administration › Paramètres | `contact_email`, `smtp_from_email` |
| Valeurs semées | `web/config/xomiton.php` (section `settings` : `contact_email`, `smtp_from_email`, `VAPID_SUBJECT`) ; `web/database/seeders/data/platform/settings.php` |
| Valeurs de repli dans le code | `config('mail.from.address', …)` dans `LegalTexts`, `SmtpSettings`, `EmailTemplateService`, `ImportNewsletterCampaigns` |
| Textes | `web/lang/fr/newsletter.php` et `admin_newsletter.php`, puis `php artisan lang:export` |
| Application mobile | build de production : `API_BASE_URL=https://<nouveau domaine>/api/v1` |
| Liens universels | `assetlinks.json` (Android), `apple-app-site-association` (iOS), entitlement iOS *Associated Domains* |
| DNS | A/AAAA, CNAME, SPF, DKIM, DMARC |
| Dossier Play Store | `docs/playstore/` |
| Charte | `design-system/` (charte, jetons) |
| Tests | ceux qui citent `contact@xomiton.app` |

> Le sitemap, `robots.txt` et les liens canoniques lisent `APP_URL` : aucun changement de code pour eux (§20 du guide).

## 11. Checklist de fin et tests

### 11.1 Cases à cocher

- [ ] Domaine enregistré, **hors du Bénin**, **au nom de l'organisation**
- [ ] Compte avec **e-mail de gestion partagé**, **double authentification** (clé de sécurité si possible), **deux accès**
- [ ] **Identité masquée**, **transfert verrouillé**, **DNSSEC** activé
- [ ] **Durée ≥ 2 ans**, **renouvellement automatique**, paiement par un moyen de l'organisation, facture archivée
- [ ] Enregistrements A/AAAA, CNAME `www`, MX, SPF, DKIM, DMARC créés
- [ ] Certificat **AutoSSL actif** pour `xomiton.app` **et** `www.xomiton.app`, **avant** toute ouverture
- [ ] Boîtes `contact@` et `noreply@` créées, essai d'envoi et de réception réussi
- [ ] Réservations défensives décidées
- [ ] Nom de domaine et accès communiqués à l'équipe technique par un canal sûr

### 11.2 Tests (à lancer vous-même ou avec l'équipe technique)

Ouvrir l'invite de commandes (Windows : touche Windows, taper `cmd`) ou le Terminal (macOS, Linux). Remplacer le nom si une variante a été retenue.

| Ce qu'on vérifie | Commande | Résultat attendu |
|---|---|---|
| Le nom mène à l'hébergement | `nslookup xomiton.app` | L'adresse affichée est celle de l'hébergement fournie par cPanel |
| `www` mène au site | `nslookup www.xomiton.app` | Même résultat (ou le nom `xomiton.app`) |
| HTTPS actif | `curl -I https://xomiton.app` (Windows : `curl.exe -I https://xomiton.app`) | Ligne `HTTP/2 200` (ou `301`/`302` vers la bonne adresse), **sans erreur de certificat** |
| Redirection `www` | `curl -I https://www.xomiton.app` | `301` vers `https://xomiton.app` |
| HTTP redirigé vers HTTPS | `curl -I http://xomiton.app` | `301` vers `https://…` (le navigateur ne l'essaiera pas de lui-même, c'est normal) |
| SPF publié | `nslookup -type=TXT xomiton.app` | Une ligne commençant par `v=spf1` |
| DKIM publié | `nslookup -type=TXT default._domainkey.xomiton.app` | Une ligne commençant par `v=DKIM1` |
| DMARC publié | `nslookup -type=TXT _dmarc.xomiton.app` | Une ligne commençant par `v=DMARC1` |
| DNSSEC | `dig +dnssec xomiton.app` (ou un vérificateur DNSSEC en ligne) | Présence de signatures ; indicateur `ad` dans la réponse d'un résolveur validant (**à vérifier** selon l'outil) |
| Courrier : SPF, DKIM, DMARC réussis | Envoyer un e-mail de `noreply@xomiton.app` vers une boîte de test (Gmail : menu ⋮ › « Afficher l'original ») | Les trois lignes **SPF**, **DKIM**, **DMARC** indiquent `PASS` |
| Délivrabilité de l'hébergeur | cPanel › Email Deliverability | Tous les voyants verts |
| Note d'un outil de test d'e-mail (par exemple mail-tester.com, un exemple) | Envoyer un message à l'adresse fournie par l'outil | Note proche de 10/10 (objectif du guide : ≥ 9/10 ; **à vérifier** selon l'outil) |

> Sous Windows, `dig` n'est pas installé par défaut : utilisez `nslookup` ou un outil en ligne.

## 12. Documents liés

[DEMARCHES_CLIENT.md](DEMARCHES_CLIENT.md) · [FORMULAIRE_INFORMATIONS_LEGALES.md](FORMULAIRE_INFORMATIONS_LEGALES.md) · [GUIDE_DEPLOIEMENT.md](GUIDE_DEPLOIEMENT.md) §12, §20 · [PLAN_FINALISATION.md](PLAN_FINALISATION.md) chantiers C4, C6
