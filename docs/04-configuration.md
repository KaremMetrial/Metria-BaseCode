# Configuration

---

## Two files, two jobs

| File | Configures | Committed? |
|---|---|---|
| `.env.docker` | **Infrastructure** — image versions, ports, container credentials, resource limits | Yes (dev defaults only) |
| `.env` | **The application** — Laravel's own settings | No, never |

They overlap on things like `DB_HOST` and `REDIS_HOST`. When they disagree:

> **The container environment always wins.**

Laravel's Dotenv does not overwrite variables that already exist in the process
environment. Compose injects `DB_HOST=mysql` as a real environment variable, so
it takes precedence over whatever `.env` says.

This is deliberate and worth understanding:

- The **same image** can be promoted from staging to production without a
  rebuild — only the environment changes.
- A stray `.env` **cannot** silently point production at a developer's database.
- The corollary bites too: setting an *empty* variable in compose **masks** the
  `.env` value. That is why `APP_KEY` is only set in the production overlay —
  injecting an empty one in development would shadow the real key and the app
  would die with `MissingAppKeyException`.

---

## Project identity

| Variable | Default | Notes |
|---|---|---|
| `COMPOSE_PROJECT_NAME` | `laravel` | Prefixes containers, networks and volumes. **Change this** if you run several projects from this template, or they will share volumes. |
| `IMAGE_TAG` | `latest` | Tag applied to built images. Set to a commit SHA in CI. |

---

## Image versions

Bump these to upgrade the platform, then `make build`.

| Variable | Default |
|---|---|
| `PHP_VERSION` | `8.4` |
| `NODE_VERSION` | `22` |
| `COMPOSER_VERSION` | `2.8` |
| `NGINX_VERSION` | `1.29` |
| `MYSQL_VERSION` | `8.4` |
| `REDIS_VERSION` | `8` |

See [Adopting & upgrading](09-adopting.md#upgrading-the-platform) for what to
check when you move a major version.

---

## Host integration

| Variable | Default | Notes |
|---|---|---|
| `UID` | auto-detected | Must match your host user or bind-mounted files come out root-owned |
| `GID` | auto-detected | " |
| `DOCKER_USER` | `app` | Name of the in-container user |
| `TZ` | `UTC` | Container timezone. Leave as UTC and format at the edges. |

`UID`/`GID` are **build arguments** — they are baked into the image. The
Makefile exports them automatically. If you change them, rebuild:

```bash
make build && make up
```

---

## Published ports

Only nginx is exposed publicly. MySQL and Redis bind to `127.0.0.1` only, so
they are reachable from your machine but not from your network.

| Variable | Default | Why this default |
|---|---|---|
| `APP_PORT` | `80` | HTTP |
| `VITE_PORT` | `5173` | Vite dev server |
| `FORWARD_DB_PORT` | `3307` | **Not 3306** — avoids clashing with a MySQL installed on the host |
| `FORWARD_REDIS_PORT` | `6380` | **Not 6379** — a locally installed Redis almost always owns 6379 |

In production **nothing but nginx is published at all**.

---

## Application

| Variable | Default | Notes |
|---|---|---|
| `APP_ENV` | `local` | `production` in the prod overlay |
| `APP_DEBUG` | `true` | Forced to `false` in production |
| `APP_URL` | `http://localhost` | |
| `APP_KEY` | *(empty)* | See below |
| `LOG_CHANNEL` | `stderr` | So Docker collects application logs |
| `LOG_LEVEL` | `debug` | `warning` in production |

### `APP_KEY`

Empty in `.env.docker` **on purpose**. In development it comes from `.env`. In
production there is no `.env` inside the image (`.dockerignore` keeps it out so
a key can never be pushed to a registry), so it must be supplied at run time:

```bash
export APP_KEY="$(make artisan ARGS='key:generate --show')"
make prod-up
```

The production overlay declares it as `${APP_KEY:?…}`, so the stack refuses to
start without it rather than booting into a confusing runtime error.

**Never commit a real key.** It decrypts every encrypted column, cookie and
session the application has.

---

## Database

| Variable | Default | Notes |
|---|---|---|
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `mysql` | The compose service name |
| `DB_PORT` | `3306` | Internal port, not the published one |
| `DB_DATABASE` | `laravel` | Created on first boot |
| `DB_TEST_DATABASE` | `laravel_testing` | Created by `docker/mysql/init/` |
| `DB_USERNAME` | `laravel` | |
| `DB_PASSWORD` | `secret` | **Dev only** — override in production |
| `DB_ROOT_PASSWORD` | `root` | **Dev only** |

> Initialisation scripts in `docker/mysql/init/` run **once**, against an empty
> data volume. Editing them later has no effect until you `make destroy`.

### Tuning MySQL

Server settings live in `docker/mysql/my.cnf`. The one you are most likely to
change:

```ini
innodb_buffer_pool_size = 512M   # 60-70% of the container's memory limit
```

Raise it together with `DB_MEMORY_LIMIT`. That file is written specifically
against MySQL **8.4**, which removed several directives that older guides still
recommend — the file documents each one and its replacement.

---

## Redis

| Variable | Default | Notes |
|---|---|---|
| `REDIS_HOST` | `redis` | |
| `REDIS_PORT` | `6379` | |
| `REDIS_PASSWORD` | `local_dev_redis_password` | Set even in dev, so the authenticated path is what you exercise daily |
| `REDIS_CLIENT` | `phpredis` | The C extension; faster than Predis |
| `REDIS_MAXMEMORY` | `512mb` | Keep **below** the container memory limit |

The entrypoint **refuses to start** without a password when
`APP_ENV=production`, so an unauthenticated cache cannot reach production by
accident.

`maxmemory` must stay below the container limit because the RDB snapshot fork
and the AOF rewrite buffer both live *outside* that budget.

`maxmemory-policy` is `noeviction` because this instance carries Laravel
queues as well as cache data. Under memory pressure Redis rejects writes rather
than silently evicting a pending job. If cache eviction is required, run a
separate cache-only Redis instance with `allkeys-lru`.

---

## Laravel drivers

| Variable | Default |
|---|---|
| `CACHE_STORE` | `redis` |
| `SESSION_DRIVER` | `redis` |
| `QUEUE_CONNECTION` | `redis` |

---

## Queue workers

| Variable | Default | Notes |
|---|---|---|
| `QUEUE_WORKERS` | `2` (dev), `4` (prod) | Processes per queue container |
| `QUEUE_NAME` | `default` | Comma-separated for priority: `high,default,low` |
| `QUEUE_TRIES` | `3` | Attempts before a job is marked failed |
| `QUEUE_BACKOFF` | `5` | Seconds between retries |
| `QUEUE_TIMEOUT` | `60` | Max seconds for one job |
| `QUEUE_SLEEP` | `3` | Seconds to sleep when the queue is empty |
| `QUEUE_MAX_TIME` | `3600` | Worker lifetime before a clean restart |
| `QUEUE_MAX_JOBS` | `1000` | Jobs before a clean restart |
| `QUEUE_MEMORY` | `256` | MB before a clean restart |

> `QUEUE_TIMEOUT` must stay **below** `stop_grace_period` (130s) or a
> shutdown will `SIGKILL` a worker mid-job and the job will be retried as a
> duplicate.

Scale workers by raising `QUEUE_WORKERS`, or by running more queue containers:

```bash
docker compose … up -d --scale queue=3
```

The **scheduler must never be scaled past 1** — two schedulers fire every task
twice.

---

## Nginx

| Variable | Default | Notes |
|---|---|---|
| `NGINX_SERVER_NAME` | `_` | Catch-all. Set a real hostname in production. |
| `NGINX_FPM_HOST` | `app` | Upstream service name |
| `NGINX_FPM_PORT` | `9000` | |
| `NGINX_ROOT` | `/var/www/html/public` | **Must** point at `public/` |

These are rendered into the server block by `envsubst` at container start.
Everything else lives in `docker/nginx/`:

| Setting | File |
|---|---|
| Rate-limit zones, log format, timeouts | `nginx.conf` |
| Routes, caching, PHP handling | `templates/default.conf.template` |
| Security headers | `snippets/security-headers.conf` |
| Compression | `snippets/gzip.conf` |
| FastCGI parameters | `snippets/fastcgi-php.conf` |
| Path denials | `snippets/hardening.conf` |

### Rate limits

Declared in `nginx.conf`, applied per-location:

| Zone | Rate | Applied to |
|---|---|---|
| `general` | 60 r/s, burst 180 | Everything |
| `api` | 30 r/s, burst 120 | `/api/` |
| `auth` | 10 r/min, burst 10 | `/login`, `/register`, password reset |

Deliberately generous: limits tight enough to be interesting are also tight
enough to break asset-heavy page loads and CI runs.

> Behind a load balancer or CDN, configure Laravel's `TrustProxies` middleware
> **and** nginx's `real_ip` module — otherwise every request appears to come
> from the proxy's IP and per-IP limiting collapses into one shared bucket.

---

## Upload size

Three limits must agree, or the smallest silently wins:

| Layer | Setting | File | Default |
|---|---|---|---|
| Nginx | `client_max_body_size` | `docker/nginx/nginx.conf` | `100M` |
| PHP | `upload_max_filesize` | `docker/php/php.ini` | `100M` |
| PHP | `post_max_size` | `docker/php/php.ini` | `108M` |

`post_max_size` must exceed `upload_max_filesize` to leave room for the rest of
the multipart body. Change all three, then `make build && make up`.

---

## Xdebug

| Variable | Default | Notes |
|---|---|---|
| `XDEBUG_MODE` | `off` | `debug`, `coverage`, `profile`, or comma-separated |
| `XDEBUG_SERVER_NAME` | `laravel` | Must match your IDE's server name |

```bash
XDEBUG_MODE=debug make restart
```

---

## Logging

| Variable | Default | Notes |
|---|---|---|
| `LOG_DRIVER` | `json-file` | Set to `journald`, `awslogs`, `gelf`… in production |
| `LOG_MAX_SIZE` | `10m` (dev), `50m` (prod) | Rotate at this size |
| `LOG_MAX_FILE` | `5` (dev), `10` (prod) | Files to keep |

Every service logs to stdout/stderr, so any Docker logging driver works without
touching application code.

---

## Resource limits **[prod]**

Applied only by `docker-compose.prod.yml`.

| Service | CPU | Memory | Reservation |
|---|---|---|---|
| `app` | `APP_CPU_LIMIT` (2) | `APP_MEMORY_LIMIT` (1g) | 256m |
| `nginx` | `NGINX_CPU_LIMIT` (1) | `NGINX_MEMORY_LIMIT` (256m) | — |
| `queue` | `QUEUE_CPU_LIMIT` (2) | `QUEUE_MEMORY_LIMIT` (1g) | — |
| `scheduler` | `SCHEDULER_CPU_LIMIT` (1) | `SCHEDULER_MEMORY_LIMIT` (512m) | — |
| `mysql` | `DB_CPU_LIMIT` (4) | `DB_MEMORY_LIMIT` (4g) | 1g |
| `redis` | `REDIS_CPU_LIMIT` (1) | `REDIS_MEMORY_LIMIT` (1g) | — |

Two pairs must be kept consistent by hand:

- `innodb_buffer_pool_size` (in `my.cnf`) ≈ 60–70% of `DB_MEMORY_LIMIT`
- `REDIS_MAXMEMORY` **<** `REDIS_MEMORY_LIMIT`

For PHP, `pm.max_children` (in `docker/php/www.conf`, default 20) times the
average process size must fit inside `APP_MEMORY_LIMIT`. At 20 × ~50 MB that is
~1 GB — exactly the default limit. Raise both together.

---

## Local overrides

To change something on your machine only, without editing tracked files:

```bash
# .env.docker.local is gitignored
APP_PORT=8080 make up
```

Or create `compose/docker-compose.override.yml` (also gitignored) and add it to
your own compose invocation.

---

## Where to change what

| To change… | Edit | Then |
|---|---|---|
| A port, credential or version | `.env.docker` | `make up` (or `make build` for versions) |
| PHP settings | `docker/php/php.ini`, `conf.d/*.ini` | `make build && make up` |
| FPM pool sizing | `docker/php/www.conf` | `make build && make up` |
| Nginx routing/headers | `docker/nginx/**` | `make build && make restart` |
| MySQL server settings | `docker/mysql/my.cnf` | `make build && make up` |
| Redis settings | `docker/redis/redis.conf` | `make build && make up` |
| Queue worker behaviour | `.env.docker` | `make up` |
| Laravel behaviour | `.env` | `make restart` |
