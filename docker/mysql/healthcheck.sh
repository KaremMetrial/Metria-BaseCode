#!/bin/bash
# =============================================================================
# MySQL healthcheck
#
# Runs a real query rather than a TCP probe: during initialisation mysqld binds
# the port long before it will accept application traffic, so a port check
# reports "healthy" while `migrate` would still fail.
#
# The password is passed via MYSQL_PWD rather than --password= so it does not
# appear in `ps` output inside the container.
# =============================================================================
set -uo pipefail

read_secret() {
    # Supports Docker secrets: VAR_FILE takes precedence over VAR.
    local name="$1" file_var="${1}_FILE"
    if [[ -n "${!file_var:-}" && -r "${!file_var}" ]]; then
        cat "${!file_var}"
    else
        printf '%s' "${!name:-}"
    fi
}

USER_NAME="${MYSQL_USER:-root}"
if [[ "${USER_NAME}" == "root" ]]; then
    PASSWORD="$(read_secret MYSQL_ROOT_PASSWORD)"
else
    PASSWORD="$(read_secret MYSQL_PASSWORD)"
fi

export MYSQL_PWD="${PASSWORD}"

OUTPUT="$(
    mysql \
        --protocol=TCP \
        --host=127.0.0.1 \
        --port="${MYSQL_TCP_PORT:-3306}" \
        --user="${USER_NAME}" \
        --connect-timeout=4 \
        --silent --skip-column-names \
        --execute='SELECT 1' 2>&1
)"
STATUS=$?

if [[ ${STATUS} -eq 0 && "${OUTPUT}" == *1* ]]; then
    exit 0
fi

echo "unhealthy: ${OUTPUT}" >&2
exit 1
