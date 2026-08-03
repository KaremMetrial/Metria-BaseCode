# Daily workflow

What to run as you actually work — and, just as importantly, what you *don't*
need to run.

---

## The golden rule: PHP changes are live

Your source is bind-mounted into the containers. Edit a controller, save, hit
refresh — the change is already there. No rebuild, no restart.

This works because the development image runs OPcache with
`validate_timestamps=1`, so PHP re-checks file mtimes on every request. In
production that is switched off, which is why production images bake the code
in instead.

### When you *do* need to act

| You changed                                                       | Run                                                       |
| ----------------------------------------------------------------- | --------------------------------------------------------- |
| `app/`, `routes/`, `config/`, `resources/`, `database/` | **nothing** — already live                         |
| `.env`                                                          | `make restart` (or nothing, if no config cache is warm) |
| `composer.json`                                                 | `make composer ARGS=update`                             |
| `package.json`                                                  | `make npm-install`                                      |
| `docker/**` or `.env.docker`                                  | `make build && make up`                                 |
| `docker/nginx/**`                                               | `make build && make restart`                            |
| Added a queued Job class                                          | `make queue-restart`                                    |

That last one catches people out — see [Queues](#working-with-queues) below.

---

## Running commands

Everything runs *inside* the container, so you never need PHP, Composer or Node
on your host.

```bash
make artisan ARGS="route:list"
make artisan ARGS="make:model Task -mfrc"
make artisan ARGS="migrate:status"
```

The `ARGS=` wrapper is quoting-safe but verbose. The helper script is nicer for
interactive use:

```bash
docker/scripts/artisan make:model Task -mfrc
docker/scripts/artisan tinker
```

Add it to your `PATH` for the session if you use it a lot:

```bash
export PATH="$PWD/docker/scripts:$PATH"
artisan route:list
```

---

## Getting a shell

```bash
make shell                    # app container, as the unprivileged app user
make root-shell               # ⚠ as root — for installing debug tools only
docker/scripts/shell nginx    # any other service
docker/scripts/shell mysql
```

You are `app` (uid 1000) by default, deliberately — if a command fails on
permissions inside the container, it would fail in production too.

---

## Watching logs

Every service logs to stdout/stderr, so Docker collects all of it:

```bash
make logs                     # everything, interleaved
make logs SERVICE=app         # PHP-FPM + your Laravel logs
make logs SERVICE=nginx       # JSON access logs
make logs SERVICE=queue       # worker output — where failed jobs show up
make logs SERVICE=mysql       # slow query log lives here too
```

Because `LOG_CHANNEL=stderr`, `Log::info()` shows up in `make logs SERVICE=app`
rather than in `storage/logs/laravel.log`. That is intentional: it is how logs
reach a log aggregator in production.

Nginx access logs are JSON, so you can filter them properly:

```bash
make logs SERVICE=nginx | jq 'select(.status >= 400)'
make logs SERVICE=nginx | jq 'select(.request_time > 1) | {uri, request_time}'
```

---

## Database work

```bash
make migrate                  # apply new migrations
make migrate-fresh            # ⚠ drop every table, re-run all migrations
make fresh                    # ⚠ same, then seed
make seed
make db                       # interactive MySQL client
```

### Connecting a GUI client

MySQL is published on **127.0.0.1:3307** (not 3306 — that avoids clashing with
a MySQL already installed on your machine):

| Field    | Value         |
| -------- | ------------- |
| Host     | `127.0.0.1` |
| Port     | `3307`      |
| Database | `laravel`   |
| User     | `laravel`   |
| Password | `secret`    |

Redis is on **127.0.0.1:6380** with the password from `.env.docker`. Both bind
to loopback only, so they are not exposed to your network.

Change the ports with `FORWARD_DB_PORT` / `FORWARD_REDIS_PORT` in `.env.docker`.

---

## Adding dependencies

```bash
make composer ARGS="require laravel/horizon"
make composer ARGS="require --dev phpunit/phpunit"
make composer ARGS="remove some/package"
make update                                   # composer update
```

```bash
make npm ARGS="install alpinejs"
make npm ARGS="install -D tailwindcss"
make npm-install                              # after pulling someone else's lockfile
```

Composer runs in a dedicated container whose PHP extension set **mirrors the
runtime exactly**. That matters: resolving dependencies against a different
extension set produces a lockfile that installs fine and then fails at runtime.

Both use cache volumes (`composer_cache`, `node_cache`), so repeat installs are
fast and survive rebuilds.

---

## Frontend

```bash
make dev                      # stack + Vite dev server with HMR on :5173
make build-assets             # one-off production build
make npm ARGS="run build"     # same thing
```

`node_modules` is a **named volume**, not part of the bind mount. It keeps tens
of thousands of small files off the shared filesystem, and it stops a
host-installed `node_modules` (with binaries compiled for your host, not the
container) from leaking in.

The trade-off: **your editor cannot see `node_modules`**. If you want IDE
autocomplete for JS packages, run `npm install` on your host as well. The
container ignores it entirely.

---

## Working with queues

Two workers run continuously in the `queue` container. Dispatch a job and it is
picked up within a second or two.

```bash
make logs SERVICE=queue       # watch jobs being processed
make queue-restart            # graceful restart
make artisan ARGS="queue:failed"
make artisan ARGS="queue:retry all"
```

> **Workers hold your code in memory.** A PHP worker boots the framework once
> and then loops. If you add or change a Job class, the running worker is still
> executing the *old* code — you will see `Class "App\Jobs\Foo" not found` even
> though the file plainly exists. Run **`make queue-restart`**.

Workers are deliberately short-lived (`--max-time=3600`, `--max-jobs=1000`,
`--memory=256`). Recycling a worker is far cheaper and more predictable than
hunting a slow leak in a long-running PHP process; supervisor restarts it
immediately, so throughput is unaffected.

---

## Scheduled tasks

The `scheduler` container runs `schedule:work`, which dispatches due tasks every
minute — no crontab anywhere.

```bash
make artisan ARGS="schedule:list"     # what is registered, and when it next runs
make artisan ARGS="schedule:run"      # force a run right now
make logs SERVICE=scheduler
```

> **Never run more than one scheduler.** Two containers means every scheduled
> task fires twice. `scale: 1` is pinned in the production compose file.

---

## Debugging with Xdebug

Xdebug is installed but **off**, because loading it in `debug` mode costs
2–5× on every request. Turn it on for a session:

```bash
XDEBUG_MODE=debug make restart
```

Then listen on port **9003** in your editor. `host.docker.internal` is wired up
explicitly via `extra_hosts`, so the same setup works on Linux, macOS and
Windows.

**VS Code** (`.vscode/launch.json`):

```json
{
  "version": "0.2.0",
  "configurations": [{
    "name": "Listen for Xdebug",
    "type": "php",
    "request": "launch",
    "port": 9003,
    "pathMappings": { "/var/www/html": "${workspaceFolder}" }
  }]
}
```

**PhpStorm**: set a server named `laravel` (matching `XDEBUG_SERVER_NAME`) with
path mapping `/var/www/html` → project root.

Turn it back off when you are done — `XDEBUG_MODE=off make restart`.

For coverage instead of stepping:

```bash
make test-coverage
```

---

## Code quality

```bash
make pint          # fix style in place
make pint-test     # check only — what CI runs
make phpstan       # static analysis (Larastan, level 5)
make lint          # both
make test          # test suite
```

Run `make lint && make test` before you push; it is exactly what the CI
pipeline runs, so a green local run means a green pipeline.

---

## Stopping and cleaning up

```bash
make stop          # pause containers, keep them
make down          # remove containers — volumes and data survive
make destroy       # ⚠ remove containers, images AND volumes. Data is gone.
make prune         # reclaim dangling Docker data (safe)
```

`make down` is the normal one. Use `make destroy` when you want a genuinely
clean slate — it is also the only way to re-run `docker/mysql/init/` scripts,
which execute once against an empty data volume.

---

## Health and diagnostics

```bash
make ps            # container status
make health        # block until all healthy
make stats         # live CPU/memory per container
make info          # php artisan about
make validate      # lint the platform itself
make config        # render the fully-merged compose config
```

`make config` is the fastest way to answer "what is *actually* being applied
after all the overlay files merge?"
