#!/usr/bin/env bash
# =============================================================================
# Block until every running container reports healthy.
#
# Distinguishes the three states that matter:
#   healthy   → done
#   unhealthy → fail immediately (retrying will not help; show the logs)
#   starting  → keep waiting until the timeout
#
# A container that has exited is always a failure, even if it exited 0: nothing
# in this stack is supposed to be a one-shot job.
#
#   docker/scripts/wait-for-health.sh
#   TIMEOUT=300 COMPOSE_MODE=prod docker/scripts/wait-for-health.sh
# =============================================================================
source "$(dirname -- "${BASH_SOURCE[0]}")/_common.sh"

TIMEOUT="${TIMEOUT:-240}"
INTERVAL="${INTERVAL:-3}"

mapfile -t CONTAINERS < <(dc ps -q 2>/dev/null || true)

if [[ ${#CONTAINERS[@]} -eq 0 ]]; then
    die "no containers are running — start them with: make up"
fi

info "waiting for ${#CONTAINERS[@]} container(s) to become healthy (timeout ${TIMEOUT}s)"

deadline=$(( SECONDS + TIMEOUT ))
declare -A ANNOUNCED=()

while :; do
    all_healthy=true
    pending=()

    for cid in "${CONTAINERS[@]}"; do
        # A single inspect call for every field we need.
        read -r name state health < <(
            docker inspect --format \
                '{{.Name}} {{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' \
                "${cid}" 2>/dev/null
        ) || { all_healthy=false; pending+=("${cid:0:12} (gone)"); continue; }

        name="${name#/}"

        case "${state}:${health}" in
            running:healthy|running:none)
                if [[ -z "${ANNOUNCED[$name]:-}" ]]; then
                    if [[ "${health}" == "none" ]]; then
                        ok "${name} is running (no healthcheck defined)"
                    else
                        ok "${name} is healthy"
                    fi
                    ANNOUNCED[$name]=1
                fi
                ;;
            running:starting)
                all_healthy=false
                pending+=("${name}")
                ;;
            running:unhealthy)
                fail "${name} is UNHEALTHY"
                echo
                docker inspect --format \
                    '{{range .State.Health.Log}}--- exit {{.ExitCode}} ---{{"\n"}}{{.Output}}{{end}}' \
                    "${cid}" 2>/dev/null | tail -40
                echo
                warn "recent logs from ${name}:"
                docker logs --tail 40 "${cid}" 2>&1 | sed 's/^/    /'
                die "aborting: ${name} failed its healthcheck"
                ;;
            *)
                fail "${name} is not running (state: ${state})"
                warn "recent logs from ${name}:"
                docker logs --tail 40 "${cid}" 2>&1 | sed 's/^/    /'
                die "aborting: ${name} exited"
                ;;
        esac
    done

    if [[ "${all_healthy}" == true ]]; then
        echo
        ok "all services are healthy"
        exit 0
    fi

    if (( SECONDS >= deadline )); then
        echo
        fail "timed out after ${TIMEOUT}s; still starting: ${pending[*]}"
        for cid in "${CONTAINERS[@]}"; do
            name="$(docker inspect --format '{{.Name}}' "${cid}" 2>/dev/null || echo "${cid}")"
            warn "last 30 log lines from ${name#/}:"
            docker logs --tail 30 "${cid}" 2>&1 | sed 's/^/    /'
        done
        exit 1
    fi

    sleep "${INTERVAL}"
done
