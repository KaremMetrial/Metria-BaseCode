# Troubleshooting

`AUTH_TOKEN_INVALID` means Laravel could not resolve the Sanctum token.
`AUTH_TOKEN_EXPIRED` means the short realtime assertion expired; refresh the
Sanctum token and reconnect. `RESOURCE_FORBIDDEN` means Laravel's existing
payment or wallet policy denied the requested resource subscription.

If connections fail in a browser, compare the browser origin with
`REALTIME_ALLOWED_ORIGINS` and confirm the reverse proxy exposes `/socket.io/`.
If no events arrive, inspect Laravel's `realtime` queue, then the realtime
service JSON logs for `event.emitted` or `event.rejected`. A signature rejection
means the Laravel and Socket.IO HMAC secrets differ.

`event.duplicate_suppressed` is expected when multiple realtime nodes consume
the same Laravel Pub/Sub envelope. Exactly one node logs `event.emitted` for an
event ID; its Socket.IO Redis adapter broadcast reaches sockets on every node.
The dedupe claim occurs before broadcast, so a process crash in that window can
lose the live notification. Recover by reconnecting and fetching authoritative
state, not by assuming Redis Pub/Sub will replay it.
