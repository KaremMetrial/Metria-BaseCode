# Troubleshooting

Symptom → cause → fix. Most of these are failures that were actually hit while
building and validating this platform, not hypotheticals.

---

## First response

Three commands answer most questions:

```bash
make ps                    # what is actually running, and its health
make logs SERVICE=<name>   # why it is unhappy
make validate              # is the platform itself sane
```

`make up` already dumps the failing container's logs and aborts rather than
returning success — so if it printed something, read that first.

---

## Startup

### `make up` hangs, then prints logs and fails

A healthcheck never passed. The output names the container and shows its last
40 lines. Jump to that service's section below.

### Port is already allocated

```
Error: bind: address already in use
```

Something else owns the port. Find it and either stop it or move ours:

```bash
ss -tlnp | grep -E ':(80|3307|6380|5173)'
```

```dotenv
# .env.docker
APP_PORT=8080
FORWARD_DB_PORT=3308
FORWARD_REDIS_PORT=6381
```

Then `make down && make up`.

### `dependency failed to start: container … is unhealthy`

Compose is doing its job — it refused to start the app because a dependency
never became healthy. The dependency is the problem; read *its* logs.

---

## MySQL

### The container restarts in a loop

Check the log first — mysqld names the exact problem:

```bash
make logs SERVICE=mysql
```

**`unknown option '--<something>'`** — a directive that MySQL 8.4 **removed**.
This is the single most common cause, because most tuning guides online target
5.7 or 8.0. Removed in 8.4 and their replacements:

| Removed | Use instead |
|---|---|
| `default_authentication_plugin` | `authentication_policy` |
| `skip-host-cache` | `host_cache_size=0` |
| `skip-character-set-client-handshake` | *(nothing — Laravel issues `SET NAMES`)* |
| `expire_logs_days` | `binlog_expire_logs_seconds` |
| `innodb_log_file_size` | `innodb_redo_log_capacity` |
| `binlog_format` | *(deprecated; ROW is the default)* |
| `query_cache_*` | *(removed in 8.0)* |

`docker/mysql/my.cnf` documents each of these inline.

**`The designated data directory /var/lib/mysql/ is unusable`** — usually *not*
about the data directory at all. Look one line up for
`Could not open file '/dev/stderr.err' for error logging`. MySQL appends `.err`
to any `log_error` value without an extension, so `log_error = /dev/stderr`
becomes `/dev/stderr.err` and fails. **Leave `log_error` unset** — a foreground
mysqld writes to stderr by default, which is what you want.

**`Table 'mysql.plugin' doesn't exist`** — a previous startup aborted partway
through initialisation and left a half-built data volume. It cannot self-repair:

```bash
make down
docker volume rm laravel_mysql_data
make up
```

### Changes to `docker/mysql/init/` do nothing

Those scripts run **once**, against an empty data volume. Editing them later has
no effect.

```bash
make destroy        # ⚠ drops the volume — all data lost
make up
```

### `Access denied for user 'laravel'`

`.env` and `.env.docker` disagree, or the credentials changed after the volume
was initialised (the user was created with the *original* password).

```bash
grep -E '^DB_' .env .env.docker      # they must match
```

To change it in place:

```bash
make db
> ALTER USER 'laravel'@'%' IDENTIFIED BY 'secret';
> FLUSH PRIVILEGES;
```

### `SQLSTATE[HY000] [2002] Connection refused`

Almost always `DB_HOST=127.0.0.1` instead of `DB_HOST=mysql`. Inside the
network, services are reachable by their compose service name; `127.0.0.1`
means "this container".

---

## Redis

### `cannot create /tmp/redis.conf: Permission denied`

The entrypoint copies the baked config to tmpfs and appends the auth stanza. Two
distinct causes:

1. **The `/tmp` tmpfs is not writable by uid 999.** Docker's short-form `tmpfs:`
   syntax **silently ignores `uid=`/`gid=`**, so a restrictive `mode=` leaves it
   root-owned. It must be `mode=1777`.
2. **The copy was made read-only.** The baked config is `0444`; `cp` preserves
   that mode, so appending fails. The entrypoint uses `cat > file` instead,
   which creates it fresh under the umask.

### `cp: cannot stat '/usr/local/etc/redis/redis.conf': Permission denied`

`COPY --chmod=0444` applied `0444` to the **parent directory it created**, and a
directory without its execute bit cannot be traversed. Create the directory in a
separate `RUN` before the `COPY`, and `chmod` the file afterwards.

### `NOAUTH Authentication required`

The app is not sending the password. Container environment beats `.env`, so:

```bash
grep REDIS_PASSWORD .env.docker
make artisan ARGS="tinker --execute='var_dump(config(\"database.redis.default.password\"));'"
```

### `[redis] FATAL: REDIS_PASSWORD is required when APP_ENV=production`

Working as designed — an unauthenticated Redis will not start in production.
Set `REDIS_PASSWORD` or use `REDIS_PASSWORD_FILE`.

---

## Nginx

### `mkdir() "/tmp/nginx/client_body" failed (2: No such file or directory)`

nginx creates each `*_temp_path` with a **single `mkdir`** and does not create
intermediate parents. With `/tmp` mounted as a fresh tmpfs, `/tmp/nginx/` does
not exist. Keep temp paths one level deep: `/tmp/nginx_client_body`.

### 404 on everything

The document root is wrong, or `public/index.php` is not in the container:

```bash
docker/scripts/shell nginx
ls -la /var/www/html/public/index.php
echo "$NGINX_ROOT"      # must be /var/www/html/public
```

### 502 Bad Gateway

nginx cannot reach PHP-FPM.

```bash
make ps                        # is app healthy?
make logs SERVICE=app
make logs SERVICE=nginx | tail -20
```

`host not found in upstream "app:9000"` means nginx started before the app
existed, or you are running the nginx container detached from the compose
network. `connect() failed` means FPM is not listening — check the app logs.

### 413 Request Entity Too Large

Three limits must all be raised. See
[Configuration → Upload size](04-configuration.md#upload-size).

### 429 Too Many Requests

Rate limiting. Expected under load tests and scrapers; if it is you, raise the
zone in `docker/nginx/nginx.conf`, then `make build && make restart`.

Behind a proxy, this often means **every request looks like one IP**. Configure
`TrustProxies` and nginx's `real_ip` module.

### Two `Cache-Control` headers on static files

`expires` generates its own `Cache-Control` alongside an explicit `add_header`.
Use one or the other — this platform uses `add_header` only, because
`immutable` cannot be expressed with `expires`.

---

## PHP / build

### The image build fails on `install-php-extensions`

Look for deprecation notices just above the failure:

```
Deprecated: PHP Startup: Use of mbstring.http_input is deprecated
Error loading the "xdebug" extension
```

The extension is fine. A **deprecated ini directive** makes PHP print to stderr
on every SAPI launch, and the installer's load-check treats that output as a
failure. Deprecated in PHP 8.4 and removed from `php.ini` here:

`mbstring.http_input`, `mbstring.http_output`, `mbstring.internal_encoding`,
`session.sid_length`, `session.sid_bits_per_character`, `E_STRICT`.

`make validate` includes a check that PHP startup is completely silent, which
catches this class of problem before it reaches a build.

### `ALERT: [pool www] user has not been defined`

PHP-FPM refuses to run as root without an explicit `user`. The container
normally starts as uid 1000, and the entrypoint drops privileges if it started
as root — so you only see this if you bypassed the entrypoint
(`docker run --user 0 … php-fpm`).

### Composer resolves fine but the app fails at runtime

The Composer container's extension set must mirror the runtime's, or you get a
lockfile that is valid where it was resolved and broken where it runs. Use
`make composer`, which runs the dedicated container built for exactly this.

---

## Laravel

### `.env` changes appear to be ignored

You ran `make optimize` (or `config:cache`). Laravel then reads
`bootstrap/cache/config.php` and stops consulting `.env` entirely.

```bash
make cache-clear
```

Do not keep the config cache warm during development — it exists to save
milliseconds in production.

### `MissingAppKeyException` — but `APP_KEY` is set in `.env`

Two candidates:

1. **A stale config cache** (see above). `make cache-clear`.
2. **An empty environment variable is shadowing it.** Container environment
   beats `.env`, so `APP_KEY=` injected by compose *masks* the real value. This
   is exactly why `APP_KEY` is set only in the production overlay.

```bash
make artisan ARGS="tinker --execute='var_dump(env(\"APP_KEY\"));'"
```

If that prints `string(0) ""`, something is injecting an empty variable.

### Permission denied writing to `storage/`

`UID`/`GID` do not match your host user. They are **build arguments**, so a
rebuild is required:

```bash
id -u; id -g                 # should be 1000/1000 for the defaults
make build && make up
```

If you invoked `docker compose` directly rather than via `make`, export them
first — the Makefile does it for you.

As a one-off repair:

```bash
make root-shell
chown -R app:app storage bootstrap/cache
```

### Queue jobs are not processed

```bash
make ps                    # is queue healthy?
make logs SERVICE=queue
```

**`Class "App\Jobs\Foo" not found`** even though the file exists — the worker
booted before the class did and holds the old code in memory.

```bash
make queue-restart
```

Do this after **every** deploy.

**Workers restarting in a loop** — usually they cannot reach Redis or MySQL. The
entrypoint's `WAIT_FOR_*` gates say so explicitly in the log.

### Scheduled tasks never run

```bash
make artisan ARGS="schedule:list"    # is anything registered?
make logs SERVICE=scheduler          # look for "Running scheduled tasks"
```

If tasks run **twice**, you have more than one scheduler. There must be exactly
one.

### `Vite manifest not found`

Assets were never built, or were built into the wrong place:

```bash
make build-assets
ls public/build/manifest.json
```

For development with HMR, use `make dev` instead — Vite serves from memory and
no manifest exists.

---

## Node / frontend

### `npm error ... The operation was rejected by your operating system`

The `node_modules` named volume was created **root-owned**. Docker initialises
an empty named volume from the image path it covers, inheriting that path's
ownership — if the path does not exist in the image, you get root.

The image now creates `/var/www/html/node_modules` explicitly. If you hit this
on an old volume:

```bash
docker volume rm laravel_node_modules laravel_node_cache
make npm-install
```

### `npm warn invalid config omit=""`

An invalid key in `.npmrc`. `omit` accepts only `dev`, `optional` or `peer` —
an empty value is not valid. Remove the line.

### `sh: 1: vite: not found`

`npm install` did not complete. Re-run it and read the output.

### The editor cannot see `node_modules`

Expected — it is a named volume, not part of the bind mount. Run `npm install`
on your host as well if you want IDE resolution; the container ignores it.

---

## Compose / scripts

### `UID: readonly variable`

`UID` is a **readonly builtin in bash**; assigning to it aborts the script.
Export it without assigning:

```bash
export UID              # legal — marks for export, does not assign
GID="$(id -g)"; export GID
```

### `mapping values are not allowed in this context`

A YAML parse error, almost always an unquoted scalar containing `": "`. This
bites with compose's error-default syntax:

```yaml
APP_KEY: ${APP_KEY:?generate one with: make key}     # ✗ breaks
APP_KEY: "${APP_KEY:?generate one with make key}"    # ✓
```

### Compose can't find files, or builds the wrong thing

You omitted `--project-directory`. The compose files live in `compose/` but
their relative paths are written against the project root:

```bash
docker compose --project-directory . --env-file .env.docker \
  -f compose/docker-compose.yml -f compose/docker-compose.dev.yml ps
```

Use `make` and this is handled for you.

### An image built the wrong stage

If a service's `build` has no `target`, Compose builds the **last stage in the
Dockerfile**. For nginx that is `production`, which needs a build context only
the production overlay supplies. Always set `target` explicitly.

### `make validate` fails on the prod compose config

The production overlay requires `APP_KEY`. `validate.sh` supplies a throwaway
value for the render; if you are calling compose by hand, export one.

---

## Performance

### Everything is slow on macOS or Windows

Bind mounts across the VM boundary are the usual cause.

- **Windows**: keep the project on the Linux filesystem inside WSL2
  (`/home/...`, never `/mnt/c/...`). This alone is often 10×.
- **macOS**: enable VirtioFS in Docker Desktop.
- Confirm `node_modules` is still a named volume — it exists for this reason.

### PHP is slow after enabling Xdebug

Expected: 2–5× on every request while loaded in `debug` mode. Turn it off when
you are not stepping:

```bash
XDEBUG_MODE=off make restart
```

### Queries are slow

The slow query log is on by default at 1 second:

```bash
make logs SERVICE=mysql | grep -A5 'Query_time'
```

Then `EXPLAIN` the offender. If the working set has outgrown memory, raise
`innodb_buffer_pool_size` **and** `DB_MEMORY_LIMIT` together.

---

## Full reset

When you want certainty rather than a diagnosis:

```bash
make destroy          # ⚠ containers, images AND volumes — all data lost
make build
make install
make up
make migrate
```

To keep your data and only rebuild the images:

```bash
make down
make build-nocache
make up
```

---

## Gathering information for a bug report

```bash
{
  echo "=== versions ==="; docker --version; docker compose version; make --version | head -1
  echo "=== ps ===";       make ps
  echo "=== validate ==="; make validate
  echo "=== config ===";   make config
  for s in app nginx mysql redis queue scheduler; do
    echo "=== logs: $s ==="; make logs SERVICE=$s 2>&1 | tail -50
  done
} > /tmp/platform-report.txt
```

**Redact `/tmp/platform-report.txt` before sharing it** — `make config` renders
every interpolated value, including passwords.
