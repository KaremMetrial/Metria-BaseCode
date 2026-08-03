#!/bin/sh
# =============================================================================
# Redis healthcheck
#
# Uses REDISCLI_AUTH (read by redis-cli automatically) instead of `-a`, which
# would put the password into `ps` output and print a warning on every probe.
# =============================================================================
set -eu

if [ -n "${REDIS_PASSWORD_FILE:-}" ] && [ -r "${REDIS_PASSWORD_FILE}" ]; then
    REDISCLI_AUTH="$(cat "${REDIS_PASSWORD_FILE}")"
    export REDISCLI_AUTH
elif [ -n "${REDIS_PASSWORD:-}" ]; then
    REDISCLI_AUTH="${REDIS_PASSWORD}"
    export REDISCLI_AUTH
fi

RESPONSE="$(redis-cli -h 127.0.0.1 -p "${REDIS_PORT:-6379}" ping 2>&1)" || {
    echo "unhealthy: ${RESPONSE}" >&2
    exit 1
}

case "${RESPONSE}" in
    PONG) exit 0 ;;
    *)
        echo "unhealthy: unexpected PING response: ${RESPONSE}" >&2
        exit 1
        ;;
esac
