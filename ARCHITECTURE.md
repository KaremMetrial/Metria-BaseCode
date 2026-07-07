# Architecture

A modular monolith for API-first products: strong boundaries and event-driven
seams **without** the operational cost of microservices. Every domain can be
extracted into a service later because domains only talk through events,
contracts, and application services — never through each other's internals.

## Layer map

```
app/
├── Core/                    ← shared kernel (no business rules)
│   ├── Support/             Result, Money (minor-units value object)
│   ├── Abstracts/           DataTransferObject, BaseRepository
│   ├── Contracts/           RepositoryInterface
│   ├── Events/              DomainEvent, EventBus, StoredInOutbox
│   ├── Outbox/              OutboxMessage (transactional outbox)
│   ├── Exceptions/          ApiException → Domain/Payment/Integration
│   ├── Http/                ApiController, ForceJsonResponse, SetLocale,
│   │                        IdempotencyMiddleware
│   ├── Tenancy/             TenantManager, TenantScope, BelongsToTenant,
│   │                        ResolveTenant (single-DB tenant_id strategy)
│   └── Traits/              ApiResponses, HasUuid, HasTranslations
│
├── Domain/                  ← business capabilities (one folder per subdomain)
│   ├── Auth/                User, RegisterUser, IssueApiToken, UserRegistered
│   ├── Governance/          AuditLog, Settings, FeatureFlags,
│   │                        ApprovalRequests (maker-checker)
│   ├── Payment/             PaymentManager + Stripe/Paymob/Fawry/PayTabs
│   │                        drivers, PaymentService, refund approvals
│   ├── Wallet/              Wallet + append-only ledger + escrow
│   │                        (hold → capture/release), row-level locking
│   ├── Webhook/             outgoing webhooks: endpoints, deliveries,
│   │                        signed + retried DeliverWebhook job
│   └── Integration/         CircuitBreaker, ApiClient base, SmsManager
│                            (Twilio/Vonage/log), FCM push
│
├── Http/                    ← delivery layer only (thin controllers)
│   ├── Controllers/Api/V1/  versioned controllers
│   ├── Requests/            FormRequest validation
│   └── Resources/           response transformers
│
└── Providers/               AppServiceProvider, DomainEventServiceProvider
```

**Dependency rule:** `Http → Domain → Core`. Core imports nothing from
Domain; Domain never imports Http. Cross-domain reactions happen through
events (e.g. `UserRegistered` → Wallet provisioning), not direct calls.

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
