# Metrial Base Code

A production-grade Metrial Base Code (Laravel 12/13) starting point for API-first products, built for
teams shipping to Egypt / UAE / GCC markets and beyond:

- **API-first** — versioned `/api/v1`, uniform JSON envelope, stable error codes,
  request-id tracing, idempotency keys, rate-limit tiers.
- **Multi-language** — English + Arabic out of the box, per-user locale,
  `Accept-Language` negotiation, RTL direction metadata, JSON model translations.
- **RBAC** — spatie/laravel-permission with `resource.action` permissions and
  seeded roles (`super-admin`, `admin`, `finance`, `support`, `customer`).
- **Governance** — immutable audit logs (secret-masking), runtime settings,
  feature flags with % rollout, and maker-checker approval workflows.
- **Event-driven** — domain events with a transactional outbox relayed to
  signed, retried outgoing webhooks.
- **Payments** — Stripe, Paymob, Fawry and PayTabs drivers behind one contract;
  refunds gated by approvals.
- **Wallet** — minor-unit balances, append-only ledger, escrow
  (hold → capture/release) with row-level locking.
- **Integrations** — circuit-breaker `ApiClient` base, SMS manager
  (Twilio / Vonage / log), FCM push.
- **Multi-tenancy (optional)** — single-DB `tenant_id` scoping, toggled by env.

Read [ARCHITECTURE.md](ARCHITECTURE.md) for the layer map, patterns, and the
end-to-end event flow diagram.

## Requirements

PHP 8.3 · Composer · PostgreSQL 16 (MySQL 8 works too) · Redis

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# configure DB_*/REDIS_* in .env, then:
php artisan migrate --seed

php artisan serve            # API at http://localhost:8000/api/v1
php artisan horizon          # queue workers (webhooks, listeners)
php artisan schedule:work    # outbox relay + governance pruning (local)
```

Smoke-test:

```bash
curl http://localhost:8000/api/v1/health
```

> All migrations ship in-repo — including UUID-ready `personal_access_tokens`
> and the spatie permission tables — so `migrate` works on the first run with
> no vendor:publish step.

## Quick tour of the API

```http
POST /api/v1/auth/register            name, email, password(+confirmation), locale
POST /api/v1/auth/login               → { user, token }   (Sanctum bearer)
GET  /api/v1/auth/me                  Authorization: Bearer <token>

GET  /api/v1/wallet                   balance / held / available
GET  /api/v1/wallet/transactions     append-only ledger (paginated)

POST /api/v1/payments                 Idempotency-Key: <uuid>
                                      { amount: "150.50", gateway: "paymob" }
                                      → payment + next_action
                                        (redirect_url | client_secret | reference_code)
GET  /api/v1/payments/{id}
POST /api/v1/payments/{id}/refund     needs `payments.refund` permission →
                                      202 approval request (maker-checker)

POST /api/v1/governance/approvals/{id}/approve   second approver executes it
GET  /api/v1/governance/audit-logs               filter by action/user/type
PUT  /api/v1/governance/flags/{name}             { enabled: true }
PUT  /api/v1/governance/settings/{key}           { value: … }

POST /api/v1/webhook-endpoints        register a consumer URL; signing secret
                                      is revealed once (rotate endpoint available)
```

Localise any request with `?lang=ar` or `Accept-Language: ar` — messages and
`meta.direction` follow.

### Response envelope

```json
{ "success": true, "message": null, "data": { … },
  "meta": { "request_id": "…", "locale": "ar", "direction": "rtl",
            "pagination": { "current_page": 1, "per_page": 20, "total": 42, "last_page": 3 } } }
```

Errors always carry a stable machine code:

```json
{ "success": false,
  "error": { "code": "insufficient_funds", "message": "…", "errors": {} },
  "meta": { "request_id": "…" } }
```

## Payments

Configure the default gateway + credentials in `.env` (`PAYMENT_DEFAULT`,
`STRIPE_*`, `PAYMOB_*`, `FAWRY_*`, `PAYTABS_*`). Per-request override:
`{ "gateway": "fawry" }`.

| Gateway | Flow returned to the client | Refunds |
| --- | --- | --- |
| Stripe | `client_secret` (PaymentIntent) | API, full/partial |
| Paymob | `redirect_url` (Accept iframe) | needs webhook-captured transaction id |
| Fawry | `reference_code` (pay cash at any Fawry point) | needs Fawry reference number |
| PayTabs | `redirect_url` (hosted page) | API, full/partial |

Point each provider's callback at
`POST /api/v1/webhooks/payments/{gateway}` — requests are authenticated by
signature (HMAC / re-query), never by session. **Verify endpoint shapes against
each provider's current docs before go-live; sandbox base URLs are the defaults.**

Add your own gateway in three steps: implement
`App\Domain\Payment\Contracts\PaymentGateway`, register it —
`app(PaymentManager::class)->extend('mygateway', fn () => new MyGateway(config('payments.gateways.mygateway')))`
— and add its config block. Callers never change.

## Wallet & escrow

```php
$wallets = app(\App\Domain\Wallet\Services\WalletService::class);

$wallets->credit($wallet, Money::fromDecimal('500.00', 'EGP'), 'Top-up');
$wallets->hold($wallet, Money::of(30_000, 'EGP'), 'Delivery escrow');   // lock
$wallets->settleHold($payerWallet, $courierWallet, Money::of(30_000, 'EGP')); // payout
$wallets->release($wallet, Money::of(30_000, 'EGP'), 'Cancelled');      // unlock
```

Every mutation locks the wallet row (`lockForUpdate`) and appends a ledger row
with `balance_after` / `held_after` snapshots — safe under concurrency,
reconstructable for finance.

## Events & outgoing webhooks

Publish through the bus and both worlds are handled:

```php
app(\App\Core\Events\EventBus::class)->publish(new PaymentSucceeded($payment));
```

- **In-process** listeners: map them in `DomainEventServiceProvider`
  (run after commit).
- **External** consumers: events implementing `StoredInOutbox` land in
  `outbox_messages` inside the same transaction; the scheduled
  `outbox:publish` command relays them to every registered
  `WebhookEndpoint` as an HMAC-signed POST with retries
  (backoff 1m / 5m / 30m / 2h) and per-delivery status.

Consumers verify `X-Webhook-Signature: t=<ts>,v1=<hmac>` where
`hmac = HMAC_SHA256("{t}.{raw_body}", endpoint_secret)` — reject stale `t`.

Built-in event names: `user.registered`, `payment.succeeded`,
`payment.failed`, `payment.refunded`, `wallet.credited`, `wallet.debited`.

## Governance

- **Audit** — add the `Auditable` trait to any model; changes are logged with
  configured attributes masked (`config/governance.php`). Retention enforced
  by the scheduled `governance:prune`.
- **Maker-checker** — register an invokable handler under
  `governance.approvals.handlers`, create requests via `ApprovalService`;
  approving executes the handler transactionally, self-approval is blocked.
  Refunds are pre-wired as the reference implementation.
- **Feature flags** — allowlist → deterministic % bucket → global toggle:
  `app(FeatureFlagService::class)->enabled('new-pricing', $user)`.
- **Settings** — cached key/value with API management endpoints.

## Multi-tenancy

Set `TENANCY_ENABLED=true`, send `X-Tenant: <tenant-uuid>` (or rely on the
user's `tenant_id`), and every `BelongsToTenant` model is scoped automatically.
Leave it off for single-tenant products — zero overhead.

## Testing & quality

```bash
composer test      # PHPUnit (sqlite :memory:) — auth, wallet/escrow,
                   # payment flow with faked gateway, idempotency, webhooks
composer lint      # Laravel Pint
composer analyse   # Larastan
```

CI (`.github/workflows/ci.yml`) runs all three on push/PR.

## Extending with a new domain

1. `app/Domain/Orders/{Models,Services,Events,…}` — keep the shape of the
   existing domains.
2. Migration + policy/permissions (add to `RolesAndPermissionsSeeder`).
3. Thin controller in `app/Http/Controllers/Api/V1` + FormRequest + Resource,
   routes in `routes/api_v1.php`.
4. Publish domain events through `EventBus`; implement `StoredInOutbox` if the
   outside world should hear about them.

## Identity upgrade path (metrial/auth)

RBAC here intentionally uses `spatie/laravel-permission` — the same foundation
`metrial/auth` builds on. When a project needs enterprise identity (SSO:
SAML/OIDC/LDAP, MFA & WebAuthn/passkeys, SCIM provisioning, adaptive/risk-based
auth, compliance reporting), upgrade without breaking existing roles:

```bash
composer require metrial/auth
php artisan metrial-auth:install   # interactive wizard, pick modules
```

Existing `resource.action` permissions, role assignments, and the seeded role
matrix carry over unchanged; `metrial/auth` layers SSO/MFA/tenancy modules on
top of the same tables.

## License

MIT — use it as the base for anything you're building.
