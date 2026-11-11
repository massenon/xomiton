#!/bin/sh
# Serveur des parcours Playwright (P5-10) : base SQLite dédiée (database/e2e.sqlite ou $E2E_DB, jamais la base locale), recréée et
# semée à chaque lancement, servie par le serveur intégré de PHP depuis public/ sur http://127.0.0.1:$1 (contexte sûr :
# le service worker s'y enregistre). Le build Vite doit exister (npm run build) : /sw.js répond 404 sans lui.
set -e
PORT="${1:-8123}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export DB_CONNECTION=sqlite DB_DATABASE="${E2E_DB:-$ROOT/database/e2e.sqlite}" APP_URL="http://127.0.0.1:$PORT" APP_DEBUG=false DEBUGBAR_ENABLED=false
rm -f "$DB_DATABASE" && touch "$DB_DATABASE"
php "$ROOT/artisan" migrate:fresh --seed --force --no-interaction >/dev/null
cd "$ROOT/public"
exec php -S "127.0.0.1:$PORT" "$ROOT/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
