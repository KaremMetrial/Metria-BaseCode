# Documentation

Everything you need to run, extend and deploy the Laravel Docker platform.

---

## Start here

**Never used this before?** Read these two, in order, and you will have a
working environment and know how to work in it day to day.

1. **[Getting started](01-getting-started.md)** — from `git clone` to a Laravel
   page in your browser. ~10 minutes.
2. **[Daily workflow](02-daily-workflow.md)** — what to run when you change
   code, add a package, write a migration, or debug something.

---

## Reference

Look things up here; you are not expected to read them front to back.

| Document | Answers |
|---|---|
| **[Command reference](03-command-reference.md)** | "What's the command for…?" — all 60 `make` targets and the helper scripts |
| **[Configuration](04-configuration.md)** | "What does this variable do, and where do I set it?" |
| **[Architecture](05-architecture.md)** | "Why is it built like this?" — services, networks, images, and the reasoning |

---

## Operations

| Document | Answers |
|---|---|
| **[Production deployment](06-production-deployment.md)** | How to build, ship, deploy, migrate and roll back |
| **[Security](07-security.md)** | What is hardened, what is not, and the pre-launch checklist |
| **[Troubleshooting](08-troubleshooting.md)** | Symptom → cause → fix for the failures you will actually hit |
| **[Adopting & upgrading](09-adopting.md)** | Dropping this into an existing project; upgrading PHP/MySQL/Laravel |

## Product architecture

| Document | Answers |
|---|---|
| **[Communication platform architecture](communication/architecture.md)** | The Phase 1 design for the reusable, tenant-isolated communication engine; no implementation is included. |
| **[Communication contract blueprint](communication/contract-blueprint.md)** | Mandatory Phase 1.5 REST, logical database, durable-event, and Socket.IO contracts before implementation. |

---

## Finding things fast

| I want to… | Go to |
|---|---|
| Get the stack running | [Getting started](01-getting-started.md) |
| Run an Artisan command | [Commands → Laravel](03-command-reference.md#laravel) |
| Add a Composer or npm package | [Daily workflow → Dependencies](02-daily-workflow.md#adding-dependencies) |
| Connect a GUI client to MySQL | [Configuration → Published ports](04-configuration.md#published-ports) |
| Turn on step debugging | [Daily workflow → Xdebug](02-daily-workflow.md#debugging-with-xdebug) |
| Understand why my `.env` change is ignored | [Troubleshooting](08-troubleshooting.md#env-changes-appear-to-be-ignored) |
| Deploy to a server | [Production deployment](06-production-deployment.md) |
| Rotate a database password | [Security → Secrets](07-security.md#secrets-management) |
| Upgrade to PHP 8.5 | [Adopting → Upgrading](09-adopting.md#upgrading-the-platform) |
| Know what is *not* production-ready yet | [Security → Known gaps](07-security.md#known-gaps-and-accepted-trade-offs) |

---

## Conventions used in these docs

Commands are written for the project root:

```bash
make up                      # preferred — the documented interface
docker/scripts/artisan tinker # equivalent helper script
```

Anything destructive is marked **⚠**. Anything that only applies to production
is marked **[prod]**. Where a default is unusual, the docs say *why* — those
notes are the difference between copying a config and understanding it.
