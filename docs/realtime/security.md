# Socket.IO security boundary

Clients send a Sanctum bearer token through Socket.IO `auth.token`, never a URL
query string. Node sends that token only to Laravel's HMAC-protected internal
authentication endpoint. Laravel validates Sanctum's hashed token record,
expiration, active user status, tenant and permissions, then returns a
short-lived realtime assertion.

Each socket automatically joins only its tenant and tenant-user rooms. Resource
subscriptions accept only `payment` and `wallet` identifiers; Laravel evaluates
the existing policy before Node joins the room. A browser cannot issue an
arbitrary `socket.join()` command.

Laravel signs each Redis event. The Socket.IO service verifies its HMAC and
schema before delivery, and Redis-backed deduplication suppresses duplicate
queue deliveries for five minutes. Raw tokens and event payloads are not logged.

Production requires non-empty `REALTIME_INTERNAL_SECRET` and
`REALTIME_EVENT_HMAC_SECRET`, private Redis access, and exact allowed origins.

## Internal request signature

The Node service signs every Laravel internal request with HMAC-SHA256 over the
following newline-delimited canonical string. The URL query is excluded; the
path is the normalized URL pathname.

```text
UPPERCASE_HTTP_METHOD
NORMALIZED_REQUEST_PATH
UNIX_TIMESTAMP
UUID_NONCE
SHA256_OF_EXACT_UTF8_BODY
```

For example, with `POST`, `/api/internal/realtime/authenticate`, timestamp
`1722679200`, nonce `0d62f994-6d25-45ca-b2eb-7081393fce10`, and body
`{"token":"example"}`, the body hash is
`39d990d1fff0df025853e25601a090c7a10bede007ed52636c47bafe890120b2`.
Both implementations verify the fixed-vector HMAC
`bde71386cedf8bb56a9251736b2b962f3ea7d17f92104ae884b15032ad79a65c` for
the test secret `realtime-transport-test-secret-that-is-long-enough`.

The public Nginx edge returns 404 for `/api/internal/realtime/*`. Only the
backend-network `internal-nginx` service exposes those paths to the realtime
container. Nonces are written with atomic cache-add semantics for 60 seconds.
