# Communication Platform — Phase 1.5 Contract Blueprint

**Status:** Proposed for architecture approval  
**Prerequisite:** [Phase 1 architecture](architecture.md)  
**Scope:** Stable contracts for backend, web, mobile, QA, platform, and operations teams. This document specifies interfaces and data boundaries only. It adds no code, migrations, routes, or infrastructure changes.

## 1. Contract decisions

This phase removes implementation ambiguity before Phase 2.

1. REST is authoritative for every durable mutation and state read.
2. The existing signed Socket.IO transport remains a best-effort, low-latency change-hint channel.
3. MySQL is the communication write authority. Redis is used only for ephemeral state, rate limiting, cache, and the existing realtime transport.
4. The transactional outbox is the sole origin for durable integration/realtime projection events.
5. All IDs sent through public contracts are canonical strings. Internal compact storage, if adopted, is invisible to clients.
6. Every public resource and event is tenant-scoped, versioned, documented in Scramble, and compatible by additive evolution.
7. Cursor synchronization, server sequence, and idempotency identity are the convergence contract for web and mobile clients.

## 2. Cross-cutting HTTP contract

### Base path, authentication, and tenancy

The public base path is:

    /api/v1/communication

All endpoints require the established API middleware: Sanctum authentication, resolved tenant context, API throttling, and a resource/action policy. A client cannot use a tenant header to escape its authenticated tenant. Internal realtime authorization endpoints remain private and outside public OpenAPI.

### Envelope and error shape

The module uses the platform response envelope:

~~~json
{
  "success": true,
  "message": null,
  "data": {},
  "meta": {
    "request_id": "string",
    "locale": "en",
    "direction": "ltr"
  }
}
~~~

Errors preserve the platform shape and use a stable machine code:

~~~json
{
  "success": false,
  "message": "Human-readable localized summary",
  "error": {
    "code": "COMMUNICATION_CONVERSATION_FORBIDDEN"
  },
  "errors": {
    "field": ["Validation message"]
  },
  "meta": {
    "request_id": "string"
  }
}
~~~

Clients branch on error.code, not localized message text. The initial error catalogue is:

| HTTP | Code | Meaning |
|---|---|---|
| 400 | COMMUNICATION_CURSOR_INVALID | malformed, incompatible, or expired cursor |
| 401 | AUTH_TOKEN_INVALID or AUTH_TOKEN_EXPIRED | Sanctum credential is invalid/expired |
| 403 | COMMUNICATION_CONVERSATION_FORBIDDEN | actor cannot see or act on the resource |
| 403 | COMMUNICATION_MEMBERSHIP_REQUIRED | actor is not an active participant |
| 403 | COMMUNICATION_ACTION_NOT_ALLOWED | profile, role, block, or lock denies the action |
| 404 | COMMUNICATION_CONVERSATION_NOT_FOUND | not found or intentionally hidden from this actor |
| 409 | COMMUNICATION_IDEMPOTENCY_CONFLICT | same key is processing or has incompatible request body |
| 409 | COMMUNICATION_SEQUENCE_CONFLICT | an explicit expected version/sequence is stale |
| 410 | COMMUNICATION_SYNC_RESET_REQUIRED | requested history/cursor was retained no longer |
| 413 | COMMUNICATION_PAYLOAD_TOO_LARGE | message, batch, or rich-text payload is too large |
| 422 | COMMUNICATION_MESSAGE_KIND_INVALID | unsupported kind or invalid discriminated payload |
| 422 | COMMUNICATION_ATTACHMENT_NOT_READY | Media asset is not clean/finalized/owned |
| 429 | COMMUNICATION_RATE_LIMITED | command or ephemeral interaction quota exceeded |

Permission failures deliberately use the same resource-hidden response where revealing the existence of a private conversation would be sensitive.

### Timestamps, IDs, and representation

- IDs are opaque UUID strings.
- Timestamps are UTC ISO-8601 values with offset, emitted by the server.
- Money, business cards, custom content, and rich-text data are typed JSON; clients must not infer unadvertised fields.
- Fields not applicable to a message kind are omitted, not sent as ambiguous null placeholders.
- All request bodies reject unknown security-sensitive fields. Read responses may grow additively.
- Enumerations are documented as open only where explicitly marked extensible. Clients render unknown future message kinds/events as safe unsupported values.

### Pagination, sorting, and filtering

Timeline, inbox, search, mentions, and membership lists use opaque cursor pagination. A response returns:

~~~json
{
  "data": [],
  "meta": {
    "next_cursor": "opaque-string-or-null",
    "previous_cursor": "opaque-string-or-null",
    "has_more": true,
    "snapshot_at": "2026-08-03T10:00:00Z"
  }
}
~~~

Cursors encode ordering/version state but are never parsed by clients. The default and maximum page sizes use the platform API limits; a conversation timeline has a smaller documented maximum to protect hot conversations. Sort keys are endpoint-specific and fixed. A search/filter grammar accepts only documented fields, operators, and values.

### Idempotency and concurrency

Every state-creating command, including create conversation, send message, invite participant, upload-reference finalization, report, and automation trigger, requires the existing Idempotency-Key request header. Replaying an identical completed command returns the stored response and Idempotency-Replayed true. Reusing a key with a different endpoint, actor, tenant, or canonical request body returns 409.

**Current-platform compatibility note:** the shared middleware already scopes a
key by method, path, actor, and tenant, and returns a conflict while the request
is in flight. It does not currently fingerprint the canonical request body.
Before any Communication endpoint claims the stronger contract above, Phase 2
must add a body-fingerprint compatibility check to the shared mechanism or a
Communication-specific adapter, with a reviewed rollout for existing clients.
Until then, clients must never reuse one idempotency key for materially
different payloads.

Message editing, conversation settings changes, and moderation actions accept an optional expected_version/expected_revision for optimistic conflict detection. Commands that omit it use last-write policy only where the domain explicitly permits it. Read-cursor advance is monotonic and does not conflict.

## 3. REST resource contract

### Conversation resources

| Method and path | Command/query | Authorization | Contract notes |
|---|---|---|---|
| GET /conversations | inbox/list | list visible conversations | cursor, state/type/context/unread filters |
| POST /conversations | create | conversation create + profile policy | idempotent; profile/context binding validated |
| GET /conversations/{id} | detail | active participant or explicit policy | hides inaccessible private resources |
| PATCH /conversations/{id} | rename/settings/avatar | profile + conversation role | optional expected_version |
| POST /conversations/{id}/archive | archive | archive capability | idempotent lifecycle transition |
| POST /conversations/{id}/restore | restore | restore capability | retention/legal-hold aware |
| POST /conversations/{id}/lock | lock/unlock | moderation/owner policy | write policy changes immediately |
| GET /conversations/{id}/sync | authoritative delta | active participant | cursor is primary recovery mechanism |

A conversation representation includes: id, tenant_id only where platform policy permits exposing it, type, title, avatar reference, lifecycle, settings/capabilities, context bindings, actor-specific membership summary, latest visible sequence, unread summary, created/updated timestamps, and version. It never embeds an unbounded message timeline or all participants by default.

### Participant resources

| Method and path | Contract |
|---|---|
| GET /conversations/{id}/participants | cursor list; participant visibility policy applies |
| POST /conversations/{id}/participants | idempotent invite/add command; one or bounded actor IDs |
| PATCH /conversations/{id}/participants/{actorId} | role or participant setting change; expected membership version optional |
| DELETE /conversations/{id}/participants/{actorId} | leave/remove; self-leave and moderator removal are distinct policy actions |
| POST /conversations/{id}/mute | actor-private mute/unmute preference |

Participant responses expose only the profile-safe identity projection from Auth. They do not duplicate user ownership data.

### Message resources

| Method and path | Contract |
|---|---|
| GET /conversations/{id}/messages | cursor timeline ordered by server sequence |
| POST /conversations/{id}/messages | idempotent append; returns canonical message and sequence |
| GET /conversations/{id}/messages/{messageId} | current representation plus authorized revision/thread summaries |
| PATCH /conversations/{id}/messages/{messageId} | edit current content; creates revision; optional expected_revision |
| DELETE /conversations/{id}/messages/{messageId} | soft delete/tombstone; policy distinguishes own/any |
| POST /conversations/{id}/messages/{messageId}/pin | pin/unpin command |
| POST /conversations/{id}/messages/{messageId}/forward | idempotent forward to authorized destination |
| POST /conversations/{id}/messages/{messageId}/reactions | add reaction; idempotent tuple |
| DELETE /conversations/{id}/messages/{messageId}/reactions/{reactionKey} | remove caller's reaction or moderator-authorized removal |

Create message request:

~~~json
{
  "client_message_id": "uuid",
  "kind": "text",
  "content": {
    "text": "Quarterly report is ready."
  },
  "thread_root_message_id": null,
  "parent_message_id": null,
  "quote_message_id": null,
  "attachment_ids": [],
  "mentions": [
    {"actor_id": "uuid"}
  ]
}
~~~

The server ignores a client timestamp for ordering. The create response includes the canonical message ID, sequence, server timestamp, revision, visibility/lifecycle state, attachment states, and a sync cursor.

Message kind/content is a discriminated contract:

| Kind family | Required content shape |
|---|---|
| text | text, with bounded plain text |
| markdown | markdown, rendered only through a server-approved sanitizer |
| rich_text | document version plus approved node tree |
| image/video/audio/document | attachment references only |
| location | latitude, longitude, optional labeled place; precision policy applies |
| contact | safe contact card projection, never arbitrary vCard execution |
| system/bot | registered system or bot payload schema |
| business_card | registered type plus typed, minimal business reference |
| quote/forward | source message reference and authorized display snapshot |
| ephemeral | otherwise valid inner kind plus expiry policy |
| custom | registered custom kind and versioned strict schema |

### Threads, receipts, search, and compliance

| Method and path | Contract |
|---|---|
| GET /conversations/{id}/threads/{rootId}/messages | cursor replies, conversation sequence order |
| POST /conversations/{id}/threads/{rootId}/resolve | resolve/reopen under thread policy |
| POST /conversations/{id}/read-cursor | monotonic advance to visible sequence; idempotent |
| GET /communication/mentions | actor's mention inbox, cursor list |
| GET /communication/search/messages | allowlisted query grammar, cursor results, search-lag metadata |
| POST /messages/{id}/report | idempotent report with category/evidence reference |
| GET/POST /moderation/cases | privileged case list/action contract |
| POST /conversations/{id}/exports | privileged asynchronous archive/export intent |

All batch commands are bounded. They return per-item outcomes and never silently turn partial success into all-or-nothing semantics.

### Sync contract

Sync is the recovery and offline contract. Request parameters are conversation cursor, direction, limit, and optional known state version. Response carries ordered changes, not merely new messages:

~~~json
{
  "data": {
    "conversation": {
      "id": "uuid",
      "version": 19,
      "latest_sequence": 842
    },
    "changes": [
      {
        "sequence": 842,
        "change_type": "message.created",
        "message": {}
      },
      {
        "sequence": 840,
        "change_type": "message.updated",
        "message": {}
      }
    ],
    "cursor": "opaque-next-cursor"
  },
  "meta": {
    "snapshot_at": "2026-08-03T10:00:00Z",
    "has_more": false
  }
}
~~~

A deletion, edit, moderation visibility change, membership transition, or retention tombstone must appear in sync so an offline device converges. A cursor older than the retained change window returns SYNC_RESET_REQUIRED with the bounded full-resync procedure.

## 4. Logical database contract

This section specifies logical tables and invariants. Physical data types and migrations require a later data ADR and review.

| Logical table | Key fields | Hard constraints |
|---|---|---|
| communication_conversations | id, tenant_id, type, state, settings_version, next_sequence, version | tenant/type/state; unique profile-specific natural keys where required |
| communication_context_bindings | id, tenant_id, conversation_id, context_type, context_id | unique tenant/conversation/context tuple |
| communication_memberships | id, tenant_id, conversation_id, actor_id, role, state, last_read_sequence, last_delivered_sequence, version | unique conversation/actor |
| communication_messages | id, tenant_id, conversation_id, sequence, author_actor_id, kind, state, current_revision, client_idempotency_key | unique conversation/sequence; unique tenant/author/idempotency key |
| communication_message_revisions | id, tenant_id, message_id, revision, editor_actor_id, content, reason | unique message/revision |
| communication_threads | id, tenant_id, conversation_id, root_message_id, state, resolved_by | unique root_message_id |
| communication_reactions | id, tenant_id, message_id, actor_id, reaction_key | unique message/actor/reaction key |
| communication_mentions | id, tenant_id, message_id, mentioned_actor_id, sequence | unique message/mentioned actor |
| communication_attachment_references | id, tenant_id, message_id, media_asset_id, state | unique message/media asset |
| communication_pins | id, tenant_id, conversation_id, message_id, pinned_by | unique conversation/message |
| communication_drafts | id, tenant_id, conversation_id, actor_id, device_id, content, expires_at | unique conversation/actor/device |
| communication_sync_cursors | id, tenant_id, conversation_id, actor_id, device_id, sequence | unique conversation/actor/device |
| communication_moderation_cases | id, tenant_id, target_type, target_id, state, decision | immutable action history linked separately |
| communication_retention_assignments | id, tenant_id, target_type, target_id, policy_version, legal_hold | one effective assignment per target |
| communication_archive_manifests | id, tenant_id, conversation_id, storage_ref, checksum, state | immutable completed manifest |
| communication_outbox | event_id, tenant_id, type, version, payload, occurred_at | event ID unique; delivery state owned by existing outbox pattern |

Required access indexes:

- tenant_id, conversation_id, sequence descending for timeline and sync;
- tenant_id, actor_id, membership state, updated time descending for inbox;
- tenant_id, mentioned_actor_id, sequence descending for mentions;
- thread_id, conversation_id, sequence for replies;
- tenant_id, state, updated time for moderation/retention workers;
- tenant-prefixed index keys for all projections and caches.

Every durable row carries tenant_id. Same-module foreign keys preserve local integrity where they do not prevent archival/partitioning. Cross-module actor, context, media, and business-resource IDs are typed references validated by ports, not cross-module database foreign keys.

### Partition, archive, and shard contract

Messages are the only initially expected append-heavy table. Partitioning is time-based only after measured growth/retention evidence. Archive is an immutable manifest plus encrypted object store payload. Purge is idempotent, audited, retention-aware, and blocked by legal hold.

The future shard key is tenant. A conversation never spans shards. The tenant directory determines database region/shard before each request/job; no hot-path cross-shard query is permitted.

## 5. Durable event contract

Domain events are internal facts. Integration events are versioned external contracts emitted through the outbox. Realtime projection events are minimum-safe derivatives of these facts.

Every integration event has:

~~~json
{
  "event_id": "uuid",
  "event_name": "communication.message.created",
  "event_version": 1,
  "occurred_at": "2026-08-03T10:00:00Z",
  "tenant_id": "uuid",
  "aggregate": {
    "type": "conversation",
    "id": "uuid",
    "version": 19
  },
  "payload": {},
  "metadata": {
    "correlation_id": "uuid-or-null",
    "causation_id": "uuid-or-null",
    "trace_id": "string-or-null"
  }
}
~~~

| Event | Required safe payload | Primary consumers |
|---|---|---|
| communication.conversation.created | conversation ID/type/state/context references | inbox, audit, search |
| communication.conversation.updated | changed fields, version, actor | sync, audit, realtime |
| communication.membership.changed | conversation, actor, old/new state/role | authorization cache, notifications, realtime |
| communication.message.created | message, conversation, sequence, kind, author, safe summary | sync, realtime, notifications, search |
| communication.message.updated | message, revision, sequence, changed-state summary | sync, realtime, search |
| communication.message.deleted | message, sequence, deletion reason code | sync, realtime, search |
| communication.reaction.changed | message, reaction key, actor, action | realtime, projections |
| communication.thread.changed | root, state, last reply sequence | realtime, projections |
| communication.read.cursor_advanced | conversation, actor, monotonic sequence | unread projection, realtime |
| communication.attachment.state_changed | message, Media asset ref, safe state | realtime, search |
| communication.moderation.action_applied | target, decision, visibility/retention state | sync, audit, search |
| communication.retention.applied | target, action, policy reference | audit, archive, search |

Compatibility rules:

- New optional fields are additive.
- Removing, renaming, changing type, changing meaning, or expanding an audience is breaking.
- Breaking change creates a new event version and dual-publishes only for a controlled migration period.
- Consumers ignore unknown optional fields and reject an unknown major/version safely.
- Event IDs are globally unique and reused across retries. Consumers store/claim event IDs according to their delivery guarantee.

## 6. Realtime Socket.IO contract

### Existing contract retained

Connection remains to the current default Socket.IO namespace and path. The client provides its Sanctum token using auth.token, never a query string. The Socket.IO service obtains a Laravel-issued short-lived assertion and emits realtime:ready. The existing generic realtime:event payload is the only server-to-client durable event carrier.

No new namespace, direct Redis access, client-selected room, or client-supplied tenant ID is allowed.

### Communication subscriptions

The existing resource subscription protocol is extended in a later implementation to accept resource_type conversation:

~~~json
{
  "resource_type": "conversation",
  "resource_id": "uuid"
}
~~~

The client emits resource:subscribe and receives one acknowledgement:

~~~json
{"ok": true}
~~~

or:

~~~json
{"ok": false, "code": "RESOURCE_FORBIDDEN"}
~~~

The edge asks Laravel's signed internal authorization endpoint. Laravel validates current assertion, token state, tenant, active membership, conversation visibility/lifecycle, and policy. Successful subscription joins the server-derived room tenant:{tenant}:conversation:{conversation}. The client never sees or controls the literal room name.

Unsubscribe uses the existing resource:unsubscribe shape and is idempotent. Membership removal, block, token revocation, or assertion expiry invalidates future subscriptions. Existing clients receive resync_required or disconnect according to the existing security policy.

### Server-to-client event envelope

Communication continues to use realtime:event:

~~~json
{
  "id": "uuid",
  "name": "communication.message.created",
  "version": 1,
  "occurred_at": "2026-08-03T10:00:00Z",
  "tenant_id": "uuid",
  "audience": {
    "type": "resource",
    "resource_type": "conversation",
    "resource_id": "uuid"
  },
  "subject": {
    "type": "message",
    "id": "uuid"
  },
  "payload": {
    "conversation_id": "uuid",
    "message_id": "uuid",
    "sequence": 842,
    "revision": 1,
    "kind": "text",
    "state": "active"
  },
  "metadata": {
    "correlation_id": "uuid-or-null",
    "causation_id": "uuid-or-null",
    "trace_id": "string-or-null"
  }
}
~~~

Message content appears in a realtime payload only when the audience is provably entitled and a small, schema-versioned rendering snapshot is needed. The default payload is identifiers/state/sequence; clients fetch or sync authoritative content.

### Ephemeral controls

The future allowed client controls are precisely named:

| Client event | Payload | Ack | Semantics |
|---|---|---|---|
| communication:typing | conversation_id, active boolean | ok/code | bounded, rate-limited, TTL refreshed |
| communication:recording | conversation_id, active boolean | ok/code | same as typing; no media bytes |
| communication:presence | status and optional custom status | ok/code | rate-limited preference/heartbeat input |
| realtime:ping | existing empty/bounded payload | existing ok | connection liveness only |

These events require the current socket assertion, server-side conversation authorization, strict schema, payload byte limits, per-socket/actor/conversation rate limits, Redis TTL, and Redis-adapter broadcast. They never create a durable message, receipt, or audit entry. Durable send/edit/delete/react/read operations are REST commands.

### Recovery and ordering

The live edge remains at-most-once to currently connected clients. Each client:

1. retains recent event IDs for duplicate suppression;
2. compares received message/change sequence with its conversation cursor;
3. calls conversation sync after reconnect, resync_required, sequence gap, unknown event version, or uncertainty;
4. applies REST sync results in server sequence/revision order;
5. advances its durable device cursor only after local persistence succeeds.

A client must never invent a sequence, treat Socket.IO acknowledgement as durable write success, or rely on Redis history for offline recovery.

## 7. Search, attachment, notification, and external contracts

### Media contract

Communication calls Media through an attachment intent/reference contract. Media must return asset ID, tenant ownership, state, checksum, media class, size, MIME, and safe preview capability. Valid send states are clean and finalized. Communication never receives an unbounded signed URL as authority; presentation requests a short-lived authorized URL from Media.

### Search contract

Search receives tenant-scoped, policy-versioned projection documents. A search response includes index_lag_seconds or a degradation flag. It never promises read-your-write before projection completion. Authorization happens before candidates are returned and again at result serialization.

### Notification contract

NotificationBridge accepts an idempotent intent containing tenant, recipient actor(s), source event ID, priority, conversation/message references, preference category, and safe preview. Notification transport failures never roll back a sent message.

### External/bot/AI contract

All adapters use versioned commands/events, signature verification, correlation IDs, idempotency identity, bounded payloads, and least-privilege actor membership. They may not write tables directly or impersonate a human without an auditable delegation record.

## 8. Acceptance evidence by team

| Team | Must prove before Phase 2 exit |
|---|---|
| Backend | command/error/idempotency/cursor policy tests and event schema fixtures |
| Web/mobile | offline temporary-ID mapping, sync convergence, unknown event/kind handling, no room construction |
| QA | tenant isolation, participant removal, edit/delete/retention, receipt monotonicity, reconnect scenarios |
| Realtime/platform | conversation subscription authorization, strict schemas, rate limits, two-node delivery and resync behavior |
| Data | query-plan/index evidence, retention/archive reconciliation, backup/restore assumptions |
| Security | IDOR, tenant escape, replay, XSS/rich-text, attachment state, block/moderation tests |
| DevOps/SRE | SLO dashboard, queue/outbox/realtime metrics, alert thresholds, incident/DR runbook |

## 9. Approval checklist

Phase 1.5 is approved only when product, security, data, and platform owners agree on:

- initial conversation profiles and hard quotas;
- the externally visible message kinds and content schemas;
- the error catalogue and hidden-resource policy;
- cursor retention/reset behavior and offline UX;
- ID representation and data residency/retention/legal-hold requirements;
- the event-version compatibility policy;
- the conversation room subscription and ephemeral-control limits;
- notification/Media/Search ownership boundaries;
- SLO, RPO, RTO, and load-test targets.

After approval, Phase 2 may implement only these contracts additively. Any deviation requires an ADR update and contract review first.
