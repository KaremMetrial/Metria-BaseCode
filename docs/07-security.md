# Security

What is hardened, how it was verified, and — just as important — what is not
covered.

---

## Controls

| Control                 | Implementation                                                                                         | Verified by                                     |
| ----------------------- | ------------------------------------------------------------------------------------------------------ | ----------------------------------------------- |
| Non-root                | php-fpm uid 1000, nginx uid**101 including the master**, redis uid 999, mysqld uid 999           | `docker top` on each container                |
| No privilege escalation | `security_opt: no-new-privileges:true` on every service                                              | compose config                                  |
| Capabilities            | `cap_drop: ALL` everywhere; only MySQL adds back `CHOWN`, `SETGID`, `SETUID`, `DAC_OVERRIDE` | compose config                                  |
| Read-only rootfs        | nginx (dev + prod); app/queue/scheduler in prod                                                        | `touch /etc/probe` → "Read-only file system" |
| Network isolation       | `backend` is `internal: true` — no egress, no ingress                                             | compose config                                  |
| Port exposure           | Only nginx. MySQL/Redis on`127.0.0.1` in dev, nothing in prod                                        | `docker ps`                                   |
| Document root           | `public/` only                                                                                       | 24 HTTP probes                                  |
| PHP execution           | `index.php` is the only executable entry point                                                       | planted`.php` files return 404                |
| Secrets                 | `*_FILE` support throughout; `.env` excluded from images                                           | `.dockerignore`                               |
| Version disclosure      | `server_tokens off`, `expose_php = Off`, `X-Powered-By` stripped                                 | response headers                                |
| Rate limiting           | Per-IP zones for general / API / auth                                                                  | 429s after burst                                |
| Supply chain            | CI publishes SBOM + signed provenance, Trivy scan                                                      | pipeline                                        |

---

## Why nginx runs as uid 101 *including the master*

The stock `nginx` image starts its master process as root and drops only the
worker processes. This platform uses `nginx-unprivileged`, where every process
runs as uid 101. The practical result: the container needs **no Linux
capabilities at all** and works with a read-only root filesystem.

The trade-off is that it cannot bind port 80 inside the container, so it listens
on 8080 and compose maps `80:8080`.

---

## Why only `index.php` executes

```nginx
location ~ ^/index\.php(/|$) { fastcgi_pass php_fpm; … }
location ~ \.php$           { deny all; return 404; }
```

The first matching regex wins, so `index.php` reaches PHP and **every other
`.php` file returns 404**. This defeats the most common post-upload attack: get
a `.php` file into a writable directory, then request it.

Verified by planting real files and requesting them:

```
public/uploads/evil.php  → 404, not executed, source not disclosed
public/shell.php         → 404, not executed, source not disclosed
```

---

## What the document root protects

The root is `public/`, so application code, `.env`, `vendor/` and `storage/` are
physically outside the served tree. The denial rules in
`snippets/hardening.conf` are defence in depth for the case where someone
mis-points `NGINX_ROOT`.

All of these return **404**:

```
/.env  /.env.docker  /composer.json  /composer.lock  /artisan
/package.json  /phpunit.xml  /Makefile  /.git/config  /.gitignore
/vendor/autoload.php  /app/Models/User.php  /config/database.php
/storage/logs/laravel.log  /bootstrap/cache/config.php
/database/migrations  /tests/TestCase.php
/status  /ping                          ← PHP-FPM introspection
```

While these keep working: `/`, `/up`, `/healthz`, `/index.php`.

---

## Response headers

Set in `snippets/security-headers.conf`, all with `always` so they are present
on 4xx/5xx responses too — the ones an attacker is most likely to see.

```http
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: accelerometer=(), camera=(), geolocation=(), microphone=(), …
Cross-Origin-Opener-Policy: same-origin
X-Permitted-Cross-Domain-Policies: none
X-Request-Id: <per-request id, also in the JSON access log>
```

### Deliberately not enabled

**HSTS** — only meaningful over TLS, and a wrong `max-age` is not revocable.
Uncomment it once TLS terminates here rather than upstream.

**CSP** — a generic policy either breaks Vite's dev client or is so permissive
it is theatre. Start from the commented report-only example and tighten per
application.

**`X-XSS-Protection`** — removed from all current browsers, and its legacy
filter was itself exploitable.

---

## PHP hardening

Dangerous functions are disabled **for the web SAPI only**, in
`docker/php/www.conf`:

```ini
php_admin_value[disable_functions] = pcntl_*,exec,passthru,shell_exec,system,
                                     proc_open,proc_close,popen,dl,…
```

`php_admin_value` cannot be overridden with `ini_set()`.

> **Why not in `php.ini`?** Because that file is shared by every SAPI, and the
> CLI containers need `pcntl_*`: `queue:work` uses `pcntl_signal` and
> `pcntl_async_signals` to catch `SIGTERM` and finish the job in flight.
> Disabling them globally silently converts every graceful shutdown into a
> hard kill mid-write.

**Relax this if your app shells out during a web request.** PDF renderers
(Browsershot, wkhtmltopdf, Snappy), some backup packages and a few image
pipelines call `proc_open`/`exec` directly. Remove the specific offending name
rather than deleting the whole line.

### `open_basedir` is deliberately absent

It reads like free hardening, but it breaks Composer, ICU/intl data lookups and
several stream wrappers in ways that surface much later as confusing runtime
errors, and it defeats the realpath cache. The container boundary, a read-only
rootfs and a non-root user bound the filesystem far more effectively. Add it
per-application if your threat model demands it.

---

## Secrets management

**Do not put production credentials in `.env.docker`.** Compose interpolation
makes every value there visible in `docker inspect` and in the container
environment — the first place a compromised dependency looks.

Every component reads the `*_FILE` convention, including in its healthchecks:

```yaml
services:
  mysql:
    environment:
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/db_root_password
      MYSQL_PASSWORD_FILE: /run/secrets/db_password
    secrets: [db_root_password, db_password]

  redis:
    environment:
      REDIS_PASSWORD_FILE: /run/secrets/redis_password
    secrets: [redis_password]

secrets:
  db_root_password:
    external: true          # or: file: ./secrets/db_root_password.txt
  db_password:
    external: true
  redis_password:
    external: true
```

`secrets/` is gitignored.

### Passwords never appear in `ps`

Three places where the obvious implementation would leak:

| Where             | Naive approach                    | What this platform does                  |
| ----------------- | --------------------------------- | ---------------------------------------- |
| MySQL healthcheck | `mysql --password=…`           | `MYSQL_PWD` environment variable       |
| Redis healthcheck | `redis-cli -a …`               | `REDISCLI_AUTH` environment variable   |
| Redis startup     | `redis-server --requirepass …` | Entrypoint writes a 0600 config to tmpfs |
| Backups           | `mysqldump -p…`                | `MYSQL_PWD`                            |

### Rotating credentials

```bash
# Database
make db
> ALTER USER 'laravel'@'%' IDENTIFIED BY '<new>';
> FLUSH PRIVILEGES;
# update the secret, then:
make up

# Redis — update the secret and restart; the entrypoint re-renders its config
make up

# APP_KEY — ⚠ invalidates every encrypted column, cookie and session.
# Requires a re-encryption plan before you rotate it.
```

---

## Network posture

```yaml
backend:
  driver: bridge
  internal: true      # no default route
```

MySQL and Redis sit *only* on this network. They cannot reach the internet, so
a compromised datastore container has nowhere to exfiltrate to and cannot pull
a second stage. Nothing outside the project can route to them either.

In development, MySQL and Redis publish to `127.0.0.1` only — reachable from
your machine, not from your network. In production nothing but nginx is
published at all.

---

## Supply chain

The CI pipeline:

- `composer validate --strict` and `composer audit` (known CVEs)
- Builds with pinned base image tags
- Publishes an **SBOM** and **signed build provenance** (`actions/attest-build-provenance`)
- **Trivy** scans the published image for CRITICAL/HIGH, uploading SARIF to the
  Security tab

Renovate or Dependabot on `docker/*/Dockerfile` and `.env.docker` will keep the
pinned versions moving.

---

## Known gaps and accepted trade-offs

Read this before you call it production-ready for *your* threat model.

**TLS is not terminated here.** Deliberate — certificate management belongs in
front. You must supply it.

**The MySQL container's entrypoint starts as root.** It needs `CHOWN`/`SETUID`
to fix data directory ownership before dropping to uid 999 via gosu. Those four
capabilities are the minimum that permits it; running `user: mysql` instead
breaks first-time initialisation of a fresh volume. The mysqld *process* does
run unprivileged — verified.

**The app container is writable in development.** The bind mount has to be, for
the workflow to function. Production is read-only.

**No CSP by default.** See above.

**No WAF, no IDS, no fail2ban.** Rate limiting is per-IP in nginx and nothing
more. Put a WAF in front if you need one.

**No secret-manager integration.** Docker secrets are supported; Vault/AWS
Secrets Manager/SOPS are not wired up.

**No runtime security monitoring.** No Falco, no auditd, no anomaly detection.

**Rate limiting is per-container.** Scale to several nginx instances and each
gets its own counters. Move rate limiting to the load balancer at that point.

**`latest` image tags in development.** `IMAGE_TAG=latest` is fine locally; use
a commit SHA in production so a deploy is reproducible.

---

## Verifying it yourself

```bash
# Everything runs unprivileged (uids, not names — 999/101/1000 expected)
for s in app nginx mysql redis queue scheduler; do
  echo "$s: $(docker top laravel-$s-1 -o user,comm 2>/dev/null | sed -n 2p)"
done

# Sensitive paths are unreachable
for p in /.env /composer.json /vendor/autoload.php /storage/logs/laravel.log /status; do
  printf '%-32s %s\n' "$p" "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost$p")"
done

# Planted PHP does not execute
mkdir -p public/uploads
echo '<?php echo "EXECUTED";' > public/uploads/probe.php
curl -s http://localhost/uploads/probe.php | grep -q EXECUTED \
  && echo "FAIL: PHP executed" || echo "PASS: not executed"
rm -f public/uploads/probe.php

# Read-only rootfs (production)
docker compose … exec app touch /etc/probe    # expect: Read-only file system

# Security headers
curl -sI http://localhost/ | grep -iE 'x-frame|x-content|referrer|permissions'
```

---

## Reporting a vulnerability

Do not open a public issue. Contact the maintainer privately with reproduction
steps and, if you have one, a suggested fix.
