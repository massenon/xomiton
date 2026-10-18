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
