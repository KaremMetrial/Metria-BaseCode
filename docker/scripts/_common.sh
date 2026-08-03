#!/usr/bin/env bash
# =============================================================================
# Shared preamble for every helper script.
#
# Sourced, not executed. Resolves the project root from this file's own
# location, so the scripts work from any working directory.
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd -- "${SCRIPT_DIR}/../.." && pwd)"
COMPOSE_DIR="${ROOT_DIR}/compose"
ENV_FILE="${ROOT_DIR}/.env.docker"

# UID is a READONLY builtin in bash — assigning to it aborts the script with
# "UID: readonly variable". Bash already sets it to the real user id, so it
# only needs marking for export (which is legal on a readonly variable) so
# that Compose can interpolate it. GID has no such builtin and is derived.
export UID
: "${GID:=$(id -g)}"
export GID
export DOCKER_BUILDKIT=1

# COMPOSE_MODE selects the overlay: dev (default), prod or ci.
COMPOSE_MODE="${COMPOSE_MODE:-dev}"
case "${COMPOSE_MODE}" in
    dev)  OVERLAY="${COMPOSE_DIR}/docker-compose.dev.yml"  ;;
    prod) OVERLAY="${COMPOSE_DIR}/docker-compose.prod.yml" ;;
    ci)   OVERLAY="${COMPOSE_DIR}/docker-compose.ci.yml"   ;;
    *)
        echo "unknown COMPOSE_MODE '${COMPOSE_MODE}' (expected dev|prod|ci)" >&2
        exit 2
        ;;
esac

# The compose invocation, as a shell function.
#
# ⚠ Never call this as `exec dc …`. `exec` replaces the shell with an EXTERNAL
# program and does not consider shell functions, so it would silently run
# /usr/bin/dc — the GNU desk calculator — producing baffling errors like
# "dc: invalid option -- 'T'". Call `dc` plainly and `exit $?` if you need to
# stop the script.
dc() {
    docker compose \
        --project-directory "${ROOT_DIR}" \
        --env-file "${ENV_FILE}" \
        -f "${COMPOSE_DIR}/docker-compose.yml" \
        -f "${OVERLAY}" \
        "$@"
}

C_CYAN=$'\033[0;36m'
C_GREEN=$'\033[0;32m'
C_YELLOW=$'\033[0;33m'
C_RED=$'\033[0;31m'
C_BOLD=$'\033[1m'
C_RESET=$'\033[0m'

info()  { printf '%s▶%s %s\n' "${C_CYAN}"   "${C_RESET}" "$*"; }
ok()    { printf '%s✔%s %s\n' "${C_GREEN}"  "${C_RESET}" "$*"; }
warn()  { printf '%s!%s %s\n' "${C_YELLOW}" "${C_RESET}" "$*" >&2; }
fail()  { printf '%s✘%s %s\n' "${C_RED}"    "${C_RESET}" "$*" >&2; }
die()   { fail "$*"; exit 1; }

# `-T` disables TTY allocation, which compose requires when there is no
# terminal (CI, cron, piped output).
tty_flag() { [[ -t 0 && -t 1 ]] && echo "" || echo "-T"; }

require_running() {
    local service="$1"
    if [[ -z "$(dc ps -q "${service}" 2>/dev/null)" ]]; then
        die "service '${service}' is not running — start it with: make up"
    fi
}
