# Deployment and scaling

Nginx proxies `/socket.io/` with WebSocket upgrade headers. The service enables
WebSocket-only transport, so a load balancer does not need sticky sessions for
long-polling fallback. Scale `realtime` horizontally; the Socket.IO Redis
adapter distributes room operations and a Redis deduplication key ensures one
instance emits each internal event through that adapter.

Required production configuration: exact `REALTIME_ALLOWED_ORIGINS`, strong
rotated `REALTIME_INTERNAL_SECRET`, strong `REALTIME_EVENT_HMAC_SECRET`, Redis
credentials, and resource limits. Keep Redis on a private network and use ACLs
to restrict both the event channel and adapter key namespace.

Production startup rejects localhost/wildcard origins, known development
secrets, weak secrets, debug logging, missing Redis authentication, and an
authorization URL that does not target `internal-nginx`. The public Nginx edge
does not route the internal authorization paths; `internal-nginx` has no host
ports and is attached only to the backend Docker network.

`/health/live` checks process liveness. `/health/ready` requires a live Redis
command, adapter-publisher, adapter-subscriber, and event-subscriber
connection. During SIGTERM, the service stops accepting connections,
closes Socket.IO, then closes Redis clients before the configured timeout.

The CI job `realtime-cluster-integration` runs the isolated two-node gate. Its
test-only overlay maps Node A and Node B to loopback ports 6101 and 6102; these
ports and test-only `node_id` ready metadata are never part of production
Compose. Redis restart can make readiness unhealthy and clients must reconnect
and resynchronize when their transport or adapter state is interrupted.
