# Xomiton — application web (Laravel 12)

Plateforme numérique d'apprentissage et d'appui holistique pour les filles, femmes et personnes LBTQ+ au Bénin. Ce dossier contient l'application Laravel : pages publiques (Blade), espace membre et pair éducateur·rice (Vue 3 + Inertia), administration (Livewire 3) et API `/api/v1` (Flutter).

Avant toute tâche, lire `../docs/MASTER_PROMPT.md` puis `../docs/IMPLEMENTATION_PLAN.md` (quoi faire, dans quel ordre).

## Prérequis

- PHP 8.3 avec les extensions `mbstring`, `intl`, `pdo_mysql`, `pdo_sqlite`, `gd`, `zip`
- Composer 2
- Node 22 et npm (compilation Vite en local ; aucun Node sur le serveur, D7)
- MySQL 8 ou MariaDB 10.6 en production ; SQLite suffit en local et pour les tests

## Installation locale

```bash
cd web
composer install
npm install
cp .env.example .env            # puis DB_CONNECTION=sqlite pour un démarrage rapide
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build                   # ou npm run dev pendant le développement
php artisan serve               # http://localhost:8000
```

Tâches planifiées (cron chaque minute en production, D7) :

```
* * * * * php artisan schedule:run
* * * * * php artisan queue:work --stop-when-empty --max-time=50
```

## Commandes utiles

| Commande | Rôle |
|---|---|
| `vendor/bin/pint` | Style de code (PSR-12, `declare(strict_types=1)`) |
| `vendor/bin/phpstan analyse` | Analyse statique, Larastan niveau 6 |
| `vendor/bin/pest` | Tests (unitaires, HTTP, Livewire) |
| `npm run typecheck` | Types Vue / TypeScript |
| `npm test` | Tests Vitest (composants Vue) |
| `npm run e2e` | Parcours Playwright |
| `php artisan xomiton:import-prototype ../prototype_social/data` | Import des données du prototype (P1-13) |

## Structure

```
app/
├─ Domain/<Module>/        règles métier, entités, événements (testées sans base)
├─ Application/<Module>/   cas d'usage, DTO
├─ Infrastructure/         dépôts Eloquent, mail, push, sauvegardes, synchronisation
├─ Http/Controllers/Api/V1 contrôleurs API (Form Request → cas d'usage → Resource)
├─ Http/Controllers/Web    pages publiques et points d'entrée Inertia
├─ Livewire/Admin/<Module> écrans d'administration
├─ Models/  Policies/  Enums/  Jobs/  Notifications/  Mail/
resources/
├─ css/theme/              thème Social (style.css jamais modifié), jetons --xm-*, surcharges
├─ css/xomiton.css         feuille commune ; css/admin.css compléments admin
├─ js/public.js            pages publiques (Bootstrap + Alpine)
├─ js/admin.js             administration (SweetAlert2, Chart.js, Quill, Choices, flatpickr)
├─ js/app.ts               espace membre et pair (Vue 3 + Inertia + Pinia + vue-i18n)
├─ views/components/       composants Blade x-* et gabarits (layouts/public, auth, plain, admin)
└─ views/partials/         en-tête, pieds de page, barres d'administration
routes/web.php · routes/admin.php · routes/api.php · routes/console.php
lang/fr/*.php              tous les textes d'interface
config/xomiton.php         réglages métier (délais, limites, lots)
```

## Comptes de démonstration (local uniquement, après `migrate --seed`)

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Admin | admin@xomiton.bj | Admin123! |
| Membre | membre@xomiton.bj | Membre123! |
| Pair | pair@xomiton.bj | Pair123! |

## Références

- Décisions client : `../design-system/DECISIONS.md`
- Design system : `../design-system/xomiton/MASTER.md`, jetons `../design-system/tokens/`
- Prototype de référence : `../prototype_social/`, `../prototype_mobile/`
- Documentation : `../docs/README.md`
