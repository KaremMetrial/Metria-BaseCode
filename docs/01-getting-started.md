# Getting started

From nothing to a running Laravel application. Roughly 10 minutes, most of it
waiting for images to build.

---

## 1. Requirements

| Requirement | Minimum | Check with |
|---|---|---|
| Docker Engine | 24+ | `docker --version` |
| Docker Compose | v2.20+ | `docker compose version` |
| GNU Make | 4.0+ | `make --version` |
| Free disk | ~6 GB | `df -h .` |
| Free RAM | 4 GB | — |

Your user must be able to talk to Docker without `sudo`:

```bash
docker ps            # should succeed
sudo usermod -aG docker "$USER"   # if it does not — then log out and back in
```

> **Docker Desktop (macOS/Windows)** works as-is. On Windows, run everything
> from inside WSL2 and keep the project on the Linux filesystem
> (`/home/...`, not `/mnt/c/...`) — bind mounts across the Windows filesystem
> boundary are roughly 10× slower.

---

## 2. Get the code

If you are starting a brand-new project:

```bash
git clone <your-repo> my-app && cd my-app
```

If you are adding this platform to an application you already have, read
**[Adopting & upgrading](09-adopting.md)** instead, then come back here at
step 3.

---

## 3. Create your `.env`

The application's `.env` is separate from the platform's `.env.docker`.

```bash
cp .env.example .env
```

Then set the four values that must point at container hostnames rather than
`localhost` — inside the network, the services are reachable by their compose
service names:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql          # not 127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

REDIS_HOST=redis       # not 127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

LOG_CHANNEL=stderr     # so Docker collects your logs
```

These must match the credentials in `.env.docker`, which is what actually
creates the database user. See **[Configuration](04-configuration.md)**.

---

## 4. Build the images

```bash
make build
```

**This takes 5–15 minutes the first time.** It compiles ~16 PHP extensions from
source, including ImageMagick. Subsequent builds are seconds — Docker caches
every layer, and only the layers after your change are rebuilt.

You should end with six images:

```bash
docker images | grep laravel/
```

---

## 5. Install dependencies

```bash
make install     # composer install; npm install too when package.json exists
```

Both run inside containers, so you do not need PHP or Node installed on your
machine. Files land on your host with your own ownership, not root's.

---

## 6. Start everything

```bash
make up
```

This starts six services and then **blocks until every healthcheck passes**:

```
▶ waiting for 6 container(s) to become healthy (timeout 240s)
✔ laravel-mysql-1 is healthy
✔ laravel-redis-1 is healthy
✔ laravel-app-1 is healthy
✔ laravel-nginx-1 is healthy
✔ laravel-queue-1 is healthy
✔ laravel-scheduler-1 is healthy

✔ all services are healthy
```

If a service fails, the command aborts and prints that container's logs — it
will not leave you guessing. Go to **[Troubleshooting](08-troubleshooting.md)**.

---

## 7. Set up the application

```bash
make key         # generate APP_KEY (only needed once)
make migrate     # create the schema
```

Optionally seed data:

```bash
make seed
```

---

## 8. Verify

Open **<http://localhost>** — you should see the Laravel welcome page.

Then confirm the whole stack is genuinely wired up:

```bash
make info        # php artisan about
```

Look for these lines. They prove the app is talking to the containers, not to
something on your host:

```
Environment ..................... local
Cache ........................... redis
Database ........................ mysql
Queue ........................... redis
Session ......................... redis
Logs ............................ stderr
```

Finally, run the test suite:

```bash
make test
```

---

## 9. Frontend (optional)

For hot-module reloading while you work on the UI:

```bash
make dev         # everything above, plus Vite on :5173
```

For a one-off production asset build:

```bash
make build-assets
```

---

## You are ready

Next: **[Daily workflow](02-daily-workflow.md)** — what to run as you actually
work, and the handful of cases where you need to rebuild.

### The five commands you will use most

```bash
make up                            # start
make logs SERVICE=app              # watch
make shell                         # get inside the container
make artisan ARGS="migrate"        # run Artisan
make down                          # stop (your data survives)
```

> **⚠ `make destroy`** removes containers, images *and volumes* — every row in
> your database included. It asks for confirmation. `make down` is the one you
> want for everyday stopping.
