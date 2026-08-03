# Production deployment

A runbook. Follow it top to bottom the first time.

---

## What production changes

| | Development | Production |
|---|---|---|
| Source | Bind-mounted, live | **Baked into the image** |
| `vendor/` + assets | Installed at runtime | Baked at build time |
| Root filesystem | Writable | **Read-only** |
| OPcache | Re-checks files every request | **Never stats the filesystem** |
| Debug | On | Off |
| Published ports | 80, 5173, 3307, 6380 | **80 only** |
| Restart policy | `unless-stopped` | `always` |
| Resource limits | None | Per-service CPU + memory |
| Log rotation | 10 MB × 5 | 50 MB × 10 |

The headline: **what you tested is bit-for-bit what runs.** There is no step
where a server pulls different dependencies than your CI did.

---

## Before the first deploy

Work through this once.

### 1. Generate and store an `APP_KEY`

There is no `.env` inside the production image — `.dockerignore` keeps it out so
a key can never be baked into a layer and pushed to a registry.

```bash
make artisan ARGS="key:generate --show"
# base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
```

Store it in your secret manager. **Losing it invalidates every encrypted
column, cookie and session.** Rotating it has the same effect — plan for it.

### 2. Set real credentials

Never deploy with the development defaults. Create a production environment
file (not committed) or, better, use Docker secrets:

```dotenv
COMPOSE_PROJECT_NAME=myapp-prod
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
LOG_LEVEL=warning

DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD=<strong, generated>
DB_ROOT_PASSWORD=<different, generated>

REDIS_PASSWORD=<strong, generated>

NGINX_SERVER_NAME=example.com
APP_PORT=80
```

> The Redis entrypoint **refuses to start** without a password when
> `APP_ENV=production`. This is deliberate — an unauthenticated cache cannot
> reach production by accident.

See [Security → Secrets management](07-security.md#secrets-management) for the
Docker secrets setup, which keeps values out of `docker inspect`.

### 3. Size the resources

Defaults suit a modest VPS. Adjust in your production env file, and keep the
two coupled pairs consistent:

- `innodb_buffer_pool_size` (in `docker/mysql/my.cnf`) ≈ 60–70% of `DB_MEMORY_LIMIT`
- `REDIS_MAXMEMORY` **<** `REDIS_MEMORY_LIMIT` (the RDB fork and AOF rewrite
  buffer live outside the `maxmemory` budget)
- `pm.max_children` × ~50 MB must fit inside `APP_MEMORY_LIMIT`

### 4. Put TLS in front

TLS is **not** terminated in these containers. Use a load balancer, or an
nginx/Caddy/Traefik reverse proxy on the host. Then:

1. Uncomment `Strict-Transport-Security` in
   `docker/nginx/snippets/security-headers.conf`.
2. Configure Laravel's `TrustProxies` middleware — otherwise every request
   appears to originate from the proxy, which also collapses per-IP rate
   limiting into a single shared bucket.

---

## Deploying

### Build

```bash
make prod-build
```

This runs the `vendor` stage (Composer, `--no-dev`, optimized autoloader), the
`assets` stage (Vite), and copies both into a clean runtime image. Compose
builds `app` first so the nginx image can copy the compiled `public/` tree out
of it.

### Start

```bash
export APP_KEY="base64:…"
make prod-up
```

`prod-up` blocks until every healthcheck passes and fails loudly if `APP_KEY`
is missing.

### Migrate

```bash
make prod-migrate
```

> **Migrations are never automatic.** `AUTO_MIGRATE` defaults to `false`
> because a rolling restart boots several containers at once and would race
> them against the same schema change. Run this as its own deployment step.

### Verify

```bash
make prod-ps
curl -fsS -o /dev/null -w '%{http_code}\n' https://example.com/up
make prod-logs SERVICE=app
```

---

## A complete deployment script

```bash
#!/usr/bin/env bash
set -euo pipefail

cd /srv/myapp
export APP_KEY="$(cat /run/secrets/app_key)"

echo "==> Backing up"
COMPOSE_MODE=prod bash docker/scripts/backup.sh

echo "==> Fetching"
git fetch --all --tags
git checkout "${1:?usage: deploy.sh <tag>}"

echo "==> Building"
make prod-build

echo "==> Starting"
make prod-up          # blocks until healthy

echo "==> Migrating"
make prod-migrate

echo "==> Restarting workers onto the new code"
COMPOSE_MODE=prod docker/scripts/artisan queue:restart

echo "==> Smoke test"
curl -fsS -o /dev/null http://localhost/up

echo "==> Deployed $1"
```

The backup runs **first**, so you always have a restore point taken immediately
before the change that might need reverting.

---

## Zero-downtime deploys

Compose alone cannot do a true rolling restart of a single service — there is a
gap while the container is replaced. Two options:

**Two-stack blue/green.** Run two projects on different ports and switch the
upstream in your front proxy:

```bash
COMPOSE_PROJECT_NAME=myapp-green APP_PORT=8081 make prod-up
# health-check :8081, then repoint the proxy, then stop blue
```

**An orchestrator.** For anything beyond a single host, this platform's images
work unchanged under Docker Swarm, Kubernetes or ECS — you are replacing the
compose files, not the Dockerfiles. `deploy.resources` limits already use the
Swarm-compatible schema.

---

## Rolling back

```bash
git checkout <previous-tag>
make prod-build && make prod-up
```

If the bad release included a migration, roll the schema back **first** —
`migrate:rollback` uses the *current* checkout's migration files:

```bash
make prod-migrate ARGS="--step=1"   # or: artisan migrate:rollback --step=1
```

If the schema change was destructive, restore instead:

```bash
COMPOSE_MODE=prod FORCE=1 bash docker/scripts/restore.sh backups/<pre-deploy>.sql.gz
```

> Write migrations that are safe to roll back, or safe to leave in place while
> the old code runs. Expand-then-contract — add a column, deploy code that
> writes both, backfill, then drop the old column in a later release — turns
> every schema change into two independently reversible deploys.

---

## Backups

```bash
COMPOSE_MODE=prod bash docker/scripts/backup.sh
```

Nightly, via host cron:

```cron
0 3 * * * cd /srv/myapp && COMPOSE_MODE=prod RETAIN_DAYS=30 \
          bash docker/scripts/backup.sh >> /var/log/myapp-backup.log 2>&1
```

Dumps use `--single-transaction` (a consistent snapshot without locking, so the
app stays online) and include routines, triggers and events. The script verifies
gzip integrity and refuses to keep a suspiciously small file.

**Copy them off the host.** A backup on the same disk as the database is not a
backup. And **test a restore on a schedule** — an untested backup is a
hypothesis, not a recovery plan.

---

## Scaling

**Queue throughput** — raise workers per container, or run more containers:

```bash
QUEUE_WORKERS=8 make prod-up
docker compose … up -d --scale queue=3
```

**Web throughput** — raise `pm.max_children` in `docker/php/www.conf` and
`APP_MEMORY_LIMIT` together, then rebuild. Beyond one host, run several
app+nginx stacks behind a load balancer.

> **Never scale the scheduler.** `scale: 1` is pinned. Two schedulers fire every
> task twice. If you need redundancy across hosts, use Laravel's
> `onOneServer()` with a shared Redis lock plus `->withoutOverlapping()`.

---

## Operating notes

**Restart workers after every deploy.** A PHP worker boots the framework once
and loops; it is still executing the old code until restarted.

```bash
COMPOSE_MODE=prod docker/scripts/artisan queue:restart
```

**Watch the right logs.**

```bash
make prod-logs SERVICE=app       # application errors
make prod-logs SERVICE=queue     # failed jobs
make prod-logs SERVICE=nginx | jq 'select(.status >= 500)'
make prod-logs SERVICE=mysql     # slow queries (>1s, logged by default)
```

**Ship logs somewhere.** Every service writes to stdout/stderr, so switching
drivers needs no application change:

```dotenv
LOG_DRIVER=journald     # or awslogs, gelf, fluentd…
```

**Maintenance mode** for anything long-running:

```bash
COMPOSE_MODE=prod docker/scripts/artisan down --render="errors::503" --retry=60
# … work …
COMPOSE_MODE=prod docker/scripts/artisan up
```

---

## Pre-launch checklist

- [ ] `APP_KEY` generated and stored in a secret manager
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] All development passwords replaced with generated values
- [ ] `REDIS_PASSWORD` set (the stack will not start without it)
- [ ] `NGINX_SERVER_NAME` set to the real hostname
- [ ] TLS terminating in front; HSTS enabled
- [ ] `TrustProxies` configured for your proxy
- [ ] Resource limits sized; `innodb_buffer_pool_size` and `REDIS_MAXMEMORY` consistent
- [ ] Nightly backups scheduled **and copied off-host**
- [ ] A restore has actually been tested
- [ ] Log driver pointed at your aggregator
- [ ] `make prod-ps` shows every service healthy
- [ ] `/up` returns 200 through the public hostname
- [ ] `curl https://example.com/.env` returns 404
- [ ] Reviewed [Security → Known gaps](07-security.md#known-gaps-and-accepted-trade-offs)
