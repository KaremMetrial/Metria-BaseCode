# ADR-006: Socket.IO real-time transport

## Status

Accepted.

## Decision

Laravel remains the authoritative API and authorization point. Committed, allowlisted domain events are handled by an after-commit queued listener, mapped to a versioned and payload-minimised envelope, HMAC-signed, and published to a dedicated Redis Pub/Sub channel. The Node.js Socket.IO service verifies the signature and schema, applies Redis-backed idempotency, then emits only to tenant-scoped rooms via the Socket.IO Redis adapter.

Redis Pub/Sub is intentionally an availability-oriented delivery transport, not a durable event log. The browser must refetch authoritative API state after reconnect or any `resync_required` signal. The existing transactional outbox remains responsible for durable external webhook delivery and is not overloaded with Socket.IO transport concerns.

## Consequences

- Low latency and horizontal Socket.IO scaling without exposing Redis to clients.
- Queue retry makes Laravel-to-Redis publication at-least-once; Redis idempotency and client event IDs make processing safe under retry.
- Connected delivery is at-most-once. Redis downtime or a disconnected client can require API resynchronization.
- Every connection has a validated Sanctum token, a short-lived internal assertion, and exactly one tenant. Resource rooms are authorized by Laravel policies before joining.
- The cluster gate (`npm run test:integration:cluster`) uses two independent Node processes. Each consumes the same signed Laravel Pub/Sub event; Redis `SET NX EX` selects one broadcaster before the Socket.IO Redis adapter distributes that broadcast to remote-node rooms.
- The duplicate claim has a configurable 300-second production TTL. It offers at-most-once live processing within that window, not durable exactly-once delivery. The claim-before-broadcast crash window is accepted and requires client resynchronization.
