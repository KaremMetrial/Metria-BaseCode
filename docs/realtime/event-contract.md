# Real-time event contract

Every client message from the server uses `realtime:event` and this envelope:

```json
{
  "id": "event UUID",
  "name": "payment.succeeded",
  "version": 1,
  "occurred_at": "ISO-8601 timestamp",
  "tenant_id": "tenant UUID",
  "audience": { "type": "users", "user_ids": ["user UUID"] },
  "subject": { "type": "payment", "id": "payment UUID" },
  "payload": {},
  "metadata": { "correlation_id": "UUID or null", "causation_id": null, "trace_id": null }
}
```

Supported version-one names: `payment.succeeded`, `payment.failed`,
`payment.refunded`, `payment.refund_failed`, `wallet.credited`, `wallet.debited`,
`security.session_revoked`, and `security.all_sessions_revoked`. Every name has
its own strict payload schema; unknown fields, unsupported names, unsupported
versions, and malformed UUID/currency/money fields are rejected.

Audience types are allowlisted: `user`, `users`, `tenant`, and `resource`.
They are converted to internal room names by the service. Clients never supply
or need room names.

To add an event: map an existing committed domain event, create a minimum safe
payload and strict Node schema, choose an allowlisted audience, add tests, and
document the new name and version before publishing it.

`id` is the idempotency identity. Reusing it inside the distributed dedupe TTL
produces no additional logical delivery; the same payload with a new `id` is a
new event. Clients should retain recently seen IDs and always resynchronize
from Laravel after reconnect, because live Redis Pub/Sub delivery is not durable.
