#!/usr/bin/env bash
# Prépare Xomiton pour la recette humaine sur un poste local (Linux, macOS, WSL, Git Bash).
#
#   scripts/recette-local.sh [--yes] [--no-reset] [--skip-install] [--skip-build] [--db CHEMIN]
#
#   --yes           ne pose aucune question (réinitialise la base de recette sans confirmation)
#   --no-reset      conserve la base existante : migre et complète les données, sans rien effacer
#   --skip-install  saute « composer install » et « npm ci » (dépendances déjà installées)
#   --skip-build    saute « npm run build » (front déjà compilé)
#   --db CHEMIN     fichier SQLite de recette (défaut : web/database/recette.sqlite)
#
# Idempotent : rejouable à volonté. Une base existante n'est JAMAIS effacée sans confirmation (ou --yes).
# Le fichier web/.env existant n'est jamais modifié ; il n'est créé que s'il est absent.
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB="$ROOT/web"
ASSUME_YES=0; RESET=1; INSTALL=1; BUILD=1; DB_PATH=""

usage() { sed -n '2,13p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

while [ $# -gt 0 ]; do
  case "$1" in
    --yes|-y) ASSUME_YES=1 ;;
    --no-reset) RESET=0 ;;
    --skip-install) INSTALL=0 ;;
    --skip-build) BUILD=0 ;;
    --db) shift; DB_PATH="${1:-}"; [ -n "$DB_PATH" ] || { echo "ERREUR : --db demande un chemin." >&2; exit 2; } ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Option inconnue : $1" >&2; usage >&2; exit 2 ;;
  esac
  shift
done

say()  { printf '\n==> %s\n' "$*"; }
ok()   { printf '  [ok] %s\n' "$*"; }
warn() { printf '  [attention] %s\n' "$*"; }
die()  { printf '\nERREUR : %s\n' "$*" >&2; exit 1; }

ask() { # ask "question" -> 0 si oui
  [ "$ASSUME_YES" = 1 ] && return 0
  [ -t 0 ] || die "Réponse impossible sans terminal interactif : relancez avec --yes (ou --no-reset)."
  local reply; read -r -p "$1 [o/N] " reply
  case "$reply" in o|O|oui|OUI|y|Y|yes) return 0 ;; *) return 1 ;; esac
}

env_get() { grep -E "^$1=" "$WEB/.env" | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }

set_env() { # set_env CLE VALEUR : remplace la ligne (même commentée) ou l'ajoute
  local tmp; tmp="$(mktemp)"
  KEY="$1" VAL="$2" awk '
    BEGIN { k = ENVIRON["KEY"]; v = ENVIRON["VAL"]; done = 0 }
    !done && $0 ~ ("^#? *" k "=") { print k "=" v; done = 1; next }
    { print }
    END { if (!done) print k "=" v }
  ' "$WEB/.env" > "$tmp" && cat "$tmp" > "$WEB/.env"; rm -f "$tmp"
}

# --- 1. Prérequis -------------------------------------------------------------------------------------------------
say "1/8 Vérification des prérequis"
missing=0
need() { command -v "$1" >/dev/null 2>&1 || { printf '  [manquant] %s : %s\n' "$1" "$2"; missing=1; return 1; }; }

if need php "installez PHP 8.3 (https://www.php.net/downloads)"; then
  php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
    && ok "PHP $(php -r 'echo PHP_VERSION;')" \
    || { printf '  [manquant] PHP 8.3 minimum (trouvé : %s)\n' "$(php -r 'echo PHP_VERSION;')"; missing=1; }
  modules="$(php -m | tr 'A-Z' 'a-z')"
  for ext in ctype dom fileinfo gd intl mbstring openssl pdo_sqlite sqlite3 tokenizer xml zip curl; do
    printf '%s\n' "$modules" | grep -qx "$ext" || { printf '  [manquant] extension PHP « %s »\n' "$ext"; missing=1; }
  done
fi
if need composer "installez Composer 2 (https://getcomposer.org/download/)"; then ok "Composer $(composer --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -n1)"; fi
if need node "installez Node 22 (https://nodejs.org/)"; then
  node_major="$(node -p 'process.versions.node.split(".")[0]')"
  if [ "$node_major" -ge 20 ]; then ok "Node $(node -v)"; [ "$node_major" -ge 22 ] || warn "Node 22 recommandé (comme la CI)."; else printf '  [manquant] Node 20 minimum (trouvé : %s)\n' "$(node -v)"; missing=1; fi
fi
need npm "fourni avec Node" && ok "npm $(npm -v)"
if command -v flutter >/dev/null 2>&1; then ok "Flutter présent (pour l'application mobile)"; else warn "Flutter absent : seule la partie web sera prête (voir docs/GUIDE_RECETTE.md §6)."; fi
[ "$missing" = 0 ] || die "Prérequis manquants (voir ci-dessus). Détail : docs/GUIDE_RECETTE.md §1."

cd "$WEB"

# --- 2. Dépendances ----------------------------------------------------------------------------------------------
if [ "$INSTALL" = 1 ]; then
  say "2/8 Dépendances PHP (composer install)"
  COMPOSER_ALLOW_SUPERUSER="${COMPOSER_ALLOW_SUPERUSER:-1}" composer install --no-interaction --prefer-dist --no-progress
  say "3/8 Dépendances JavaScript (npm ci)"
  if [ -d node_modules ] && [ -f node_modules/.package-lock.json ] && [ ! package-lock.json -nt node_modules/.package-lock.json ]; then
    ok "node_modules à jour : npm ci inutile"
  else
    npm ci --no-audit --no-fund
  fi
else
  say "2/8 et 3/8 Dépendances : sautées (--skip-install)"
  [ -f vendor/autoload.php ] || die "vendor/ absent : lancez sans --skip-install."
fi

# --- 4. Fichier .env ---------------------------------------------------------------------------------------------
say "4/8 Fichier .env"
if [ -f .env ]; then
  ok ".env déjà présent : conservé tel quel"
else
  cp .env.example .env
  ok ".env créé depuis .env.example (SQLite, e-mails/SMS/push consignés dans les journaux)"
fi
if [ -n "$DB_PATH" ]; then
  case "$DB_PATH" in /*) ;; *) DB_PATH="$PWD/$DB_PATH" ;; esac
  set_env DB_CONNECTION sqlite
  set_env DB_DATABASE "$DB_PATH"
  ok "base de recette fixée : $DB_PATH"
fi
grep -qE '^APP_KEY=.+' .env || { php artisan key:generate --ansi --no-interaction; }
ok "APP_KEY présente"

app_env="$(env_get APP_ENV)"
if [ "$app_env" != "local" ] && [ "$app_env" != "testing" ] && [ "$app_env" != "staging" ]; then
  die "APP_ENV=$app_env dans web/.env : ce script prépare une recette locale (APP_ENV=local) et n'efface jamais une base de production."
fi
db_conn="$(env_get DB_CONNECTION)"
[ "$db_conn" = "sqlite" ] || die "DB_CONNECTION=$db_conn : la recette locale utilise SQLite (DB_CONNECTION=sqlite)."
db_file="$(env_get DB_DATABASE)"
[ -n "$db_file" ] || die "DB_DATABASE vide dans web/.env."
case "$db_file" in /*) db_abs="$db_file" ;; *) db_abs="$WEB/$db_file" ;; esac

# --- 5. Base de données ------------------------------------------------------------------------------------------
say "5/8 Base de données de recette ($db_abs)"
mkdir -p "$(dirname "$db_abs")"
if [ -s "$db_abs" ]; then
  if [ "$RESET" = 0 ]; then
    ok "base conservée (--no-reset) : migrations puis données de démonstration"
    php artisan migrate --force --no-interaction
    php artisan db:seed --force --no-interaction
  else
    warn "Une base existe déjà : TOUTES ses données (comptes, publications, signalements de test) seront effacées."
    if ask "Effacer et recréer la base de recette ?"; then
      php artisan migrate:fresh --seed --force --no-interaction
    else
      printf '  Base conservée. Relancez avec --no-reset pour la mettre à jour sans rien effacer.\n'
      RESET=0
      php artisan migrate --force --no-interaction
      php artisan db:seed --force --no-interaction
    fi
  fi
else
  touch "$db_abs"
  php artisan migrate:fresh --seed --force --no-interaction
fi
php artisan storage:link --no-interaction >/dev/null 2>&1 || true
ok "données de démonstration en place"

# --- 6. Front ----------------------------------------------------------------------------------------------------
if [ "$BUILD" = 1 ]; then
  say "6/8 Compilation du front (npm run build)"
  rm -f public/hot
  npm run build
else
  say "6/8 Compilation du front : sautée (--skip-build)"
  [ -f public/build/manifest.json ] || warn "public/build/manifest.json absent : les pages ne seront pas stylées sans build."
fi

# --- 7. Contrôle rapide ------------------------------------------------------------------------------------------
say "7/8 Contrôle rapide"
users="$(php artisan tinker --execute='echo App\Models\User::count();' 2>/dev/null | tail -n1 | tr -dc '0-9')"
[ "${users:-0}" -gt 0 ] && ok "${users} comptes de démonstration" || warn "aucun compte trouvé (APP_ENV doit être local)."
php artisan schedule:list >/dev/null 2>&1 && ok "planificateur déclaré" || warn "php artisan schedule:list a échoué."

# --- 8. Récapitulatif --------------------------------------------------------------------------------------------
app_url="$(env_get APP_URL)"; app_url="${app_url:-http://localhost:8000}"
say "8/8 Prêt pour la recette"
cat <<RECAP

  Lancer le site, le planificateur et la file :   scripts/recette-lancer.sh
  Puis ouvrir (exactement cette adresse) :        $app_url

  Pages utiles : $app_url/  ·  /connexion  ·  /sos  ·  /signaler  ·  /admin
  Journal (e-mails, SMS, push, codes OTP) :       $WEB/storage/logs/laravel.log

  Comptes de démonstration
  ------------------------------------------------------------------------------
  Rôle                        Identifiant                 Mot de passe
  Super-administrateur·rice   admin@xomiton.bj            Admin123!
  Administrateur·rice         moderation@xomiton.bj       Admin123!
  Modérateur·rice             moderateur@xomiton.bj       Modo123!
  Membre                      membre@xomiton.bj           Membre123!
  Membre (second compte)      membre2@xomiton.bj          Membre123!
  Pair éducateur·rice         pair@xomiton.bj             Pair123!
  Pair en attente (RG-03)     pair.attente@xomiton.bj     Pair123!  (connecté avec un bandeau « en attente »)

  Code de vérification (OTP) à chaque nouvel appareil, et à chaque connexion admin :
    grep -a "\[otp:log\]" "$WEB/storage/logs/laravel.log" | tail -1

  Guide complet : docs/GUIDE_RECETTE.md   ·   Cases à cocher : docs/CHECKLIST_RECETTE.md
RECAP
