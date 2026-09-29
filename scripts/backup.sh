#!/usr/bin/env bash
# Daily backup: MySQL dump + private uploaded documents, 14-day retention.
# Credentials are read from the app's .env at run time (never stored in this script).
set -euo pipefail
umask 077

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_DIR="${HOME}/backups"
STAMP="$(date +%Y%m%d-%H%M)"
mkdir -p "$BACKUP_DIR"

env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_HOST="$(env_value DB_HOST)"; DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"; DB_PASS="$(env_value DB_PASSWORD)"

# Pass the password through a temporary option file so it never appears in the process list.
CNF="$(mktemp)"; trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DB_USER" "$DB_PASS" "${DB_HOST:-localhost}" > "$CNF"

mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --routines --no-tablespaces "$DB_NAME" \
  | gzip -9 > "$BACKUP_DIR/vector7-db-$STAMP.sql.gz"

tar -czf "$BACKUP_DIR/vector7-files-$STAMP.tar.gz" -C "$APP_DIR/storage/app" private

find "$BACKUP_DIR" -name 'vector7-*' -type f -mtime +14 -delete
echo "$(date -Is) backup ok: $STAMP"
