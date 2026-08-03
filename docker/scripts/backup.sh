#!/usr/bin/env bash
# =============================================================================
# Database backup
#
#   docker/scripts/backup.sh
#   RETAIN_DAYS=30 docker/scripts/backup.sh
#   COMPOSE_MODE=prod docker/scripts/backup.sh
#
# Writes a gzipped, point-in-time-consistent dump to backups/.
# =============================================================================
source "$(dirname -- "${BASH_SOURCE[0]}")/_common.sh"

BACKUP_DIR="${BACKUP_DIR:-${ROOT_DIR}/backups}"
RETAIN_DAYS="${RETAIN_DAYS:-14}"
STAMP="$(date -u +%Y%m%d-%H%M%S)"

require_running mysql
mkdir -p "${BACKUP_DIR}"

DATABASE="$(dc exec -T mysql printenv MYSQL_DATABASE | tr -d '\r')"
TARGET="${BACKUP_DIR}/${DATABASE}-${STAMP}.sql.gz"

info "dumping '${DATABASE}' → ${TARGET#"${ROOT_DIR}/"}"

# --single-transaction takes a consistent snapshot WITHOUT locking tables
#   (InnoDB only — it is why the app stays online during the dump).
# --routines/--triggers/--events are not included by default and their absence
#   is only discovered at restore time, when they are already gone.
# MYSQL_PWD keeps the password out of `ps`.
if ! dc exec -T mysql sh -c '
        MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump \
            --user=root \
            --single-transaction \
            --quick \
            --routines \
            --triggers \
            --events \
            --hex-blob \
            --default-character-set=utf8mb4 \
            --databases "$MYSQL_DATABASE"
    ' 2>"${BACKUP_DIR}/.${STAMP}.err" | gzip -9 > "${TARGET}"
then
    fail "mysqldump failed:"
    sed 's/^/    /' "${BACKUP_DIR}/.${STAMP}.err" >&2
    rm -f "${TARGET}" "${BACKUP_DIR}/.${STAMP}.err"
    exit 1
fi

# mysqldump exits 0 on some partial failures, so verify the artifact itself.
if ! gzip -t "${TARGET}" 2>/dev/null; then
    rm -f "${TARGET}"
    die "backup is corrupt (failed gzip integrity check)"
fi

SIZE="$(du -h "${TARGET}" | cut -f1)"
if [[ ! -s "${TARGET}" ]] || (( $(stat -c%s "${TARGET}") < 1024 )); then
    die "backup is suspiciously small (${SIZE}) — refusing to keep it"
fi

rm -f "${BACKUP_DIR}/.${STAMP}.err"
ok "backup complete (${SIZE})"

if (( RETAIN_DAYS > 0 )); then
    REMOVED="$(find "${BACKUP_DIR}" -maxdepth 1 -name '*.sql.gz' -type f -mtime "+${RETAIN_DAYS}" -print -delete | wc -l)"
    (( REMOVED > 0 )) && info "pruned ${REMOVED} backup(s) older than ${RETAIN_DAYS} days"
fi

echo "${TARGET}"
