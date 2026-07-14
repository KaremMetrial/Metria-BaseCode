# Enterprise Security Threat Model

**Version:** 1.0.0  
**Date:** 2026-07-14  
**Classification:** Enterprise Internal / Production Readiness Protocol  

## Executive Summary
This document establishes the comprehensive threat model across all 8 bounded contexts of the Laravel modular monolith. Each domain is evaluated against STRIDE (Spoofing, Tampering, Repudiation, Information Disclosure, Denial of Service, Elevation of Privilege) attack vectors, with verified architectural controls and code-level mitigations enforced across the platform.

---

## 1. Domain: Auth (`app/Domain/Auth/`)

### Threat Vectors & Mitigations
| Threat ID | Attack Vector | STRIDE Category | Verified Architectural & Code Control | File Reference |
| :--- | :--- | :---: | :--- | :--- |
| **AUTH-T01** | **Social Login Impersonation / Token Bypass**<br>Attacker sends forged user ID or unverified OAuth credentials directly to social login callback. | Spoofing<br>Elevation of Privilege | Strict OIDC Cryptographic Token Verification (`SocialProviderStrategy::verifySocialIdentity()`). Validates exact token signature (`userFromToken($token)`) and enforces OIDC claims (`iss`, `aud`, `exp`, `tenant`) before associating social identities. | `SocialAuthController.php#L44-L72`<br>`SocialProviderStrategy.php` |
| **AUTH-T02** | **OTP Brute-Force & Enumeration**<br>Attacker spams OTP verification endpoints with high-frequency guesses or concurrent requests. | Denial of Service<br>Spoofing | Multi-Layered Protection inside `VerifyOtp::execute()`: Proactive `RateLimiter::attempt()` (5 attempts/minute) + Cache lock (`lock("otp:{$user->id}")`) + Database row lock (`lockForUpdate()`) checking attempt thresholds before code comparison. | `VerifyOtp.php#L35-L88` |
| **AUTH-T03** | **Stale or Leaked JWT Token Replay**<br>Revoked or expired access tokens reused across API requests. | Spoofing<br>Information Disclosure | Token blacklisting & strict cache expiration check in `JwtGuard` with tenant scope verification (`aud` claim). | `JwtGuard.php`<br>`TokenBlacklistService.php` |

---

## 2. Domain: Governance (`app/Domain/Governance/`)

### Threat Vectors & Mitigations
| Threat ID | Attack Vector | STRIDE Category | Verified Architectural & Code Control | File Reference |
| :--- | :--- | :---: | :--- | :--- |
| **GOV-T01** | **Cross-Tenant Audit Log Tampering / Leakage**<br>Malicious tenant or compromised worker alters or queries audit logs of another tenant. | Tampering<br>Information Disclosure | Global `BelongsToTenant` scope automatically appends `where('tenant_id', $currentTenantId)` to all `AuditLog` queries. Audit rows are append-only (`Auditable` trait) without update/delete policies. | `AuditLog.php`<br>`Auditable.php` |
| **GOV-T02** | **Approval Workflow Mutation Bypass & Race Conditions**<br>Multiple approvers approve the same pending request concurrently, triggering duplicate mutation side-effects. | Tampering<br>Elevation of Privilege | Unified atomic database transaction inside `ApprovalService::approve()`. Row-lock via `lockForUpdate()` ensures step verification (`isPending()`) and mutation handler execution (`$handler->execute()`) occur sequentially in isolated tenant context (`TenantManager::runInContext()`). | `ApprovalService.php#L44-L82` |

---

## 3. Domain: Media (`app/Domain/Media/`)

### Threat Vectors & Mitigations
| Threat ID | Attack Vector | STRIDE Category | Verified Architectural & Code Control | File Reference |
| :--- | :--- | :---: | :--- | :--- |
| **MED-T01** | **Malicious File Upload / Path Traversal / Polyglot Executable**<br>Attacker uploads `.php` disguised as `.jpg` or uses `../../` in filename. | Tampering<br>Elevation of Privilege | Deep MIME check + Magic Bytes verification in `MediaVerificationService`. Filenames are regenerated using cryptographic UUIDs (`HasUuid`). | `MediaVerificationService.php` |
| **MED-T02** | **Worker Denial of Service via Huge Variant Queues**<br>Attacker uploads massive image files to exhaust CPU/memory during variant generation. | Denial of Service | Asynchronous queue processing (`ProcessMediaVariants`, `VerifyMediaUpload`) with strict operational limits (`timeout = 300`, `maxExceptions = 3`, explicit exponential `backoff()`). | `ProcessMediaVariants.php`<br>`VerifyMediaUpload.php` |

---

## 4. Domain: Payment (`app/Domain/Payment/`)

### Threat Vectors & Mitigations
| Threat ID | Attack Vector | STRIDE Category | Verified Architectural & Code Control | File Reference |
| :--- | :--- | :---: | :--- | :--- |
| **PAY-T01** | **Database Lock Pool Exhaustion during Gateway Outages**<br>Attacker initiates bulk refunds while external payment gateway is unresponsive, holding DB row locks across network HTTP timeouts. | Denial of Service | Two-Phase Saga Pattern in `PaymentService::executeRefund()`. Database row lock (`lockForUpdate()`) transitions state to `ProcessingRefund` in milliseconds and commits before any remote gateway API call (`$this->gateway->refund()`) executes. | `PaymentService.php#L154-L186`<br>`ADR-003` |
| **PAY-T02** | **Webhook Forgery / Replay Attacks / Cross-Tenant Injection**<br>Attacker sends forged webhook JSON or replays old Stripe/PayPal callbacks without valid signatures. | Spoofing<br>Tampering | Strict HMAC signature verification before parsing payload. Webhook controller immediately binds exact tenant context (`TenantManager::set($payment->tenant_id)`) upon resolving payment entity before performing idempotency checks (`webhook_events` table). | `PaymentWebhookController.php#L35-L65` |

---

## 5. Domain: RBAC (`app/Domain/RBAC/`)

### Threat Vectors & Mitigations
| Threat ID | Attack Vector | STRIDE Category | Verified Architectural & Code Control | File Reference |
| :--- | :--- | :---: | :--- | :--- |
| **RBAC-T01** | **Tenant Privilege Escalation via Global Role Assignment**<br>Tenant Admin assigns themselves system-wide superuser permissions or accesses another tenant's roles. | Elevation of Privilege | Single-Guard, Multi-Role ABAC/RBAC engine enforced with strict `BelongsToTenant` boundaries. `RoleMetadata` models cannot be queried or assigned across `tenant_id` boundaries. | `RoleMetadata.php`<br>`RbacServiceProvider.php` |
| **RBAC-T02** | **Stale Permission Cache Exploitation**<br>User's permissions are revoked by admin, but active session continues using cached authorization capabilities. | Elevation of Privilege | Cache key versioning & event-driven invalidation (`RoleUpdated`, `PermissionRevoked`). `Gate::before` checks active cache version tag per tenant and user before granting access. | `RbacCacheManager.php` |

---

## 6. Domain: Territory (`app/Domain/Territory/`)

### Threat Vectors & Mitigations
| Threat ID | Attack Vector | STRIDE Category | Verified Architectural & Code Control | File Reference |
| :--- | :--- | :---: | :--- | :--- |
| **TER-T01** | **Zone Code Collisions & Race Conditions**<br>Concurrent API requests create duplicate `code` entries for the same tenant, breaking routing and dispatch logic. | Denial of Service<br>Tampering | Composite unique database index `zones_tenant_code_unique` (`['tenant_id', 'code']`) enforced at the relational database level (`2026_07_14_000002_add_unique_index_to_zones_table.php`). | `2026_07_14_000002_add_unique_index_to_zones_table.php` |
| **TER-T02** | **Geospatial Ray-Casting CPU Exhaustion**<br>Attacker submits overly complex polygon arrays (100,000+ vertices) to crash coordinate matching. | Denial of Service | Polygon coordinate validation bounds checking + array length caps (`max:500` points) in Form Requests (`StoreZoneRequest`) before invoking `containsCoordinate($lat, $lng)`. | `Zone.php#L53-L77` |

---

## 7. Domain: Wallet (`app/Domain/Wallet/`)

### Threat Vectors & Mitigations
| Threat ID | Attack Vector | STRIDE Category | Verified Architectural & Code Control | File Reference |
| :--- | :--- | :---: | :--- | :--- |
| **WAL-T01** | **Double-Spending & Negative Balance Race Conditions**<br>Attacker sends two concurrent transfer requests for total wallet balance simultaneously. | Elevation of Privilege<br>Tampering | Strict row-level pessimistic locking (`Wallet::whereKey($walletId)->lockForUpdate()->first()`) inside a single atomic database transaction inside `WalletService::transfer()` and `withdraw()`. Balance assertions check (`$wallet->balance >= $amount`) occur *after* acquiring row lock. | `WalletService.php#L45-L95` |
| **WAL-T02** | **Ledger Tampering & Balance Desynchronization**<br>Direct DB modifications alter `balance` column without creating corresponding immutable `WalletTransaction` audit records. | Tampering<br>Repudiation | Immutable `WalletTransaction` append-only ledger design. `balance` column is treated as a read-cache; background integrity checker audits `sum(transactions.amount)` against `wallets.balance`. | `WalletTransaction.php` |

---

## 8. Domain: Webhook (`app/Domain/Webhook/` & Outbox Core)

### Threat Vectors & Mitigations
| Threat ID | Attack Vector | STRIDE Category | Verified Architectural & Code Control | File Reference |
| :--- | :--- | :---: | :--- | :--- |
| **WEB-T01** | **Duplicate Event Relay / Event Storming during Cluster Scaling**<br>Multiple outbox relay cron workers poll `outbox_messages` concurrently and dispatch duplicate events across the cluster. | Denial of Service | 4-Stage State Machine with row-level reservation locks (`lock('FOR UPDATE SKIP LOCKED')`). Each message is claimed (`status = 'reserved'`, `reserved_by = $workerUuid`) before `EventBus::publish()` executes out-of-transaction. | `PublishOutboxMessages.php#L35-L74`<br>`ADR-004` |
| **WEB-T02** | **Outgoing Webhook Payload Tampering & Man-in-the-Middle**<br>Downstream consumer receives intercepted or spoofed webhooks from unauthorized third parties. | Spoofing<br>Tampering | Stripe-style cryptographic timestamped HMAC-SHA256 headers (`X-Webhook-Signature: t=<timestamp>,v1=<hmac>`) computed using unique `endpoint->secret` in `DeliverWebhook`. | `DeliverWebhook.php#L62-L78` |

---

## Governance & Continuous Verification
All controls documented in this Threat Model are verified via automated PHPUnit integration suites (`vendor/bin/phpunit`) and strict static analysis (`phpstan level: max`). Any new domain feature or modification must pass feature flag governance (`config/features.php`) and update this document before production deployment.
