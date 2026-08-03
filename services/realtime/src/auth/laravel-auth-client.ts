import crypto from "node:crypto";
import type { RealtimeConfig } from "../config.js";
import { identitySchema, type SocketIdentity } from "../types/realtime.js";

type AuthResult = { identity: SocketIdentity; assertion: string; expires_at: string };

export function canonicalInternalRequest(method: string, path: string, timestamp: string, nonce: string, body: string): string {
  const normalizedMethod = method.trim().toUpperCase();
  const normalizedPath = `/${path.replace(/^\/+/, "")}`;
  if (!/^[A-Z]+$/.test(normalizedMethod) || /[\r\n]/.test(normalizedPath)) throw new Error("INTERNAL_REQUEST_INVALID");
  return [normalizedMethod, normalizedPath, timestamp, nonce, crypto.createHash("sha256").update(body).digest("hex")].join("\n");
}

export function signInternalRequest(method: string, path: string, timestamp: string, nonce: string, body: string, secret: string): string {
  return crypto.createHmac("sha256", secret).update(canonicalInternalRequest(method, path, timestamp, nonce, body)).digest("hex");
}

export class LaravelAuthClient {
  constructor(private readonly config: RealtimeConfig) {}

  async authenticate(token: string): Promise<AuthResult> {
    return this.post<AuthResult>(this.config.REALTIME_AUTH_URL, { token }, (body) => {
      const parsed = identitySchema.parse(body.identity);
      if (typeof body.assertion !== "string" || typeof body.expires_at !== "string") throw new Error("AUTH_RESPONSE_INVALID");
      return { identity: parsed, assertion: body.assertion, expires_at: body.expires_at };
    });
  }

  async authorizeResource(assertion: string, resourceType: "payment" | "wallet", resourceId: string): Promise<void> {
    await this.post(this.config.REALTIME_RESOURCE_AUTH_URL, { assertion, resource_type: resourceType, resource_id: resourceId }, () => undefined);
  }

  private async post<T>(url: string, payload: Record<string, string>, transform: (body: Record<string, unknown>) => T): Promise<T> {
    const body = JSON.stringify(payload);
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = crypto.randomUUID();
    const signature = signInternalRequest("POST", new URL(url).pathname, timestamp, nonce, body, this.config.REALTIME_INTERNAL_SECRET);
    let response: Response;
    try {
      response = await fetch(url, { method: "POST", headers: { "content-type": "application/json", "x-realtime-timestamp": timestamp, "x-realtime-nonce": nonce, "x-realtime-signature": signature }, body, signal: AbortSignal.timeout(3000) });
    } catch {
      throw new Error("AUTH_SERVICE_UNAVAILABLE");
    }
    const decoded = await response.json().catch(() => ({})) as Record<string, unknown>;
    if (!response.ok) throw new Error(typeof (decoded.error as Record<string, unknown> | undefined)?.code === "string" ? String((decoded.error as Record<string, unknown>).code) : "AUTH_SERVICE_UNAVAILABLE");
    return transform(decoded);
  }
}
