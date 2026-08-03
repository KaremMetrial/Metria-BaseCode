# Adopting & upgrading

Dropping this platform into an existing Laravel project, and keeping it current.

---

## Adding it to an existing project

Nothing in the platform is application-specific, so adoption is a copy plus a
little configuration.

### 1. Copy the platform

```bash
cd /path/to/your-app

cp -r /path/to/platform/docker    .
cp -r /path/to/platform/compose   .
cp    /path/to/platform/Makefile  .
cp    /path/to/platform/.env.docker .
cp    /path/to/platform/.dockerignore .

# optional
cp -r /path/to/platform/docs .
cp -r /path/to/platform/.github .
```

**If you already have these files**, take care:

| File | If it exists |
|---|---|
| `Makefile` | Merge — or keep the platform's and rename yours `Makefile.app` |
| `.dockerignore` | Merge. The platform's excludes `.env`, `vendor/`, `node_modules/` — all of which you want |
| `docker-compose.yml` | Leave it. This platform never reads one from the root |
| `docker/` | Rename yours first; do not merge blindly |

### 2. Name the project

```dotenv
# .env.docker
COMPOSE_PROJECT_NAME=myapp
```

**Do this before the first `make up`.** It prefixes containers, networks and
volumes — leave it as `laravel` and two projects from this template will fight
over the same volumes.

### 3. Point your `.env` at the services

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql          # service name, not 127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=local_dev_redis_password

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

LOG_CHANNEL=stderr
```

These must match `.env.docker`, which is what actually creates the database
user and sets the Redis password.

### 4. Check your PHP requirements

```bash
grep '"php"' composer.json
```

The platform ships **PHP 8.4**. If your app needs an older release, set
`PHP_VERSION` in `.env.docker` — 8.2 and 8.3 both work unchanged.

Then check for extensions the platform does not install:

```bash
composer check-platform-reqs
```

The bundled set is: `bcmath exif gd imagick intl mbstring mysqli opcache pcntl
pdo_mysql pdo_pgsql pgsql redis soap sockets zip` (plus `pdo_sqlite`/`sqlite3`,
which PHP includes by default). Anything else goes in `docker/php/Dockerfile`:

```dockerfile
RUN install-php-extensions \
        bcmath exif gd intl … \
        ldap memcached          # your additions
```

### 5. Build and start

```bash
make build
make install
make up
make migrate
```

### 6. Migrate your data in

```bash
# From a dump
make db < /path/to/existing-dump.sql

# Or from a running MySQL on the host
mysqldump -h 127.0.0.1 -u root -p old_db | make db
```

### 7. Adjust for your application

Things worth reviewing once:

| If your app… | Do this |
|---|---|
| Shells out during web requests (PDF/screenshot packages) | Remove the relevant names from `disable_functions` in `docker/php/www.conf` |
| Accepts uploads over 100 MB | Raise all three limits — see [Configuration](04-configuration.md#upload-size) |
| Uses PostgreSQL | `pdo_pgsql` is already installed; swap the `mysql` service for `postgres` |
| Uses several queues | Set `QUEUE_NAME=high,default,low` |
| Has long-running jobs | Raise `QUEUE_TIMEOUT`, and keep it **below** `stop_grace_period` |
| Uses Horizon | Replace the `queue` command with `horizon`; update the healthcheck |
| Uses Reverb/websockets | Add a service on the `edge` network and proxy it in nginx |

---

## Using Horizon instead of the plain worker

```bash
make composer ARGS="require laravel/horizon"
make artisan ARGS="horizon:install"
```

Then in `docker/supervisor/conf.d/queue.conf`:

```ini
[program:queue]
command = php /var/www/html/artisan horizon
numprocs = 1                       ; Horizon manages its own workers
stopwaitsecs = 120
```

Horizon supervises its own worker pool, so `numprocs` must be 1 and the
`QUEUE_*` variables no longer apply — configure them in `config/horizon.php`.
The existing supervisor healthcheck keeps working unchanged.

---

## Swapping MySQL for PostgreSQL

`pdo_pgsql` and `pgsql` are already in the image.

```yaml
# compose/docker-compose.yml
postgres:
  image: postgres:17-bookworm
  environment:
    POSTGRES_DB: ${DB_DATABASE:-laravel}
    POSTGRES_USER: ${DB_USERNAME:-laravel}
    POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
  networks: [backend]
  volumes:
    - postgres_data:/var/lib/postgresql/data
  healthcheck:
    test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
    interval: 10s
    timeout: 5s
    retries: 10
    start_period: 30s
```

```dotenv
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
```

Update `depends_on` on the app, queue and scheduler services, and adapt
`docker/scripts/backup.sh` to `pg_dump`.

---

## Upgrading the platform

### Image versions

All of them are variables. Bump, rebuild, test:

```dotenv
PHP_VERSION=8.5
NODE_VERSION=24
NGINX_VERSION=1.31
MYSQL_VERSION=8.4
REDIS_VERSION=8
COMPOSER_VERSION=2.9
```

```bash
make build && make up && make test
```

### Upgrading PHP

```bash
PHP_VERSION=8.5 make build
```

Watch for two things:

1. **Extension availability.** `install-php-extensions` pins versions that work
   with the target release, but a brand-new PHP often lands before `imagick` or
   a PECL extension catches up. If the build fails there, either wait or drop
   the extension.
2. **Deprecated ini directives.** Every PHP minor deprecates a few, and a
   deprecated directive prints to stderr on *every* SAPI launch — which breaks
   the extension installer's load check. `make validate` has a dedicated check
   for this:

```bash
make validate      # "php startup emits no warnings"
```

### Upgrading MySQL

**Always back up first**, and read the release notes for removed variables —
this is the single most common cause of a container that will not start.

```bash
make backup
MYSQL_VERSION=8.5 make build && make up
make logs SERVICE=mysql        # mysqld names any unknown option explicitly
```

MySQL supports in-place upgrades **one major version at a time**. To skip
versions, dump and reload.

### Upgrading Laravel

The platform is version-agnostic — it has been validated against Laravel 13 and
supports 11 and 12 unchanged.

```bash
make composer ARGS="require laravel/framework:^13.0 --with-all-dependencies"
make artisan ARGS="optimize:clear"
make test
```

Check the framework's PHP requirement against `PHP_VERSION` before you start.

---

## Keeping dependencies current

Point Renovate or Dependabot at the Dockerfiles and `.env.docker`:

```json
{
  "extends": ["config:recommended"],
  "packageRules": [
    {
      "matchManagers": ["dockerfile", "docker-compose"],
      "groupName": "docker base images"
    }
  ]
}
```

The CI pipeline already runs `composer audit` and Trivy-scans published images,
so a vulnerable dependency surfaces as a failing build rather than a surprise.

---

## Keeping the platform in sync across projects

Once this is your template for several applications, you need a way to pull
improvements into projects that have already diverged.

**Simplest — a template repository.** Keep the platform in its own repo and
copy `docker/` + `compose/` forward when you want an update. Diff before
copying:

```bash
diff -ru ./docker /path/to/platform/docker | less
```

**Cleaner — a git subtree.** Keeps history and makes updates a single command:

```bash
git subtree add  --prefix=docker  <platform-repo> main --squash
git subtree pull --prefix=docker  <platform-repo> main --squash
```

Whichever you choose, treat `.env.docker` as **per-project** — it holds
project-specific ports, names and credentials and should never be overwritten
by a platform update.

---

## Removing the platform

It is entirely additive; nothing in your application depends on it.

```bash
make destroy                     # ⚠ removes containers, images and volumes
rm -rf docker/ compose/ .env.docker .dockerignore Makefile docs/
```

Then restore whatever `DB_HOST`/`REDIS_HOST` values your previous environment
used.
