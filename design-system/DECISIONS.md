# Décisions actées — 8 octobre 2026

Réponses du client aux points D2, D4, D5, D6, D7 de `xomiton_implementation/12_RISQUES_ET_DECISIONS.md`, avec leurs conséquences. Statut : **actées**, sauf mention « à confirmer ».

## D2 — Framework CSS : Bootstrap seul (avis demandé)
**Recommandation retenue : Bootstrap 5.3.3, Tailwind retiré.** On ne garde pas les deux.

Pourquoi Bootstrap :
1. **Le thème Social est du Bootstrap.** Le fichier `public/assets/css/style.css` (435 Ko) est littéralement « Bootstrap v5.3.3 + composants Social » : navbar, offcanvas, cartes, avatars, `data-bs-theme` (mode sombre), grille, utilitaires. Toutes les vues existantes l'utilisent.
2. **Tailwind obligerait à réécrire chaque composant** (le thème Social n'a pas d'équivalent Tailwind) et à abandonner la fidélité visuelle exigée pour la conversion Sunshine → Social.
3. **Deux frameworks se battent** : le *preflight* (reset) de Tailwind et celui de Bootstrap entrent en collision, les classes homonymes (`container`, `collapse`, `hidden`, `border`) divergent, et le CSS à livrer double de poids. Sur un réseau mobile limité, c'est un coût réel.
4. Aujourd'hui Tailwind est **installé mais inutilisé** (`resources/css/app.css` ne sert à rien) : le retirer ne coûte rien.
5. Les **tokens `--xm-*` sont indépendants du framework** : si un jour on migre vers Tailwind, seule la couche de surcharge change.

Limite connue : le thème est livré **compilé, sans source Sass**, et les couleurs des boutons y sont en dur. On ne peut donc pas simplement changer `$primary`. Solution actée : la couche `tokens/social-xomiton.css`, chargée après `style.css`, réécrit les variables `--bs-*` et les composants concernés. `style.css` n'est jamais modifié. Si on obtient les sources Sass de Social, on pourra basculer vers une compilation Sass (gain de poids), sans changer les tokens.
Actions : supprimer `tailwindcss` et `@tailwindcss/vite` de `package.json`, retirer le plugin de `vite.config.js`, supprimer `resources/css/app.css` (remplacé par les trois fichiers de tokens).

## D4 — Passerelles sociales : abandonnées *(révisée le 15 octobre 2026 : module réintégré, voir D4-bis)*
**Décision : aucune passerelle réseau social** (Facebook, TikTok, Roméo, GanXo, Telegram, Tinder). Motif du client : inscription complexe en plusieurs étapes.
Conséquences :
- **Retirer** le module M16 et la phase 12 « passerelles » ; **retirer** les tables `social_channels`, `outbound_posts`, `outbound_deliveries` (domaine I de `03`).
- **Conserver** de simples **boutons de partage par lien** (WhatsApp, Facebook, Telegram, X, copier le lien), sans compte, sans API, sans jeton.
- L'exigence 3 de `Xomiton.txt` devient « contenus partageables par liens » ; **à faire confirmer par écrit** auprès du bailleur/commanditaire, car l'exigence d'origine parlait de « connecter » la plateforme aux réseaux.
- **Simplifier l'inscription** (voir `xomiton/pages/authentification.md`) : une seule étape obligatoire, le profil Xomiton devient progressif.

## D5 — Palette : validée
Primaire **`#0433C3`**, secondaire **`#E30E0E`** (adoptées telles quelles).
Contrôle de contraste : bleu sur blanc 9,26:1 (AAA) · rouge sur blanc 4,84:1 (AA, limite basse) · blanc sur rouge 4,84:1 (AA) · **bleu contre rouge 1,91:1 (interdit)** · bleu sur fond sombre 2,04:1 (inutilisable : variante `#7C97FF`) · rouge sur fond sombre 3,89:1 (insuffisant : variante `#FF6B6B`).
Conséquences : échelles 50→900 dérivées (`tokens/`), variantes pour thème sombre, **rouge réservé à l'attention** (≤ 10 % de l'écran, jamais décoratif ni seul), neutres chauds hérités des illustrations. Détail : `charte-graphique/CHARTE_GRAPHIQUE.md` §3-4.
Point d'attention : dans Bootstrap, `secondary` sert normalement à un gris neutre. Ici il devient le rouge de marque ; pour un texte atténué, utiliser `.text-body-secondary` (mappé sur `--xm-text-muted`). Dans les vues actuelles, `*-secondary` n'apparaît qu'une fois (`home.blade.php`) : le risque de régression est faible.

## D6 — Typographie : Montserrat (titres), Arial (sous-titres) — et l'écriture inclusive reste ouverte
La réponse porte sur la **typographie**, pas sur l'écriture inclusive que posait D6. Je consigne donc :
- **Titres : Montserrat** (interprété comme la famille Montserrat standard ; « Classic » à confirmer). **Sous-titres : Arial.**
- **Texte courant : Arial** (*hypothèse*, car non précisé ; à confirmer).
- Inter (utilisée aujourd'hui par la vitrine) est abandonnée.
- Vigilance : Arial est une police système ; sur **Android**, elle est remplacée par Roboto ou Liberation Sans. La mise en page doit être testée avec ces polices.
- **Reste à trancher : le système d'écriture inclusive** (point médian « expert·e·s », ou formulations épicènes, ou l'actuel mélange « conseiller‑ère‑x‑s / touxtes / ielles »). **Proposition par défaut** : formulations épicènes en priorité, point médian ailleurs, pas de néologismes dans les écrans de crise ; orthographe unique du sigle (LBTQ+ ou LGBTQ+). À valider.

## D7 — Hébergement : cPanel mutualisé conservé
Le client reste sur **cPanel mutualisé** et comptera sur les **tâches cron**. C'est faisable, mais cela change l'architecture temps réel et la fiabilité du SOS. Ce qui a été constaté sur l'existant : PHP 8.2 (`ea-php82`), `memory_limit` 128 Mo, `max_execution_time` 30 s, `display_errors On` (à couper en production), projet déployé à la racine avec un `.htaccess` qui redirige vers `public/`.

### Ce qu'on ne pourra pas avoir
| Prévu | Pourquoi impossible en mutualisé | Remplacement |
|---|---|---|
| Redis (files, cache, sessions, limitation de débit) | Pas de service Redis | `QUEUE_CONNECTION=database`, `CACHE_STORE=database` ou `file`, `SESSION_DRIVER=database` |
| Workers permanents (`queue:work`) | Pas de processus persistant | **Cron toutes les minutes** : `php artisan schedule:run` et `php artisan queue:work --stop-when-empty --max-time=50` |
| Laravel Reverb / WebSockets | Pas de processus persistant ni de port ouvert | **Polling Livewire** (`wire:poll` 15-30 s pour la cloche, 10 s sur le tableau d'alertes d'un·e agent·e de garde, 4 s seulement dans une conversation ouverte) + Web Push |
| Serveur Node de WoWonder (chat) | Pas de Node persistant | Chat par polling ; le dossier `nodejs/` est abandonné |
| Exports PDF/Word/Excel volumineux | 128 Mo, 30 s | Génération par morceaux via cron, limite de lignes, job dédié |

### SOS : conception adaptée (le point critique)
- L'envoi des notifications d'un SOS **ne passe pas par la file** : il s'exécute **dans la requête** (après enregistrement en base), avec appels HTTP en parallèle (`Http::pool`), délai maximal 3 s par fournisseur ; les échecs sont consignés puis **rejoués par le cron chaque minute**.
- L'**escalade** (« pas d'accusé de réception après N minutes ») est vérifiée par cron : granularité d'**une minute**, donc délai par défaut de 5 min respecté à ±1 min.
- **Surveillance indispensable** : un cron arrêté = des SOS non escaladés. Mettre en place un « signal de vie » : le cron appelle chaque minute une URL de supervision externe gratuite (type Healthchecks.io/UptimeRobot) qui **alerte par e-mail/SMS si le signal manque**.
- Prévoir **SMS de secours** et numéros d'urgence mis en cache hors ligne.
- Limites d'hébergeur à vérifier : nombre de processus simultanés (« Entry Processes »), CPU/IO. Le polling multiplie les requêtes : intervalles longs, réponses légères, cache de page public. **Si les limites sont atteintes, l'option VPS redevient nécessaire.**

### Déploiement et configuration
- `public/` doit devenir la **racine web** (domaine ou sous-domaine pointé vers `xomiton/public`) ; garder la règle de repli du `.htaccess` actuel seulement si ce n'est pas possible, avec blocage des dotfiles, de `storage`, `vendor`, `composer.*`.
- PHP **8.3** via *MultiPHP Manager* (8.2 minimum accepté par Laravel 12), `display_errors Off`, `memory_limit` ≥ 256M si le plan le permet.
- **Compiler Vite en local** et déployer `public/build` (pas de Node sur le serveur) ; `composer install --no-dev --optimize-autoloader` en local ou par terminal cPanel ; déploiement par Git cPanel ou SFTP.
- Sauvegardes : cPanel + `mysqldump` planifié vers un stockage externe chiffré.
- Cache et sessions en fichier/base : purger par cron.

### Garde-fou d'architecture
Les pilotes sont **pilotés par configuration** (files, cache, sessions, diffusion). Le jour où un VPS est disponible : passage à Redis + workers + Reverb **sans modifier le code applicatif**. (Nouvel ADR-11.)

### Sécurité : constat confirmé sur le serveur existant
`xomiton/cronjdfp0i4.php` n'est **pas** une tâche cron : après décodage (sans l'exécuter), c'est une **porte dérobée chiffrée** (AES-256-GCM protégée par un mot de passe haché) qui permet d'exécuter des commandes shell, de lire/écrire/téléverser/télécharger des fichiers et d'exécuter du SQL avec des identifiants fournis. `f.php` (contenu « dsds ») est vraisemblablement un fichier de test du même auteur. **On ne sait pas qui l'a déposé.** Traiter le compte d'hébergement comme **compromis** : supprimer les deux fichiers, changer les mots de passe cPanel/FTP/base, régénérer `APP_KEY` (les sessions et données chiffrées en dépendent : prévoir la migration), consulter les journaux d'accès (requêtes POST vers `cronjdfp0i4.php`), et rechercher d'autres fichiers récents suspects dans `public/` et `storage/`.

## D8 — Sortie rapide retirée, newsletter dans le pied de page (15 octobre 2026)
**Décision du client : le bouton « Sortie rapide » / « Quitter ce site » est retiré de tout le projet.**
Conséquences :
- Retirés : composant Blade `x-quick-exit`, fonction Alpine `xmQuickExit` (et le raccourci `Échap` ×2), classe `.xm-quick-exit`, bouton flottant du prototype, bouton de la page Urgences, préférence « Afficher le bouton Sortie rapide » des paramètres membre.
- Le bloc « Rester en sécurité » du pied de page public est **remplacé par l'inscription à la newsletter** (double opt-in, désinscription en un clic, conforme RGPD) ; le module Newsletter de l'administration gère abonné·es, campagnes, planification et statistiques.
- Les autres mesures de discrétion restent : titre d'onglet neutre possible, notifications masquées sur écran verrouillé, mode discret de l'application, SOS toujours accessible.
- La charte PDF et ses sources (`charte-pdf/`) sont corrigées et régénérées (15 octobre 2026) : « Discrète » s'appuie sur le mode discret, le pictogramme « Quitter » est remplacé par « Newsletter », la superposition ne garde que le bouton SOS au-dessus des modales.

## D4-bis — Passerelles réseaux sociaux : réintégrées (15 octobre 2026)
**Décision du client : le module Passerelles réseaux sociaux est réintégré.** La partie « inscription simplifiée » de D4 reste valable (une seule étape obligatoire).
Conséquences :
- Le module M16 et l'écran *Administration › Plateforme › Passerelles réseaux* (`prototype_social/admin/passerelles.php`) sont conservés : connexion des comptes de l'organisation (Facebook, TikTok, Roméo, GanXo, Telegram, Tinder), file de publication planifiée (BD SSR, messages clés, message personnalisé ≤ 500 caractères), bibliothèque de messages clés, salons de discussion entre pairs.
- Les tables `social_channels`, `outbound_posts`, `outbound_deliveries` sont rétablies dans le modèle de données, avec `gateway_templates`.
- Les passerelles publient **au nom de l'organisation** uniquement : aucun compte personnel de membre n'est connecté, aucune donnée de membre n'est envoyée aux réseaux. Les boutons de partage par lien restent disponibles pour les membres.
- Jetons d'accès chiffrés en base, publication par la tâche cron (lots, rejeu en cas d'échec), journal d'audit de chaque connexion et publication.
- **Roméo, GanXo et Tinder n'ont pas d'API de publication** (confirmé par le client) : ils fonctionnent en **publication manuelle assistée** (texte et visuel prêts à copier, puis l'équipe marque la publication « publiée »). Facebook et Telegram sont publiés automatiquement par API ; TikTok selon l'accès à son API de contenu, sinon en manuel assisté.

## D9 — Espace membre et pair éducateur·rice en Vue 3 (15 octobre 2026)
**Décision du client : l'espace connecté membre et pair éducateur·rice est développé en Vue 3**, servi par Laravel avec **Inertia.js**. Les pages publiques restent en Blade (`x-*`) et l'administration en Livewire 3.
Pourquoi :
- interactions immédiates sur réseau mobile faible (j'aime, vote, messagerie) grâce aux mises à jour optimistes ;
- hors connexion (brouillons, signalement, SOS) avec IndexedDB ;
- même API `/api/v1` que l'application Flutter : la logique n'est écrite qu'une fois ;
- compatible cPanel mutualisé (D7) : build Vite en local, aucun Node sur le serveur.
Conséquences :
- Pile : Vue 3 (Composition API, `<script setup>`, TypeScript recommandé), Inertia.js, Pinia, vue-i18n ; pas de bibliothèque d'interface supplémentaire.
- Les composants Vue `Xm*` reproduisent **exactement le HTML** des composants Blade `x-*` et du prototype `prototype_social/` (thème Social, classes `xm-*`) : un seul design.
- Les pages publiques ne chargent jamais Vue (SEO et poids) ; l'administration reste en Livewire (modales CRUD, DataTables, SweetAlert2).
- L'équipe maintient deux technologies côté interface (Vue et Livewire) : conventions dans `docs/DEVELOPMENT_STANDARDS.md`.

## Q21 — Bouton SOS : interaction du design system, au-dessus des modales (18 octobre 2026)
Décision d'équipe (plan `docs/IMPLEMENTATION_PLAN.md` §7, Q21 b), appliquée par P5-05 et P5-08 ; écart volontaire au prototype `prototype_social/sos.php`.
- **Interaction** : maintien 2 s (anneau de progression) → décompte 5 s annulable (« Annuler » 52 px, haut contraste) → envoi ; alternatives : double appui, Entrée ou activation par lecteur d'écran, puis confirmation ; Espace maintenue 2 s. Le prototype envoyait au clic simple après 3 s.
- **Tailles** : 64 px (bouton flottant, `x-sos-button`), 96 px sur la page `/sos` (prototype : 280 px).
- **Après l'envoi** : « Ton alerte est partie » (`aria-live="assertive"`), personnes prévenues, appel du contact principal, « Je suis en sécurité », repentir 60 s (absents du prototype). Case « Partager ma position » cochée par défaut, dernier choix mémorisé ; repli département / commune sans GPS, envoi automatique au bout de 15 s.
- **Superposition** : bouton flottant `.xm-sos-fab` à **z-index 1060**, au-dessus des modales (1055) conformément à D8 ; toasts (1090) en haut à droite, jamais sur le bouton. Le prototype et l'ancien composant étaient à 1040 (corrigé dans `web/` et `design-system/blade/`). Ouvrir le panneau SOS ferme une modale ouverte (pièges de focus concurrents).
- **Espaces connectés** : le bouton flottant envoie directement (sans GPS : la géolocalisation n'est accordée qu'à `/sos` et `/signaler`, département du profil joint), puis ouvre le suivi `/sos?alerte=`.

## Q11 — Signalement : six étapes du design system, écarts au prototype (18 octobre 2026)
Décision d'équipe (plan `docs/IMPLEMENTATION_PLAN.md` §7, Q11 a et Q18 a), appliquée par P5-04 ; écart volontaire au prototype `prototype_social/signalement.php`.
- **Parcours** : six étapes « Étape n sur 6 » (type · faits · besoins et lieu · preuves · contact · vérification, `xomiton/pages/signaler-et-sos.md`) au lieu du formulaire unique à onglets Dénonciation / Besoins du prototype ; même charge utile web et mobile. Sans JavaScript, les six étapes s'affichent à la suite et partent en un seul envoi.
- **« Une question par écran sur mobile »** (`signaler-et-sos.md`) : appliquée étape par étape, pas champ par champ. Les étapes 1 (signalement ou besoin, type de violence), 3 (besoins, département, commune, position) et 5 (anonymat, recontact, moyen et canal) regroupent des questions dont l'une dépend de l'autre ; les découper porterait le parcours à une douzaine d'écrans, loin des 4 étapes de FR-ALT-01. Chaque étape tient sur un écran mobile, avec « Retour » et un seul bouton primaire.
- **Gabarit** : sobre 640 px pour tout le monde (Q18 a), sans le visuel `hero-10` ni la colonne `col-lg-8` / `col-lg-9` du prototype.
- **Badge** : « Sans compte · anonyme » au lieu de « Sans compte · sans trace » : un brouillon local (sans contact, position ni preuves, `xomiton.alerts.draft_days` jours) reste sur l'appareil jusqu'à l'envoi ; « Retour » à la première étape ou « Effacer le brouillon » le supprime, comme l'annonce l'encadré d'anonymat. Côté serveur : aucune IP ni user agent pour une session d'invité·e, contenu des sessions chiffré.

## Q20 — Écrans d'authentification : gabarit du design system, écarts au prototype (18 octobre 2026)
Décision d'équipe (plan `docs/IMPLEMENTATION_PLAN.md` §7, Q20 a, avec Q8 c, Q10 c, Q12 c, Q13 b, Q30 a, Q36), appliquée par P4-09 ; écart volontaire à `prototype_social/sign-in.php`, `sign-up.php` et `forgot-password.php`.
- **Gabarit** : `xomiton/pages/authentification.md` pour tous les écrans (connexion, inscription, code, mot de passe oublié, nouveau mot de passe) : ≥ 992 px illustration 5/12 (logo blanc + promesse) · formulaire 7/12 centré, 440 px (520 px pour l'inscription, stepper à quatre libellés ; borne 440–520) ; < 992 px formulaire seul, logo au-dessus. Le prototype mettait la connexion en 6/6 à 520 px, l'inscription en 5/7 à 640 px et le mot de passe oublié en carte centrée sans illustration. `sign-in.jpg` recompressée (1600 px, 69 Ko au lieu de 3423 px et 420 Ko).
- **Pas de rouge hors erreurs** : le lien « Urgences » de la connexion devient neutre avec une icône téléphone (prototype : `text-danger`) ; la jauge reste neutre tant que le champ est vide.
- **Inscription** : quatre phases Compte · Profil · Sécurité · Consentements, seule la première obligatoire (D4), une route par phase, compte créé et session ouverte dès la phase 1 (Q12 c) ; compteur « Étape n sur 4 », suivi de « facultative » pour les phases 2 à 4, étapes facultatives marquées dans le stepper, titre d'onglet propre à chaque phase. Le prototype enchaînait Identité · Contact et lieu · Profil · Sécurité, toutes obligatoires, en un seul formulaire.
- **Champs retirés de l'inscription** : prénom et nom (pseudonyme seul, le vrai nom n'est jamais demandé), âge (profil progressif `age_range`, P8-08), téléphone (attend Q7), identité de genre, orientation et statut allié·e / ressource (profil progressif, consentement `sensitive_data`, Q10 c), confirmation du mot de passe (bouton « Afficher le mot de passe », WCAG 3.3.7 ; la confirmation reste sur « Nouveau mot de passe »). Type de membre : membre ou pair éducateur·rice (Q10 c ; prototype : quatre types, « pair animateur·rice »).
- **Consentements** : CGU + charte et confidentialité en deux cases distinctes, jamais pré-cochées, sur l'écran 1 (prototype : une case unique à la dernière étape) ; données sensibles et newsletter en phase 4, facultatives.
- **Mot de passe** : 10 caractères, majuscule, minuscule, chiffre (Q30 a ; prototype : 8, majuscule, chiffre) ; jauge 4 segments + texte (« Trop court » à « Excellent », préfixe lu par les lecteurs d'écran) + règles cochées en direct, au lieu d'une barre colorée seule.
- **Mot de passe oublié** : un seul e-mail portant le code et le lien (Q8 c), bouton « Envoyer le code » (prototype : « Envoyer le lien »), puis écran du code ; réponse identique que le compte existe ou non.
- **Connexion** : code demandé seulement sur un appareil non reconnu et à chaque connexion admin (Q13 b), absent du prototype ; écran du code à 6 cases, collage réparti (séparateurs ignorés, aussi sans JavaScript). Tutoiement sur tout le tunnel, promesse de l'illustration comprise (Q36 ; prototype : vous).
- **Référencement** : `/connexion` et `/inscription` indexées avec titre et description de `lang/fr/seo.php` ; mot de passe oublié, code, nouveau mot de passe et phases 2 à 4 en `noindex` (`SEO_CONTENT_GUIDE_INSTRUCTION.md`).
- **À confirmer** : le seuil « 15 ans et plus » du prototype (champ Âge obligatoire) n'est pas repris ; aucune règle d'âge minimal n'est écrite dans la spécification. Question ouverte au plan, §7 Q68 (a) aucun seuil, b) case d'attestation d'âge, c) tranche `age_range` obligatoire), à trancher avec le conseil juridique (consentement des mineur·es au traitement de données sensibles).
- **À arbitrer, énumération des comptes** (plan §7 Q69, P16-03) : à l'inscription, une adresse déjà utilisée reçoit « Un compte existe déjà avec cette adresse e-mail… » (`auth.api.email_taken`, web et API), qui révèle l'existence du compte, alors que `authentification.md` demande des messages qui ne le révèlent pas. Choix actuel : ergonomie (la personne sait qu'il faut se connecter) ; option recommandée : réponse neutre et e-mail « compte existant » au titulaire.
- **Inscriptions fermées** : la page garde le formulaire newsletter partagé `x-newsletter-form` (variante page, case de consentement, Q66), qui vouvoie (newsletter publique, `VOICE_AND_TONE.md` §3) ; les textes propres à la page autour du formulaire sont neutres, mais l'illustration et le message par défaut tutoient : deux registres sur cet écran tant que le composant n'accepte pas ses textes de la page (à reprendre avec le lot newsletter). Page en `noindex` avec son titre (« Inscriptions momentanément fermées ») tant que les inscriptions sont fermées.

## Q22 — Composants : les choix de web/ (18 octobre 2026)
Choix appliqué (plan `docs/IMPLEMENTATION_PLAN.md` §7, Q22 b : choix déjà faits dans `web/` à partir du prototype et de D9) par P0-07 (`x-*`) et P6-04 (`Xm*`, même HTML, test de parité) ; écart volontaire aux fichiers du DS Blade `design-system/blade/components/`.
- **Modale** : Bootstrap Modal partout (`x-modal`, `XmModal`, puis `x-admin.modal` en P10-02) : focus piégé, Échap, pied fixe, z-index 1055 ; le DS Blade décrivait une modale Alpine de 560 px à 1050.
- **Toast** : Bootstrap Toast en haut à droite, 4,5 s, pause au survol, fermable, sur le site public et l'espace Vue (`x-toast`, `XmToast`, `xomiton.js`) ; SweetAlert2 dans l'administration ; le DS Blade : Alpine en bas au centre, 6 s. Un toast n'est jamais la seule source d'information.
- **KPI** : `.card.xm-stat` + `.kpi-value`, props `trend` et `href` (`x-stat`, `XmStat`) ; le DS Blade : `.xm-card--stat`.
- **Badges de statut** : `.xm-badge-*` au lieu de `.xm-status--*` (sélecteur `[class*=status-]:before` du thème Social).
- **Focus** : anneau bleu `--xm-focus-ring` (variante `--xm-focus-ring-on-dark` possible) ; le prototype (`xomiton.css` l.107) : jaune `#F0A800`.
- **Icônes** : Bootstrap Icons + `.xm-fw` (ADR-18), jamais Font Awesome (présent dans le HTML du prototype).
- **Catalogue** : `/design-system` (local et recette, hors `admin.access`) et `/design-system/vue` ; `COMPOSANTS.md` et le README citaient `/admin/design-system`.
- **Reste à faire** : réaligner le DS Blade (`design-system/blade/components/`), `COMPOSANTS.md` et le README sur ces choix ; P6-04 reste partiel jusque-là.

## Q24 — Profil : six onglets et grille du prototype (19 octobre 2026)
Décision d'équipe (plan `docs/IMPLEMENTATION_PLAN.md` §7, Q24 a : le prototype prime sur `docs/`), appliquée par P8-07 et P8-08 (lot profil) ; écarts volontaires à `MODULE_SPECIFICATIONS.md` §B6, `FRONT_END_LAYOUT_INSTRUCTION.md` §2 et `FRONT_END_SECTION_ORDER_INSTRUCTION.md` §6 (corrigés).
- **Onglets** : les six de `partials/profile-head.php` l.8-12 — Publications (compteur) · À propos · Connexions · Médias · Événements · Activité ; la spécification n'en avait que cinq (sans Publications). Une adresse par onglet (`/profil/a-propos`, `/connexions`, `/medias`, `/evenements`, `/activite`). Activité (journal : connexions, réglages, mot de passe) : **son profil seulement** ; `/membres/{pseudo}` en montre cinq.
- **Grille** : 2/3 contenu + 1/3 colonne (Présentation, Médias, Connexions) comme `my-profile.php` l.23 et `x_profile_side()`, et non `col-lg-3` + `col-lg-9` ; dans le gabarit membre Vue (sidenav `col-lg-3` persistant, D9) elle devient `col-md-8 col-lg-6` + `col-md-4 col-lg-3`, comme les autres écrans membres.
- **Identité** : pseudonyme seul (RG-02) ; le prototype affichait le nom, l'e-mail et le téléphone dans À propos et promettait le nom « visible par tes connexions » : retirés, texte corrigé. Tranche d'âge jamais affichée.
- **Champs sensibles** (identité de genre, orientation, statut allié·e / personne ressource) : absents du prototype ; jamais montrés à une autre personne sans **choix explicite** « Afficher sur mon profil » (décoché par défaut) et, pour le genre et l'orientation, sans consentement `sensitive_data` en cours.
- **Événements d'une autre personne** : seulement ceux qu'elle organise, jamais sa participation (le prototype ne montrait que les siens).
- **Médias** : vignettes menant à la publication ; la visionneuse GLightbox du prototype n'est pas reprise.
- **Profil progressif** : bandeau « Complète ton profil, c'est facultatif » en tête de son profil, un groupe à la fois (huit groupes), « Je préfère ne pas le dire », « Passer ce groupe » et « Plus tard » (session).


## Q85 — Page de maintenance : direction visuelle propre (19 octobre 2026)
Choix appliqué par P13-07 (plan `docs/IMPLEMENTATION_PLAN.md`, Q85, à valider) : le prototype n'a pas de page publique de maintenance (seulement le formulaire `admin/mises-a-jour.php`) ; la page reçoit une direction propre, dans la charte, sans dépendre du build.
- **Composition « chantier calme »** : bandeau prioritaire SOS · Signaler · Urgences tout en haut (RG-01, D8 ; tuiles sur mobile, pastilles au-delà de 560 px), logo (SVG en ligne, une seule image pour le clair et le sombre), état « Maintenance en cours » porté par l'eyebrow avec un point animé (la pastille séparée est retirée : à 320 px elle débordait et redisait l'eyebrow), grand titre Montserrat 800 avec un mot surligné en bleu, message public, carte d'état (reprise en clair, compte à rebours, barre d'avancement), illustration SVG originale (fenêtre en mise à jour, bloc suspendu, engrenages, bouclier au cœur), cartes « Numéros d'urgence » et « Pendant la maintenance », pied avec le lien discret « Espace administration ».
- **Couleurs** : bleu dominant, **rouge réservé au SOS et à Signaler** (deux éléments, jamais décoratif : le cœur du bandeau et le badge « L'aide d'urgence reste ouverte » passent en bleu, revue du 19/10/2026), aucun arc-en-ciel ; motif de points bleus estompé et halo en arrière-plan, décoratifs et discrets.
- **Jetons de composant** (revue du 19/10/2026), tous dérivés de `tokens.css`, aucune nouvelle valeur : `--xm-art-halo` = `--xm-primary-subtle` ; `--xm-art-soft` / `--xm-art-mid` = `--xm-blue-200` / `--xm-blue-400` (sombre : `--xm-chart-seq-2` / `-3`) ; `--xm-mark` = `--xm-blue-100` (sombre : `--xm-primary-rgb` à 28 %) ; `--xm-dots` / `--xm-glow` = `--xm-primary-rgb` à 16 % / 10 % ; `--xm-shadow-card` = `--xm-shadow-sm` + ombre teintée `--xm-primary-rgb` ; `--xm-logo-ink` = `--xm-blue-500` (sombre : `--xm-neutral-0`, comme `logo-sombre.svg`) ; bandeau d'aperçu = `--xm-blue-800` sur `--xm-neutral-0`. Le `#151B3A` et les `--xm-ribbon-*` disparaissent.
- **Mouvement** : chaque animation se joue à l'ouverture et s'arrête avant 5 s (WCAG 2.2.2 : engrenages un tour, bloc suspendu deux allers-retours, points pulsés deux fois) ; le point vert « vérification de la réouverture » ne pulse que si le script vérifie vraiment ; tout est coupé par `prefers-reduced-motion`.
- **Dépassement et panne** : reprise annoncée dépassée → « Bientôt » et « La reprise prend un peu plus de temps que prévu », sans compte à rebours ni barre ; 503 générique (panne) → titre neutre « Service momentanément indisponible », bandeau « Si le SOS en ligne ne répond pas, appelez directement le 117 ou le 118 », sans carte « Pendant la maintenance ».
- **Autonomie** : CSS en ligne minifié (`resources/views/maintenance/*.css`, jetons `--xm-*` recopiés dans le bloc `.xmm`, seul endroit avec des hexadécimaux ; règle sombre écrite une fois, la préférence du système en est dérivée par `MaintenancePage::pageCss`), requêtes de conteneur (même rendu dans l'aperçu de l'administration), aucune ressource Vite ; clair / sombre par `data-bs-theme` ou la préférence du système ; animations transform / opacity coupées par `prefers-reduced-motion`.
- **Thème Social** : `.btn-success` (vert du thème, 2,45:1 avec le blanc) est remappé sur `--xm-success` et le texte de `.alert-warning` (2,97:1) sur `--xm-warning-text` (5:1) dans `social-xomiton.css`, pour tous les écrans ; `style.css` reste intact.
- **Réutilisation** : la même feuille, le même logo et la même illustration servent l'aperçu en direct de l'administration (P13-06) et l'écran plein `XmMaintenance` de l'espace membre (P13-08).

## Q25 — Collecte : tranche d'âge, jamais un âge exact (19 octobre 2026)
Décision d'équipe (plan `docs/IMPLEMENTATION_PLAN.md` §7, Q25 a : le prototype prime sur `docs/`), appliquée par P9-01 (lot dossiers-api-pair) ; écart volontaire à l'ancienne RG-08 de `BUSINESS_ANALYSIS_PRODUCT_SPECIFICATION.md` (« âge compris entre 10 et 99 ans », reformulée).
- **Saisie** : la collecte (`collecte.php` l.27, l.104-105) et les dossiers portent une **tranche d'âge** (`x_age_ranges()` : 10-14 · 15-17 · 18-24 · 25-34 · 35-49 · 50+, `cases.age_range`), jamais un âge exact ; le graphique sexe × tranche d'âge du tableau de bord (`admin/index.php`) s'en sert tel quel.
- **RG-08** : « tranche comprise entre 10-14 et 50+ » ; règle `age_range` du catalogue de validation gardée, appliquée aux tranches d'après les bornes `age_min` / `age_max` de la règle `plausibility` (10 et 99 par défaut : toutes les tranches) ; règle désactivée → toutes les tranches de la liste admises.

## Décisions du client du 28 octobre 2026 (Q16, Q54, Q56, Q57, Q58, Q61, Q64, Q68, Q89) et e-mails
Le client a tranché ; appliqué sans commit.
- **Q16** : bouton principal du hero de l'accueil = **« Créer un compte »** (`lang/fr/pages/accueil.php` `hero.cta_primary`, une seule clé, miroir mobile par `lang:export`). « Rejoignez-nous maintenant » reste l'accroche de la section réseau social (`buttons.join_now`).
- **Q61** : attente de la position avant un SOS sans position = **8 s au total** (`xomiton.alerts.sos_locate_seconds`), puis envoi sans position. `sos_manual_seconds` est supprimé : plus de seconde attente (web `sos.js` et mobile `sos_controller.dart`). Le repli département / commune reste proposé volontairement via « Envoyer sans attendre », sans minuterie.
- **Q57** : file d'e-mails purgée dès l'envoi : `MailOutbox::markSent()` vide `html`, `text`, `headers`, `reply_to`, remplace l'adresse par une empreinte HMAC et garde id, gabarit, statut, dates. Les e-mails en échec gardent leur contenu `xomiton.retention.mail_outbox_failed_days` (7) jours puis `mail:purge-logs` supprime la ligne.
- **Q68** : âge minimal d'inscription = **15 ans**, source unique `xomiton.accounts.min_age` (distincte de `age_min`, plausibilité de la tranche d'âge). **Appliqué** : case obligatoire « J'ai 15 ans ou plus » (web, API `age_confirmed` → 422 `age_confirmation_required`, Flutter), sans date de naissance (minimisation) ; seul l'horodatage est conservé via le consentement `age_confirmed`.
- **Q56** : « tu » dans l'assistant et le SOS, « vous » sur le site public. **Q89** : lien Google Maps gardé, avec avertissement. **Q58 / Q64** : FAQ et quiz maintenus (déjà validés). **Q54** : **Tranché** (b) : section `#alerte` de l'accueil (`alerte.blade.php`, classe `.lp-band-alert`) en fond rouge très clair `--xm-alert-surface` (`--xm-red-50` ; sombre `#2A1216`) avec bordure `--xm-alert-border`, texte `--xm-text` et `--xm-danger-text` (AA clair et sombre) ; le rouge plein reste réservé au SOS (maquette de téléphone). Boutons : mêmes libellés et hiérarchie ; « Contacts d'urgence » passe en contour rouge lisible sur le fond clair. Captures : `docs/captures/accueil/alerte-{390,1280}-{light,dark}.png`.
- **E-mails « premium »** : une seule mise en page (`resources/views/mail/layout.blade.php`, via `App\Application\Mail\EmailLayout`), tables et styles en ligne, 600 px, responsive à 620 px, mode sombre par `prefers-color-scheme` (+ `[data-ogsc]`, repli clair en ligne), logo PNG hébergé (`logo-mono-blanc-email.png`, jamais de SVG), bouton principal de 48 px, pré-en-tête masqué, pied avec mentions de confidentialité et désinscription (newsletter), version texte brute complète. Les jetons `--xm-*` sont recopiés en hexadécimal dans **un seul fichier**, `app/Application/Mail/EmailTheme.php` (piles de repli Montserrat puis Arial) ; à tenir synchronisé avec `tokens.css`. Mode discret : ni objet ni pré-en-tête ne portent code, référence, zone, titre ou date (migration des anciens défauts). Captures : `docs/captures/emails/`.

## Q7 — Fournisseurs push, SMS et assistant (28 octobre 2026)
Le client a choisi ; appliqué par défaut en `log` / `kb` (rien ne part tant que `.env` ne l'active pas).
- **Push** : Firebase Cloud Messaging (gratuit), API HTTP v1, compte de service Google (JSON hors dépôt, `FIREBASE_CREDENTIALS`) ; `FCM_DRIVER=fcm`. Charge utile discrète (`type`, `id`, `category`), priorité haute pour SOS et alertes, jeton invalide → abonnement supprimé.
- **SMS** : deux pilotes au choix, `SMS_DRIVER=vonage|twilio` (défaut `log`) ; numéros E.164 (+229), journal sans texte ni numéro (empreinte). Un devis local béninois reste à confirmer (expéditeur alphanumérique autorisé ou numéro) : un pilote générique sera ajouté si le devis l'exige.
- **Assistant** : Gemini API branché derrière `AssistantProvider`, **désactivé par défaut** (`ASSISTANT_LLM_ENABLED=false`). Garde-fous : la base de connaissances répond d'abord, crise / violence / SOS jamais envoyés, données personnelles masquées, consigne en « tu » sans conseil médical ou juridique définitif, délai 4 s puis repli, limite par membre, journal sans contenu. **Activation en production subordonnée à l'avis juridique** (données de santé / violences vers un tiers hors UE, DPA Google, région).

## Images fournies par le client : illustrations et écrans d'ouverture (28 octobre 2026)
Le client a déposé dix photographies dans `images/` (`design-system-1` à `4`, `xomiton-splash-1` à `6`). Ce sont des **photographies de personnes**, ni des captures de design system ni des maquettes de splash ; copies optimisées dans `design-system/assets/` (originaux intacts), alt et usage dans `assets/MANIFEST.json`, règles dans la charte v1.1 (§7.1 et chapitre 7 du PDF).
- **Droits d'image : licence confirmée par le client le 2026-10-28, preuve à archiver hors dépôt.** Valable pour ces dix fichiers ; les autres images de travail de la charte gardent la mention « droits à confirmer ».
- **Usage** : `assets/illustrations/` (4 images, paysage ou portrait : bandeaux, cartes de thématique, articles) ; `assets/splash/` (4 images portrait : splash et onboarding) ; `assets/splash/references-a-eviter/` (2 images réduites, **jamais à publier**, conservées comme contre-exemples dans la charte).
- **Consignes** : pas de texte incrusté ; recadrage portrait 9:16 centré sur le regard ; `alt` obligatoire (descriptif, jamais « image » ni le nom du fichier) ; dégradé de lisibilité sous tout texte (4,5:1) ; aucune photographie de personne identifiable pour un contenu sensible (violence, santé, signalement, SOS) sans consentement écrit de la personne ; chaque photographie est présentée comme une illustration de l'esprit de Xomiton, jamais comme une membre ou une victime réelle.
- **Traitement des fichiers** : WebP sans EXIF (le fichier `splash-6` portait des métadonnées Lightroom), taille native ou réduite (≤ 900 px), 15 à 61 Ko chacun, 352 Ko pour les dix. Les pastilles de la plateforme source (couronne, « Ai ») incrustées dans six captures ont été retirées par **recadrage du bas** de l'image, sans retouche ; la résolution (675 à 920 px de large, 1200 px pour `splash-1`) convient à l'écran, pas à l'impression grand format.
- **Images générées par IA** : les pastilles « Ai » signalent `design-system-3` et `splash-3`. Elles sont utilisables, mais ne se présentent pas comme de vraies personnes de la communauté ; à signaler dans les mentions si le client l'exige.
- **Écartées du splash** : `splash-4` (slogans en anglais incrustés, buste dénudé : contraire à « pas de texte incrusté ») et `splash-5` (bouches scotchées : image de campagne sur le silence imposé, anxiogène à l'ouverture d'une application de sécurité). Aucun usage en onboarding.
- **Splash : code non modifié.** Le carrousel (`mobile/` et `prototype_mobile/`) compte quatre diapositives liées à quatre textes (`mobile_onboarding_*`) ; ajouter ou remplacer des diapositives suppose de nouveaux textes validés et la revue de discrétion. Les quatre alternatives retenues (`splash-paillettes-drapeau`, `splash-couple-etreinte-drapeau`, `splash-regard-lumiere-chaude`, `splash-lumiere-doree-drapeau`, 201 Ko) sont prêtes : avec les 247 Ko actuels, le total resterait à 448 Ko, sous le plafond de 700 Ko d'images du splash.
- **Mode discret** : écran d'ouverture neutre (logo sur fond uni), sans photographie ni drapeau, pour que l'écran de lancement ne signale pas l'appartenance à une communauté à quelqu'un qui regarde le téléphone.
- **Vigilance** : visages réels et drapeau arc-en-ciel sur un écran de lancement exposent la personne qui l'ouvre dans un contexte social défavorable ; le choix du mode discret doit être proposé dès la première ouverture.

## Décisions du client du 29 octobre 2026 : application mobile unique (D-MOB-1 à D-MOB-14)
Le client a tranché ; consigné dans `docs/PLAN_MOBILE_APP_UNIQUE.md` §10, `docs/FEUILLE_DE_DECISION.md` §E et `docs/DECISIONS_A_VALIDER.md` §4. **Aucun code Laravel ni Flutter n'a été écrit pour cette décision** : seules les maquettes (`prototype_mobile/`) et les documents de décision sont mis à jour. Les maquettes doivent être validées par le client avant tout code mobile.
- **Une seule application, un seul login** (D-MOB-1) : membre, pair éducateur·rice et admin utilisent les mêmes écrans de connexion. L'inscription publique ne crée que des **membres**. Un membre peut recevoir le rôle **pair** (membre ET pair : réseau social + espace pair). L'admin est par défaut aussi membre (réseau social + admin). L'admin crée un·e pair par **invitation** (même mécanisme d'inscription) et valide inscriptions et demandes depuis le tableau de bord web **ou** l'appli mobile.
- **Navigation** (design) : sélecteur d'espace affiché après connexion pour un compte à plusieurs rôles, commutateur d'espace dans l'en-tête (pastille « Membre ▾ », « Pair ▾ », « Admin ▾ »), carte « Mes espaces » dans le profil ; barre du bas du mode membre : 5 onglets existants + un onglet « Espace pair » ou « Admin » bien visible ; barres du pair et de l'admin : onglet « Réseau » (fil, messages). L'espace n'est qu'une navigation : l'autorisation reste vérifiée par le serveur.
- **Espace pair dans la v1** (D-MOB-2) : corrige la recommandation de Q38 (« pas d'espace pair en v1 »).
- **Périmètre admin mobile élargi** (D-MOB-3) : alertes, validation des inscriptions et des pairs, attribution de rôles et invitations, mode maintenance, notifications, modération essentielle (contenus signalés), recherche de membre, tableau d'astreinte. Le rôle admin, la suspension de compte, les rapports et les exports restent sur le web (D-MOB-16 : **Tranché 2026-10-29 (recommandation retenue)**).
- **Conflit 409** (D-MOB-5) : le **pair tranche lui-même** (valeur du serveur ou valeur de son appareil), explication simple, effet immédiat, historique conservant les deux versions. Remplace « tranché par l'administration » (API §21.4) pour le mobile : endpoint de résolution à créer.
- **Position SOS** (D-MOB-4) : **arrondie** (~1 km) pour les admins ; la position exacte sur demande est abandonnée. **Tranché 2026-10-29 (recommandation retenue)** : jamais de position exacte pour l'admin, même en intervention.
- **Push SOS et alertes** (D-MOB-10) : envoyé aux **admins ET aux pairs éducateurs** (charge discrète `{type, id}`, sans récit ni position) ; **Tranché 2026-10-29 (recommandation retenue)** : à la création, admins **de garde** + pairs **de la zone** de l'alerte, puis **escalade nationale à 5 minutes** sans accusé (tous les admins et tous les pairs). Complète Q7 (28/10/2026).
- **Demande du rôle pair** (D-MOB-15) : **Tranché 2026-10-29 (recommandation retenue)**. Un membre demande le rôle depuis la carte « Mes espaces » de son profil (motif court facultatif, une demande à la fois, jamais d'auto-attribution) ; l'admin approuve ou refuse (motif) dans la file des inscriptions, web ou mobile.
- **Rôle admin** (D-MOB-16) : **Tranché 2026-10-29 (recommandation retenue)**. Il ne s'attribue que depuis le site web (super-admin) ; l'écran mobile l'affiche comme « sur le site web uniquement ».
- **Validation des inscriptions** (D-MOB-17) : **Tranché 2026-10-29 (recommandation retenue)**. Seules les inscriptions signalées (e-mail jetable, inscriptions répétées) attendent l'équipe ; le réglage « tout valider » reste disponible.
- **Âge** (D-MOB-18) : **Tranché 2026-10-29 (recommandation retenue)**. Conservé : application 15 ans, tranches Play 16-17 et 18+, à valider par le conseil juridique avant publication ; la règle des 15 ans est affichée à l'inscription.
- **Une seule version Play** (D-MOB-11) ; **fiche Play** (D-MOB-14) : âge minimal 15 ans (Q68 inchangée), **pas de tarif**, **tous les pays**, tranches d'âge Play « 16-17 » et « 18+ » seulement, **français uniquement**. Point de cohérence à valider : tranche la plus basse déclarée (16-17) supérieure à l'âge minimal de l'application (15 ans).
- **Estimation révisée** : 15 lots (P15-10 à P15-24), ≈ 115 j.p. (85 mobile + 30 serveur), ≈ 138 avec 20 % de marge (avant : 98 et ≈ 118) ; ≈ 10 semaines à trois personnes (deux Flutter, un·e Laravel), ≈ 17 à 20 semaines avec un seul Flutter.
- **Recommandations du plan non contestées, retenues par défaut** (D-MOB-6 à 9, 13) : PIN obligatoire pair et admin, expiration des données pair à 14 jours, mode discret par défaut, jeton admin court avec confirmation renforcée, nom « Notes » du mode discret.
- **Maquettes** : connexion unique, choix de l'espace (écran 31), inscription membre seulement, espace pair (P1 à P12), admin mobile (A1 à A12). La bascule de connectivité des maquettes pair et admin s'appelle « Connexion » pour ne pas être confondue avec l'onglet « Réseau » (réseau social). Thème clair par défaut, aucune bascule de thème, ruban arc-en-ciel, menu ☰ complet conservés.
