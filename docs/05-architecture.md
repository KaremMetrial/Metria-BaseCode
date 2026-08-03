# Architecture

How the platform is built, and why each decision was made that way.

---

## The stack

```text
                        ┌──────────────────────────────┐
   host :80 ───────────▶│ nginx        (uid 101)       │
                        │ read-only rootfs, no caps    │
                        └──────────────┬───────────────┘
                                       │ FastCGI :9000
                                       │ (keepalive pool)
        ┌──────────────────────────────┼──────────────────────────────┐
        │                              │                              │
┌───────▼────────┐            ┌────────▼────────┐            ┌────────▼────────┐
│ app            │            │ queue           │            │ scheduler       │
│ php-fpm        │            │ supervisor →    │            │ supervisor →    │
│ uid 1000       │            │ queue:work × N  │            │ schedule:work   │
└───────┬────────┘            └────────┬────────┘            └────────┬────────┘
        │                              │                              │
        └──────────────────────────────┼──────────────────────────────┘
                                       │
                    ══════════ backend (internal: true) ══════════
                                       │
                    ┌──────────────────┴──────────────────┐
                    │                                     │
            ┌───────▼────────┐                   ┌────────▼────────┐
            │ mysql 8.4      │                   │ redis 8         │
            │ InnoDB tuned   │                   │ AOF + RDB       │
            └────────────────┘                   └─────────────────┘
```

| Service | Image | Runs as | Published |
|---|---|---|---|
| `nginx` | `nginx-unprivileged:1.29` | uid 101 | **:80** — the only one |
| `app` | `php:8.4-fpm` | uid 1000 | no |
| `queue` | same image as `app` | uid 1000 | no |
| `scheduler` | same image as `app` | uid 1000 | no |
| `mysql` | `mysql:8.4` | uid 999 (mysqld) | loopback only, dev |
| `redis` | `redis:8` | uid 999 | loopback only, dev |
| `composer` | `php:8.4-cli` | uid 1000 | tooling, on demand |
| `node` | `node:22` | uid 1000 | tooling, `:5173` in dev |

---

## Two networks

```yaml
networks:
  edge:                  # published traffic + outbound egress
    driver: bridge
  backend:               # datastores
    driver: bridge
    internal: true
```

`internal: true` **removes the default route** from that network. MySQL and
Redis sit *only* on `backend`, which means:

- They cannot reach the internet. A compromised database container has nowhere
  to exfiltrate to and cannot pull a second-stage payload.
- Nothing outside the compose project can route to them.

The application containers straddle both networks: `backend` to reach the
datastores, `edge` for outbound calls (webhooks, third-party APIs) and to
receive traffic from nginx.

---

## One image, three roles

`app`, `queue` and `scheduler` are the **same image**. The role is chosen at
runtime:

```ini
# docker/supervisor/supervisord.conf
[include]
files = /etc/supervisor/conf.d/%(ENV_SUPERVISOR_ROLE)s.conf
```

Set `SUPERVISOR_ROLE=queue` and supervisor loads only `queue.conf`; set
`scheduler` and it loads only `scheduler.conf`.

**Why not three images?** Because then the worker executing a job could be
running different code from the container that queued it. One artifact makes
that class of bug impossible, and it cuts build time and registry storage by
roughly two thirds.

The `app` container skips supervisor entirely and runs `php-fpm` directly.

---

## The PHP image

Five stages in `docker/php/Dockerfile`:

```text
        ┌──────────┐
        │   base   │  extensions, user, config, entrypoint
        └────┬─────┘
   ┌─────────┴──────────┐
   ▼                    ▼
┌─────────────┐   ┌──────────┐      ┌────────┐
│ development │   │  vendor  │      │ assets │  (node:22)
│ +Xdebug     │   │ composer │      │  vite  │
│ +Composer   │   └────┬─────┘      └───┬────┘
└─────────────┘        │                │
                       └───────┬────────┘
                               ▼
                        ┌─────────────┐
                        │ production  │  no Composer, no Node, no Xdebug
                        └─────────────┘
```

**`base`** installs ~16 extensions via
[`mlocati/php-extension-installer`](https://github.com/mlocati/docker-php-extension-installer),
which resolves build dependencies, compiles, strips them again, and pins PECL
versions that actually work with the target PHP release. Doing this by hand with
`pecl install` is where most Laravel Dockerfiles break on a new PHP version —
`imagick` on PHP 8.4 being the classic example.

**`vendor`** copies `composer.json`/`composer.lock` *first*, installs, and only
then copies the source:

```dockerfile
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader …
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative
```

That ordering means editing a controller does not invalidate the dependency
layer. A BuildKit cache mount keeps Composer's download cache between builds.

The `assets` stage detects whether the application has a `package.json`.
API-only applications receive an empty `public/build` directory and do not
need Node dependencies; applications with a frontend install from their
lockfile and run the configured build script.

**`production`** copies the finished `vendor/` and the compiled `public/build`
into a clean `base`. Composer, Node and Xdebug are simply never installed in it —
verified: `php artisan about` reports `Composer Version: -` in production.

---

## The nginx image

Built on **`nginx-unprivileged`**, not the stock `nginx` image. The stock image
runs its master process as root and drops only the workers; this one runs
everything as uid 101, which is why the container needs no capabilities at all
and works with a read-only root filesystem.

The consequence is that it listens on **8080**, and compose maps `80:8080`.

Configuration is split so you can change one thing without reading all of it:

| File | Contains |
|---|---|
| `nginx.conf` | http block, JSON log format, rate-limit zones, timeouts |
| `templates/default.conf.template` | The server block, rendered by `envsubst` at boot |
| `snippets/security-headers.conf` | Response headers |
| `snippets/gzip.conf` | Compression types |
| `snippets/fastcgi-php.conf` | FastCGI parameters |
| `snippets/static-assets.conf` | Shared static-file behaviour |
| `snippets/hardening.conf` | Path denials |

### Only `index.php` is executable

```nginx
location ~ ^/index\.php(/|$) { fastcgi_pass php_fpm; … }
location ~ \.php$           { deny all; return 404; }
```

Order matters — the first regex wins. The effect is that a `.php` file
smuggled into an upload directory is served as 404 rather than executed. This
is verified in testing by planting `public/uploads/evil.php` and confirming it
neither executes nor discloses its source.

### In production, assets are baked into the edge image

```yaml
nginx:
  build:
    target: production
    additional_contexts:
      app_image: "service:app"
```

Compose builds `app` first and exposes its image as a named build context, so
nginx copies the *compiled* `public/` tree straight out of it. No shared volume,
no dependency on the PHP container being up to serve a CSS file, and no way for
the two to drift apart.

---

## Healthchecks that do real work

Every healthcheck performs an actual operation. The alternative — a check that
passes while the service is broken — is worse than none, because it makes
`depends_on: service_healthy` a lie.

| Service | Check | Why not the obvious thing |
|---|---|---|
| `app` | FastCGI request to FPM's `/ping` via `cgi-fcgi` | `php-fpm -t` only re-parses the config file. It reports healthy for a pool that is wedged, saturated, or not listening at all. |
| `nginx` | `curl` to `/healthz` | — |
| `mysql` | `SELECT 1` over TCP | mysqld binds the port long before it accepts queries. A port probe goes healthy while `migrate` still fails. |
| `redis` | `redis-cli ping` via `REDISCLI_AUTH` | `-a` would leak the password into `ps` output. |
| `queue` | `supervisorctl status`, all programs `RUNNING` | `pgrep php` reports healthy while a worker crash-loops, because supervisor keeps respawning it. |
| `scheduler` | same | same |

`make up` blocks on all of them and dumps the offending container's logs if any
goes unhealthy, rather than returning success and leaving you to discover it.

---

## Storage and state

| Volume | Holds | Survives `make down`? |
|---|---|---|
| `mysql_data` | The database | Yes |
| `mysql_logs` | Slow query log | Yes |
| `redis_data` | AOF + RDB | Yes |
| `composer_cache` | Composer downloads | Yes |
| `node_modules` | JS dependencies | Yes |
| `node_cache` | npm cache | Yes |
| `app_storage` **[prod]** | Uploads, logs, sessions | Yes |

Only `make destroy` removes them.

In **development**, source is bind-mounted and `storage/` is just a directory in
your working tree. In **production** there is no bind mount at all: code is
baked into the image and `storage/` is the `app_storage` volume.

### Why `node_modules` is a named volume

It keeps tens of thousands of small files off the shared filesystem (a large
speed-up on macOS and Windows), and it prevents a host-installed `node_modules`
— with binaries compiled for your host architecture — from leaking into the
container. The cost is that your editor cannot see it; run `npm install` on the
host too if you want IDE resolution.

---

## The compose layering

```text
docker-compose.yml          services, networks, volumes, healthchecks, hardening
        │
        ├── docker-compose.dev.yml    bind mounts, ports, Vite, tooling profiles
        ├── docker-compose.prod.yml   baked images, read-only rootfs, limits
        └── docker-compose.ci.yml     tmpfs datastores, relaxed durability
```

The base file is never used alone. Every invocation supplies base + one overlay.

Two mechanics worth knowing:

- **Lists merge, scalars replace.** `tmpfs` entries from the base and the
  overlay are *combined* by mount path. That is how production nginx still gets
  the `/etc/nginx/conf.d` tmpfs it needs to render its config template on a
  read-only filesystem, while only overriding the sizes.
- **`--project-directory` is mandatory.** The compose files live in `compose/`
  but all their relative paths are written against the project root. Every
  invocation passes `--project-directory .` so `context: .` means the project,
  not `compose/`. The Makefile and helper scripts do this for you.

---

## Request lifecycle

```text
browser → :80 → nginx (uid 101)
                  │
                  ├─ /healthz            → answered by nginx, never touches PHP
                  ├─ /build/*.css        → served from nginx's own layer,
                  │                        Cache-Control: immutable, 1 year
                  ├─ /.env, /vendor/…    → 404 (hardening.conf)
                  ├─ /uploads/evil.php   → 404, never executed
                  └─ everything else     → try_files → /index.php
                                             │
                                             ▼
                                    FastCGI :9000 (keepalive)
                                             │
                                        php-fpm (uid 1000)
                                             │
                                    ┌────────┴────────┐
                                    ▼                 ▼
                                 mysql:3306      redis:6379
                                        (backend network)
```

The document root is `public/`. Application code, `.env`, `vendor/` and
`storage/` are physically *outside* the served tree — the denial rules in
`hardening.conf` are defence in depth, not the primary control.

---

## Job lifecycle

```text
app container                          queue container
  dispatch(Job) ──▶ redis:6379 ──▶ queue:work (supervisor, ×N)
                     (list)              │
                                         ├─ success → job removed
                                         ├─ fail    → retry (3×, 5s backoff)
                                         └─ exhausted → failed_jobs table
```

Workers exit cleanly after `--max-time=3600`, `--max-jobs=1000` or
`--memory=256`, and supervisor restarts them immediately. Recycling a PHP worker
is cheaper and far more predictable than chasing a slow leak in a process that
lives for weeks.

On shutdown, supervisor sends `SIGTERM`; Laravel finishes the job in flight and
then exits. `stop_grace_period: 130s` gives it room — which is why
`QUEUE_TIMEOUT` (60s) must stay well under that.

> This is also why `pcntl_*` functions are **not** in `disable_functions` in
> `php.ini`. They are blocked for the web SAPI only, in `www.conf`. Disabling
> them globally would silently turn every graceful worker shutdown into a hard
> kill.

---

## Design decisions, stated plainly

| Decision | Reason |
|---|---|
| Debian, not Alpine | musl's allocator and DNS resolver have both produced subtle, hard-to-diagnose PHP bugs. The image is bigger; it is also boring. |
| `nginx-unprivileged` | The stock image's master runs as root. |
| One image for three roles | Guarantees the worker runs identical code to the dispatcher. |
| Supervisor, not one process per container | Lets several workers share a container, and gives the healthcheck a real supervision state to query. |
| Healthchecks do real work | A check that passes while the service is broken makes `depends_on` meaningless. |
| `AUTO_MIGRATE=false` | A rolling restart would race several containers against the same schema change. |
| `scale: 1` on the scheduler | Two schedulers fire every task twice. |
| OPcache JIT off | It rarely helps request/response Laravel and occasionally regresses. Benchmark before enabling. |
| No `open_basedir` | It breaks Composer, ICU lookups and stream wrappers in ways that surface much later. The container boundary is the real control. |
| Assets baked into nginx | No shared volume, no drift, no dependency on PHP being up to serve a stylesheet. |
