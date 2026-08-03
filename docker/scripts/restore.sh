#!/usr/bin/env bash
# =============================================================================
# Database restore
#
#   docker/scripts/restore.sh backups/laravel-20260731-120000.sql.gz
#
# This OVERWRITES the target database, so it asks for confirmation first
# (skip with FORCE=1, e.g. in an automated disaster-recovery drill).
# =============================================================================
source "$(dirname -- "${BASH_SOURCE[0]}")/_common.sh"

FILE="${1:-}"
[[ -n "${FILE}" ]] || die "usage: restore.sh <backup.sql.gz>"
[[ -f "${FILE}" ]] || die "no such file: ${FILE}"

require_running mysql

DATABASE="$(dc exec -T mysql printenv MYSQL_DATABASE | tr -d '\r')"

if [[ "${FILE}" == *.gz ]]; then
    gzip -t "${FILE}" 2>/dev/null || die "archive is corrupt: ${FILE}"
    READER=(gzip -dc "${FILE}")
else
    READER=(cat "${FILE}")
fi

if [[ "${FORCE:-0}" != "1" ]]; then
    warn "This will OVERWRITE the '${DATABASE}' database with ${FILE}."
    read -r -p "Type 'restore' to continue: " confirm
    [[ "${confirm}" == "restore" ]] || die "aborted"
fi

info "restoring ${FILE} → ${DATABASE}"

# The dump was written with --databases, so it carries its own CREATE DATABASE
# and USE statements; no --database flag is needed (or wanted) here.
if "${READER[@]}" | dc exec -T mysql sh -c '
        MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --default-character-set=utf8mb4
    '
then
    ok "restore complete"
else
    die "restore failed — the database may be in a partial state"
fi

warn "clearing application caches so they do not reference stale data"
dc exec -T app php artisan optimize:clear 2>/dev/null || warn "could not clear caches (is the app container running?)"
