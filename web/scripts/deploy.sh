#!/usr/bin/env bash
# Déploiement Xomiton sur cPanel mutualisé (P17-02, RUNBOOK §1). À lancer depuis le terminal cPanel / SSH, une fois
# le code, vendor/ et public/build/ déposés. Idempotent : rejouable sans effet de bord.
#
#   scripts/deploy.sh [--first] [--no-backup] [--composer] [--skip-preflight]
#   scripts/deploy.sh --rollback          # annule les migrations du dernier déploiement (voir RUNBOOK §3)
#
# PHP_BIN : binaire PHP 8.3 (défaut /opt/cpanel/ea-php83/root/usr/bin/php, sinon `php`).
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
APP_DIR="$(pwd)"
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php83/root/usr/bin/php}"
[ -x "$PHP_BIN" ] || PHP_BIN="php"
STATE_FILE="storage/app/deploy-last-migrations"

FIRST=0; BACKUP=1; COMPOSER=0; PREFLIGHT=1; ROLLBACK=0
for arg in "$@"; do
  case "$arg" in
    --first) FIRST=1 ;;
    --no-backup) BACKUP=0 ;;
    --composer) COMPOSER=1 ;;
    --skip-preflight) PREFLIGHT=0 ;;
    --rollback) ROLLBACK=1 ;;
    *) echo "Option inconnue : $arg" >&2; exit 2 ;;
  esac
done

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERREUR : %s\n' "$*" >&2; exit 1; }
artisan() { "$PHP_BIN" artisan "$@"; }
env_get() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }

MAINTENANCE_BY_US=0
trap 'rc=$?; if [ "$MAINTENANCE_BY_US" = 1 ]; then printf "\nECHEC (code %s). Le mode maintenance reste ACTIF : corriger, relancer scripts/deploy.sh, ou voir RUNBOOK §3 (retour arrière).\n" "$rc" >&2; fi' ERR

maintenance_on() {
  [ "$FIRST" = 1 ] && return 0
  if artisan xomiton:maintenance on --minutes=10 --message="Mise à jour en cours, retour dans quelques minutes." --reason="deploy.sh"; then
    MAINTENANCE_BY_US=1
  elif artisan xomiton:maintenance status >/dev/null 2>&1; then
    echo "Maintenance déjà active : elle ne sera pas levée par ce script."
  else
    die "xomiton:maintenance indisponible (base injoignable ?). Utiliser --first pour un premier déploiement."
  fi
}

maintenance_off() {
  if [ "$MAINTENANCE_BY_US" = 1 ]; then
    artisan xomiton:maintenance off --reason="deploy.sh terminé"
    MAINTENANCE_BY_US=0
  fi
}

say "Contrôles préalables"
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || die "PHP 8.3 requis ($("$PHP_BIN" -r 'echo PHP_VERSION;') trouvé) : définir PHP_BIN."
for ext in ctype curl fileinfo intl json mbstring openssl pdo_mysql sodium tokenizer xml zip; do
  "$PHP_BIN" -m | grep -qix "$ext" || die "Extension PHP manquante : $ext (cPanel > Select PHP Version)."
done
[ -f .env ] || die ".env absent : le créer depuis .env.example (RUNBOOK §1.3)."
[ "$(env_get APP_ENV)" = "production" ] || die "APP_ENV doit valoir production."
[ "$(env_get APP_DEBUG)" = "false" ] || die "APP_DEBUG doit valoir false."
[ -n "$(env_get APP_KEY)" ] || die "APP_KEY vide : lancer UNE fois « $PHP_BIN artisan key:generate » (jamais sur un site déjà en service, voir RUNBOOK §8)."
[ -f public/build/manifest.json ] || die "public/build/manifest.json absent : npm run build en local puis envoyer public/build."
if [ "$COMPOSER" = 1 ]; then
  command -v composer >/dev/null || die "composer introuvable : construire vendor/ en local et l'envoyer."
  "$PHP_BIN" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction
fi
[ -f vendor/autoload.php ] || die "vendor/ absent : composer install --no-dev --optimize-autoloader (local) puis envoi."
mkdir -p storage/app storage/logs storage/framework/{cache/data,sessions,views} bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

if [ "$ROLLBACK" = 1 ]; then
  say "Retour arrière des migrations du dernier déploiement"
  [ -f "$STATE_FILE" ] || die "$STATE_FILE absent : rien à annuler automatiquement (restaurer la sauvegarde, RUNBOOK §9)."
  steps="$(cat "$STATE_FILE")"
  maintenance_on
  if [ "${steps:-0}" -gt 0 ]; then artisan migrate:rollback --step="$steps" --force; fi
  rm -f "$STATE_FILE"
  artisan config:clear; artisan route:clear; artisan view:clear
  artisan config:cache; artisan route:cache; artisan view:cache
  maintenance_off
  echo "Migrations annulées. Remettre maintenant le code de la version précédente (archive de l'envoi précédent), puis relancer scripts/deploy.sh."
  exit 0
fi

say "Sauvegarde avant migration"
if [ "$BACKUP" = 1 ] && [ "$FIRST" = 0 ]; then
  scripts/backup.sh --db-only
else
  echo "ignorée"
fi

say "Mode maintenance"
maintenance_on

say "Migrations"
pending="$(artisan migrate:status --pending 2>/dev/null | grep -c 'Pending' || true)"
echo "${pending:-0} migration(s) en attente."
artisan migrate --force
echo "${pending:-0}" > "$STATE_FILE"

say "Stockage et caches"
[ -e public/storage ] || artisan storage:link || echo "storage:link impossible (symlink() désactivé ?) : voir DEPLOIEMENT_CPANEL.md §2."
artisan config:clear; artisan route:clear; artisan view:clear; artisan event:clear
artisan config:cache; artisan route:cache; artisan view:cache; artisan event:cache

say "Fin de maintenance"
maintenance_off

if [ "$PREFLIGHT" = 1 ]; then
  say "Test de fumée"
  if [ "$FIRST" = 1 ]; then artisan xomiton:preflight --skip-cron; else artisan xomiton:preflight; fi
fi

say "Déploiement terminé ($APP_DIR)"
