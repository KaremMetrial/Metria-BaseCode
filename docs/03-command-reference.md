# Command reference

Every `make` target and helper script. Run `make` with no arguments for the
same list in your terminal.

**Passing arguments:** targets that wrap another tool take `ARGS`:

```bash
make artisan  ARGS="route:list --except-vendor"
make composer ARGS="require laravel/horizon"
make npm      ARGS="run build"
make test     ARGS="--filter=UserTest"
```

---

## Lifecycle

| Target | Description |
|---|---|
| `make build` | Build all development images |
| `make build-nocache` | Rebuild from scratch, ignoring the layer cache |
| `make up` | Start the stack, then block until every healthcheck passes |
| `make dev` | As `up`, plus the Vite dev server (HMR) on `:5173` |
| `make down` | Stop and remove containers — **volumes survive** |
| `make restart` | Restart every service |
| `make stop` | Stop containers without removing them |
| `make start` | Start previously stopped containers |
| `make ps` | Container status |
| `make health` | Block until every healthcheck reports healthy |
| `make destroy` | **⚠** Remove containers, images **and volumes**. Prompts for confirmation. |

`build-nocache` is rarely what you want — it discards ~10 minutes of cached
extension compilation. Reach for it only when you suspect a stale cached layer.

---

## Logs & shell

| Target | Description |
|---|---|
| `make logs` | Tail all logs |
| `make logs SERVICE=nginx` | Tail one service |
| `make shell` | Bash shell in the app container, as `app` |
| `make root-shell` | **⚠** Root shell in the app container (debugging only) |
| `make db` | Interactive MySQL client on the app database |
| `make redis-cli` | Authenticated `redis-cli` session |

Valid `SERVICE` values: `app`, `nginx`, `mysql`, `redis`, `queue`, `scheduler`.

---

## Laravel

| Target | Description |
|---|---|
| `make artisan ARGS="…"` | Any Artisan command |
| `make tinker` | Tinker REPL |
| `make migrate` | Run pending migrations |
| `make migrate-fresh` | **⚠** Drop all tables, re-run every migration |
| `make seed` | Run seeders |
| `make fresh` | **⚠** `migrate:fresh --seed` |
| `make key` | Generate `APP_KEY` |
| `make optimize` | Cache config, routes, views and events |
| `make cache` | Alias for `optimize` |
| `make cache-clear` | Clear every Laravel cache |
| `make storage-link` | Create the `public/storage` symlink |
| `make queue-restart` | Gracefully restart queue workers |

> **Do not leave `make optimize` applied during development.** Once config is
> cached, Laravel stops reading `.env` entirely and your edits appear to be
> ignored. `make cache-clear` undoes it. See
> [Troubleshooting](08-troubleshooting.md#env-changes-appear-to-be-ignored).

---

## Dependencies

| Target | Description |
|---|---|
| `make install` | `composer install` + `npm install` when `package.json` exists |
| `make update` | `composer update` |
| `make composer ARGS="…"` | Any Composer command |
| `make composer-validate` | `composer validate --strict` |
| `make npm-install` | `npm install` |
| `make npm ARGS="…"` | Any npm command |
| `make build-assets` | Production asset build (`npm run build`) |

---

## Quality

| Target | Description |
|---|---|
| `make test` | Run the test suite |
| `make test-coverage` | Tests with coverage (enables Xdebug for the run) |
| `make pest` | Run Pest directly (install it first — see below) |
| `make phpunit` | Run PHPUnit directly |
| `make pint` | Fix code style in place |
| `make pint-test` | Check style without modifying files |
| `make phpstan` / `make stan` | Static analysis (Larastan, level 5) |
| `make lint` | `pint-test` + `phpstan` |

Pint, PHPUnit and Larastan are installed and configured. **Pest is not** —
adopting it restructures `tests/`, which is the application's decision:

```bash
make composer ARGS="require pestphp/pest --dev --with-all-dependencies"
make artisan  ARGS="pest:install"
make pest
```

---

## Backup & restore

| Target | Description |
|---|---|
| `make backup` | Dump the database to `backups/`, gzipped |
| `make restore FILE=backups/x.sql.gz` | **⚠** Restore a dump (overwrites the database) |

```bash
make backup
RETAIN_DAYS=30 bash docker/scripts/backup.sh    # custom retention (default 14)
make restore FILE=backups/laravel-20260731-143835.sql.gz
```

Dumps use `--single-transaction` (consistent snapshot without locking, so the
app stays online) and include routines, triggers and events — none of which are
in a default `mysqldump`, and all of which you only miss at restore time. The
script verifies gzip integrity and refuses to keep a suspiciously small file.

`backups/` is gitignored: a dump contains every row you have, password hashes
included.

---

## Production **[prod]**

| Target | Description |
|---|---|
| `make prod-build` | Build production images (vendor + assets baked in) |
| `make prod-up` | Start the production stack |
| `make prod-down` | Stop the production stack |
| `make prod-logs` | Tail production logs |
| `make prod-migrate` | Run migrations against production |
| `make prod-ps` | Production container status |

`prod-up` requires `APP_KEY` in the environment — there is no `.env` inside the
image. It fails loudly if you forget. See
[Production deployment](06-production-deployment.md).

---

## CI

| Target | Description |
|---|---|
| `make ci-build` | Build CI images |
| `make ci-up` | Start the CI stack (tmpfs datastores) |
| `make ci-test` | Run the full CI pipeline locally |
| `make ci-down` | Tear down the CI stack and its volumes |

The CI overlay puts MySQL and Redis on tmpfs and relaxes durability
(`innodb-flush-log-at-trx-commit=0`, no binlog, no doublewrite). Reckless in
production; correct for a database that is thrown away minutes later.

---

## Diagnostics

| Target | Description |
|---|---|
| `make config` | Render the merged development compose config |
| `make config-prod` | Render the merged production compose config |
| `make validate` | Lint every compose file, script and Dockerfile |
| `make stats` | Live per-container CPU/memory |
| `make info` | `php artisan about` |
| `make prune` | Remove dangling Docker data (safe) |

`make validate` checks that all three overlays parse, that every shell script is
syntactically valid, that nginx and PHP-FPM configs parse, that **PHP startup
emits no warnings**, and that every required extension is present. That startup
check exists because a deprecated ini directive prints a notice on every SAPI
launch and breaks tooling that reads stdout.

If `shellcheck` or `hadolint` are installed, they run too; if not, they are
skipped with a notice rather than failing.

---

## Helper scripts

Thin wrappers around the same compose invocation. Useful when `ARGS="…"` gets
awkward, and they work from any subdirectory.

```bash
docker/scripts/artisan  make:model Task -mfrc
docker/scripts/composer require laravel/horizon
docker/scripts/npm      run build
docker/scripts/shell               # app container
docker/scripts/shell    nginx      # any service
docker/scripts/logs     queue
docker/scripts/backup.sh
docker/scripts/restore.sh backups/dump.sql.gz
docker/scripts/wait-for-health.sh
docker/scripts/validate.sh
```

Environment variables they honour:

| Variable | Applies to | Effect |
|---|---|---|
| `COMPOSE_MODE` | all | `dev` (default), `prod` or `ci` |
| `ROOT=1` | `shell` | Open the shell as root |
| `LINES=500` | `logs` | Number of lines to tail |
| `TIMEOUT=300` | `wait-for-health.sh` | Seconds before giving up |
| `RETAIN_DAYS=30` | `backup.sh` | Prune dumps older than N days |
| `FORCE=1` | `restore.sh` | Skip the confirmation prompt |

```bash
COMPOSE_MODE=prod docker/scripts/logs app
COMPOSE_MODE=prod docker/scripts/backup.sh
```

---

## Raw Docker Compose

If you bypass the Makefile, you must reproduce two things: the
`--project-directory` (so relative paths resolve from the project root, not
`compose/`) and the `--env-file`.

```bash
docker compose \
  --project-directory . \
  --env-file .env.docker \
  -f compose/docker-compose.yml \
  -f compose/docker-compose.dev.yml \
  ps
```

Swap the second `-f` for `docker-compose.prod.yml` or `docker-compose.ci.yml`.
Also export `UID` and `GID`, which the Makefile sets for you:

```bash
export UID GID=$(id -g)
```
