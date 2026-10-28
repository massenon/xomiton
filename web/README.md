# Xomiton — application web (Laravel 12)

Plateforme numérique d'apprentissage et d'appui holistique pour les filles, femmes et personnes LBTQ+ au Bénin. Ce dossier contient l'application Laravel : pages publiques (Blade), espace membre et pair éducateur·rice (Vue 3 + Inertia), administration (Livewire 3) et API `/api/v1` (Flutter).

Avant toute tâche, lire `../docs/MASTER_PROMPT.md` puis `../docs/IMPLEMENTATION_PLAN.md` (quoi faire, dans quel ordre).

## Prérequis

- PHP 8.3 avec les extensions `mbstring`, `intl`, `pdo_mysql`, `pdo_sqlite`, `gd`, `zip`
- Composer 2
- Node 22 et npm (compilation Vite en local ; aucun Node sur le serveur, D7)
- MySQL 8 ou MariaDB 10.6 en production ; SQLite suffit en local et pour les tests

## Installation locale

Recette humaine : `scripts/recette-local.sh` puis `scripts/recette-lancer.sh` (à la racine du dépôt), détaillés dans `../docs/GUIDE_RECETTE.md`. Pas à pas manuel :

```bash
cd web
composer install
npm install
cp .env.example .env            # déjà réglé pour SQLite (database/recette.sqlite)
php artisan key:generate
touch database/recette.sqlite
php artisan migrate --seed
npm run build                   # ou npm run dev pendant le développement
php artisan serve               # http://localhost:8000
```

Tâches planifiées (cron chaque minute en production, D7) :

```
* * * * * php artisan schedule:run
* * * * * php artisan queue:work --stop-when-empty --max-time=50
```

## Tâches planifiées

Le planificateur est déclaré dans `routes/console.php` (`php artisan schedule:list` pour le vérifier). Toutes les tâches sont idempotentes, protégées par `withoutOverlapping()`, exprimées dans `config('app.timezone')`, sans `runInBackground()` (cPanel mutualisé, D7). Les règles sont dans `app/Application/Retention` et `app/Application/Maintenance` ; les commandes dans `app/Console/Commands`. Chaque commande peut aussi être lancée à la main.

| Commande | Fréquence | Rôle |
|---|---|---|
| `mail:flush-outbox [--limit=50]` | chaque minute | Envoie les e-mails de `mail_outbox` avec le mailer par défaut si `MAIL_MAILER` n'est ni `log` ni `array` (sinon ne fait rien et le dit) ; 50 au plus par exécution, `attempts` et `last_error` tenus, trace dans `mail_logs` |
| `heartbeat:ping` | chaque minute | GET sur `HEARTBEAT_URL` (délai 5 s) pour la supervision externe ; silencieux si l'URL n'est pas définie |
| `otp:purge` | quotidien 03:00 | Supprime les codes OTP expirés ou consommés depuis plus de 24 h (`xomiton.retention.otp_hours`) |
| `mail:purge-logs` | quotidien 03:00 | Supprime les `mail_logs` de plus de 180 jours |
| `audit:purge [--days=N]` | quotidien 03:00 | Supprime les `audit_logs` au-delà de la rétention : option `--days`, sinon paramètre `audit_log_days` de l'administration, sinon `xomiton.retention.audit_log_days` (90 j) ; bornes 7–3650 |
| `accounts:anonymize [--dry-run]` | quotidien 03:00 | Comptes supprimés logiquement depuis plus de 30 j : pseudo `membre-supprimé-<id>`, e-mail `supprime-<id>@anonyme.invalid`, nom, téléphone, profil, avatar, réglages, jetons, sessions et abonnements push effacés ; publications et commentaires conservés sous le pseudo anonymisé ; une seule fois par compte |
| `cases:anonymize [--dry-run]` | quotidien 03:00 | Dossiers clos ou archivés depuis plus de 5 ans : notes, récits, auteur·e, lieu, note de services, signataire du consentement et commentaires de suivi remplacés par `[anonymisé]`, `anonymized_at` posé ; colonnes statistiques conservées |
| `sessions:purge` | quotidien 03:00 | Supprime les sessions en base dont `last_activity` dépasse la durée de session + 1 jour |
| `sync:prune` | quotidien 03:00 | Supprime les `sync_outbox_receipts` de plus de 90 jours et révoque (`is_revoked`) les appareils sans contact depuis plus de 180 jours |
| `reports:purge` | quotidien 03:30 | Fichiers de `generated_reports` au-delà d'`expires_at` supprimés du disque `xomiton.reports.disk` ; la ligne est marquée (fichier et taille effacés) si le journal d'audit la référence, supprimée sinon |
| `cache:purge-expired` | quotidien 03:30 | Pilote `file` seulement : supprime par lots les fichiers de `storage/framework/cache/data` dont l'horodatage d'expiration en tête est passé (le pilote ne purge jamais seul une entrée jamais relue) ; sans effet pour les autres pilotes |
| `indicators:snapshot [--month=AAAA-MM]` | mensuel, le 1er à 04:00 | Calcule les indicateurs intégrés actifs du mois précédent (`App\Application\Steering\IndicatorCalculator`, une méthode par `calc_key`) au niveau national et par département actif, puis les enregistre dans `indicator_values` (idempotent, valeurs manuelles conservées) ; IND-04 (`pct_72h`) est signalé non calculable |

Prévues par les phases suivantes (lignes commentées dans `routes/console.php`, à activer avec la commande) : `alerts:escalate`, `notifications:retry`, `newsletter:process`, `gateways:publish` (chaque minute) ; `reports:run-schedules` (quotidien) ; `backups:run` (02:00).

Hors planificateur, à lancer à la main lors d'une rotation d'`APP_KEY` (P1-15, runbook) : `php artisan xomiton:reencrypt [--from=<ancienne clé>] [--chunk=500] [--dry-run] [--force]` rechiffre toutes les colonnes de `config('xomiton.encrypted_columns')` avec la clé courante à partir de l'ancienne (`--from`, sinon la première d'`APP_PREVIOUS_KEYS`), exige une sauvegarde terminée de moins de 24 h dans `backups` (sauf `--force`), s'arrête proprement après 50 s et reprend à l'appel suivant (les valeurs déjà rechiffrées sont ignorées) ; journal + entrée d'audit `platform.reencrypt`.

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
| Super-admin | admin@xomiton.bj | Admin123! |
| Admin | moderation@xomiton.bj | Admin123! |
| Modérateur·rice | moderateur@xomiton.bj | Modo123! |
| Membre | membre@xomiton.bj, membre2@xomiton.bj | Membre123! |
| Pair | pair@xomiton.bj | Pair123! |
| Pair en attente de validation | pair.attente@xomiton.bj | Pair123! |

## Références

- Décisions client : `../design-system/DECISIONS.md`
- Design system : `../design-system/xomiton/MASTER.md`, jetons `../design-system/tokens/`
- Prototype de référence : `../prototype_social/`, `../prototype_mobile/`
- Documentation : `../docs/README.md`
