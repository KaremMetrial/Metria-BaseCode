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
rather than direct calls. Two concrete examples:

- Governance's `approvals.handlers` config map invokes a handler by class
  name without importing it beyond that array — Governance drives, the
  handler's owning module (e.g. Payment's `ApproveRefundHandler`) supplies.
- Payment depends on Governance's maker-checker and audit-logging
  capabilities through `Modules\Shared\Domain\Contracts\ApprovalGateway`
  and `AuditRecorder` — contracts owned by Shared and bound to Governance's
  concrete services in `GovernanceServiceProvider`. `PaymentService` never
  imports Governance's `ApprovalService`/`ApprovalRequest`/`AuditLogger`
  directly, so Payment stays extractable without dragging Governance's
  internals along. The `Auditable` trait models opt into (Payment, Wallet,
  Media, Webhook, Territory, Auth's `User`) lives in
  `Modules\Shared\Infrastructure\Traits` for the same reason — it resolves
  its observer through `Modules\Shared\Domain\Contracts\AuditObserver`
  rather than importing Governance's `AuditableObserver`.

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
| **Template Method (CRUD)**      | `BaseCrudController`                                     | index/show/store/update/destroy written once; a plain-CRUD module (`WebhookEndpointController`) declares 3-4 class properties instead of five hand-written actions. |

## One identity model, many roles — no per-role code

There is exactly one `User` model and one HTTP surface. `super-admin`,
`admin`, `finance`, `support`, `customer`, a future `client`, or any other
role is a row in `roles`/`role_metadata` (seeded in
`RolesAndPermissionsSeeder`) carrying a set of `resource.action` permissions
— never a `hasRole('admin')` branch in a controller. Every endpoint uses the
same request/response envelope (`ApiResponses`) and the same authorization
seam (`Gate::authorize()` + a Policy's `viewAny/view/create/update/delete`
abilities, backed by `spatie/laravel-permission`). Two roles hitting the same
endpoint get identical response shapes; they differ only in which Policy
checks pass and which fields a Resource chooses to expose (e.g. a Resource
can `mergeWhen($request->user()->can('finance.view'), [...])`) — the
difference lives in data/policy, not in duplicated routes or controllers.
Adding a role is a seeder change; it never requires new controller code.

## Adding a new CRUD module without duplicating CRUD

For a module that's genuinely just "manage records of X" (no workflow,
no maker-checker, no side-effecting Actions), extend
`Modules\Shared\Presentation\Http\Controllers\BaseCrudController` instead of
hand-writing index/show/store/update/destroy:

```php
class WidgetController extends BaseCrudController
{
    protected string $modelClass = Widget::class;
    protected string $resourceClass = WidgetResource::class;
    protected string $storeRequestClass = StoreWidgetRequest::class;
    // protected ?string $updateRequestClass = UpdateWidgetRequest::class; // optional, defaults to storeRequestClass
}
```

That's the whole controller — pagination (honoring `core.api.per_page` /
`max_per_page`), the `ApiResponses` envelope, and `Gate::authorize()` against
the model's Policy all come from the base class. Override `indexQuery()`,
`storeAttributes()`, or `updateAttributes()` only when you need extra
scoping or to inject computed fields (see `WebhookEndpointController`, which
overrides `store()` outright because it also has to generate a one-time
secret — the base class is a floor to build on, not a ceiling that forces
every mutation through bare `Model::update()`). A module with real business
rules (approval gates, DTOs, multi-step Actions — see RBAC's
`RoleController` or `PaymentService`) should keep writing its controller by
hand; `BaseCrudController` targets the copy-pasted shape, not every
controller in the codebase.

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

- **Governance's `approvals.handlers` config still names handler classes by
  module** (e.g. `payments.refund => Payment\...\ApproveRefundHandler`),
  and a handler's `__invoke` receives Governance's concrete `ApprovalRequest`
  model. This is the accepted direction for a plugin-style registry
  (Governance drives, the owning module supplies the handler) rather than
  the problematic kind of coupling — but it does mean a handler class is
  never fully framework-agnostic of Governance's domain model. Not treated
  as debt; documented so it isn't mistaken for an oversight.
- **HSTS, CSP, and a WAF** are not configured at the application layer —
  expected to be handled by the edge/reverse proxy in front of this service.
- **No dedicated secrets manager** — secrets are environment variables /
  Docker `*_FILE` mounts, not Vault/AWS Secrets Manager. Acceptable for the
  current deployment target; revisit if that changes.
