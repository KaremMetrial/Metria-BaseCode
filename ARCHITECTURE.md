# Architecture

A modular monolith for API-first products: strong boundaries and event-driven
seams **without** the operational cost of microservices. Every domain can be
extracted into a service later because domains only talk through events,
contracts, and application services — never through each other's internals.

## Layer map

The codebase is organized as **modules**, not a single `app/Domain` tree.
`app/` itself now holds only host-level wiring (`Http/`, `Providers/`); every
business capability lives under `modules/{Module}/`, each split into three
internal layers:

```
modules/
├── Shared/                  ← shared kernel (no business rules)
│   ├── Domain/               Contracts, Events, Specifications, Support (Money)
│   ├── Application/           Abstracts (BaseRepository), Exceptions, Support
│   └── Infrastructure/        Tenancy (TenantManager, TenantScope,
│                              BelongsToTenant), Events (EventBus, Outbox),
│                              Broadcasting, Realtime, Queue, Persistence,
│                              Localization, Traits, config/
│
├── Auth/                    User, RegisterUser, IssueApiToken, UserRegistered,
│                            MFA, lockout policies
├── RBAC/                    Roles, permissions, tenant-scoped policy resolution
├── Governance/               AuditLog, Settings, FeatureFlags,
│                            ApprovalRequest (maker-checker)
├── Payment/                  PaymentManager + Stripe/Paymob/Fawry/PayTabs
│                            drivers, PaymentService, refund approvals
├── Wallet/                   Wallet + append-only ledger + escrow
│                            (hold → capture/release), row-level locking
├── Webhook/                  Outgoing webhooks: endpoints, deliveries,
│                            signed + retried DeliverWebhook job
├── Integration/               CircuitBreaker, ApiClient base, SmsManager
│                            (Twilio/Vonage/log), FCM push
├── Media/                    Upload/verify/process pipeline, virus scanning,
│                            moderation, state machine
├── Communication/             In-app messaging / conversations
├── Currency/                  Exchange rates, currency conversion
└── Territory/                 Countries/regions/duplicate detection

Each module follows the same three sub-layers:
  Domain/          entities, contracts, domain events — no framework code
  Infrastructure/   Eloquent models' concerns, services, providers, config/
  Presentation/     Http/Controllers, Requests, Resources, Policies, routes/
```

**Dependency rule:** within a module, `Presentation → Infrastructure →
Domain`. Across modules, the intended seam is events and shared contracts
(e.g. `UserRegistered` → Wallet provisioning) rather than direct calls —
Governance's `approvals.handlers` config map (invoking a handler by name,
never importing it beyond that array) is the model to follow. Payment and
Governance are a known exception: `PaymentService` currently depends on
Governance's `ApprovalService`/`ApprovalRequest`/`AuditLogger` directly.
Treat that as tracked debt, not the pattern to copy for new cross-module
work — see the "Known gaps" section below.

## Patterns in use (and why)

| Pattern                               | Where                                                      | Why                                                                                                                          |
| ------------------------------------- | ---------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| **Manager / Driver (Strategy)** | `PaymentManager`, `SmsManager`                         | Swap Stripe↔Paymob↔Fawry↔PayTabs per env/market with config; add gateways without touching callers.                       |
| **Transactional Outbox**        | `EventBus` + `outbox_messages` + `outbox:publish`    | External side effects (webhooks) never fire for rolled-back state and are never lost — at-least-once delivery with retries. |
| **Action classes**              | `RegisterUser`, `IssueApiToken`                        | One use case per class: testable, discoverable, no god-services.                                                             |
| **Application services**        | `PaymentService`, `WalletService`, `ApprovalService` | Orchestrate transactions + events; controllers stay 5–15 lines.                                                             |
| **Value Object**                | `Money`                                                  | Integer minor units end float-rounding bugs; currency-mismatch guarded at the type level.                                    |
| **Append-only ledger**          | `wallet_transactions`, `audit_logs`                    | Financial and compliance history is immutable and reconstructable.                                                           |
| **Maker-checker (four-eyes)**   | `ApprovalService` + `governance.approvals.handlers`    | Sensitive ops (refunds) need a second approver; self-approval is blocked.                                                    |
| **Pessimistic locking**         | `WalletService` (`lockForUpdate`)                      | Concurrent spends serialize — no double-spending under race.                                                                |
| **Idempotency keys**            | `IdempotencyMiddleware`                                  | Client retries of`POST /payments` replay the response instead of double-charging.                                          |
| **Circuit breaker**             | `CircuitBreaker`, `ApiClient`                          | A dead third party fails fast instead of exhausting workers.                                                                 |
| **Observer**                    | `AuditableObserver`                                      | Every create/update/delete on audited models is logged with masked secrets.                                                  |
| **Repository (optional)**       | `BaseRepository`                                         | Available for complex query encapsulation; simple domains use Eloquent directly — no ceremony for its own sake.             |

## Event flow (end to end)

```
POST /api/v1/payments            Stripe webhook: payment_intent.succeeded
        │                                        │
        ▼                                        ▼
 PaymentService::create()          PaymentService::handleWebhook()
   Payment row (pending)             verify HMAC → parse → transition
   gateway->createPayment()          publish PaymentSucceeded ──┐
        │                                                       │ same DB tx
        ▼                                                       ▼
  client_secret / redirect                        outbox_messages row
                                                        │  (every minute)
                                                        ▼
                                             outbox:publish (locked batch)
                                                        │
                                                        ▼
                                        WebhookDispatcher → deliveries
                                                        │
                                                        ▼
                                DeliverWebhook job: HMAC-signed POST,
                                backoff 1m/5m/30m/2h, per-endpoint status
```

In-process listeners (`DomainEventServiceProvider`) run after commit via
`DB::afterCommit`; external consumers get the same event through the outbox.

## Multi-language

`SetLocale` resolves `?lang` → user preference → `Accept-Language` → fallback,
and every response's `meta` carries `locale` + `direction` (`rtl` for ar/fa/ur/he)
so clients can render correctly. `HasTranslations` stores per-model translations
as JSON (`{"en": "...", "ar": "..."}`) with automatic fallback — no extra package.

## Multi-tenancy

Single database, `tenant_id` column strategy: `BelongsToTenant` applies a
global scope from `TenantManager`, resolved per request by the `tenant`
middleware (header `X-Tenant` or the user's tenant). Toggle with
`TENANCY_ENABLED`; off by default so single-tenant projects pay zero cost.
If you outgrow this (noisy neighbours, per-tenant DB compliance), swap to
database-per-tenant behind the same `TenantManager` seam.

## Error envelope

Success and failure are machine-parseable and stable:

```json
{ "success": true,  "message": null, "data": {…},
  "meta": { "request_id": "…", "locale": "ar", "direction": "rtl" } }

{ "success": false,
  "error": { "code": "insufficient_funds", "message": "…", "errors": {} },
  "meta": { "request_id": "…", "locale": "en" } }
```

All exceptions render centrally in `bootstrap/app.php`; domain code throws
typed exceptions (`DomainException`, `PaymentException`, `IntegrationException`)
with stable `error.code` values clients can switch on.

## Security posture

- Sanctum bearer tokens; `throttle:auth` (10/min) on login/register,
  `throttle:api` (120/min), `throttle:payments` (30/min).
- RBAC via spatie/laravel-permission, `resource.action` permission naming,
  roles seeded (`super-admin`, `admin`, `finance`, `support`, `customer`).
- Webhook ingress: per-gateway signature verification (`hash_equals`,
  timestamp tolerance); webhook egress: HMAC-signed with rotatable secrets.
- Audit logger masks `password`, `token`, `secret`, `api_key`, …
- Secrets only in `.env`; nothing sensitive in code or logs.
- `config/cors.php` / `config/sanctum.php` are published explicitly
  (`CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`) rather than left on
  framework defaults.

## Known gaps

- **Payment ↔ Governance coupling.** `PaymentService` imports Governance's
  `ApprovalService`, `ApprovalRequest`, and `AuditLogger` directly, and
  `Governance`'s config imports Payment's `ApproveRefundHandler`. This is a
  real, bidirectional class-level dependency — not an events-only seam — so
  neither module can be extracted into its own service without the other.
  The fix is to have Payment raise a `RefundRequested` domain event that
  Governance subscribes to, rather than Payment orchestrating Governance's
  services inline.
- **HSTS, CSP, and a WAF** are not configured at the application layer —
  expected to be handled by the edge/reverse proxy in front of this service.
- **No dedicated secrets manager** — secrets are environment variables /
  Docker `*_FILE` mounts, not Vault/AWS Secrets Manager. Acceptable for the
  current deployment target; revisit if that changes.
