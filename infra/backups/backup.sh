#!/usr/bin/env bash
#
# CoPot — 3-2-1 backup.
#   3 copies: live DB + this local dump + off-site (Backblaze B2 via rclone)
#   2 media: local disk + cloud
#   1 off-site: B2
#
# Runs nightly from cron (installed by infra/ansible/playbook.yml).
# Usage:   ./backup.sh
set -euo pipefail

DEPLOY_ROOT="${DEPLOY_ROOT:-/opt/copot}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/copot}"
COMPOSE="docker compose --env-file ${DEPLOY_ROOT}/.env.prod -f ${DEPLOY_ROOT}/docker-compose.prod.yml"
DB_SERVICE="postgres"
RCLONE_REMOTE="${RCLONE_REMOTE:-backblaze}"
RCLONE_PATH="${RCLONE_PATH:-copot-backups}"
KEEP_DAILY="${KEEP_DAILY:-7}"
KEEP_WEEKLY="${KEEP_WEEKLY:-4}"

# Docker compose stats the CWD; make the script independent of where it's
# launched from (e.g. `sudo -u copot` keeps a /root CWD the user can't read).
cd "$DEPLOY_ROOT"

mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
DUMP="$BACKUP_DIR/copot-${STAMP}.dump"

echo "[$(date -Iseconds)] starting CoPot backup → $DUMP"

# --- PostgreSQL (custom format, parallel-restore friendly) ---
$COMPOSE exec -T "$DB_SERVICE" pg_dump -Fc -U "${POSTGRES_USER:-copot}" "${POSTGRES_DB:-copot}" > "$DUMP"

# --- Redis snapshot (best-effort; Redis is a cache, data is non-authoritative) ---
if $COMPOSE ps redis --status running --format '{{.Name}}' | grep -q .; then
    $COMPOSE exec -T redis redis-cli BGSAVE >/dev/null || true
    sleep 1
    $COMPOSE cp redis:/data/dump.rdb "$BACKUP_DIR/redis-${STAMP}.rdb" 2>/dev/null || true
fi

# --- Local rotation (2nd medium: local disk) ---
find "$BACKUP_DIR" -name 'copot-*.dump' -mtime +"$KEEP_DAILY" -delete
find "$BACKUP_DIR" -name 'redis-*.rdb'  -mtime +"$KEEP_DAILY" -delete

# Keep one weekly snapshot (Sundays) for the last N weeks.
if [ "$(date +%u)" -eq 7 ]; then
    cp "$DUMP" "$BACKUP_DIR/copot-weekly-${STAMP}.dump"
    find "$BACKUP_DIR" -name 'copot-weekly-*.dump' -mtime +"$((KEEP_WEEKLY * 7))" -delete
fi

# --- Off-site (1 off-site: Backblaze B2) ---
if command -v rclone >/dev/null 2>&1; then
    echo "[$(date -Iseconds)] syncing to ${RCLONE_REMOTE}:${RCLONE_PATH}"
    rclone copy "$BACKUP_DIR" "${RCLONE_REMOTE}:${RCLONE_PATH}" \
        --transfers 4 --checkers 8 --stats=10s --quiet
else
    echo "[warn] rclone not installed — off-site copy skipped" >&2
fi

echo "[$(date -Iseconds)] backup complete"
