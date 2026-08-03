import crypto from "node:crypto";
import { createClient } from "redis";
import type { RealtimeConfig } from "../config.js";
import { realtimeEventSchema, type RealtimeEvent } from "../types/realtime.js";

export async function createRedisClient(config: RealtimeConfig) {
  const client = createClient({
    url: config.REALTIME_REDIS_URL,
    password: config.REALTIME_REDIS_PASSWORD || undefined,
    disableOfflineQueue: true,
    socket: { reconnectStrategy: (retries) => Math.min(1_000 * (retries + 1), 5_000) }
  });
  // A no-op handler prevents an unhandled EventEmitter `error` before the
  // application attaches its structured lifecycle logger.
  client.on("error", () => undefined);
  await client.connect();
  return client;
}

export function verifyTransportMessage(raw: string, secret: string): RealtimeEvent {
  if (Buffer.byteLength(raw, "utf8") > 65_536) throw new Error("INTERNAL_EVENT_TOO_LARGE");
  const message = JSON.parse(raw) as { payload?: string; signature?: string };
  if (typeof message.payload !== "string" || typeof message.signature !== "string") throw new Error("INTERNAL_EVENT_INVALID");
  const payload = Buffer.from(message.payload, "base64");
  const expected = crypto.createHmac("sha256", secret).update(payload).digest();
  const actual = Buffer.from(message.signature, "hex");
  if (actual.length !== expected.length || !crypto.timingSafeEqual(actual, expected)) throw new Error("INTERNAL_EVENT_SIGNATURE_INVALID");
  return realtimeEventSchema.parse(JSON.parse(payload.toString("utf8")));
}

type RedisDeduplicationClient = {
  set(key: string, value: string, options: { NX: true; EX: number }): Promise<string | null>;
};

export async function claimEvent(client: RedisDeduplicationClient, prefix: string, id: string, ttlSeconds = 300): Promise<boolean> {
  const result = await client.set(`${prefix}dedupe:${id}`, "1", { NX: true, EX: ttlSeconds });
  return result === "OK";
}
