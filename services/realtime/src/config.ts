import { z } from "zod";

const envSchema = z.object({
  NODE_ENV: z.enum(["development", "test", "production"]).default("development"),
  REALTIME_HOST: z.string().default("0.0.0.0"),
  REALTIME_PORT: z.coerce.number().int().min(1).max(65535).default(6001),
  REALTIME_PATH: z.string().regex(/^\//).default("/socket.io"),
  REALTIME_ALLOWED_ORIGINS: z.string().min(1),
  REALTIME_MAX_CONNECTIONS_PER_USER: z.coerce.number().int().min(1).max(100).default(10),
  REALTIME_MAX_ROOMS_PER_SOCKET: z.coerce.number().int().min(3).max(100).default(20),
  REALTIME_MAX_PAYLOAD_BYTES: z.coerce.number().int().min(1024).max(1_048_576).default(65_536),
  REALTIME_MAX_EVENT_BYTES: z.coerce.number().int().min(1024).max(1_048_576).default(65_536),
  REALTIME_AUTH_URL: z.string().url(),
  REALTIME_RESOURCE_AUTH_URL: z.string().url(),
  REALTIME_INTERNAL_SECRET: z.string().min(32),
  REALTIME_SESSION_TTL_SECONDS: z.coerce.number().int().min(30).max(3600).default(300),
  REALTIME_REDIS_URL: z.string().url(),
  REALTIME_REDIS_PASSWORD: z.string().optional(),
  REALTIME_REDIS_KEY_PREFIX: z.string().min(1).default("metrial:realtime:"),
  REALTIME_EVENT_CHANNEL: z.string().min(1).default("metrial:realtime:events"),
  REALTIME_EVENT_HMAC_SECRET: z.string().min(32),
  REALTIME_DEDUPE_TTL_SECONDS: z.coerce.number().int().min(1).max(86_400).default(300),
  // Intentionally exposed only when NODE_ENV=test, for deterministic cluster tests.
  REALTIME_NODE_ID: z.string().min(1).max(64).optional(),
  REALTIME_LOG_LEVEL: z.enum(["debug", "info", "warn", "error"]).default("info"),
  REALTIME_SHUTDOWN_TIMEOUT_MS: z.coerce.number().int().min(1000).max(60_000).default(10_000)
});

export type RealtimeConfig = z.infer<typeof envSchema> & { allowedOrigins: string[] };

export function loadConfig(env: NodeJS.ProcessEnv = process.env): RealtimeConfig {
  const parsed = envSchema.parse(env);
  const allowedOrigins = parsed.REALTIME_ALLOWED_ORIGINS.split(",").map((value) => value.trim()).filter(Boolean);
  if (parsed.NODE_ENV === "production") {
    if (allowedOrigins.includes("*") || allowedOrigins.some((origin) => /^https?:\/\/(?:localhost|127\.0\.0\.1)(?::|\/|$)/i.test(origin))) throw new Error("REALTIME_ALLOWED_ORIGINS must use exact non-localhost production origins");
    if ([parsed.REALTIME_INTERNAL_SECRET, parsed.REALTIME_EVENT_HMAC_SECRET].some((secret) => secret.length < 48 || secret.includes("local_development") || secret.includes("please_replace"))) throw new Error("REALTIME secrets must be production-grade values");
    if (!parsed.REALTIME_REDIS_PASSWORD) throw new Error("REALTIME_REDIS_PASSWORD is required in production");
    if (new URL(parsed.REALTIME_AUTH_URL).hostname !== "internal-nginx" || new URL(parsed.REALTIME_RESOURCE_AUTH_URL).hostname !== "internal-nginx") throw new Error("REALTIME internal authorization URLs must target internal-nginx");
    if (parsed.REALTIME_LOG_LEVEL === "debug") throw new Error("REALTIME_LOG_LEVEL cannot be debug in production");
  }
  return { ...parsed, allowedOrigins };
}
