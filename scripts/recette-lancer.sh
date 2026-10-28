#!/usr/bin/env bash
# Démarre pour la recette : le site (php artisan serve), le planificateur (schedule:work) et la file (queue:work).
# Ctrl+C arrête proprement les trois. À lancer après scripts/recette-local.sh.
#
#   scripts/recette-lancer.sh [--host HOTE] [--port PORT] [--no-queue] [--no-schedule]
#
#   --host HOTE   interface d'écoute (défaut 127.0.0.1 ; 0.0.0.0 pour tester depuis un téléphone du même Wi-Fi)
#   --port PORT   port (défaut : celui d'APP_URL dans web/.env, sinon 8000). APP_URL doit rester l'adresse ouverte.
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB="$ROOT/web"
HOST="127.0.0.1"; PORT=""; QUEUE=1; SCHEDULE=1

while [ $# -gt 0 ]; do
  case "$1" in
    --host) shift; HOST="${1:?--host demande une valeur}" ;;
    --port) shift; PORT="${1:?--port demande une valeur}" ;;
    --no-queue) QUEUE=0 ;;
    --no-schedule) SCHEDULE=0 ;;
    -h|--help) sed -n '2,9p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Option inconnue : $1" >&2; exit 2 ;;
  esac
  shift
done

die() { printf 'ERREUR : %s\n' "$*" >&2; exit 1; }
[ -f "$WEB/.env" ] || die "web/.env absent : lancez d'abord scripts/recette-local.sh."
[ -f "$WEB/vendor/autoload.php" ] || die "vendor/ absent : lancez d'abord scripts/recette-local.sh."
cd "$WEB"

app_url="$(grep -E '^APP_URL=' .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//')"
if [ -z "$PORT" ]; then
  PORT="$(printf '%s' "$app_url" | sed -nE 's#^https?://[^/:]+:([0-9]+).*#\1#p')"; PORT="${PORT:-8000}"
fi
[ -f public/build/manifest.json ] || printf 'Attention : public/build absent, les pages ne seront pas stylées (npm run build).\n' >&2
[ ! -f public/hot ] || printf 'Attention : public/hot présent (serveur Vite oublié) : supprimez-le pour utiliser le build.\n' >&2

port_busy() { (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }
if port_busy "$PORT"; then die "le port $PORT est déjà utilisé (autre serveur ?). Arrêtez-le ou utilisez --port, en alignant APP_URL."; fi

PIDS=()
kill_tree() { # tue un processus et ses descendants
  local pid="$1" child
  for child in $(pgrep -P "$pid" 2>/dev/null || true); do kill_tree "$child"; done
  kill "$pid" 2>/dev/null || true
}
STOPPING=0
stop_all() {
  [ "$STOPPING" = 1 ] && return; STOPPING=1
  printf '\nArrêt du site, du planificateur et de la file...\n'
  for pid in "${PIDS[@]:-}"; do [ -n "$pid" ] && kill_tree "$pid"; done
  wait 2>/dev/null || true
  printf 'Arrêté. Les journaux restent dans web/storage/logs/laravel.log.\n'
}
trap 'stop_all; exit 0' INT TERM
trap 'stop_all' EXIT

prefix() { sed -u "s/^/[$1] /"; }
start() { # start NOM commande...
  local name="$1"; shift
  "$@" > >(prefix "$name") 2>&1 &
  PIDS+=("$!")
}

printf 'Xomiton, recette locale. Ouvrez %s (Ctrl+C pour tout arrêter)\n\n' "$app_url"
start site php artisan serve --host="$HOST" --port="$PORT" --no-reload
[ "$SCHEDULE" = 1 ] && start planif php artisan schedule:work
[ "$QUEUE" = 1 ] && start file php artisan queue:work --sleep=3 --tries=3 --max-time=3600

# Surveille : si un des processus s'arrête (erreur), on arrête tout.
while true; do
  for pid in "${PIDS[@]}"; do
    if ! kill -0 "$pid" 2>/dev/null; then printf 'Un processus s'"'"'est arrêté : arrêt général.\n' >&2; exit 1; fi
  done
  sleep 2
done
