---
name: xomiton-implementation-plan
description: Plan d'implémentation complet de Xomiton, du prototype PHP vers la plateforme réelle (Laravel 12, Blade public, Livewire 3 administration, Vue 3 + Inertia espace membre et pair, API /api/v1, Flutter mobile, hébergement cPanel). Phases, tâches numérotées, dépendances, critères de « terminé », décisions d'implémentation et points à trancher. À charger pour savoir quoi faire ensuite, dans quel ordre, et pour mettre à jour l'avancement.
---

# Plan d'implémentation — du prototype à la plateforme

Version 1.0 — 17 octobre 2026. Ce plan est **vivant** : chaque tâche porte un statut (`à faire` · `en cours` · `fait` · `bloqué`) mis à jour à chaque livraison. Il complète, sans les remplacer, les documents de `docs/` (le *quoi*) : il dit le *dans quel ordre* et le *quand c'est fini*.

Sources de vérité, par priorité : `design-system/DECISIONS.md` → `design-system/xomiton/` → `prototype_social/`, `prototype_mobile/` → `docs/`. Tout conflit relevé pendant l'implémentation est consigné en §7 puis dans `DECISIONS.md`, jamais tranché seul.

---

## 0. Lecture rapide

| | |
|---|---|
| **Objectif** | Livrer en production la plateforme décrite dans `docs/`, fidèle à 100 % au prototype (`FINAL_DESIGN_DIRECTION.md`), sur cPanel mutualisé (D7). |
| **Pile** | Laravel 12 · PHP 8.3 · MySQL 8 / MariaDB 10.6 · Blade `x-*` (public) · Livewire 3 (admin) · Vue 3 + Inertia + Pinia (membre, pair) · API `/api/v1` Sanctum · Flutter 3 (mobile) · Bootstrap 5.3.3 thème Social, jetons `--xm-*`. |
| **Dépôt** | Mono-dépôt : `web/` (Laravel), `mobile/` (Flutter), `docs/`, `design-system/`, `prototype_social/`, `prototype_mobile/` (références, conservés jusqu'à parité). |
| **Phases** | P0 Socle → P1 Données → P2 Identité & accès → P3 Noyau transverse → P4 Site public → P5 Alertes, SOS, signalement → P6 API & coquille Vue → P7 Fil & sondages → P8 Communauté & profil → P9 Espace pair & synchro → P10 Admin socle & pilotage → P11 Admin données → P12 Admin plateforme → P13 Admin système → P14 Communication (templates, newsletter, passerelles) → P15 Mobile Flutter → P16 Qualité → P17 Déploiement & exploitation. |
| **Chemin critique** | P0 → P1 → P2 → P3 → P5 (SOS, la fonction qui protège) → P10 (alertes côté admin) → P17 (cron, supervision). Tout le reste se parallélise autour. |
| **Définition de « terminé »** | Celle de `MASTER_PROMPT.md` §6, appliquée à chaque tâche (navigateur pour chaque rôle · tests nominal / 422 / 403 · structure et responsive du prototype · clavier, focus, contraste, lecteur d'écran · textes dans `lang/` · docs à jour · diff relu). |

---

## 1. Décisions d'implémentation (techniques, dans le cadre des décisions client)

Ces choix ne contredisent aucune décision de `DECISIONS.md` ; ils précisent *comment* l'appliquer. À consigner comme ADR dans `SOFTWARE_ARCHITECTURE.md` §10 une fois validés par l'équipe.

| Code | Décision | Pourquoi |
|---|---|---|
| ADR-17 | **Mono-dépôt** : application Laravel dans `web/`, application Flutter dans `mobile/`. Les prototypes restent à la racine comme référence visuelle ; ils seront archivés (`archive/`) quand chaque écran aura son équivalent vérifié « à l'identique ». | Un seul historique Git pour docs, design system, web et mobile ; déploiement cPanel par Git possible en pointant le domaine sur `web/public`. |
| ADR-18 | **Trois paquets Vite** : `public.js` (Bootstrap + Alpine, pages Blade), `admin.js` (Bootstrap + SweetAlert2, Chart.js, Quill, Choices, flatpickr ; Alpine fourni par Livewire), `app.ts` (Vue 3 + Inertia + Pinia + vue-i18n). CSS : `xomiton.css` commun (`style.css` → `fonts` → `tokens` → `social-xomiton` → `components` → `xomiton`) et `admin.css`. Pas de Tailwind (D2), pas de Font Awesome (icônes Bootstrap). | D9 : les pages publiques ne chargent jamais Vue ; l'admin ne charge pas Vue. Un seul CSS pour garantir le même rendu dans les trois espaces. |
| ADR-19 | **Authentification maison** (contrôleurs + Form Requests + Sanctum), sans Breeze ni Fortify. | Les parcours OTP en 4 phases et le HTML du prototype ne correspondent à aucun kit ; Fortify imposerait ses vues et ses flux. |
| ADR-20 | **Tests avec Pest**, SQLite en mémoire pour les tests, MySQL en local et en production. Les migrations restent compatibles MariaDB 10.6 (pas d'index multi-valeurs JSON : la segmentation newsletter se fait par tables de liaison `newsletter_subscriber_tags` / `_interests` en plus des colonnes JSON d'affichage). | Rapidité des tests, portabilité de l'hébergement. |
| ADR-21 | **Écrans d'administration** : un trait `WithDataTable` (recherche, filtres dans l'URL, tri, pagination, sélection groupée, export CSV) et des composants Blade `x-admin.*` (`kpi`, `modal`, `table`, `filters`, `empty`, `pagination`) partagés par tous les composants Livewire. Aucun composant « générique » monolithique. | Cohérence §4 d'`UI_UX_DESIGN_SYSTEM.md` sans dette d'abstraction. |
| ADR-22 | **Sauvegardes** au format du prototype : une archive ZIP chiffrée par mot de passe contenant un fichier JSON par table (« collection ») + `manifest.json` ; export et import par morceaux (`chunkById`) compatibles 30 s / 128 Mo ; `mysqldump` nocturne en complément quand il est disponible. | Reprend l'aperçu (collections, écarts, extrait masqué) et la restauration confirmée (RG-13) du prototype. |
| ADR-23 | **Exports** : PDF par `barryvdh/laravel-dompdf`, Excel par `openspout/openspout` (flux, mémoire constante), Word par `phpoffice/phpword` ; génération par job par morceaux, fichier stocké hors racine web, lien signé. | Contraintes D7 (128 Mo, 30 s). |
| ADR-24 | **HTML riche** : Quill côté client, `mews/purifier` (HTMLPurifier) côté serveur avec une liste blanche propre aux e-mails. | XSS (DEVELOPMENT_STANDARDS §7). |
| ADR-25 | **Push** : Web Push VAPID (`minishlink/web-push`) pour le navigateur, FCM HTTP v1 (`laravel-notification-channels/fcm`) pour Flutter, canal `fcm` et canal `webpush` branchés sur les mêmes classes `Notification`. SMS de secours derrière une interface `SmsSender` (pilote `log` par défaut). | NOTIFICATIONS_INSTRUCTION §1. |
| ADR-26 | **Hors connexion web** : service worker Workbox (via `vite-plugin-pwa`) pour le cache des pages publiques critiques (SOS, urgences, signalement) et IndexedDB (`idb`) pour la boîte d'envoi ; même protocole `sync/push` que Flutter. | SOFTWARE_ARCHITECTURE §5, NFR-08. |
| ADR-27 | **Identifiants publics** : les URL de l'espace membre exposent des identifiants opaques (ULID) pour publications, conversations, groupes, événements ; les clés primaires restent `bigint` en base (`DATABASE_ENGINEERING.md`). | Pas d'énumération de contenus ; aucun changement du modèle. |
| ADR-28 | **Rôle `moderateur`** créé dès P2 avec les permissions du §3 de `ROLE_ET_PERMISSIONS.md`, mais **non attribuable** tant que le client ne l'a pas validé (statut « proposé »). | Évite une migration ultérieure ; respecte le statut « à valider ». |

---

## 2. Vue d'ensemble des phases

Taille : S (≤ 1 j·p) · M (2–3 j·p) · L (4–6 j·p) · XL (> 6 j·p, à découper en livraisons intermédiaires). Les estimations sont indicatives pour un·e développeur·se full stack qui connaît la pile.

| Phase | Objectif | Livrable vérifiable | Dépend de | Taille |
|---|---|---|---|---|
| **P0** Socle | Projet Laravel 12 outillé, thème et jetons intégrés, CI verte, gabarits Blade des 4 espaces | `php artisan serve` affiche la coquille publique, admin et Vue avec le CSS du prototype ; CI Pint + Larastan + Pest + Vite passe | — | L |
| **P1** Données | Toutes les migrations, modèles, enums, factories, seeders, import du prototype | `migrate:fresh --seed` crée 70+ tables ; `xomiton:import-prototype` charge `prototype_social/data` ; tests de contraintes | P0 | XL |
| **P2** Identité & accès | Connexion, inscription 4 phases, OTP e-mail, mot de passe oublié, rôles et permissions, policies, audit, limitation de débit, en-têtes de sécurité | Parcours complets web + API pour chaque rôle ; tests 403 par rôle | P1 | L |
| **P3** Noyau transverse | Service de templates e-mails, notifications (cloche, mail, push), paramètres, téléversements, i18n `lang/fr`, journal d'audit, référentiels en cache | `EmailTemplateService::send()` fonctionne avec SMTP ou boîte locale ; `Notification` multi-canal ; `lang/fr/*.php` centralisé | P2 | L |
| **P4** Site public | 13 écrans Blade + SEO + accessibilité (accueil, thématiques, ressources, violences, lois, Do Glegbe, aide, urgences, newsletter, confidentialité, connexion/inscription/mot de passe) | Chaque page identique au prototype aux 4 largeurs ; Lighthouse ≥ 90 ; sitemap, robots, JSON-LD | P3 | XL |
| **P5** Alertes, SOS, signalement | Signalement anonyme 4 étapes + code de suivi, SOS (maintien 2 s, compte à rebours, position), routage RG-04, escalade RG-05, notifications dans la requête, rejeu cron, hors connexion | Alerte créée en < 3 s, contacts prévenus, escalade à 5 min vérifiée par test de temps | P3 | L |
| **P6** API v1 & coquille Vue | Fondation API (Resources, erreurs, pagination curseur, idempotence, limitation), Inertia, gabarit membre 3 colonnes, composants `Xm*`, store Pinia, `useApi()`, `useToast()`, hors connexion | `/fil` s'affiche en Vue avec le gabarit du prototype ; `GET /api/v1/me` ; composants `Xm*` testés Vitest | P2 | L |
| **P7** Fil & sondages | Publications (texte, photo, pseudonyme, visibilité), sondages FR-SOC-02, j'aime, commentaires, partage par lien, filtres, « Charger plus », détail de publication | Parcours Playwright : publier → voter → retirer le vote → commenter | P6 | L |
| **P8** Communauté & profil | Messagerie (polling 4 s, blocage), groupes, pages, événements, forums, qui suivre / invitations, profil 5 onglets, paramètres, notifications, assistant, annuaire public, médiathèque, CCC en lecture | Chaque écran B4–B8 identique au prototype, API couverte par tests | P7 | XL |
| **P9** Espace pair & synchro | Tableau de bord pair, collecte hors ligne (règles de validation), outils CCC, alertes routées vers moi, moteur `sync/pull` + `sync/push` + conflits | Collecte sans réseau puis synchronisation sans doublon ; conflit 409 visible dans l'admin | P6, P5 | L |
| **P10** Admin socle & pilotage | Gabarit admin Livewire, `WithDataTable`, `x-admin.*`, toasts, cloche, recherche globale ; Tableau de bord, Alertes & signalements, Indicateurs, Rapports | Écrans D1 identiques au prototype ; `Livewire::test()` sur recherche, filtres, modale, suppression | P5, P3 | XL |
| **P11** Admin données | Registres, Fiches VBG/VDH, Suivi des victimes, Annuaire (routage simulé), Règles de validation | Écrans D2 ; scope pair prouvé par test | P10, P9 | L |
| **P12** Admin plateforme | Utilisateurs & rôles (matrice), Inscriptions, Contenu (ressources, thématiques, FAQ, catégories), Outils CCC, Réseau social, Modération, Assistant LLM, Passerelles | Écrans D3 (hors Newsletter, en P14) | P10, P8 | XL |
| **P13** Admin système | Hors connexion & synchro, Mises à jour & sauvegardes, Paramètres, Journal d'audit, Recherche globale | Écrans D4 ; restauration précédée d'une sauvegarde automatique (RG-13) | P10 | L |
| **P14** Communication | Templates e-mails (D6), Newsletter (D5 + pied de page public + cron), Passerelles réseaux (D4-bis : Facebook, Telegram, manuel assisté) | Campagne : inscription double opt-in → programmation → envoi par lots → ouverture / clic / désinscription ; publication Telegram réelle en recette | P3, P10 | XL |
| **P15** Mobile Flutter | 30 écrans du simulateur en 6 modules, Riverpod, go_router, Dio, Hive chiffré, SOS natif, push FCM, synchro | `flutter test` ; parcours inscription → OTP → fil ; SOS hors ligne | P6, P5, P9 | XL |
| **P16** Qualité | Accessibilité (axe, clavier, lecteur d'écran), performance (budgets NFR-01/02), SEO (audit par page), sécurité (ASVS niveau 2, en-têtes, téléversements), couverture ≥ 70 % domaine, Playwright complet | Rapports joints à la PR ; 0 violation axe sérieuse ; Lighthouse ≥ 90 | toutes | L |
| **P17** Déploiement & exploitation | Recette puis production cPanel : `.htaccess`, cron, supervision, sauvegardes, rotation `APP_KEY`, runbook, nettoyage du serveur compromis (D7) | Production en ligne, signal de vie supervisé, procédure de restauration testée | P16 | M |

Parallélisation possible dès P3 terminé : {P4, P5} · puis {P6→P7→P8, P10→P11→P12→P13, P14} · P9 après P6 et P5 · P15 dès que l'API des modules concernés est stable (P5, P6, P7, P9).

---

## 3. Détail des tâches

Chaque tâche : **quoi** · fichiers principaux · **fini quand** (critères) · tests. Codes `P<phase>-<n>`.

### P0 — Socle technique

| Code | Tâche | Fichiers | Fini quand | Statut |
|---|---|---|---|---|
| P0-01 | Créer l'application Laravel 12 dans `web/`, verrouiller PHP 8.3, `declare(strict_types=1)` par Pint, préfixe `App\` standard | `web/composer.json`, `web/pint.json` | `php artisan about` OK | fait |
| P0-02 | Paquets Composer du socle : `laravel/sanctum`, `spatie/laravel-permission`, `livewire/livewire ^3`, `inertiajs/inertia-laravel ^2`, `tightenco/ziggy`, `mews/purifier` ; dev : `pestphp/pest` (+ plugins laravel, livewire), `larastan/larastan`, `barryvdh/laravel-debugbar`. Les paquets d'export et de push (`barryvdh/laravel-dompdf`, `openspout/openspout`, `phpoffice/phpword`, `minishlink/web-push`, `laravel-notification-channels/fcm`) sont ajoutés par la phase qui les utilise (P3, P10) | `composer.json` | `composer install` et `vendor/bin/pest` passent | fait (Larastan ajouté au verrou sans installation locale : `phpstan/phpstan` est distribué sans dépôt source et l'environnement de développement actuel ne peut pas télécharger les archives GitHub ; la CI l'installe normalement) |
| P0-03 | Front : retirer Tailwind (D2), `package.json` avec Vue 3, Inertia, Pinia, vue-i18n, Bootstrap 5.3.3, Bootstrap Icons, Alpine + Focus, SweetAlert2, Chart.js, Quill, Choices, flatpickr, idb ; dev : TypeScript, vue-tsc, Vitest, Testing Library, Playwright, axe | `web/package.json`, `vite.config.js`, `tsconfig.json` | `npm run build` produit 3 paquets JS + 2 CSS | fait |
| P0-04 | Thème et jetons : copier `style.css` (jamais modifié), `tokens.css`, `social-xomiton.css`, `components.css`, `xomiton.css`, `fonts.css` + Montserrat WOFF2, images de marque, favicons, `manifest.webmanifest` ; ordre de chargement imposé | `web/resources/css/theme/*`, `web/resources/fonts/`, `web/public/assets/images/*`, `web/public/*.png` | Les polices et le placeholder du thème sont résolus au build ; aucun hexadécimal hors `tokens.css` dans les fichiers neufs | fait |
| P0-05 | Configuration cPanel (D7, ADR-11) : `.env.example` (`QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=database`, locale `fr`, fuseau `Africa/Porto-Novo`), `config/xomiton.php` (réglages métier), `config/purifier.php`, `config/permission.php`, `config/sanctum.php` | `web/.env.example`, `web/config/*.php` | `php artisan config:show xomiton` liste les réglages | fait |
| P0-06 | Intégration continue : GitHub Actions (Pint `--test`, Larastan niveau 6, Pest, vue-tsc, Vitest, `vite build`) ; `phpstan.neon` niveau 6 ; `pint.json` preset `laravel` + `declare_strict_types` | `.github/workflows/ci.yml`, `web/phpstan.neon`, `web/pint.json` | CI verte sur la branche | fait (à vérifier au premier passage de la CI) |
| P0-07 | Composants Blade `x-*` du design system : reprendre les 19 composants de `design-system/blade/components/`, les relire contre `COMPOSANTS.md` et le prototype, ajouter `x-img`, `x-badge`, `x-datatable` (admin), `x-bell`, `x-skeleton`, `x-timeline` ; test de rendu par composant | `web/resources/views/components/*.blade.php`, `tests/Feature/Components/*` | Chaque composant rendu dans `/design-system` (local) ; `<x-img>` sans `alt` lève une exception | en cours (19 composants copiés, `x-seo` créé et testé) |
| P0-08 | Gabarits : `layouts/public` (lien d'évitement, bandeau hors connexion, en-tête sticky, `main#contenu`, pied 4 colonnes + newsletter), `layouts/auth` (2 colonnes), `layouts/plain` (sobre : SOS, signalement, urgences), `layouts/admin` (sidebar, topbar, contenu, footer), `app.blade.php` (racine Inertia avec `@inertiaHead`, `@routes`) ; partials `head` (title-meta porté : canonical, robots, OG, Twitter, JSON-LD Organization + BreadcrumbList), `public-header`, `public-footer`, `admin-sidebar`, `admin-topbar` | `web/resources/views/components/layouts/*`, `partials/*` | Une page de chaque gabarit s'affiche avec le CSS du prototype aux 4 largeurs | fait (public, auth, sobre vérifiés à 360 et 1440 px ; admin vérifié en P2 avec un compte) |
| P0-09 | Routes squelette et nommage : `routes/web.php` (public + Inertia), `routes/admin.php` (préfixe `/admin`, `auth`, `can:admin.access`), `routes/api.php` (`/api/v1`), noms `module.action` ; redirections des anciennes URL du prototype (`fil.php` → `/fil`, `landing.php` → `/`) ; `PlaceholderController` pour les écrans non encore transposés | `web/routes/*.php`, `bootstrap/app.php` | `php artisan route:list` conforme à `MODULE_SPECIFICATIONS.md` | fait (67 routes, tests HTTP) |
| P0-10 | Page `/design-system` (local et recette) : catalogue vivant des composants et états (`COMPOSANTS.md` §Catalogue) | `routes/web.php`, `resources/views/design-system.blade.php` | Tous les composants et états visibles, thème sombre inclus | à faire |
| P0-11 | Documentation développeur : `web/README.md` (installation locale, commandes, structure), mise à jour de `docs/README.md` (index + ce plan) et de `.claude/skills/xomiton/SKILL.md` | `web/README.md`, `docs/README.md` | Une personne nouvelle installe et lance le projet en suivant le README | en cours (`web/README.md` écrit) |

### P1 — Base de données

Ordre des migrations : `DATABASE_ENGINEERING.md` §4 (référentiels → identité → contenus → réseau social → alertes → dossiers → pilotage et modération → passerelles → communication → plateforme). Une migration par table, `down()` fonctionnel, clés étrangères nommées, `restrictOnDelete()` pour dossiers et alertes.

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P1-01 | Référentiels : `zones` (12 départements), `communes` (77), `violence_types`, `populations` + seeders officiels | Seeder idempotent ; test `commune_in_zone` | à faire |
| P1-02 | Identité : `users` (pseudo unique, status enum, zone/commune), `profiles` (casts `encrypted` : `gender_identity`, `orientation`, `trusted_contact_phone`), `user_settings`, tables spatie, `personal_access_tokens`, `sessions`, `otp_codes`, `consents` (ligne par événement) | Test : `profiles.completion` 0–100, consents jamais mis à jour | à faire |
| P1-03 | Contenus : `thematiques` (catégorie, sections JSON, avertissement de contenu), `resources`, `faqs`, `ccc_tools`, `assistant_kb`, `bad_words`, `categories` | Seeder depuis `prototype_social/inc/x_thematiques_content.php` et `data/faqs.json` | à faire |
| P1-04 | Réseau social : `posts` (ULID public, `is_anonymous`, `tag`, `visibility`, compteurs), `post_media`, `post_likes`, `comments`, `polls`, `poll_options`, `poll_votes` (unique option × user), `follows`, `invitations`, `groups`, `group_members`, `pages`, `page_followers`, `events`, `event_rsvps`, `forums`, `forum_topics`, `forum_replies`, `conversations`, `conversation_participants`, `messages`, `blocks` | Index `posts(status, created_at)`, `messages(conversation_id, id)` | à faire |
| P1-05 | Alertes et annuaire : `directory_contacts` (index `(role, zone_id, is_active)`), `alerts` (champs chiffrés, `tracking_code` unique, `source`), `alert_notifications` (fournisseur, statut, tentatives), `alert_escalations` | Index `alerts(status, created_at)`, `alerts(type, status)` | à faire |
| P1-06 | Dossiers : `validation_rules` (catalogue), `cases` (code `VBG-0000` unique, `notes` chiffré), `case_forms` (5 sections JSON), `case_followups` (`comment` chiffré) | Test RG-06 unicité, RG-07 date ≤ aujourd'hui (règle applicative) | à faire |
| P1-07 | Pilotage et modération : `indicators` (IND-01…16), `indicator_values`, `generated_reports`, `moderation_reports` | Seeder des 16 indicateurs avec formule et source | à faire |
| P1-08 | Passerelles (D4-bis) : `social_channels` (`access_token` chiffré), `gateway_templates`, `outbound_posts`, `outbound_deliveries` (unique post × canal), `peer_rooms` | Seeder des 6 réseaux déconnectés + modèles de messages clés | à faire |
| P1-09 | Communication : `email_templates` (slug unique, `is_system`), `mail_logs`, `newsletter_subscribers` (+ tables de liaison tags / intérêts, ADR-20), `newsletter_campaigns`, `newsletter_campaign_recipients` (unique), `newsletter_clicks` (unique recipient × url_hash), `notifications` (Laravel) | Seeder des templates système (`bienvenue`, `code-verification`, `reinitialisation-mot-de-passe`, `compte-valide`, `alerte-routee`, `rappel-evenement`, `newsletter-confirmation`) | à faire |
| P1-10 | Plateforme : `settings` (clé/valeur typée, groupe), `app_versions`, `backups`, `audit_logs` (écriture seule), `sync_devices`, `sync_outbox_receipts` (clé d'idempotence), `sync_conflicts`, `jobs`, `failed_jobs`, `cache`, `cache_locks` | — | à faire |
| P1-11 | Modèles Eloquent : `$fillable`, casts (`encrypted`, enums PHP, `array`, dates), relations, scopes (`visibleTo`, `routedTo`, `active`, `published`), `HasUlid` pour les contenus publics ; enums `App\Enums\*` avec `label()` et `badge()` | Larastan niveau 6 sans erreur sur `app/Models` | à faire |
| P1-12 | Factories et seeders : factories pour chaque modèle ; `DatabaseSeeder` = référentiels + rôles/permissions + templates système + indicateurs + paramètres + 3 comptes de démonstration (`admin@xomiton.bj`, `membre@xomiton.bj`, `pair@xomiton.bj`, mots de passe du prototype en local uniquement) ; `DemoSeeder` optionnel (contenus fictifs du prototype) | `migrate:fresh --seed` < 30 s | à faire |
| P1-13 | Commande `xomiton:import-prototype <dossier>` : lit chaque collection JSON, mappe les champs (§7 du doc BD), crée zones/communes manquantes, journalise les rejets, ignore `stock*` | Import de `prototype_social/data/` sans erreur, rapport de rejets | à faire |
| P1-14 | Tâches de rétention : purge OTP (24 h), `mail_logs` (180 j), `audit_logs` (90 j paramétrable), anonymisation des comptes supprimés depuis 30 j, anonymisation des dossiers clos depuis 5 ans | Tests sur horloge simulée | à faire |

### P2 — Identité et accès

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P2-01 | Rôles et permissions spatie : seeder de la matrice §3–§4 de `ROLE_ET_PERMISSIONS.md` (`posts.create`, `alerts.take`, `cases.view_all`, `newsletter.send`, `email_templates.delete_system`, `backups.restore`, `roles.assign_admin`, `admin.access`…) ; rôle `moderateur` proposé (ADR-28) | Test : chaque rôle a exactement ses permissions | à faire |
| P2-02 | Connexion web (`/connexion`) : e-mail ou pseudonyme, message indifférencié, états `en_attente` / `suspendu` / maintenance **après** vérification, « Rester connecté·e », `next` limité aux chemins internes, limite 5/min, journal | Tests : succès, échec, 429, maintenance (RG-14) | à faire |
| P2-03 | Inscription 4 phases (`/inscription`) : phase 1 compte (jauge de solidité serveur + client), phase 2 profil (zone → commune, type membre / pair → `en_attente`), phase 3 sécurité, phase 4 consentements (cases distinctes, horodatées) ; données conservées entre étapes ; inscriptions fermées → message | Tests : création, pair en attente, consentements enregistrés, fermeture | à faire |
| P2-04 | OTP par e-mail : table `otp_codes`, service `OtpService` (6 chiffres, haché, 10 min, 5 essais, renvoi 30 s, 5 envois / h), écran unique `purpose = login \| signup \| reset`, template `code-verification` | Tests : expiration, essais, renvoi | à faire |
| P2-05 | Mot de passe oublié / réinitialisation : message identique, OTP `reset`, jeton, révocation des sessions et jetons, e-mail de confirmation | Tests : compte inexistant même réponse et même délai | à faire |
| P2-06 | Sanctum : jetons pour Flutter (`auth/login` renvoie un jeton nommé par appareil), cookie de session pour Vue, `auth/logout`, `me/sessions` (liste et révocation) | Tests API | à faire |
| P2-07 | Policies : `UserPolicy`, `PostPolicy`, `CasePolicy` (scope pair), `AlertPolicy` (routée vers moi), `DirectoryContactPolicy`, `EmailTemplatePolicy` (`deleteSystem`), `BackupPolicy` (`restore`, `delete`), `CampaignPolicy`, `SocialChannelPolicy`… ; `Gate::before` superadmin | Test « autorisé » et « refusé 403 » par rôle et par route protégée (§6 du doc rôles) | à faire |
| P2-08 | Sécurité HTTP : middleware d'en-têtes (CSP avec nonce, HSTS, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` géolocalisation uniquement sur `/sos`), `RateLimiter` nommés (`login`, `otp`, `report`, `newsletter`), cookies `Secure`/`SameSite` | Tests d'en-têtes | à faire |
| P2-09 | Journal d'audit : service `Audit::log(action, subject, detail)`, écouteur sur connexion / déconnexion / échec, masquage des champs sensibles | Test : aucune route de modification de `audit_logs` | à faire |
| P2-10 | Validation des pair·es (FR-AUTH-05) côté service : `ValidatePeerAccount` envoie `compte-valide` et notifie ; branché sur l'écran admin en P12 | Test | à faire |
| P2-11 | Mode maintenance applicatif (RG-14) : paramètre `maintenance`, middleware qui laisse passer admin et superadmin, page sobre | Test | à faire |

### P3 — Noyau transverse

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P3-01 | `EmailTemplateService::send($slug, $to, $vars)` + `TemplateMail` : variables `{{ }}` échappées, mise en page Xomiton, SMTP depuis `settings` (ou `.env`), repli boîte d'envoi (`mail_logs.transport = outbox`), journal `mail_logs`, avertissement variables inconnues | Tests avec `Mail::fake()` et transport outbox | à faire |
| P3-02 | Notifications Laravel : canaux `database`, `mail` (via templates), `webpush`, `fcm`, `sms` (interface `SmsSender`, pilote `log`) ; classes du catalogue §2 (`AccountValidated`, `AlertRouted`, `SosTriggered`, `SosUnacknowledged`, `NewMessage`, `ReactionGrouped`, `EventReminder`, `PollEnded`, `ContentReported`, `SyncConflict`) ; discrétion (texte neutre si « masquer le contenu sensible ») ; préférences par catégorie (sécurité et SOS non désactivables) | Tests par canal | à faire |
| P3-03 | Paramètres : modèle `Setting` typé (bool, int, string, json, secret chiffré), cache 24 h, helper `setting()`, groupes (général, sécurité, SMTP, identité visuelle, newsletter, synchro) | Test cache invalidé à l'écriture | à faire |
| P3-04 | Téléversements : service `MediaUploader` (liste blanche, poids, ré-encodage GD/Imagick, suppression EXIF, WebP, stockage `storage/app`, URL signées), disque `public` uniquement pour les visuels non sensibles | Tests : EXIF retiré, type refusé | à faire |
| P3-05 | Internationalisation : `lang/fr/{auth,validation,microcopy,menus,buttons,errors,notifications,admin,feed,…}.php` depuis `MICROCOPY.md`, `CTA_LIBRARY.md`, `VOICE_AND_TONE.md` ; structure `lang/fon`, `lang/yo`, `lang/en` ; export JSON des messages pour Vue (`php artisan lang:export`) | Aucun texte en dur dans les vues neuves (test par grep en CI) | à faire |
| P3-06 | Référentiels en cache (zones, communes, catégories, types de violence, populations) + ressource API `zones` | Test invalidation | à faire |
| P3-07 | Tâches planifiées (`routes/console.php`) : `alerts:escalate`, `notifications:retry`, `newsletter:process`, `gateways:publish`, `heartbeat:ping`, `backups:run` (02:00), `logs:purge`, `otp:purge`, `queue:work --stop-when-empty --max-time=50` ; chaque commande idempotente | `schedule:list` conforme à `SOFTWARE_ARCHITECTURE.md` §6 | à faire |
| P3-08 | Événements de domaine (`AlertCreated`, `SosTriggered`, `PollVoted`, `CampaignSent`, `UserRegistered`, `PeerValidated`…) et écouteurs (notifications, audit) | Tests unitaires sans base | à faire |

### P4 — Site public (Blade)

Référence écran par écran : `MODULE_SPECIFICATIONS.md` §A, `FRONT_END_SECTION_ORDER_INSTRUCTION.md`, `HOME_PAGE_COPY.md`, `SEO_CONTENT_GUIDE_INSTRUCTION.md`, `FRONT_END_SEO_PERFECT_AUDIT.md`. Chaque page : contrôleur invocable, vue Blade, `x-seo`, données structurées, test HTTP 200 + snapshot des titres.

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P4-01 | Accueil `/` : 11 sections dans l'ordre, textes de `HOME_PAGE_COPY.md`, FAQ depuis la base (`FAQPage`), CTA selon connexion, hero fluide | Identique au prototype `index.php` aux 4 largeurs ; Lighthouse ≥ 90 | à faire |
| P4-02 | Thématiques `/thematiques`, `/thematiques/{slug}` : filtres par catégorie, fiche (fil d'Ariane, accordéon, avertissement, contacts liés, « J'ai besoin d'aide », liées) | — | à faire |
| P4-03 | Ressources `/ressources`, `/ressources/{slug}` : recherche, filtres type / catégorie, « Charger plus » (pagination), téléchargement signé | — | à faire |
| P4-04 | Comprendre les violences `/violences`, Lois et codes `/lois`, Do Glegbe `/do-glegbe` (fiches, quiz Alpine) | — | à faire |
| P4-05 | Aide `/aide`, `/aide/{slug}` : FAQ par catégorie, recherche | — | à faire |
| P4-06 | Contacts d'urgence `/urgences` (gabarit sobre) : numéros cliquables, plan de sécurité (cases en `localStorage`), cache hors ligne | Fonctionne hors connexion | à faire |
| P4-07 | Newsletter `/newsletter` : inscription (pied de page, pot de miel, consentement, 1 / 20 s), confirmation double opt-in, désinscription un clic + motif, pixel d'ouverture, redirection de clic signée HMAC | Tests : double opt-in, lien signé invalide → 403 | à faire |
| P4-08 | Confidentialité et conditions `/confidentialite` (ancres `#charte`, `#confidentialite`, `#conditions`) | — | à faire |
| P4-09 | Écrans d'authentification (gabarit auth) : connexion, inscription 4 phases (stepper, jauge), OTP (6 cases, collage), mot de passe oublié, nouveau mot de passe ; branchés sur P2 | Identiques à `sign-in.php`, `sign-up.php`, `forgot-password.php` | à faire |
| P4-10 | Actualités `/actualites`, `/actualites/{slug}` (`blog.php`, `blog-details.php` du prototype, présents dans l'en-tête public) ; décision à confirmer : module « Actualités » = ressources de type article ou table dédiée | Conflit consigné §7 | à faire |
| P4-11 | SEO technique : `x-seo` (title ≤ 60, description ≤ 160, canonical, robots, OG, Twitter, JSON-LD), `sitemap.xml` (spatie), `robots.txt`, redirections 301 des URL du prototype, page 404 (`error-404.php`) et 503 sobres | Audit `FRONT_END_SEO_PERFECT_AUDIT.md` vert par page | à faire |
| P4-12 | Performance public : images WebP/AVIF + `srcset` via `x-img`, `loading="lazy"` sauf LCP, polices `font-display: swap`, CSS critique, budget < 500 Ko / JS < 150 Ko | NFR-01, NFR-02 mesurés | à faire |

### P5 — Alertes, SOS, signalement

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P5-01 | Domaine `Alerts` : entités, `AlertRouter` (RG-04 : rôle + zone → point focal zone → point focal national → premier actif), `EscalationPolicy` (RG-05), `TrackingCode`, événements | Tests unitaires identiques aux cas de `route_alert()` du prototype | à faire |
| P5-02 | Cas d'usage `CreateReport` (dénonciation / besoin), `TriggerSos`, `AcknowledgeAlert`, `ChangeAlertStatus`, `ReassignAlert`, `AddInternalNote`, `CancelSos`, `MarkSafe` ; `Idempotency-Key` obligatoire | Tests 409 sur rejeu | à faire |
| P5-03 | Notifications dans la requête : `Http::pool` vers push / SMS / e-mail, 3 s max par fournisseur, échecs consignés dans `alert_notifications`, rejeu `notifications:retry` | Test : fournisseur lent → requête < 4 s, rejeu planifié | à faire |
| P5-04 | Public `/signaler` (gabarit sobre) : encadré anonymat, stepper 4 étapes, une question par écran, récapitulatif, code de suivi, consultation du statut par code ; limite 10 / h / IP ; hors connexion (boîte d'envoi IndexedDB) | Parcours Playwright anonyme | à faire |
| P5-05 | Public `/sos` : bouton 96 px, maintien 2 s, compte à rebours 5 s annulable, position (si accord, `Permissions-Policy`), confirmation et personnes prévenues, appel du contact principal, « Je suis en sécurité » ; accessible clavier ; hors ligne → appel direct des numéros en cache | FR-ALT-03 ; test clavier | à faire |
| P5-06 | API `signalements/`, `sos/` (`cancel`, `safe`), `urgences/`, `zones/`, suivi par code | Tests API 201 / 422 / 429 / 409 | à faire |
| P5-07 | `alerts:escalate` (cron) : escalade des SOS sans accusé à 5 min ± 1, notification point focal national, journal | Test avec `travel()` | à faire |
| P5-08 | Bouton SOS flottant (`xm-sos-fab`, z-index 1060) sur les espaces connectés, composant `x-sos-button` / `XmSosButton` | Jamais masqué par une modale ni un toast | à faire |

### P6 — API v1 et coquille Vue (membre et pair)

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P6-01 | Fondation API : `ApiController` de base, Resources `{data, meta, links}`, gestionnaire d'erreurs `{message, errors}` (401/403/404/409/422/429), pagination par curseur (fil, messages) et par page, middleware `Idempotency-Key`, limites nommées, versionnement `/api/v1` | Tests du format d'erreur et de la pagination | à faire |
| P6-02 | Inertia : `HandleInertiaRequests` (props partagées : utilisateur·rice, permissions `can`, compteurs, messages flash, `lang`), racine `app.blade.php`, Ziggy pour les routes | `/fil` répond en Inertia | à faire |
| P6-03 | Gabarit membre Vue : `XmHeader` (recherche, SOS, messages, réglages, cloche avec polling 30 s, avatar), `XmSidenav` (carte profil avec statistiques réelles, groupes de liens, offcanvas < 992 px), `XmRightColumn` (Qui suivre, Événements, Urgence), modales globales | Identique à `partials/header.php`, `sidenav.php`, `profile-rightsidebar.php` | à faire |
| P6-04 | Composants `Xm*` (même HTML que `x-*`) : `XmButton`, `XmField`, `XmCard`, `XmModal` (focus piégé, pied fixe), `XmStat`, `XmBadge`, `XmAvatar`, `XmEmptyState`, `XmSkeleton`, `XmStepper`, `XmTabs`, `XmSosButton`, `XmToast` + `useToast()`, `XmImg` ; tests Vitest | Rendu comparé au Blade (test de snapshot HTML commun) | à faire |
| P6-05 | Infrastructure Vue : `useApi()` (CSRF, 401 → connexion, 419 → rafraîchir, 422 → erreurs de champ, 429 → toast), stores Pinia (`session`, `notifications`, `drafts`, `outbox`), IndexedDB (`idb`) pour brouillons et boîte d'envoi, bandeau hors connexion, `vue-i18n` alimenté par `lang/` | Tests Vitest des stores | à faire |
| P6-06 | API `me/` : `profile` (GET, PATCH), `settings`, `security`, `newsletter`, `sessions`, `export` (RGPD, job), `DELETE` compte (effacement différé 30 j) | Tests | à faire |
| P6-07 | API `notifications/` : liste paginée, `read-all`, `unread-count` | Tests | à faire |

### P7 — Fil et sondages

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P7-01 | Domaine `Feed` : règles de visibilité (`public`, `followers`, `me`), filtre de mots (`bad_words`), RG-16 masquage après 3 signalements (paramétrable), compteurs dénormalisés | Tests unitaires | à faire |
| P7-02 | API `feed/`, `posts/` (CRUD, ULID public), `likes`, `comments` (paginés), `poll/vote` (POST choices[], DELETE), partage par lien, signaler, masquer, enregistrer ; votes individuels jamais exposés (RG-09) | Tests 201 / 422 / 403 ; test d'anonymat des votes | à faire |
| P7-03 | Page `/fil` : accès rapides, « Créer une publication » (pastilles Photo · Événement · Sondage · Violences · Signaler), À la une (thématiques), filtres (Tout · Témoignages · Questions · Ressources · Événements · Sondages), liste + « Charger plus », colonne droite | Identique à `fil.php` | à faire |
| P7-04 | Modale « Créer une publication » et « Créer un sondage » (question 5–200 avec compteur, 2–6 réponses, texte, image 5 Mo avec aperçu, durée, visibilité, interrupteurs) ; brouillon IndexedDB | Identique à `partials/modals.php` | à faire |
| P7-05 | Carte publication `XmPost` (port de `x_render_post`) : en-tête (avatar, pseudo, badge, temps), corps, média, sondage (`XmPoll` : vote sans rechargement, barres %, réponse en tête, coche, total, retrait, terminé), actions (j'aime optimiste, commenter, partager), menu | Parcours Playwright publier → voter → retirer | à faire |
| P7-06 | Détail `/fil/{ulid}` : carte complète, commentaires paginés, champ de commentaire | — | à faire |
| P7-07 | Tâche `polls:close` + notification « Sondage terminé » | Test | à faire |

### P8 — Communauté et profil

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P8-01 | Messagerie : API `conversations/` (liste, création privée et de groupe, messages paginés par curseur, envoi, lecture, blocage, signalement), page `/messages` (liste avec recherche et non lus, conversation, bulles, polling 4 s uniquement si ouverte, état hors ligne), notification « Nouveau message » discrète | Identique à `messaging.php` | à faire |
| P8-02 | Groupes `/groupes`, `/groupes/{ulid}` : Mes groupes / Découvrir, rejoindre (privé → demande), membres, publications du groupe ; groupes privés invisibles aux non-membres (FR-SOC-05) | Test de visibilité | à faire |
| P8-03 | Pages `/pages`, `/pages/creer`, `/pages/{ulid}` : pages d'organisations partenaires, suivre | — | à faire |
| P8-04 | Événements `/evenements`, `/evenements/{ulid}` : liste, détail, « Je participe », rappel la veille (`events:remind`), modale de création | — | à faire |
| P8-05 | Forums `/forums`, `/forums/{ulid}` : sujets, réponses, vues | — | à faire |
| P8-06 | Qui suivre `/qui-suivre` : suggestions, suivre, invitations (envoyer, accepter, refuser) | — | à faire |
| P8-07 | Profil `/profil` (et `/membres/{pseudo}`) : en-tête (couverture, avatar, pseudo, zone, statistiques, complétion), onglets Activité · À propos · Connexions · Médias · Événements ; nom réel jamais public (RG-02) | Test RG-02 | à faire |
| P8-08 | Profil progressif : bandeau « Complète ton profil », un groupe à la fois, « Pourquoi on demande ça ? », consentement `sensitive_data` requis pour orientation et identité de genre | Test consentement | à faire |
| P8-09 | Paramètres `/parametres` : Compte · Apparence (clair, sombre, auto) · Notifications (préférences par catégorie) · Sécurité (masquer le contenu sensible, code PIN, sessions) · Confidentialité (export, suppression) · Newsletter ; *sans* sortie rapide (D8) | — | à faire |
| P8-10 | Notifications `/notifications` (liste, tout marquer lu), cloche (regroupement des réactions) | — | à faire |
| P8-11 | Assistant `/assistant` : conversation avec base de connaissances (`assistant_kb`), garde-fous de crise (mots clés → numéros d'urgence, SOS, escalade vers un humain), fournisseur LLM derrière une interface (`null` par défaut = réponses de la base) | Test garde-fous | à faire |
| P8-12 | Annuaire public `/annuaire` (contacts publics, filtres rôle / zone), médiathèque `/medias` (`albums.php`), outils CCC en lecture (`/ccc`, `/boites-a-images`, `/bd-ssr`, `/manuel-pair`), visionneuse BD (`x-comic-viewer`), lecteur Plyr | — | à faire |
| P8-13 | Recherche membre (en-tête) : ressources et contacts | — | à faire |

### P9 — Espace pair éducateur·rice et synchronisation

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P9-01 | Domaine `Cases` : `CaseCode` (RG-06), règles RG-07, RG-08, catalogue de règles de validation (`email`, `phone_bj`, `date_not_future`, `age_range`, `min_len_20`, `positive_int`, `commune_in_zone`, `case_code`, `password_strong`) activables, plausibilité, doublons suspects | Tests unitaires | à faire |
| P9-02 | API `pair/` : `cases` (CRUD scope `visibleTo`), `followups`, `alerts` (routées vers moi, accuser réception), règles actives | Test : un pair ne voit pas le dossier d'un autre | à faire |
| P9-03 | Tableau de bord `/espace-pair` : KPI (collectés, ce mois, en cours, clos), tâches, raccourcis, alertes routées vers moi (polling 10 s si de garde) | Identique à `espace-pair.php` | à faire |
| P9-04 | Collecte `/collecte` : formulaire hors ligne (zone → commune en cascade, population, sexe, âge, type, orientation avec consentement), règles actives appliquées côté client et serveur, file d'attente visible, avertissement « jamais de nom ni de coordonnées » | Collecte sans réseau puis envoi | à faire |
| P9-05 | Outils CCC `/ccc` en mode présentation (boîtes à images, BD SSR, manuel, lois) | — | à faire |
| P9-06 | Moteur de synchronisation : `POST sync/push` (lots de 50, `Idempotency-Key = uuid`, reçus `sync_outbox_receipts`), `GET sync/pull?since=` (annuaire, urgences, thématiques, dossiers du pair, curseur), résolution par champ (serveur gagne statut / attribution, appareil gagne notes de l'auteur, sinon 409 → `sync_conflicts`), `sync_devices` (dernier contact) | Tests : rejeu sans doublon, conflit 409 | à faire |
| P9-07 | Boîte d'envoi web (IndexedDB) : signalement, SOS, collecte ; envoi au retour du réseau et toutes les 5 min ; SOS n'attend jamais si le réseau est là | Test Vitest du store `outbox` | à faire |

### P10 — Administration : socle et pilotage (Livewire)

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P10-01 | Gabarit admin : sidebar (menu de `UI_UX_DESIGN_SYSTEM.md` §2 avec compteurs alertes nouvelles / comptes en attente), topbar (titre, fil d'Ariane, recherche globale Ctrl K, état en ligne, file de synchro, actions de page, plein écran, cloche, avatar), footer version ; `< 992 px` repliée | Identique à `admin-top.php` / `admin-bottom.php` | à faire |
| P10-02 | Trait `WithDataTable` + composants `x-admin.kpi` (accordéon < 768 px), `x-admin.filters` (URL `?q=&st=`), `x-admin.table` (cartes < 768 px, `aria-sort`, squelettes `wire:loading`), `x-admin.pagination` (« Affichage de x à y sur n »), `x-admin.bulk-bar`, `x-admin.modal` (montée en permanence, `wire:model.blur`, pied fixe), `x-admin.empty`, export CSV (BOM, `;`) | `Livewire::test()` du trait sur un écran témoin | à faire |
| P10-03 | Toasts SweetAlert2 (`$this->dispatch('toast')`), confirmations (`wire:confirm`, modale rouge, saisie de confirmation), cloche admin (onglets Non lus · Lues · Toutes, polling 30 s, sources : alertes, inscriptions, modération, conflits) | — | à faire |
| P10-04 | Tableau de bord `/admin` : 7 KPI, graphiques Chart.js (cas par mois, type, département, population, sexe × âge ; tableau de données accessible sous chaque graphique), dernières alertes (prendre en charge), SOS actifs (carte schématique + Maps), raccourcis, état de la plateforme | Identique à `admin/index.php` | à faire |
| P10-05 | Alertes & signalements `/admin/alertes` : onglets par type, colonnes, filtres, prendre en charge, changer de statut, réattribuer, note interne, export CSV, polling 10 s pour les agent·es de garde | Identique à `alertes.php` ; tests Livewire | à faire |
| P10-06 | Indicateurs `/admin/indicateurs` : IND-01…16 calculés (service `IndicatorCalculator`, IND-09 = taux d'ouverture newsletter), valeur, cible, tendance, filtres période et zone, modale « Nouvel indicateur personnalisé » | Tests unitaires des formules | à faire |
| P10-07 | Rapports `/admin/rapports` : assistant (type, période, dimensions, format PDF / Excel / Word, destinataires), job `GenerateReport` par morceaux, historique, téléchargement signé | Génération < 30 s par morceau | à faire |
| P10-08 | Recherche globale `/admin/recherche` (Ctrl K) : résultats groupés (utilisateur·rices, dossiers, alertes, annuaire, thématiques, réseau, journal), scopes respectés | — | à faire |

### P11 — Administration : gestion des données

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P11-01 | Registres `/admin/registres` : colonnes, filtres, modale « Nouveau dossier » (avertissement), export CSV scopé | Identique à `registres.php` | à faire |
| P11-02 | Fiches VBG / VDH `/admin/fiches` : 5 sections (identification pseudonymisée, faits, services reçus, orientation vers l'annuaire, consentement), impression | — | à faire |
| P11-03 | Suivi des victimes `/admin/suivi` : frise par dossier (`x-timeline`), « Ajouter une étape », liste des étapes, prochaine échéance | — | à faire |
| P11-04 | Annuaire `/admin/annuaire` : KPI, liste, filtres, import / export CSV, modale contact (lien éventuel vers un compte), **aperçu du routage** (simulateur besoin × zone, tableau département × besoin, badge « repli ») | Simulateur identique à `route_alert()` | à faire |
| P11-05 | Règles de validation `/admin/validation` : KPI, catalogue (activer / désactiver, exemples), plausibilité, doublons suspects, rapport de qualité | — | à faire |

### P12 — Administration : plateforme

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P12-01 | Utilisateurs & rôles `/admin/utilisateurs` : KPI, liste filtrable, modale utilisateur·rice, validation des comptes en attente (P2-10), suspension, **matrice des permissions** (lecture, édition par superadmin, RG-15 journalisée) | Tests 403 pour admin sur rôles d'administration | à faire |
| P12-02 | Inscriptions `/admin/inscriptions` : KPI, options (ouvertes, validation manuelle), inscriptions par semaine, demandes en attente, 10 dernières | — | à faire |
| P12-03 | Contenu `/admin/contenu` : onglets Ressources · Thématiques · FAQ · Catégories ; modales ; publier / brouillon ; téléversement de fichiers et images (P3-04) | — | à faire |
| P12-04 | Outils CCC `/admin/ccc` : onglets Manuel · Boîtes à images · BD SSR · Lois ; KPI ; remplacer une image | — | à faire |
| P12-05 | Réseau social `/admin/reseau` : onglets Groupes · Pages · Événements · Forums · Publications ; KPI ; masquer, supprimer, voir | — | à faire |
| P12-06 | Modération `/admin/moderation` : KPI, file (contenus signalés, décisions journalisées), mots interdits, charte de bienveillance, suspensions | Test RG-16 | à faire |
| P12-07 | Assistant LLM `/admin/assistant` : base de connaissances (modale), prompt système, garde-fous et escalade, conversations signalées, statistiques | — | à faire |
| P12-08 | Passerelles `/admin/passerelles` (D4-bis) : cartes des 6 réseaux (connecter / déconnecter le compte de l'organisation), file de publication (BD SSR, message clé, personnalisé ≤ 500, réseaux, date), statuts, marquer publié, retirer, modèles de messages clés, salons entre pairs ; connecteurs en P14 | Identique à `passerelles.php` | à faire |

### P13 — Administration : système

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P13-01 | Hors connexion & synchro `/admin/synchronisation` : KPI, appareils & pairs, résolution des conflits (valeur serveur / appareil / fusion, choix journalisé), journal, paramètres, cache local | Test : résolution tracée | à faire |
| P13-02 | Mises à jour & optimisation `/admin/mises-a-jour` : KPI, historique des versions, publier ou planifier, fenêtre de maintenance (RG-14), optimisation (cache, images, journaux, index, service worker), pages les plus utilisées, adoption | — | à faire |
| P13-03 | **Sauvegardes** (ADR-22) : service `BackupService` (export par table en JSON par morceaux, ZIP chiffré, `backups`), « Sauvegarder maintenant », liste, aperçu (KPI, tableau par collection avec écart, extrait des 5 premiers enregistrements masqués), téléchargement, suppression (superadmin), restauration par saisie de « RESTAURER » précédée d'une sauvegarde automatique (RG-13), `backups:run` nocturne vers stockage externe | Test RG-13 ; restauration vérifiée sur base de recette | à faire |
| P13-04 | Paramètres `/admin/parametres` : généraux, sécurité (session, 2FA à confirmer), identité visuelle, informations système ; superadmin seul pour SMTP et purge | — | à faire |
| P13-05 | Journal d'audit `/admin/journal` : KPI, filtres, export ; lecture seule | — | à faire |

### P14 — Communication

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P14-01 | Templates e-mails `/admin/emails` : KPI, liste (recherche nom / slug, filtre catégorie), modale XL (slug généré et verrouillé si système, Quill + HTML, variables cliquables, aperçu bureau / mobile avec données fictives, envoi de test), suppression sécurisée par slug (RG-10), carte SMTP (mot de passe inchangé si vide), derniers envois | Identique à `emails.php` ; tests RG-10 | à faire |
| P14-02 | Newsletter `/admin/newsletter` : KPI permanents ; onglet Statistiques (12 mois, par statut, par campagne, liens cliqués, intérêts) ; onglet Abonné·es (liste, filtres, actions groupées, import CSV avec attestation de consentement, export, modales RGPD) ; onglet Campagnes (liste, modale : objet, expéditeur, réponse, aperçu, Quill, variables, segment avec comptage en direct, test, mode d'envoi) | Identique à `newsletter.php` ; RG-11, RG-12 testées | à faire |
| P14-03 | Envoi : job `SendCampaignBatch` (200 / min, personnalisation, liens de suivi HMAC, pixel, `List-Unsubscribe`, `List-Unsubscribe-Post`), `newsletter:process`, gestion des rebonds, « Traiter la file d'envoi » | Test de bout en bout en recette | à faire |
| P14-04 | Connecteurs passerelles (ADR-15) : interface `SocialPublisher`, `FacebookPublisher` (Graph API), `TelegramPublisher` (Bot API), `TikTokPublisher` (si API de contenu), `ManualPublisher` (texte et visuel prêts à copier), jetons chiffrés, `gateways:publish` (lots, rejeu, statut `manuel`), journal de chaque publication ; aucune donnée de membre | Publication Telegram réelle en recette | à faire |

### P15 — Application mobile Flutter (`mobile/`)

Structure `lib/core` + `lib/features/<feature>/{data, domain, presentation}` (`SOFTWARE_ARCHITECTURE.md` §8), Riverpod, go_router, Dio, Hive chiffré, jetons depuis `design-system/tokens/design-tokens.json`.

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P15-01 | Socle : projet Flutter 3, thème depuis les jetons (Montserrat, palette, sombre), routeur avec redirection `onboardingProvider.seen` + session, client Dio (`/api/v1`, jeton Sanctum, 401), Hive chiffré, i18n fr, CI `flutter analyze` + `flutter test` | Splash → connexion | à faire |
| P15-02 | `auth` : splash et carrousel, connexion, inscription 4 phases, OTP (`purpose`), mot de passe oublié, nouveau mot de passe | Parcours testé | à faire |
| P15-03 | `home` : tableau de bord (membre / pair), thématiques, fiche, ressources (hors ligne), notifications | — | à faire |
| P15-04 | `sos` : signaler et suivre, SOS natif (maintien, compte à rebours, position, appel), alerte envoyée, signalement 4 étapes, contacts d'urgence en cache ; boîte d'envoi Hive, `sync/push` au retour du réseau (FR-ALT-06) | Aucune perte après redémarrage | à faire |
| P15-05 | `feed` : fil, publication et commentaires, créer une publication, créer un sondage (vote optimiste) | — | à faire |
| P15-06 | `community` : messages, conversation (polling), groupes, événements, assistant | — | à faire |
| P15-07 | `profile` : mon profil, profil progressif, paramètres, sécurité et confidentialité (mode discret, PIN), newsletter | — | à faire |
| P15-08 | Push FCM (charge utile sans donnée sensible, aperçu masqué en mode discret), barre d'onglets 5 entrées avec SOS au centre, mode discret | — | à faire |
| P15-09 | Espace pair mobile : collecte hors ligne, dossiers, alertes routées, synchronisation complète (`sync/pull`) | — | à faire |

### P16 — Qualité transverse

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P16-01 | Accessibilité : axe-core dans Playwright sur chaque page, parcours clavier, lecteur d'écran (NVDA / VoiceOver) sur les parcours critiques, contraste, mouvement réduit, 3.3.8 authentification accessible | 0 violation sérieuse ou critique | à faire |
| P16-02 | Performance : budgets NFR-01 / NFR-02 mesurés (Lighthouse CI sur les pages publiques), `preventLazyLoading()` hors production, cache des référentiels, images optimisées, découpage `admin.js` par page | Rapports joints | à faire |
| P16-03 | Sécurité : revue ASVS niveau 2 (en-têtes, sessions, téléversements, chiffrement, limitation, énumération), `composer audit`, `npm audit`, tests de rejeu d'idempotence, revue des journaux (aucune donnée sensible) | Checklist signée | à faire |
| P16-04 | Tests : couverture ≥ 70 % `app/Domain` + `app/Application`, tests 403 pour toutes les routes protégées, Playwright (inscription → OTP → fil ; signalement anonyme ; SOS ; campagne newsletter ; sauvegarde / restauration) | CI complète verte | à faire |
| P16-05 | Vérification « à l'identique » : captures côte à côte prototype / implémentation aux 4 largeurs pour chaque écran, écarts consignés dans `DECISIONS.md` | Dossier de captures par PR | à faire |
| P16-06 | Textes : revue microcopy et écriture inclusive (D6, zone ouverte) de toutes les chaînes `lang/` | Revue éditoriale | à faire |

### P17 — Déploiement et exploitation (cPanel, D7)

| Code | Tâche | Fini quand | Statut |
|---|---|---|---|
| P17-01 | Assainir l'hébergement compromis : supprimer `cronjdfp0i4.php` et `f.php`, changer mots de passe cPanel / FTP / base, régénérer `APP_KEY` (plan de ré-encryption), analyser les journaux d'accès | Rapport d'incident | à faire |
| P17-02 | Recette : sous-domaine pointé sur `web/public`, PHP 8.3 (MultiPHP), `display_errors Off`, `memory_limit ≥ 256M`, `.htaccess` (dotfiles, `storage`, `vendor`, `composer.*` bloqués), build Vite local déployé, `composer install --no-dev --optimize-autoloader`, `config:cache`, `route:cache`, `view:cache`, `event:cache` | Site en recette | à faire |
| P17-03 | Cron : `schedule:run` et `queue:work --stop-when-empty --max-time=50` chaque minute ; vérification « Entry Processes » ; `heartbeat:ping` vers Healthchecks.io / UptimeRobot avec alerte à 3 min (NFR-03) | Alerte reçue lors d'un arrêt simulé du cron | à faire |
| P17-04 | Sauvegardes : `backups:run` nocturne + `mysqldump` cPanel vers stockage externe chiffré ; test de restauration documenté | Restauration réussie en recette | à faire |
| P17-05 | Runbook (`docs/RUNBOOK.md`) : déploiement, rotation des clés, gestion d'un SOS non escaladé, restauration, maintenance, rotation des jetons des passerelles, procédure en cas d'incident | Relu par la coordination | à faire |
| P17-06 | Production : bascule, DNS, HTTPS, supervision, premier `xomiton:import-prototype` si des données réelles existent, formation de l'équipe | Production en ligne | à faire |

---

## 4. Ordre d'exécution recommandé (jalons)

| Jalon | Contenu | Valeur livrée |
|---|---|---|
| **J1 — Socle prêt** | P0, P1, P2, P3 | Projet installable, base complète, comptes et rôles, e-mails, notifications ; rien de visible pour le public mais tout est testable. |
| **J2 — Protéger** | P5, P4 (urgences, SOS, signalement, connexion), P10-01…05 | Un·e visiteur·se peut signaler et déclencher un SOS ; l'équipe traite les alertes dans l'admin ; escalade et supervision en place. **Premier déploiement en recette.** |
| **J3 — Informer** | Reste de P4, P12-03, P12-04 | Site public complet, SEO, contenus gérés depuis l'admin. |
| **J4 — Communauté** | P6, P7, P8, P12-05, P12-06 | Espace membre complet en Vue, modération. |
| **J5 — Terrain** | P9, P11, P13-01 | Pair·es : collecte hors ligne, dossiers, suivi, synchronisation. |
| **J6 — Piloter et communiquer** | P10-06…08, P12-01, P12-02, P12-07, P12-08, P13-02…05, P14 | Indicateurs, rapports, newsletter, templates, passerelles, sauvegardes. |
| **J7 — Mobile** | P15 | Application Flutter alignée sur l'API. |
| **J8 — Production** | P16, P17 | Qualité vérifiée, production cPanel, exploitation. |

---

## 5. Correspondance prototype → cible

| Prototype (`prototype_social/`) | Cible | Phase |
|---|---|---|
| `index.php`, `thematiques.php`, `thematique.php`, `ressources.php`, `ressource.php`, `violences.php`, `lois-benin.php`, `do-glegbe.php`, `help.php`, `help-details.php`, `urgences.php`, `newsletter.php`, `privacy-and-terms.php`, `blog.php`, `blog-details.php`, `error-404.php` | Blade (`routes/web.php`) | P4 |
| `sign-in.php`, `sign-up.php`, `forgot-password.php` | Blade gabarit auth + P2 | P4-09 |
| `signalement.php`, `sos.php` | Blade gabarit sobre + API | P5 |
| `fil.php`, `post-create.php`, `post-details.php`, `messaging.php`, `groups.php`, `group-details.php`, `pages.php`, `create-page.php`, `events.php`, `events-2.php`, `event-details.php`, `event-details-2.php`, `forums.php`, `forum-topic.php`, `who-to-follow.php`, `my-profile*.php`, `settings.php`, `notifications.php`, `assistant.php`, `annuaire.php`, `albums.php`, `ccc.php`, `boites-a-images.php`, `bd-ssr.php`, `manuel-pair-animateur.php` | Vue + Inertia (`resources/js/Pages/**`) | P7, P8 |
| `espace-pair.php`, `collecte.php` | Vue + Inertia | P9 |
| `admin/*.php` (24 écrans) | Livewire (`app/Livewire/Admin/**`, `routes/admin.php`) | P10–P14 |
| `api.php`, `cron/newsletter.php` | `routes/api.php`, `routes/console.php` | P6, P3-07 |
| `inc/x_*.php` (règles métier) | `app/Domain/**`, `app/Application/**` | P1–P14 |
| `data/*.json` | Migrations + `xomiton:import-prototype` | P1 |
| `prototype_mobile/` (30 écrans) | `mobile/lib/features/**` | P15 |

La table complète page → module → technologie est maintenue dans `MODULE_SPECIFICATIONS.md` ; ce tableau indique seulement la phase.

---

## 6. Risques et parades

| Risque | Impact | Parade |
|---|---|---|
| Cron arrêté sur cPanel → SOS non escaladés | Critique (sécurité des personnes) | `heartbeat:ping` supervisé (P17-03), SMS de secours, numéros d'urgence en cache, envoi SOS dans la requête (P5-03) |
| Limites « Entry Processes » dépassées par le polling | Indisponibilité | Intervalles longs (30 s cloche, 4 s seulement en conversation ouverte), réponses légères, cache des pages publiques ; bascule VPS prévue par ADR-11 |
| Fidélité au prototype difficile à garantir sur 100+ écrans | Rejets en revue | Composants `x-*` / `Xm*` copiés du prototype (P0-07, P6-04), captures aux 4 largeurs à chaque PR (P16-05) |
| Deux technologies d'interface (Vue, Livewire) | Charge de maintenance | Conventions `DEVELOPMENT_STANDARDS.md` §3–§4, même HTML, mêmes jetons, revue croisée |
| Chiffrement des champs sensibles et rotation d'`APP_KEY` | Perte de données si mal géré | Commande de ré-encryption (P1-02), procédure dans le runbook (P17-05), sauvegarde avant rotation |
| API TikTok indisponible | Fonction partielle | `ManualPublisher` par défaut (D4-bis) |
| Hébergement compromis (porte dérobée) | Fuite de données | P17-01 avant toute mise en ligne |
| Réseau mobile faible | Abandon des parcours | Budgets de poids (P4-12), hors connexion (P6-05, P9-07), mises à jour optimistes |

---

## 7. Points à trancher (à consigner dans `DECISIONS.md`)

| # | Sujet | Options | Recommandation |
|---|---|---|---|
| Q1 | Module « Actualités » (`blog.php`, `blog-details.php`, lien « Actualités » de l'en-tête public) absent de `MODULE_SPECIFICATIONS.md` | a) ressources de type « article » ; b) table `articles` dédiée ; c) retirer le lien | a) : réutilise l'édition de contenu de l'admin, un seul module |
| Q2 | Rôle `moderateur` (« proposé, à valider ») | a) valider ; b) fusionner dans `admin` | a) (ADR-28 le prépare sans l'exposer) |
| Q3 | RG-16 (masquage après 3 signalements) « proposée » | a) activer avec seuil paramétrable ; b) modération manuelle seule | a) |
| Q4 | Écriture inclusive (D6, point ouvert) | formulations épicènes + point médian, sigle unique | Proposition par défaut de D6 ; appliquer dès P3-05 et corriger en P16-06 |
| Q5 | Double authentification administrateur (`parametres.php` la mentionne) | a) TOTP pour admin / superadmin ; b) OTP e-mail à chaque connexion admin ; c) non | b) : cohérent avec ADR-12, aucune application tierce |
| Q6 | « Rester connecté·e » : durée du cookie | 14 j / 30 j | 14 j, révocable dans Sécurité |
| Q7 | Fournisseur SMS de secours et fournisseur LLM de l'assistant | à choisir par le client | Interfaces prêtes (ADR-25, P8-11), pilote `log` / base de connaissances en attendant |
| Q8 | Suppression des pages `events-2.php`, `event-details-2.php` (doublons du thème Social) | garder une seule version | Une seule version (celle référencée par la navigation) |
| Q9 | Stockage externe des sauvegardes (S3 compatible, FTP distant, Drive) | selon l'hébergeur | Disque Laravel configurable (`BACKUP_DISK`) |

---

## 8. Suivi d'avancement

| Phase | Statut | Dernière mise à jour | Note |
|---|---|---|---|
| P0 Socle | en cours | 17/10/2026 | Projet créé, front compilé, config cPanel, CI écrite ; gabarits et composants en cours |
| P1 Données | à faire | | |
| P2 Identité & accès | à faire | | |
| P3 Noyau transverse | à faire | | |
| P4 Site public | à faire | | |
| P5 Alertes, SOS, signalement | à faire | | |
| P6 API & coquille Vue | à faire | | |
| P7 Fil & sondages | à faire | | |
| P8 Communauté & profil | à faire | | |
| P9 Espace pair & synchro | à faire | | |
| P10 Admin socle & pilotage | à faire | | |
| P11 Admin données | à faire | | |
| P12 Admin plateforme | à faire | | |
| P13 Admin système | à faire | | |
| P14 Communication | à faire | | |
| P15 Mobile Flutter | à faire | | |
| P16 Qualité | à faire | | |
| P17 Déploiement | à faire | | |
