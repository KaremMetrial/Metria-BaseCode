import crypto from "node:crypto";
import { describe, expect, it } from "vitest";
import { verifyTransportMessage } from "../src/redis/event-transport.js";

describe("signed Redis transport", () => {
  const secret = "a-very-long-test-secret-that-is-never-a-production-secret";
  const payload = JSON.stringify({ id: "33333333-3333-4333-8333-333333333333", name: "wallet.credited", version: 1, occurred_at: "2026-08-03T00:00:00.000Z", tenant_id: "11111111-1111-4111-8111-111111111111", audience: { type: "users", user_ids: ["22222222-2222-4222-8222-222222222222"] }, subject: { type: "wallet", id: "44444444-4444-4444-8444-444444444444" }, payload: { wallet_id: "44444444-4444-4444-8444-444444444444", user_id: "22222222-2222-4222-8222-222222222222", amount: 100, currency: "EGP", balance: 100 }, metadata: { correlation_id: null, causation_id: null, trace_id: null } });

  it("accepts a valid signature", () => {
    const message = JSON.stringify({ payload: Buffer.from(payload).toString("base64"), signature: crypto.createHmac("sha256", secret).update(payload).digest("hex") });
    expect(verifyTransportMessage(message, secret).name).toBe("wallet.credited");
  });

  it("rejects a forged signature", () => {
    const message = JSON.stringify({ payload: Buffer.from(payload).toString("base64"), signature: "0".repeat(64) });
    expect(() => verifyTransportMessage(message, secret)).toThrow("INTERNAL_EVENT_SIGNATURE_INVALID");
  });

  it("rejects unknown public payload fields", () => {
    const event = JSON.parse(payload) as Record<string, unknown>;
    event.payload = { ...(event.payload as Record<string, unknown>), internal_secret: "never-public" };
    const serialized = JSON.stringify(event);
    const message = JSON.stringify({ payload: Buffer.from(serialized).toString("base64"), signature: crypto.createHmac("sha256", secret).update(serialized).digest("hex") });
    expect(() => verifyTransportMessage(message, secret)).toThrow();
  });
});
