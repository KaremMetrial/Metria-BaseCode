# Metrial BaseCode API documentation

Scramble generates OpenAPI 3.1 documents from the Laravel routes, Form Requests, Resources, response types, and middleware.

| Document | Route | Audience |
| --- | --- | --- |
| Client API | `/docs/api` | Web, mobile, and supported client integrations |
| Administrative API | `/docs/admin` | Privileged operational integrations |
| Webhook API | `/docs/webhooks` | Payment and outbound-webhook implementers |

Internal service routes, including Socket.IO authorization endpoints under `/api/internal`, are intentionally excluded.

## Access and generation

Documentation is disabled by default. Enable it only in `local`, `testing`, or protected staging environments with `SCRAMBLE_ENABLED=true`. Staging users need `api-docs.view` (or `admin.super`); production returns 404 unless an operator explicitly enables the feature and adds `production` to `SCRAMBLE_ALLOWED_ENVIRONMENTS`.

Generate CI artifacts with:

```bash
mkdir -p artifacts/openapi
php artisan scramble:analyze
php artisan scramble:export --path=artifacts/openapi/api.json
php artisan scramble:export --api=admin --path=artifacts/openapi/admin.json
php artisan scramble:export --api=webhooks --path=artifacts/openapi/webhooks.json
```

Use `Authorization: Bearer <Sanctum token>` for protected routes. Tenant-scoped operations resolve tenant context from the authenticated user; `X-Tenant-ID`/`X-Tenant` cannot switch a normal user to another tenant. Payment creation uses `Idempotency-Key` for safe retries. See [the realtime documentation](../realtime/architecture.md) for Socket.IO events.
