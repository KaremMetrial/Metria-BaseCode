#!/bin/sh
# =============================================================================
# Redis entrypoint
#
# Merges the baked-in config with a runtime-generated auth stanza. This keeps
# the password out of:
#   - the image layers        (it is not in redis.conf)
#   - the process arguments   (no `--requirepass` on the command line)
#   - `docker inspect` output beyond the env var itself
#
# Supports REDIS_PASSWORD_FILE for Docker secrets.
# =============================================================================
set -eu

SOURCE_CONFIG="${REDIS_CONFIG:-/usr/local/etc/redis/redis.conf}"
RUNTIME_CONFIG="/tmp/redis.conf"

if [ -n "${REDIS_PASSWORD_FILE:-}" ] && [ -r "${REDIS_PASSWORD_FILE}" ]; then
    REDIS_PASSWORD="$(cat "${REDIS_PASSWORD_FILE}")"
fi
REDIS_PASSWORD="${REDIS_PASSWORD:-}"

# `cat >` rather than `cp`: cp preserves the source file's mode, and the baked
# config is intentionally 0444 (read-only), so the copy would be read-only too
# and appending the auth stanza below would fail with "Permission denied".
# A redirect creates the file fresh under the umask instead — 0600, owned by
# the redis user, which is what a file holding a password should be.
umask 077
cat "${SOURCE_CONFIG}" > "${RUNTIME_CONFIG}"

if [ -n "${REDIS_PASSWORD}" ]; then
    printf '\n# --- injected at runtime ---\nrequirepass %s\n' "${REDIS_PASSWORD}" >> "${RUNTIME_CONFIG}"
    echo "[redis] authentication enabled"
else
    # Fail loudly in production rather than exposing an unauthenticated Redis.
    if [ "${APP_ENV:-local}" = "production" ]; then
        echo "[redis] FATAL: REDIS_PASSWORD is required when APP_ENV=production" >&2
        exit 1
    fi
    echo "[redis] WARNING: no REDIS_PASSWORD set — running without authentication" >&2
fi

# Allow overriding the maxmemory ceiling without rebuilding the image.
if [ -n "${REDIS_MAXMEMORY:-}" ]; then
    printf 'maxmemory %s\n' "${REDIS_MAXMEMORY}" >> "${RUNTIME_CONFIG}"
fi

if [ "$#" -eq 0 ]; then
    set -- redis-server "${RUNTIME_CONFIG}"
fi

exec "$@"
