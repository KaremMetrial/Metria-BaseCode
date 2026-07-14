# ADR-004: Outbox Relay State Machine and Concurrency Control

**Status:** Approved & Implemented  
**Date:** 2026-07-14  
**Context:** Enterprise Production Readiness  

## 1. Context and Problem Statement
Our Outbox pattern (`PublishOutboxMessages` artisan command running every minute via cron/scheduler) polls the `outbox_messages` database table and dispatches events to the message broker (`EventBus::publish()`).
In a distributed, multi-node cluster, multiple relay workers or overlapping cron runs execute simultaneously. Previously, the command queried pending rows and processed them without row-level reservation locks (`lockForUpdate()`).
When `EventBus::publish()` performed network I/O or experienced backpressure, multiple nodes picked up the exact same `pending` outbox messages concurrently, causing duplicate event dispatches across downstream microservices and external webhooks.

## 2. Decision
We refactored `PublishOutboxMessages` into a **4-Stage State Machine** backed by atomic row-level database reservation:
1. **Database Schema Extension:** Added `reserved_at` (timestamp) and `reserved_by` (UUID) columns to `outbox_messages`.
2. **Stage 1 (Atomic Reservation via SKIP LOCKED):**
   Inside a short-lived database transaction, query pending rows using `lock('FOR UPDATE SKIP LOCKED')`:
   ```php
   OutboxMessage::where('status', 'pending')
       ->orWhere(fn ($q) => $q->where('status', 'reserved')->where('reserved_at', '<', now()->subMinutes(10)))
       ->limit($batchSize)
       ->lock('FOR UPDATE SKIP LOCKED')
       ->get();
   ```
   Immediately update claimed rows to `status = 'reserved'`, set `reserved_at = now()`, `reserved_by = $workerUuid`, and commit the reservation transaction.
3. **Stage 2 (Out-of-Transaction Processing):**
   Outside any database transaction or lock, iterate over the reserved messages and invoke `EventBus::publish($message->toDomainEvent())`.
4. **Stage 3 & 4 (Finalization or Failure):**
   Update individual messages to `published` (with `processed_at`) on success, or `failed` (with `error_message` and retry increment) on error.
5. **Stale Lock Recovery:** If a worker node crashes mid-processing, any message stuck in `reserved` state for > 10 minutes is automatically picked up and recovered by active workers.

## 3. Consequences
### Positive
- **Zero Duplicate Events:** `FOR UPDATE SKIP LOCKED` guarantees that each outbox row is claimed by exactly one worker node across the cluster.
- **High-Throughput Concurrency:** Workers never block or wait on locked rows; they skip immediately to available messages.
- **Self-Healing:** Automatic stale reservation expiration prevents dead or crashed workers from permanently blocking event delivery.
