# ADR-002: Multi-Tenant Isolation and Queue Context Propagation

**Status:** Approved & Implemented  
**Date:** 2026-07-14  
**Context:** Enterprise Production Readiness  

## 1. Context and Problem Statement
In our multi-tenant enterprise system (`TenantManager`), every incoming HTTP request sets the active tenant context (`TenantManager::set()`). However, when async jobs or queued event listeners (`ShouldQueue`) are dispatched, they execute in background queue workers that do not inherit HTTP request state. If a job executes without restoring the tenant context, models and global scopes either fail with "No active tenant context set" or risk leaking data across tenants.

Furthermore, placing context-switching logic inside global service providers (`boot()` or `queue::before`) couples infrastructure lifecycle handlers to background jobs and creates hidden race conditions during worker loop iteration.

## 2. Decision
1. **Pure TenantManager:** Keep `TenantManager::set()` pure without hidden side-effects. Introduce `runInContext(string $tenantId, callable $callback)` to ensure guaranteed cleanup of context using `try { ... } finally { $this->set($previous); }`.
2. **Explicit Job Middleware (`RestoreTenantContext`):** Every tenant-aware background job or listener MUST declare explicit middleware:
   ```php
   public function middleware(): array
   {
       return [new \App\Core\Queue\Middleware\RestoreTenantContext()];
   }
   ```
3. **Webhook Context Binding:** Controller endpoints handling asynchronous external webhooks (`PaymentWebhookController`) MUST immediately bind `TenantManager::set($payment->tenant_id)` upon resolving the entity before executing domain mutations.

## 3. Consequences
### Positive
- **Guaranteed Isolation:** Queue workers automatically restore exact tenant context during `$next($job)` execution and clear context immediately when the job finishes.
- **Zero Cross-Tenant Leaks:** Models with `BelongsToTenant` scope are safely filtered even in high-throughput worker pools.
