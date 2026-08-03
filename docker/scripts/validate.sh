#!/usr/bin/env bash
# =============================================================================
# Static validation of the platform itself (no containers required).
#
# Checks, in order of how expensive they are:
#   1. every compose overlay parses and interpolates
#   2. shell scripts are syntactically valid (and lint clean, if shellcheck is
#      installed)
#   3. Dockerfiles lint clean (if hadolint is installed)
#   4. nginx and PHP-FPM configuration parses (only if the images exist)
#
# Optional tools are skipped with a notice rather than failing the run, so this
# is safe to call from CI and from a bare workstation alike.
# =============================================================================
source "$(dirname -- "${BASH_SOURCE[0]}")/_common.sh"

FAILURES=0
check() {
    local label="$1"; shift
    if "$@" >/tmp/validate.$$ 2>&1; then
        ok "${label}"
    else
        fail "${label}"
        sed 's/^/    /' /tmp/validate.$$ >&2
        FAILURES=$(( FAILURES + 1 ))
    fi
    rm -f /tmp/validate.$$
}

printf '%s\n' "${C_BOLD}1. Compose files${C_RESET}"
# The production overlay declares APP_KEY as required (${APP_KEY:?...}) so a
# real deployment fails loudly rather than booting without an encryption key.
# That check would also abort this purely structural validation, so a throwaway
# value is supplied for the render. It is never used to run anything.
VALIDATE_APP_KEY="base64:$(head -c 32 /dev/zero | base64)"
for mode in dev prod ci; do
    check "compose config (${mode})" \
        env COMPOSE_MODE="${mode}" APP_KEY="${VALIDATE_APP_KEY}" bash -c '
            source "'"${SCRIPT_DIR}"'/_common.sh"
            dc config --quiet
        '
done

printf '\n%s\n' "${C_BOLD}2. Shell scripts${C_RESET}"
mapfile -t SCRIPTS < <(find "${ROOT_DIR}/docker" -type f \( -name '*.sh' -o -name 'artisan' -o -name 'composer' -o -name 'npm' -o -name 'shell' -o -name 'logs' \) | sort)
for script in "${SCRIPTS[@]}"; do
    check "bash -n $(basename "${script}")" bash -n "${script}"
done

if command -v shellcheck >/dev/null 2>&1; then
    for script in "${SCRIPTS[@]}"; do
        check "shellcheck $(basename "${script}")" shellcheck -x -S warning "${script}"
    done
else
    warn "shellcheck not installed — skipping shell lint"
fi

printf '\n%s\n' "${C_BOLD}3. Dockerfiles${C_RESET}"
mapfile -t DOCKERFILES < <(find "${ROOT_DIR}/docker" -name Dockerfile | sort)
if command -v hadolint >/dev/null 2>&1; then
    for df in "${DOCKERFILES[@]}"; do
        check "hadolint ${df#"${ROOT_DIR}/"}" hadolint "${df}"
    done
elif docker image inspect hadolint/hadolint:latest >/dev/null 2>&1; then
    for df in "${DOCKERFILES[@]}"; do
        check "hadolint ${df#"${ROOT_DIR}/"}" \
            docker run --rm -i hadolint/hadolint:latest hadolint - <"${df}"
    done
else
    warn "hadolint not installed — skipping Dockerfile lint"
    for df in "${DOCKERFILES[@]}"; do
        # Minimal sanity check: a Dockerfile with no FROM is definitely broken.
        check "FROM present in ${df#"${ROOT_DIR}/"}" grep -qE '^\s*FROM' "${df}"
    done
fi

printf '\n%s\n' "${C_BOLD}4. Service configuration${C_RESET}"
NGINX_IMAGE="${COMPOSE_PROJECT_NAME:-laravel}/nginx:${IMAGE_TAG:-latest}"
APP_IMAGE="${COMPOSE_PROJECT_NAME:-laravel}/app:${IMAGE_TAG:-latest}"

if docker image inspect "${NGINX_IMAGE}" >/dev/null 2>&1; then
    # `nginx -t` needs the rendered template, so run the real entrypoint first.
    #
    # NGINX_FPM_HOST is overridden to a literal address because nginx resolves
    # upstream hostnames at config-PARSE time. This container is not attached
    # to the compose network, so the real `app` name would fail to resolve and
    # report "host not found in upstream" — a DNS artifact of the test harness,
    # not a syntax error in the configuration being checked.
    check "nginx -t (config syntax)" \
        docker run --rm --entrypoint sh -e NGINX_FPM_HOST=127.0.0.1 "${NGINX_IMAGE}" -c '
            /docker-entrypoint.sh nginx -t 2>&1
        '
else
    warn "${NGINX_IMAGE} not built — skipping nginx -t (run: make build)"
fi

if docker image inspect "${APP_IMAGE}" >/dev/null 2>&1; then
    check "php-fpm -t"  docker run --rm --entrypoint php-fpm "${APP_IMAGE}" -t
    check "php -v"      docker run --rm --entrypoint php     "${APP_IMAGE}" -v

    # PHP startup must be SILENT. A deprecated ini directive (they change every
    # minor release — mbstring.http_input, session.sid_length, E_STRICT …) makes
    # PHP print a notice on every single SAPI launch. Besides the log noise,
    # that output breaks anything parsing stdout and makes the PECL extension
    # installer treat a perfectly good module as unloadable.
    check "php startup emits no warnings" \
        docker run --rm --entrypoint sh "${APP_IMAGE}" -c '
            out="$(php -r "" 2>&1)"
            if [ -n "$out" ]; then
                echo "unexpected output on PHP startup:" >&2
                echo "$out" >&2
                exit 1
            fi
        '

    # Every extension Laravel and this platform rely on must actually be there.
    check "required PHP extensions present" \
        docker run --rm --entrypoint php "${APP_IMAGE}" -r '
            $required = ["pdo_mysql","mysqli","pdo_pgsql","pgsql","intl","mbstring",
                         "zip","gd","imagick","redis","pcntl","bcmath","exif",
                         "soap","sockets","Zend OPcache"];
            $loaded = array_map("strtolower", get_loaded_extensions());
            $missing = [];
            foreach ($required as $ext) {
                if (!in_array(strtolower($ext), $loaded, true)) { $missing[] = $ext; }
            }
            if ($missing) {
                fwrite(STDERR, "missing extensions: " . implode(", ", $missing) . PHP_EOL);
                exit(1);
            }
        '
else
    warn "${APP_IMAGE} not built — skipping PHP config test (run: make build)"
fi

echo
if (( FAILURES > 0 )); then
    die "${FAILURES} check(s) failed"
fi
ok "all validation checks passed"
