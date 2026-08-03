import http from "node:http";
import { createAdapter } from "@socket.io/redis-adapter";
import type { RedisClientType } from "redis";
import { Server } from "socket.io";
import { ZodError } from "zod";
import type { RealtimeConfig } from "./config.js";
import { LaravelAuthClient } from "./auth/laravel-auth-client.js";
import { logger } from "./observability/logger.js";
import { createRedisClient, claimEvent, verifyTransportMessage } from "./redis/event-transport.js";
import { automaticRooms, Room, roomsForAudience } from "./rooms.js";
import { subscriptionSchema, type SocketIdentity } from "./types/realtime.js";

declare module "socket.io" { interface SocketData { identity: SocketIdentity; assertion: string; sessionExpiry: number; } }

export async function createRealtimeApp(config: RealtimeConfig) {
  const log = logger(config);
  const nodeId = config.NODE_ENV === "test" ? config.REALTIME_NODE_ID : undefined;
  let redisClients: Array<Pick<RedisClientType, "isReady">> = [];
  const httpServer = http.createServer((request, response) => {
    if (request.url === "/health/live") return void response.writeHead(200, { "content-type": "application/json" }).end('{"status":"live"}');
    const ready = redisClients.length === 4 && redisClients.every((client) => client.isReady);
    if (request.url === "/health/ready") return void response.writeHead(ready ? 200 : 503, { "content-type": "application/json" }).end(JSON.stringify({ status: ready ? "ready" : "unavailable" }));
    response.writeHead(404).end();
  });
  const commandClient = await createRedisClient(config);
  const adapterPublisher = commandClient.duplicate();
  const adapterSubscriber = commandClient.duplicate();
  const eventSubscriber = commandClient.duplicate();
  redisClients = [commandClient, adapterPublisher, adapterSubscriber, eventSubscriber];
  for (const [name, client] of [["command", commandClient], ["adapter-publisher", adapterPublisher], ["adapter-subscriber", adapterSubscriber], ["event-subscriber", eventSubscriber]] as const) {
    client.on("error", (error) => log.error("redis.error", { client: name, error_code: error.name }));
    client.on("reconnecting", () => log.warn("redis.reconnecting", { client: name }));
    client.on("ready", () => log.info("redis.ready", { client: name }));
    client.on("end", () => log.warn("redis.end", { client: name }));
  }
  await Promise.all([adapterPublisher.connect(), adapterSubscriber.connect(), eventSubscriber.connect()]);

  const io = new Server(httpServer, {
    path: config.REALTIME_PATH,
    transports: ["websocket"],
    maxHttpBufferSize: config.REALTIME_MAX_PAYLOAD_BYTES,
    cors: { origin: (origin, callback) => callback(null, origin === undefined || config.allowedOrigins.includes(origin)), credentials: false },
    allowRequest: (request, callback) => callback(null, Boolean(request.headers.origin && config.allowedOrigins.includes(request.headers.origin)))
  });
  io.adapter(createAdapter(adapterPublisher, adapterSubscriber));
  const auth = new LaravelAuthClient(config);
  const userSockets = new Map<string, number>();

  io.use(async (socket, next) => {
    const token = typeof socket.handshake.auth.token === "string" ? socket.handshake.auth.token : extractBearer(socket.handshake.headers.authorization);
    if (!token) return next(new Error("AUTH_TOKEN_MISSING"));
    try {
      const authenticated = await auth.authenticate(token);
      const count = userSockets.get(authenticated.identity.sub) ?? 0;
      if (count >= config.REALTIME_MAX_CONNECTIONS_PER_USER) return next(new Error("RATE_LIMITED"));
      socket.data.identity = authenticated.identity;
      socket.data.assertion = authenticated.assertion;
      socket.data.sessionExpiry = Date.parse(authenticated.expires_at);
      return next();
    } catch (error) {
      const code = error instanceof Error && /^(AUTH_|USER_|TENANT_|RATE_LIMITED)/.test(error.message) ? error.message : "AUTH_SERVICE_UNAVAILABLE";
      return next(new Error(code));
    }
  });

  io.on("connection", (socket) => {
    const identity = socket.data.identity;
    userSockets.set(identity.sub, (userSockets.get(identity.sub) ?? 0) + 1);
    socket.join(automaticRooms(identity));
    socket.emit("realtime:ready", { user_id: identity.sub, tenant_id: identity.tenant_id, resync_required: true, ...(nodeId ? { node_id: nodeId } : {}) });
    log.info("socket.connected", { socket_id: socket.id, user_id: identity.sub, tenant_id: identity.tenant_id, token_id: identity.token_id });

    const expiryTimer = setTimeout(() => {
      socket.emit("realtime:resync_required", { code: "AUTH_TOKEN_EXPIRED" });
      socket.disconnect(true);
    }, Math.max(1, socket.data.sessionExpiry - Date.now()));

    socket.on("realtime:ping", (ack?: (result: { ok: boolean }) => void) => ack?.({ ok: true }));
    socket.on("resource:subscribe", async (input: unknown, ack?: (result: { ok: boolean; code?: string }) => void) => {
      try {
        const subscription = subscriptionSchema.parse(input);
        if (socket.rooms.size >= config.REALTIME_MAX_ROOMS_PER_SOCKET) throw new Error("RATE_LIMITED");
        await auth.authorizeResource(socket.data.assertion, subscription.resource_type, subscription.resource_id);
        socket.join(Room.resource(identity.tenant_id, subscription.resource_type, subscription.resource_id));
        ack?.({ ok: true });
      } catch (error) {
        const code = error instanceof ZodError ? "PAYLOAD_INVALID" : error instanceof Error ? error.message : "ROOM_NOT_ALLOWED";
        log.warn("room.subscription_denied", { socket_id: socket.id, user_id: identity.sub, tenant_id: identity.tenant_id, error_code: code });
        ack?.({ ok: false, code });
      }
    });
    socket.on("resource:unsubscribe", (input: unknown, ack?: (result: { ok: boolean; code?: string }) => void) => {
      const result = subscriptionSchema.safeParse(input);
      if (!result.success) return ack?.({ ok: false, code: "PAYLOAD_INVALID" });
      socket.leave(Room.resource(identity.tenant_id, result.data.resource_type, result.data.resource_id));
      ack?.({ ok: true });
    });
    socket.on("disconnect", (reason) => {
      clearTimeout(expiryTimer);
      const remaining = Math.max(0, (userSockets.get(identity.sub) ?? 1) - 1);
      if (remaining === 0) userSockets.delete(identity.sub); else userSockets.set(identity.sub, remaining);
      log.info("socket.disconnected", { socket_id: socket.id, user_id: identity.sub, tenant_id: identity.tenant_id, reason });
    });
  });

  await eventSubscriber.subscribe(config.REALTIME_EVENT_CHANNEL, async (raw) => {
    try {
      if (Buffer.byteLength(raw, "utf8") > config.REALTIME_MAX_EVENT_BYTES) throw new Error("INTERNAL_EVENT_TOO_LARGE");
      const event = verifyTransportMessage(raw, config.REALTIME_EVENT_HMAC_SECRET);
      if (!(await claimEvent(commandClient, config.REALTIME_REDIS_KEY_PREFIX, event.id, config.REALTIME_DEDUPE_TTL_SECONDS))) {
        log.info("event.duplicate_suppressed", { event_id: event.id, node_id: nodeId, dedupe_claimed: false, duplicate_suppressed: true });
        return;
      }
      if (event.name === "security.session_revoked" || event.name === "security.all_sessions_revoked") {
        const sockets = await io.in(Room.user(event.tenant_id, event.subject.id)).fetchSockets();
        const tokenId = "token_id" in event.payload && typeof event.payload.token_id === "string" ? event.payload.token_id : undefined;
        for (const socket of sockets) {
          if (event.name === "security.all_sessions_revoked" || socket.data.identity.token_id === tokenId) {
            socket.emit("security:session_revoked", { code: event.name, resync_required: true });
            socket.disconnect(true);
          }
        }
      }
      io.to(roomsForAudience(event)).emit("realtime:event", event);
      log.info("event.emitted", { event_id: event.id, event_name: event.name, tenant_id: event.tenant_id, node_id: nodeId, dedupe_claimed: true, broadcast_performed: true });
    } catch (error) {
      log.warn("event.rejected", { error_code: error instanceof Error ? error.message : "INTERNAL_EVENT_INVALID" });
    }
  });

  const close = async () => {
    await new Promise<void>((resolve) => io.close(() => resolve()));
    await Promise.all([eventSubscriber.quit(), adapterPublisher.quit(), adapterSubscriber.quit(), commandClient.quit()]);
  };
  return { httpServer, close, log };
}

function extractBearer(header: string | string[] | undefined): string | undefined {
  const value = Array.isArray(header) ? header[0] : header;
  const match = value?.match(/^Bearer\s+(.+)$/i);
  return match?.[1];
}
