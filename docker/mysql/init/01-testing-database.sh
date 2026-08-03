#!/bin/bash
# =============================================================================
# Runs ONCE, on first initialisation of an empty data volume.
#
# Re-running requires dropping the volume — editing this file has no effect on
# an already-initialised database.
#
# A shell script rather than plain .sql so credentials come from the
# environment (or Docker secrets) instead of being baked into the image.
#
# The primary database/user are created by the image itself from
# MYSQL_DATABASE / MYSQL_USER / MYSQL_PASSWORD. This adds only the extras.
#
# AUTHENTICATION NOTE
# -------------------
# This file is executable, so the official entrypoint EXECUTES it as a
# subprocess rather than sourcing it. That subprocess does not inherit the
# entrypoint's internal `mysql` command array, and MYSQL_PWD is not exported —
# the entrypoint sets it inline, per command. So the password must be supplied
# here explicitly, or every statement fails with:
#
#   ERROR 1045 (28000): Access denied for user 'root'@'localhost'
#                       (using password: NO)
#
# MYSQL_PWD is used rather than --password= so the secret never appears in the
# process list.
# =============================================================================
set -euo pipefail

APP_USER="${MYSQL_USER:-laravel}"
TEST_DATABASE="${MYSQL_TEST_DATABASE:-${MYSQL_DATABASE:-laravel}_testing}"

# Support Docker secrets: MYSQL_ROOT_PASSWORD_FILE takes precedence.
if [ -n "${MYSQL_ROOT_PASSWORD_FILE:-}" ] && [ -r "${MYSQL_ROOT_PASSWORD_FILE}" ]; then
    MYSQL_ROOT_PASSWORD="$(cat "${MYSQL_ROOT_PASSWORD_FILE}")"
fi

if [ -z "${MYSQL_ROOT_PASSWORD:-}" ]; then
    echo "[init] FATAL: MYSQL_ROOT_PASSWORD is not set; cannot create ${TEST_DATABASE}" >&2
    exit 1
fi

export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"

echo "[init] creating test database '${TEST_DATABASE}' for user '${APP_USER}'"

mysql --protocol=socket -uroot <<-SQL
	CREATE DATABASE IF NOT EXISTS \`${TEST_DATABASE}\`
	    CHARACTER SET utf8mb4
	    COLLATE utf8mb4_0900_ai_ci;

	-- Laravel migrations create and drop tables, views and (for some
	-- packages) routines, so the app user needs more than plain CRUD.
	GRANT ALL PRIVILEGES ON \`${TEST_DATABASE}\`.* TO '${APP_USER}'@'%';

	FLUSH PRIVILEGES;
SQL

echo "[init] '${TEST_DATABASE}' ready"
