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
- En-têtes de cache : ressources versionnées 1 an `immutable` ; HTML `no-cache`.
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
