---
name: xomiton-performance
description: Exigences de performance de Xomiton pour un public sur réseau mobile limité et un hébergement mutualisé — budgets, images optimisées, CSS et JS optimisés, chargement différé, préchargement des polices, minification, cache, performances serveur et SEO. À charger avant d'ajouter une dépendance, une image, un script ou une requête lourde.
---

# Performance

## 1. Budgets (pages publiques, mobile 4G lente)
| Mesure | Budget |
|---|---|
| LCP | < 2,5 s |
| CLS | < 0,1 |
| INP | < 200 ms |
| Poids transféré initial (hors images différées) | < 500 Ko |
| JS initial compressé | < 150 Ko (pages publiques : < 60 Ko, pas de Vue) |
| CSS critique | < 50 Ko compressé |
| Requêtes initiales | < 30 |
| Réponse serveur (TTFB) | < 600 ms |

## 2. Images optimisées
- Formats AVIF puis WebP, repli JPEG ; `srcset` + `sizes` ; largeur maximale 1600 px (tâche « Compression des images »).
- `width` et `height` toujours renseignés (CLS) ; `loading="lazy"` et `decoding="async"` sauf image LCP (`fetchpriority="high"`, préchargée).
- Téléversements : redimensionnement et ré-encodage côté serveur, suppression EXIF.
- Icônes : SVG (Bootstrap Icons) ; favicons en ICO / SVG / PNG dédiés.

## 3. CSS optimisé
- Un seul framework (Bootstrap 5.3.3), pas de Tailwind (D2) ; purge des classes inutilisées du thème Social au build (PurgeCSS avec liste de sécurité pour les classes dynamiques).
- Ordre : `style.css` → `tokens.css` → `social-xomiton.css` → `components.css` → `xomiton.css` ; CSS critique en ligne pour le hero.
- Pas d'`@import` en cascade ; animations uniquement sur `transform` et `opacity`.

## 4. JS optimisé
- Pages publiques : JS minimal (Bootstrap bundle + `xomiton.js`), chargé en `defer`.
- Espace membre : Vue avec découpage par route (`import()`), Pinia, pas de bibliothèque d'interface lourde. Paquet initial `app.ts` (P4-12, mesuré le 18/10/2026 : 149,6 → 119,4 Ko gzip, −30,2 Ko) : Bootstrap + Popper (`shared/bootstrap.js`, −17,6 Ko) chargés au repos après le montage ou au premier geste sur un déclencheur `data-bs-*` (`app/bootstrapOnDemand.ts`, geste retenu puis rejoué ; `shared/bootstrap-lazy.js` pour les modules partagés) ; vue-i18n sans son compilateur de messages (`__INTLIFY_DROP_MESSAGE_COMPILER__`, −4,6 Ko net) remplacé par `app/messageCompiler.ts` (sous-ensemble produit par `lang:export`, comparé message par message au compilateur d'origine en Vitest) ; `side-channel` de `qs` (tiré par `@inertiajs/core`) remplacé par un équivalent sur `WeakMap` (`app/shims/side-channel.js`, greffon Vite `xm-side-channel-shim`, build seulement : `vite dev` et Vitest gardent le paquet d'origine ; même résultat de `qs` vérifié en Vitest, −8,2 Ko). Les composants n'importent jamais `shared/bootstrap.js` en dur (garde Vitest `app/bootstrapImports.test.ts` et garde du manifest au build) : `XmModal` attend `loadBootstrap()` avant d'ouvrir, `XmToast` a un repli sans Bootstrap si le morceau ne se charge pas. Postes restants : Vue ~34 Ko, axios ~14 Ko et `@inertiajs/core` + lodash-es + qs ~25 Ko (dépendances d'Inertia 2), vue-i18n ~13 Ko (API de composition sans compilateur, mesuré isolément). Aucune dépendance en double.
- Administration : Livewire + Alpine, Chart.js et Quill chargés **uniquement** sur les écrans qui en ont besoin (`$adminHead`, `$adminScripts`).
- Pas de jQuery dans le code neuf ; dépendances auditées (poids, maintenance) avant ajout.

## 5. Chargement différé (lazy loading)
Images hors écran, iframes, vidéos (Plyr à l'interaction), graphiques sous la ligne de flottaison, modules Vue secondaires (messagerie, assistant), « Charger plus » au lieu de listes infinies sur mobile.

## 6. Polices
- Montserrat **auto-hébergée** (WOFF2 latin, latin étendu), `font-display: swap`, préchargement des graisses 700 et 800 utilisées par les titres : `<link rel="preload" as="font" type="font/woff2" crossorigin>`.
- Texte en Arial (police système, aucun téléchargement).

## 7. Minification et livraison
- Build Vite en local (pas de Node sur le serveur, D7) : minification, hachage des noms (`app.3f9a.js`), compression gzip/Brotli si disponible.
- En-têtes de cache : ressources versionnées 1 an `immutable` ; images livrées 30 jours ; HTML des pages publiques sans session `public, max-age=60, stale-while-revalidate=300` + ETag (§11), HTML des pages à session `no-cache, private`.
- Service worker : cache hors connexion des ressources, contacts d'urgence et thématiques ; mise à jour par la tâche « Mise à jour du cache hors connexion ».

## 8. Serveur et base de données
- `php artisan optimize` (config, routes, vues, événements en cache) au déploiement.
- Pas de N+1, pagination, index ([DATABASE_ENGINEERING.md](DATABASE_ENGINEERING.md)), cache des référentiels 24 h.
- Exports volumineux par morceaux via cron (128 Mo, 30 s) ; envoi de newsletter par lots de 200.
- Polling léger : réponses JSON minimales, intervalles longs (30 s cloche, 4 s conversation ouverte seulement).

## 9. SEO friendly
Rendu serveur des pages publiques (Blade), HTML sémantique, liens explorables (`<a href>`), sitemap et `robots.txt`, aucune page publique dépendante du JS pour son contenu.

## 10. Contrôle
Lighthouse CI (mobile) sur l'accueil, une thématique, les ressources et le signalement à chaque demande de fusion ; échec si un budget est dépassé.

Au build : `npm run build` = `vite build && node scripts/check-budgets.mjs` ; tailles gzip lues dans `public/build/manifest.json` (entrée + imports statiques) ; strict (échec) : `public.js` < 60 Ko, `critical.css` < 50 Ko, `app.ts` < 150 Ko, et `shared/bootstrap.js` absent des imports statiques d'`app.ts` (gabarit compris) ; indicatif (avertissement) : `xomiton.css` < 120 Ko et, pour chaque `Pages/**/*.vue`, JS initial de la page (`app.ts` + morceau de page + imports statiques) < 150 Ko, Bootstrap importé en dur par une page signalé ; `npm run build:nocheck` construit sans contrôle. Mesures du 18/10/2026 : `public.js` 56,2 Ko, `critical.css` 3,1 Ko, `xomiton.css` 62,6 Ko, `app.ts` 120,0 Ko ; pages de 121,8 (Member/Placeholder) à 137,8 Ko (Community/GroupShow), Member/Messages 136,7 Ko.

**Mesures locales de la revue finale (P16-02, 27/10/2026, indicatives).** Serveur intégré de PHP (`php -S`, un seul processus, sans OPcache dédié ni réseau) sur une base SQLite dédiée semée (`migrate:fresh --seed`), PHP 8.3, 20 requêtes séquentielles après une requête de chauffe, `curl` `time_starttransfer` (TTFB) moyen. Ces chiffres valident l'absence de dérive, pas le p95 de production : le **p95 < 600 ms sur `GET feed`, `POST sos`, `GET sync/pull` et le TTFB des pages publiques sur l'hébergement cible restent à mesurer en recette** (test de charge léger sur le serveur cPanel, base MySQL, données réalistes).

| Requête | TTFB moyen (20 req.) |
|---|---|
| `GET /` | 22 ms |
| `GET /thematiques` | 21 ms |
| `GET /ressources` | 25 ms |
| `GET /violences` | 22 ms |
| `GET /urgences` | 20 ms |
| `GET /api/v1/feed` (membre) | 28 ms |
| `GET /api/v1/sync/pull` (pair, appareil enregistré) | 34 ms |
| `POST /api/v1/sos` (anonyme, clé d'idempotence neuve) | 65 ms |

`Model::preventLazyLoading(! app()->isProduction())` est actif dans `AppServiceProvider::boot()` : toute relation chargée à la volée lève une exception en développement et dans les tests. Les N+1 révélés par la suite Pest ont été corrigés (liste des comptes d'administration : `profile` ; copie d'une remise sociale : `post` ; préférences de notification : `settings` ; migration de renommage de permission : `permissions`).

## 11. Pages publiques : lenteur, rechargement et navigation (mesures du 31/10/2026)

**Constat du client** : l'accueil et les pages légales semblent lentes et se rechargent entièrement (flash blanc) à chaque navigation. **Méthode** : application installée en production locale (`APP_ENV=production`, `APP_DEBUG=false`, `php artisan optimize`, `npm run build`, SQLite semée par `migrate:fresh --seed`), servie par `php -S` (4 processus, OPcache CLI) derrière un mandataire qui compresse en gzip comme `mod_deflate` ; Chromium 141 + Playwright (`PW_CHROMIUM`), **4G lente (1,6 Mb/s descendant, 150 ms) et CPU ×4**, médiane de 2 passes ; « froid » = cache vide, « chaud » = 2e visite du même contexte. Reproduire : `sh scripts/perf/serve.sh` puis `PW_CHROMIUM=/opt/pw-browsers/chromium node scripts/perf/measure.mjs` (mesures navigateur), `php scripts/perf/probe.php` (SQL, temps serveur, en-têtes), `node scripts/perf/waterfall.mjs /cookies` (cascade des requêtes), `node scripts/perf/navigation.mjs` (clic accueil → page légale, avec et sans règles). **Limite : poste de test Linux rapide, pas un mutualisé cPanel** ; les ordres de grandeur et les écarts avant/après sont fiables, les valeurs absolues (TTFB en particulier) ne le sont pas.

### 11.1 Causes identifiées (mesurées)

| Cause | Mesure | Statut |
|---|---|---|
| **Aucune réutilisation possible des pages** : chaque visite ouvrait une session en base (lecture, insertion, écriture), posait `xomiton-session` + `XSRF-TOKEN` et répondait `no-cache, private`. Un visiteur ou un robot de plus = une ligne de plus dans `sessions`. | 4 à 7 requêtes SQL par page, dont 3 de session ; 2 `Set-Cookie` ; rien à garder dans le navigateur ni dans un cache. | Corrigé (§11.2 b) |
| Home : trois blocs lus à chaque requête (FAQ, fiches, catégories). Pas de N+1 (une requête par bloc, catégories en eager loading) ; index manquant sur `faqs`. | 3 requêtes de contenu par visite. | Cache de données + index |
| Image LCP et visuels en JPEG. | `05.jpg` 34 Ko → WebP 9 Ko ; 6 couvertures 85 Ko → 28 Ko chacune (WebP 640 px, 320 px pour les petits écrans). Image LCP déjà préchargée, `fetchpriority=high`, `width`/`height` partout, `lazy` ailleurs (déjà le cas). | Corrigé |
| Préchargements `modulepreload` du JS public : priorité haute, se disputent la bande passante avec le CSS qui bloque le premier rendu. | Retirés : FCP −150 ms, chargement −170 ms (4G lente). | Corrigé |
| Debugbar / `APP_DEBUG=true` en local. | HTML de l'accueil **141 Ko avec Debugbar, 72 Ko sans** ; chargé seulement si `APP_ENV=local` et `APP_DEBUG=true` (désenregistré de l'auto-découverte, jamais présent avec `--no-dev`). | Corrigé |
| `php artisan serve` : un seul processus PHP. | 60 pages demandées 8 à la fois : **2,2 s avec 1 processus, 0,65 s avec 4**. | Documenté (INSTALLATION_LOCALE.md §3.4) |
| OPcache et caches Laravel absents en CLI. | Mesuré en production locale : requête chaude 4–8 ms avec les caches Laravel ; le premier appel de chaque processus coûte 80–200 ms (amorçage). Sans OPcache, PHP recompile ~1 000 fichiers à chaque requête : réglage à vérifier chez l'hébergeur. | Documenté (GUIDE_DEPLOIEMENT.md §10) |
| **Non cause** : polices (Montserrat préchargée, `swap`), CSS critique (3,1 Ko inline), JS en module différé, TTFB serveur (~30 ms), CLS (0 partout). | | Déjà en place |
| **Reste, non traité** : police Bootstrap Icons complète (131 Ko, priorité haute) ; CSS principal 65 Ko gzip partagé par les trois espaces (budget 120 Ko respecté) ; trois GIF animés lazy de l'accueil (240–510 Ko, la conversion en WebP animé n'économise presque rien, MP4/WebM changerait le contenu) ; deux requêtes de maintenance par page. | Premier rendu froid borné par la bande passante : 5 ressources bloquantes ou prioritaires se partagent 1,6 Mb/s. | Recommandations |

### 11.2 Mécanismes installés

**a) Navigation sans rechargement perceptible**, par ordre efficacité / coût, tout en CSS ou en quelques octets de JS :
1. **Speculation Rules** (`partials/speculation-rules.blade.php`, liste blanche dans `config/xomiton.php` › `speculation`) : `prefetch` en `eagerness: moderate` (survol ~200 ms) et `prerender` en `conservative` (appui sur le lien), sur les seules pages publiques en lecture seule (accueil, thématiques, ressources, actualités, aide, lois, Do Glegbe, pages légales), sans téléchargements ni flux, `rel=nofollow`, `download`, `target`, `data-no-prefetch`. Jamais SOS, signalement, urgences, authentification, compte, API, administration ni POST ; `/violences` (auto-évaluation) exclue aussi : un survol ne doit pas laisser de trace serveur d'une page non ouverte. Script soumis à la CSP : il porte le **nonce** de la requête. Le prerender n'est qu'à l'appui, pas au survol, pour ne pas charger le processeur d'un téléphone d'entrée de gamme ; le navigateur n'applique rien avec « économiser les données ».
2. **Transitions de vue inter-documents** (`resources/css/app/view-transitions.css`, recopiée dans `critical.css` : la règle doit être connue avant le premier rendu) : `@view-transition { navigation: auto }` dans `@media (prefers-reduced-motion: no-preference)`, fondu de 0,15 s. Le navigateur garde l'ancienne page affichée jusqu'à ce que la nouvelle soit prête : plus de flash blanc. Vérifié dans Chromium (événement `pagereveal` avec `viewTransition`).
3. **Repli de préchargement** (`resources/js/shared/prefetch.js`, 727 octets gzip, sans dépendance, testé par Vitest) pour Safari et Firefox : relit la même liste blanche dans le JSON des règles, demande la page en basse priorité au survol ou au toucher ; la réponse mise en cache (`max-age=60`) sert la navigation suivante. Inactif si le navigateur gère les règles, avec « économiser les données » ou en 2G.

*Mesure (clic accueil → `/cookies`, 4G lente, CPU ×4, assets déjà en cache ; écarts sur 2 passes)* :

| Variante | 1er octet | FCP page d'arrivée | clic → DOM prêt |
|---|---|---|---|
| Sans règles, survol 600 ms | 35–40 ms | 500–580 ms | 700–750 ms |
| Avec règles, survol 600 ms (page préchargée) | **3 ms** | **336–344 ms** | **490–610 ms** |
| Avec règles, clic immédiat (rien de préchargé) | 25 ms | 560–640 ms | 750–810 ms |

Gain mesuré du préchargement : environ −150 à −240 ms de FCP et −100 à −260 ms jusqu'au DOM prêt quand la personne survole avant de cliquer ; aucun effet sans survol. **Limite honnête** : le prerender (activation instantanée) ne s'est pas déclenché dans le Chromium de test (`activationStart` toujours 0, y compris appui maintenu 1,2 s) ; il est donc déclaré mais **non vérifié** ici, seul le prefetch l'est.

**Comparaison avec une vraie navigation de type SPA** (taille gzip mesurée sur le build) :

| Option | Poids ajouté | Total `public.js` (budget 60 Ko ; 57,1 Ko aujourd'hui) | Verdict |
|---|---|---|---|
| Turbo Drive 8.0 | **25,6 Ko** (minifié, gzip) | ~82,7 Ko | Refusée : hors budget |
| htmx 2.0 + `hx-boost` | **16,4 Ko** | ~73,5 Ko | Refusée : hors budget |
| Livewire `wire:navigate` | Livewire entier (estimation, non mesurée : plusieurs dizaines de Ko) en plus d'Alpine déjà présent dans `public.js` | nettement > 60 Ko, deux Alpine à réconcilier | Refusée ; reste réservée à l'administration, où elle est déjà installée |
| Speculation Rules + transitions CSS + repli (retenue) | **0,7 Ko** de JS (gzip), 0,2 Ko de CSS inline | 57,1 Ko | **Retenue** |

Risques d'une navigation SPA, au-delà du poids : nonce CSP à rejouer sur chaque script injecté ; Alpine à ré-initialiser (`x-data` des pages remplacées) et Bootstrap (composants, `data-bs-*`) à rebrancher ; service worker et boîte d'envoi hors connexion à garder cohérents ; focus et annonce de page à gérer à la main (lecteurs d'écran : titre, `main#contenu`) ; SEO et partage inchangés mais tests e2e à doubler. La solution retenue garde de vraies navigations (chaque page est un document : focus, titre, historique, CSP, Alpine et lecteurs d'écran se comportent normalement) et supprime le coût perçu : page déjà là au clic (préchargement), pas de flash (transition de vue).

**b) Cache HTTP et serveur** (`App\Http\Middleware\CacheableGuestPage`, alias `guest.page`, posé sur les routes publiques en lecture seule) : pour une requête GET/HEAD **sans cookie de session ni « se souvenir de moi » ni `Authorization`**, la session est en mémoire (pilote `array`, aucune requête SQL de session), aucun cookie n'est posé, la réponse porte `Cache-Control: public, max-age=60, stale-while-revalidate=300`, `ETag` et `Vary: Cookie`, et `If-None-Match` (tolérant à `W/` et au suffixe `-gzip` d'Apache) donne **304**. L'empreinte ignore le nonce CSP (aléatoire à chaque réponse : la sécurité est inchangée) et le 304 ne porte pas d'en-tête CSP, pour que la copie du navigateur garde celui qui correspond aux nonces de son corps. Une personne avec cookie ou connectée suit le parcours normal (`no-cache, private`, jamais `public`). Le formulaire de newsletter du pied de page, qui exige un jeton CSRF, est remplacé sur ces pages sans session par un bouton vers `/newsletter` (page à session et formulaire protégé) : **aucune exemption CSRF n'a été ajoutée**. Cache de données de l'accueil (`App\Support\PublicContentCache`, cache fichier compatible cPanel) : fiches et FAQ, vidé par `PublicContentObserver` à chaque écriture sur `Thematique`, `Faq` ou `Category` (publication, dépublication, édition, suppression), TTL 10 minutes (une publication programmée apparaît au plus 10 minutes après son heure). Pas de cache de HTML complet : il porte un nonce par requête. Index `faqs_home_index` ajouté.

**c) Assets** : WebP voisins (`05.webp`, `04.webp`, `6033855.webp`, `covers/th-N.webp` et `-320.webp`) choisis automatiquement par `<x-img>` pour toute image livrée dans `public/assets` (`<picture>` WebP puis JPEG ; AVIF non produit : GD de l'hébergement) ; image LCP préchargée en WebP avec `fetchpriority=high` ; polices Montserrat préchargées (`swap`) ; JS en module différé ; `.htaccess` : `/build/*` 1 an `immutable`, `/assets/*` 30 jours + `stale-while-revalidate`, types MIME `webp`/`avif`/`woff2`, Brotli et gzip (vérifié par un test sur le contenu du fichier ; **Apache et LiteSpeed n'ont pas pu être lancés ici** : à contrôler en ligne avec les commandes de GUIDE_DEPLOIEMENT.md §10). CSS critique : 3,1 Ko gzip inline (budget 50) ; le CSS principal reste bloquant (le différer produirait un flash de page sans style, le critique ne couvrant que l'en-tête et le héros).

**d) Serveur** : `php artisan optimize` dans `scripts/deploy.sh` et les procédures ; OPcache et réglages dans GUIDE_DEPLOIEMENT.md §10 ; accueil : 3 requêtes de contenu à froid, **0 au chaud**, test de borne. **e) Poste Windows** : INSTALLATION_LOCALE.md §3.4.

### 11.3 Résultats avant / après

Avant = état du dépôt à `6212984` ; après = ce lot. Budgets : `public.js` 57,1 Ko / 60 (avant 56,6), CSS principal 64,6 Ko / 120, CSS critique 3,1 Ko / 50.

| Mesure (4G lente, CPU ×4, froid) | Accueil avant → après | Pages légales avant → après |
|---|---|---|
| 1er octet (TTFB) | 118 → 29–35 ms | 29–51 → 23–42 ms |
| FCP = LCP | 1 968 → **1 452–1 484 ms** (−25 %) | 1 396–1 456 → **1 260–1 348 ms** (−9 %) |
| Requêtes / Ko transférés | 27 / 453 Ko → 25 / 382 Ko | 14 / 327–331 Ko (inchangé) |
| CLS | 0 → 0 | 0 → 0 |

| Mesure (chaud : 2e visite) | Accueil avant → après | Pages légales avant → après |
|---|---|---|
| TTFB | 55 → **2 ms** (cache HTTP) | 37–41 → **1–2 ms** |
| FCP / LCP | 656 / 812 → **388 / 472 ms** | 464–520 → **316–380 ms** |
| Ko transférés | 162 → 76 Ko | 36–39 → **26 Ko** (les 26 Ko restants : polices et images revalidées après `max-age`) |

| Serveur (production, SQLite, requête chaude) | Avant | Après |
|---|---|---|
| Requêtes SQL accueil | 7 (3 de session, 1 maintenance, 3 contenu) | **2** (état + annonce de maintenance) ; 5 au premier rendu après modification de contenu |
| Requêtes SQL pages légales | 4 (2 de session + insertion) | **2** |
| Pages publiques : cookies posés / lignes `sessions` créées | 2 / 1 par visite | **0 / 0** |
| `Cache-Control` | `no-cache, private` | `public, max-age=60, stale-while-revalidate=300` + `ETag` + 304 |
| Temps serveur chaud (accueil / légal) | 23 / 7–11 ms | 5,6 / 3,5–4 ms |
| Pages à session (`/connexion`, `/supprimer-mon-compte`) | 3–4 SQL, cookies, `no-cache, private` | inchangé (voulu : jetons CSRF) |

Chiffres du FCP froid : ils restent dominés par la bande passante simulée (CSS 65 Ko + police 37 Ko + JS 57 Ko + icônes 131 Ko se partagent 1,6 Mb/s) ; la compression Brotli d'un vrai serveur et HTTP/2 les amélioreront, un mutualisé lent les dégradera. **Ce qui change réellement la perception** est la navigation : page déjà présente au clic, transition sans flash, 2e page en ~0,35 s au lieu de ~0,5 s.

### 11.4 Contrôles automatisés

`tests/Feature/Performance/GuestPageCachingTest.php` : en-têtes `Cache-Control` / `ETag` / `Vary` et absence de `Set-Cookie` et de ligne `sessions` sur 13 pages invitées ; 304 (avec `W/` et `-gzip`) sans corps ni CSP ; ETag stable malgré un nonce différent ; jamais `public` avec cookie de session, compte ou `Authorization`, ni sur connexion / signalement / SOS / urgences / newsletter ; borne SQL (≤ 5 à froid, ≤ 2 au chaud, 0 de session) ; invalidation du cache de l'accueil à chaque écriture ; Speculation Rules (JSON, nonce = nonce de la CSP, `moderate` / `conservative`, liste blanche sans aucune route sensible, POST ou nommée SOS / auth / API / admin) ; absence des règles sur connexion / SOS / signalement / urgences ; transitions de vue sous `prefers-reduced-motion: no-preference` ; `.htaccess` ; Debugbar jamais découvert ni chargé hors `local` + `APP_DEBUG=true`. Vitest : `resources/js/shared/prefetch.test.js`. Budgets : `npm run build` (échec au-delà de 60 Ko pour `public.js`).
