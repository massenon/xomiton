#!/usr/bin/env bash
# Sauvegarde mysqldump + fichiers (P17-04, ADR-22, RUNBOOK §9). Complète `backups:run` (archive JSON chiffrée) : le
# mysqldump est la sauvegarde officielle. Cron conseillé (heure du serveur) : 30 1 * * * /chemin/web/scripts/backup.sh
#
#   scripts/backup.sh [--db-only]
#
# Variables : PHP_BIN, BACKUP_DIR (défaut ~/backups/xomiton, HORS racine web), BACKUP_KEEP_DAYS (14),
#   BACKUP_PASSPHRASE_FILE (fichier contenant la phrase de passe : chiffre en AES-256 avec openssl),
#   BACKUP_UPLOAD_CMD (commande appelée avec le chemin de chaque fichier, ex. « rclone copy --no-traverse » + destination
#   dans un script ; sinon copier à la main hors serveur).
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/xomiton}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
DB_ONLY=0
[ "${1:-}" = "--db-only" ] && DB_ONLY=1

env_get() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }
die() { printf 'ERREUR : %s\n' "$*" >&2; exit 1; }

[ -f .env ] || die ".env absent."
command -v mysqldump >/dev/null || die "mysqldump introuvable : utiliser backups:run (archive JSON de repli) ou la sauvegarde cPanel."
[ "$(env_get DB_CONNECTION)" = "mysql" ] || die "DB_CONNECTION doit valoir mysql."

umask 077
mkdir -p "$BACKUP_DIR"
stamp="$(date +%Y%m%d-%H%M%S)"
produced=()

finish() { # chiffre si demandé, retient le fichier produit
  local file="$1"
  if [ -n "${BACKUP_PASSPHRASE_FILE:-}" ]; then
    openssl enc -aes-256-cbc -pbkdf2 -salt -pass "file:$BACKUP_PASSPHRASE_FILE" -in "$file" -out "$file.enc"
    rm -f "$file"; file="$file.enc"
  fi
  produced+=("$file")
}

# Le mot de passe passe par l'environnement, jamais par la ligne de commande (visible dans ps).
db_file="$BACKUP_DIR/xomiton-db-$stamp.sql.gz"
MYSQL_PWD="$(env_get DB_PASSWORD)" mysqldump --host="$(env_get DB_HOST)" --port="$(env_get DB_PORT)" \
  --user="$(env_get DB_USERNAME)" --single-transaction --quick --routines --no-tablespaces --default-character-set=utf8mb4 \
  "$(env_get DB_DATABASE)" | gzip -9 > "$db_file"
[ -s "$db_file" ] || die "Dump vide."
gzip -t "$db_file" || die "Dump corrompu."
finish "$db_file"

if [ "$DB_ONLY" = 0 ]; then
  files="$BACKUP_DIR/xomiton-files-$stamp.tar.gz"
  # storage/app (médias, exports) et .env (clés) ; ni vendor, ni caches, ni journaux.
  tar -czf "$files" --exclude='storage/app/backups' --exclude='storage/app/private/backups' storage/app .env
  finish "$files"
fi

find "$BACKUP_DIR" -maxdepth 1 -type f -name 'xomiton-*' -mtime "+$KEEP_DAYS" -delete

if [ -n "${BACKUP_UPLOAD_CMD:-}" ]; then
  for f in "${produced[@]}"; do $BACKUP_UPLOAD_CMD "$f"; done
fi

printf 'Sauvegarde terminée :\n'
printf '  %s\n' "${produced[@]}"
