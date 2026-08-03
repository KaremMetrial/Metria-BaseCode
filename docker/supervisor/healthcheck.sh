#!/usr/bin/env bash
# =============================================================================
# Supervisor healthcheck (queue / scheduler containers)
#
# Asks supervisord for the real state of every managed program and fails unless
# all of them are RUNNING. A plain `pgrep php` would report healthy while a
# worker is crash-looping, because supervisor keeps respawning it.
# =============================================================================
set -euo pipefail

CONFIG="${SUPERVISOR_CONFIG:-/etc/supervisor/supervisord.conf}"

STATUS="$(supervisorctl -c "${CONFIG}" status 2>&1)" || {
    # supervisorctl exits non-zero when any program is not RUNNING, so inspect
    # the output before deciding — but a missing socket is always fatal.
    if grep -qiE 'refused connection|no such file|unix:///' <<<"${STATUS}"; then
        echo "unhealthy: cannot reach supervisord" >&2
        echo "${STATUS}" >&2
        exit 1
    fi
}

# Drop blank lines before judging: a trailing newline would otherwise count as
# a line that "is not RUNNING" and fail an entirely healthy container.
STATUS="$(grep -vE '^[[:space:]]*$' <<<"${STATUS}" || true)"

if [[ -z "${STATUS}" ]]; then
    echo "unhealthy: supervisord reported no programs" >&2
    exit 1
fi

if grep -qvE '\bRUNNING\b' <<<"${STATUS}"; then
    echo "unhealthy: not all programs are RUNNING" >&2
    echo "${STATUS}" >&2
    exit 1
fi

echo "${STATUS}"
exit 0
