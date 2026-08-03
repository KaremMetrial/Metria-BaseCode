import { z } from "zod";

const uuid = z.string().uuid();
const money = z.number().int().nonnegative();
const currency = z.string().regex(/^[A-Z]{3}$/);
const gateway = z.string().min(1).max(64);
export const resourceTypeSchema = z.enum(["payment", "wallet", "conversation"]);
export type ResourceType = z.infer<typeof resourceTypeSchema>;

export const identitySchema = z.object({
  sub: uuid,
  tenant_id: uuid,
  roles: z.array(z.string()),
  permissions: z.array(z.string()),
  token_id: z.string().min(1)
}).strict();
export type SocketIdentity = z.infer<typeof identitySchema>;

const audienceSchema = z.discriminatedUnion("type", [
  z.object({ type: z.literal("user"), user_id: uuid }).strict(),
  z.object({ type: z.literal("users"), user_ids: z.array(uuid).min(1).max(100) }).strict(),
  z.object({ type: z.literal("tenant") }).strict(),
  z.object({ type: z.literal("resource"), resource_type: resourceTypeSchema, resource_id: uuid }).strict()
]);
const metadataSchema = z.object({ correlation_id: uuid.nullable(), causation_id: uuid.nullable(), trace_id: z.string().max(128).nullable() }).strict();
const envelopeBase = { id: uuid, version: z.literal(1), occurred_at: z.string().datetime(), tenant_id: uuid, audience: audienceSchema, metadata: metadataSchema };

const paymentSucceededPayload = z.object({ payment_id: uuid, user_id: uuid, gateway, amount: money, currency }).strict();
const paymentFailedPayload = paymentSucceededPayload.extend({ reason: z.string().max(512).nullable() }).strict();
const paymentRefundedPayload = z.object({ payment_id: uuid, user_id: uuid, gateway, refunded_amount: money, currency }).strict();
const paymentRefundFailedPayload = z.object({ payment_id: uuid, user_id: uuid, gateway, attempted_amount: money, reason: z.string().min(1).max(512), currency }).strict();
const walletPayload = z.object({ wallet_id: uuid, user_id: uuid, amount: money, currency, balance: z.number().int() }).strict();
const sessionRevokedPayload = z.object({ user_id: uuid, email: z.string().email(), session_id: uuid, token_id: z.string().nullable() }).strict();
const allSessionsRevokedPayload = z.object({ user_id: uuid, email: z.string().email() }).strict();
const conversationCreatedPayload = z.object({
  conversation_id: uuid,
  conversation_version: z.number().int().nonnegative(),
  type: z.enum(["direct", "private_group", "private_channel"]),
  state: z.literal("active")
}).strict();
const messageCreatedPayload = z.object({
  conversation_id: uuid,
  message_id: uuid,
  sequence: z.number().int().positive(),
  kind: z.enum(["text", "markdown", "system"]),
  revision: z.number().int().positive(),
  author_actor_id: uuid
}).strict();
const membershipRemovedPayload = z.object({
  conversation_id: uuid,
  actor_id: uuid,
  conversation_version: z.number().int().positive(),
  state: z.literal("left")
}).strict();

export const eventSchemas = {
  "payment.succeeded": paymentSucceededPayload,
  "payment.failed": paymentFailedPayload,
  "payment.refunded": paymentRefundedPayload,
  "payment.refund_failed": paymentRefundFailedPayload,
  "wallet.credited": walletPayload,
  "wallet.debited": walletPayload,
  "security.session_revoked": sessionRevokedPayload,
  "security.all_sessions_revoked": allSessionsRevokedPayload,
  "communication.conversation.created": conversationCreatedPayload,
  "communication.message.created": messageCreatedPayload,
  "communication.membership.removed": membershipRemovedPayload
} as const;

type SubjectType = "payment" | "wallet" | "user" | "conversation" | "message" | "membership";
const eventSchema = <TName extends keyof typeof eventSchemas>(name: TName, subjectType: SubjectType) => z.object({
  ...envelopeBase,
  name: z.literal(name),
  subject: z.object({ type: z.literal(subjectType), id: uuid }).strict(),
  payload: eventSchemas[name]
}).strict();

export const realtimeEventSchema = z.discriminatedUnion("name", [
  eventSchema("payment.succeeded", "payment"),
  eventSchema("payment.failed", "payment"),
  eventSchema("payment.refunded", "payment"),
  eventSchema("payment.refund_failed", "payment"),
  eventSchema("wallet.credited", "wallet"),
  eventSchema("wallet.debited", "wallet"),
  eventSchema("security.session_revoked", "user"),
  eventSchema("security.all_sessions_revoked", "user"),
  eventSchema("communication.conversation.created", "conversation"),
  eventSchema("communication.message.created", "message"),
  eventSchema("communication.membership.removed", "membership")
]);
export type RealtimeEvent = z.infer<typeof realtimeEventSchema>;

export const subscriptionSchema = z.object({ resource_type: resourceTypeSchema, resource_id: uuid }).strict();
