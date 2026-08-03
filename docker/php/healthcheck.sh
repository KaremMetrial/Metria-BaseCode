#!/bin/sh
# =============================================================================
# PHP-FPM healthcheck
#
# Performs a real FastCGI request against the pool's ping endpoint. This proves
# the master is up, a worker was available, and it completed a request — unlike
# `php-fpm -t`, which only re-parses the config file and would happily report
# "healthy" for a pool that is wedged, saturated, or not listening at all.
# =============================================================================
set -eu

FPM_HOST="${PHP_FPM_HOST:-127.0.0.1}"
FPM_PORT="${PHP_FPM_PORT:-9000}"
PING_PATH="${PHP_FPM_PING_PATH:-/ping}"
EXPECTED="${PHP_FPM_PING_RESPONSE:-pong}"

RESPONSE="$(
    SCRIPT_NAME="${PING_PATH}" \
    SCRIPT_FILENAME="${PING_PATH}" \
    REQUEST_METHOD=GET \
    REQUEST_URI="${PING_PATH}" \
    QUERY_STRING='' \
    cgi-fcgi -bind -connect "${FPM_HOST}:${FPM_PORT}" 2>/dev/null
)" || {
    echo "unhealthy: cannot reach php-fpm at ${FPM_HOST}:${FPM_PORT}" >&2
    exit 1
}

case "${RESPONSE}" in
    *"${EXPECTED}"*)
        exit 0
        ;;
    *)
        echo "unhealthy: unexpected ping response from php-fpm" >&2
        echo "${RESPONSE}" >&2
        exit 1
        ;;
esac
