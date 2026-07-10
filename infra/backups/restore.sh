#!/usr/bin/env bash
#
# CoPot — restore a PostgreSQL backup produced by backup.sh.
#
# Usage:
#   ./restore.sh /var/backups/copot/copot-20260101-030000.dump
#
# Overwrites current DB contents (--clean --if-exists). Run on the VPS.
set -euo pipefail

DUMP="${1:-}"
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
    echo "Usage: $0 <path-to-copot-*.dump>" >&2
    exit 1
fi

DEPLOY_ROOT="${DEPLOY_ROOT:-/opt/copot}"
COMPOSE="docker compose --env-file ${DEPLOY_ROOT}/.env.prod -f ${DEPLOY_ROOT}/docker-compose.prod.yml"
DB_SERVICE="postgres"

echo "[$(date -Iseconds)] restoring $DUMP into ${POSTGRES_DB:-copot} (destructive)"
read -r -p "Type the DB name to confirm (${POSTGRES_DB:-copot}): " CONFIRM
[ "$CONFIRM" = "${POSTGRES_DB:-copot}" ] || { echo "aborted"; exit 1; }

# pg_restore runs inside the container; stream the local file in on stdin.
cat "$DUMP" | $COMPOSE exec -T "$DB_SERVICE" \
    pg_restore --clean --if-exists --no-owner --no-privileges \
    -U "${POSTGRES_USER:-copot}" -d "${POSTGRES_DB:-copot}"

echo "[$(date -Iseconds)] restore complete"
