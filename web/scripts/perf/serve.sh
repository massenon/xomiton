#!/bin/sh
# Serveur de mesure : php -S (4 processus, OPcache CLI) + mandataire gzip. Usage : sh scripts/perf/serve.sh [portProxy] [portPhp]
# Prérequis : APP_ENV=production APP_DEBUG=false, base SQLite semée (DB_DATABASE), npm run build, php artisan optimize.
cd "$(dirname "$0")/../.." || exit 1
PROXY="${1:-8099}"; PHP="${2:-8098}"
(cd public && PHP_CLI_SERVER_WORKERS=4 exec php -d opcache.enable_cli=1 -d opcache.validate_timestamps=0 -S "127.0.0.1:$PHP" -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >/tmp/perf-php.log 2>&1) &
exec node scripts/perf/proxy.mjs "$PROXY" "$PHP"
