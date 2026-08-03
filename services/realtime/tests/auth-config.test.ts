import { describe, expect, it } from "vitest";
import { canonicalInternalRequest, signInternalRequest } from "../src/auth/laravel-auth-client.js";
import { loadConfig } from "../src/config.js";

describe("internal authorization signature", () => {
  const secret = "realtime-transport-test-secret-that-is-long-enough";
  const timestamp = "1722679200";
  const nonce = "0d62f994-6d25-45ca-b2eb-7081393fce10";
  const body = '{"token":"example"}';

  it("uses the documented method/path/timestamp/nonce/body canonical form", () => {
    const canonical = canonicalInternalRequest("POST", "/api/internal/realtime/authenticate", timestamp, nonce, body);
    expect(canonical).toBe(`POST\n/api/internal/realtime/authenticate\n${timestamp}\n${nonce}\n39d990d1fff0df025853e25601a090c7a10bede007ed52636c47bafe890120b2`);
    expect(signInternalRequest("POST", "/api/internal/realtime/authenticate", timestamp, nonce, body, secret)).toBe("bde71386cedf8bb56a9251736b2b962f3ea7d17f92104ae884b15032ad79a65c");
    expect(signInternalRequest("GET", "/api/internal/realtime/authenticate", timestamp, nonce, body, secret)).not.toBe(signInternalRequest("POST", "/api/internal/realtime/authenticate", timestamp, nonce, body, secret));
    expect(signInternalRequest("POST", "/api/internal/realtime/authorize-resource", timestamp, nonce, body, secret)).not.toBe(signInternalRequest("POST", "/api/internal/realtime/authenticate", timestamp, nonce, body, secret));
  });

  it("rejects development configuration in production", () => {
    expect(() => loadConfig({ NODE_ENV: "production", REALTIME_ALLOWED_ORIGINS: "http://localhost", REALTIME_AUTH_URL: "http://nginx/api/internal/realtime/authenticate", REALTIME_RESOURCE_AUTH_URL: "http://nginx/api/internal/realtime/authorize-resource", REALTIME_INTERNAL_SECRET: "local_development_realtime_internal_secret_please_replace", REALTIME_EVENT_HMAC_SECRET: "local_development_realtime_event_hmac_secret_please_replace", REALTIME_REDIS_URL: "redis://redis:6379", REALTIME_REDIS_PASSWORD: "password" })).toThrow();
  });
});
