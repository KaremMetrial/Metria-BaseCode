#!/usr/bin/env bash
# =============================================================================
# Laravel container entrypoint
#
# Responsibilities:
#   1. Guarantee Laravel's writable directories exist
#   2. Repair ownership/permissions *only when they are actually wrong*
#   3. Optionally block until MySQL / Redis accept connections
#   4. Optionally run deploy-time Artisan steps (opt-in, never implicit)
#   5. Drop from root to the unprivileged app user before exec'ing the command
#
# Everything here is idempotent and safe to re-run.
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"
APP_USER="${APP_USER:-app}"

log()  { printf '\033[0;36m[entrypoint]\033[0m %s\n' "$*"; }
warn() { printf '\033[0;33m[entrypoint]\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[0;31m[entrypoint]\033[0m %s\n' "$*" >&2; exit 1; }

cd "${APP_DIR}"

# -----------------------------------------------------------------------------
# 1. Writable directories
# -----------------------------------------------------------------------------
WRITABLE_DIRS=(
    storage/app/public
    storage/framework/cache/data
    storage/framework/sessions
    storage/framework/testing
    storage/framework/views
    storage/logs
    bootstrap/cache
)

ensure_directories() {
    local dir
    for dir in "${WRITABLE_DIRS[@]}"; do
        [[ -d "${dir}" ]] || mkdir -p "${dir}" 2>/dev/null || {
            warn "could not create ${dir} (read-only mount?) — continuing"
            continue
        }
    done
}

# -----------------------------------------------------------------------------
# 2. Permissions
#
# A recursive chown across a large bind mount costs seconds on every boot, so
# only pay for it when the top-level owner is actually wrong.
# -----------------------------------------------------------------------------
fix_permissions() {
    [[ "$(id -u)" == "0" ]] || return 0

    local uid gid dir owner
    uid="$(id -u "${APP_USER}")"
    gid="$(id -g "${APP_USER}")"

    for dir in storage bootstrap/cache; do
        [[ -d "${dir}" ]] || continue
        owner="$(stat -c '%u' "${dir}")"
        if [[ "${owner}" != "${uid}" ]]; then
            log "repairing ownership of ${dir} → ${uid}:${gid}"
            chown -R "${uid}:${gid}" "${dir}" || warn "chown ${dir} failed"
        fi
    done

    chmod -R u+rwX,g+rwX "${WRITABLE_DIRS[@]}" 2>/dev/null || true
}

# -----------------------------------------------------------------------------
# 3. Dependency gates
# -----------------------------------------------------------------------------
wait_for_tcp() {
    local host="$1" port="$2" label="$3" timeout="${4:-60}" waited=0

    log "waiting for ${label} at ${host}:${port} (timeout ${timeout}s)"
    until php -r '
        $fp = @fsockopen($argv[1], (int) $argv[2], $errno, $errstr, 2);
        if ($fp === false) { exit(1); }
        fclose($fp);
        exit(0);
    ' "${host}" "${port}" 2>/dev/null; do
        waited=$((waited + 2))
        if (( waited >= timeout )); then
            die "${label} did not become reachable within ${timeout}s"
        fi
        sleep 2
    done
    log "${label} is reachable"
}

wait_for_dependencies() {
    if [[ "${WAIT_FOR_DB:-false}" == "true" ]]; then
        wait_for_tcp "${DB_HOST:-mysql}" "${DB_PORT:-3306}" "MySQL" "${WAIT_TIMEOUT:-90}"
    fi
    if [[ "${WAIT_FOR_REDIS:-false}" == "true" ]]; then
        wait_for_tcp "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}" "Redis" "${WAIT_TIMEOUT:-90}"
    fi
}

# -----------------------------------------------------------------------------
# 4. Optional deploy steps (opt-in only — never migrate behind the operator's back)
#
# Runs as the app user even when the container started as root, so that any
# file these commands emit (compiled views, caches) is owned correctly.
# -----------------------------------------------------------------------------
run_deploy_steps() {
    [[ -f artisan ]] || return 0

    local php=(php)
    [[ "$(id -u)" == "0" ]] && php=(gosu "${APP_USER}" php)

    if [[ "${AUTO_MIGRATE:-false}" == "true" ]]; then
        log "running database migrations (AUTO_MIGRATE=true)"
        "${php[@]}" artisan migrate --force --no-interaction
    fi

    if [[ "${AUTO_OPTIMIZE:-false}" == "true" ]]; then
        log "caching config/routes/views (AUTO_OPTIMIZE=true)"
        "${php[@]}" artisan optimize --no-interaction
    fi

    if [[ "${AUTO_STORAGE_LINK:-false}" == "true" && ! -e public/storage ]]; then
        log "linking public/storage (AUTO_STORAGE_LINK=true)"
        "${php[@]}" artisan storage:link --no-interaction || warn "storage:link failed"
    fi
}

# -----------------------------------------------------------------------------
# Main
# -----------------------------------------------------------------------------
ensure_directories
fix_permissions
wait_for_dependencies
run_deploy_steps

log "starting: $*"

if [[ "$(id -u)" == "0" ]]; then
    log "dropping privileges to ${APP_USER}"
    exec gosu "${APP_USER}" "$@"
fi

exec "$@"
