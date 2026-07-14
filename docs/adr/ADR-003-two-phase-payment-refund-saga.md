# ADR-003: Two-Phase Payment Refund Saga Pattern

**Status:** Approved & Implemented  
**Date:** 2026-07-14  
**Context:** Enterprise Production Readiness  

## 1. Context and Problem Statement
Payment refund processing involves communicating with external payment gateway APIs over HTTP while simultaneously updating local database state and releasing lock constraints.
Previously, `PaymentService::executeRefund()` wrapped both the local state update and the remote gateway HTTP request inside a single database transaction using row-level locking (`$payment->lockForUpdate()`).
If the external payment gateway experienced network latency or hung for 30–60 seconds, the database connection and row lock were held open across the entire network duration. Under concurrency or retry spikes, this caused connection pool exhaustion and deadlocks across the `Payment` domain.

## 2. Decision
We refactored `executeRefund()` into an asynchronous **Two-Phase Saga Pattern**:
1. **Phase 1 (Atomic Reservation):** Inside a short-lived database transaction, lock the payment row (`lockForUpdate()`), verify eligibility (`isRefundable()`), and immediately transition status to `PaymentStatus::ProcessingRefund`. Commit the transaction and release the database lock.
2. **Phase 2 (Remote Gateway I/O):** Outside of any database transaction or row lock, execute `$this->gateway->refund(...)`.
3. **Phase 3 (Saga Finalization):**
   - On gateway success: In a fresh short-lived transaction, transition payment to `Refunded` (or `RefundReversed`) and dispatch success events.
   - On gateway failure: In a short-lived transaction, transition payment to `RefundFailed`, dispatch `PaymentRefundFailed` domain event, and re-throw exception for visibility.

## 3. Consequences
### Positive
- **Zero Database Lock Contention:** Database row locks are held for milliseconds during state transition rather than seconds during network I/O.
- **Resilience against Gateway Latency:** Gateway connection timeouts never block local database transaction pools.
- **Auditable Failure Recovery:** The explicit `ProcessingRefund` and `RefundFailed` states provide clear auditability for operations teams to investigate or retry stalled refunds without double-refunding.
