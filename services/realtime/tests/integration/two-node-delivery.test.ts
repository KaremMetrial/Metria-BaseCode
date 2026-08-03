import { execFile, execFileSync } from "node:child_process";
import crypto from "node:crypto";
import { setTimeout as delay } from "node:timers/promises";
import { io, type Socket } from "socket.io-client";
import { afterAll, beforeAll, describe, expect, it } from "vitest";

type UserFixture = { id: string; email: string; token: string; token_id?: string; second_token?: string; second_token_id?: string; wallet?: string };
type Fixtures = { tenant_a: string; tenant_b: string; conversation: string; a1: UserFixture; a2: UserFixture; b1: UserFixture };
type Envelope = Record<string, unknown>;

const fixtures = JSON.parse(process.env.REALTIME_CLUSTER_FIXTURES ?? "{}") as Fixtures;
const root = process.env.REALTIME_CLUSTER_ROOT ?? "";
const project = process.env.REALTIME_CLUSTER_PROJECT ?? "";
const envFile = process.env.REALTIME_CLUSTER_ENV_FILE ?? "";
const clusterFile = process.env.REALTIME_CLUSTER_COMPOSE_FILE ?? "";
const origin = "http://realtime-cluster.test";
const composeBase = ["compose", "-p", project, "--env-file", envFile, "-f", `${root}/compose/docker-compose.yml`, "-f", clusterFile];
const clusterDescribe = process.env.REALTIME_CLUSTER_FIXTURES ? describe.sequential : describe.skip;

class TestClient {
  readonly events: Envelope[] = [];
  readonly resyncCodes: string[] = [];
  readonly disconnected: Promise<void>;
  nodeId = "";
  private readonly ready: Promise<void>;

  constructor(private readonly url: string, token: string) {
    this.socket = io(url, { path: "/socket.io", transports: ["websocket"], auth: { token }, extraHeaders: { Origin: origin }, reconnection: false });
    this.disconnected = new Promise<void>((resolve) => this.socket.once("disconnect", () => resolve()));
    this.ready = new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error(`Socket did not become ready at ${url}`)), 10_000);
      this.socket.once("connect_error", (error) => { clearTimeout(timeout); reject(error); });
      this.socket.once("realtime:ready", (payload: { node_id?: string }) => { clearTimeout(timeout); this.nodeId = payload.node_id ?? ""; resolve(); });
    });
    this.socket.on("realtime:event", (event: Envelope) => this.events.push(event));
    this.socket.on("realtime:resync_required", (payload: { code?: string }) => this.resyncCodes.push(payload.code ?? ""));
  }

  readonly socket: Socket;

  waitReady(): Promise<void> { return this.ready; }

  subscribe(resourceType: "wallet" | "payment" | "conversation", resourceId: string): Promise<{ ok: boolean; code?: string }> {
    return new Promise((resolve) => this.socket.emit("resource:subscribe", { resource_type: resourceType, resource_id: resourceId }, resolve));
  }

  close(): void { this.socket.close(); }
}

function compose(args: string[], environment: NodeJS.ProcessEnv = process.env): string {
  return execFileSync("docker", [...composeBase, ...args], { cwd: root, env: environment, encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] });
}

function publish(envelope: Envelope): void {
  const encoded = Buffer.from(JSON.stringify(envelope)).toString("base64");
  compose([
    "exec", "-T", "-e", `REALTIME_CLUSTER_ENVELOPE=${encoded}`, "app", "php", "artisan", "tinker", "--execute",
    '$event = json_decode(base64_decode(getenv("REALTIME_CLUSTER_ENVELOPE")), true, flags: JSON_THROW_ON_ERROR); app(\\Modules\\Shared\\Infrastructure\\Realtime\\RedisRealtimePublisher::class)->publish($event);'
  ]);
}

function publishConcurrently(envelope: Envelope): Promise<void> {
  const encoded = Buffer.from(JSON.stringify(envelope)).toString("base64");
  return new Promise((resolve, reject) => execFile("docker", [
    ...composeBase, "exec", "-T", "-e", `REALTIME_CLUSTER_ENVELOPE=${encoded}`, "app", "php", "artisan", "tinker", "--execute",
    '$event = json_decode(base64_decode(getenv("REALTIME_CLUSTER_ENVELOPE")), true, flags: JSON_THROW_ON_ERROR); app(\\Modules\\Shared\\Infrastructure\\Realtime\\RedisRealtimePublisher::class)->publish($event);'
  ], { cwd: root }, (error) => error ? reject(error) : resolve()));
}

function revokeToken(tokenId: string): void {
  compose(["exec", "-T", "-e", `REALTIME_CLUSTER_TOKEN_ID=${tokenId}`, "app", "php", "artisan", "tinker", "--execute", 'Laravel\\Sanctum\\PersonalAccessToken::query()->findOrFail(getenv("REALTIME_CLUSTER_TOKEN_ID"))->delete();']);
}

function revokeUserTokens(userId: string): void {
  compose(["exec", "-T", "-e", `REALTIME_CLUSTER_USER_ID=${userId}`, "app", "php", "artisan", "tinker", "--execute", 'Laravel\\Sanctum\\PersonalAccessToken::query()->where("tokenable_type", Modules\\Auth\\Domain\\Models\\User::class)->where("tokenable_id", getenv("REALTIME_CLUSTER_USER_ID"))->delete();']);
}

function removeConversationMember(conversationId: string, userId: string): void {
  compose(["exec", "-T", "-e", `REALTIME_CLUSTER_CONVERSATION_ID=${conversationId}`, "-e", `REALTIME_CLUSTER_USER_ID=${userId}`, "app", "php", "artisan", "tinker", "--execute", 'Modules\\Communication\\Domain\\Models\\Membership::query()->withoutGlobalScopes()->where("conversation_id", getenv("REALTIME_CLUSTER_CONVERSATION_ID"))->where("actor_id", getenv("REALTIME_CLUSTER_USER_ID"))->update(["state" => "left"]);']);
}

async function expectConnectionRejected(url: string, token: string): Promise<void> {
  await new Promise<void>((resolve, reject) => {
    const socket = io(url, { path: "/socket.io", transports: ["websocket"], auth: { token }, extraHeaders: { Origin: origin }, reconnection: false });
    const timeout = setTimeout(() => finish(new Error(`Revoked credential unexpectedly connected at ${url}`)), 10_000);
    const finish = (error?: Error) => {
      clearTimeout(timeout);
      socket.removeAllListeners();
      socket.close();
      if (error) reject(error); else resolve();
    };
    socket.once("realtime:ready", () => finish(new Error(`Revoked credential unexpectedly became ready at ${url}`)));
    socket.once("connect_error", () => finish());
  });
}

function walletEvent(id: string, audience: Envelope["audience"]): Envelope {
  return {
    id, name: "wallet.credited", version: 1, occurred_at: new Date().toISOString(), tenant_id: fixtures.tenant_a,
    audience, subject: { type: "wallet", id: fixtures.a1.wallet },
    payload: { wallet_id: fixtures.a1.wallet, user_id: fixtures.a1.id, amount: 100, currency: "USD", balance: 1100 },
    metadata: { correlation_id: id, causation_id: null, trace_id: null }
  };
}

function securityEvent(id: string, name: "security.session_revoked" | "security.all_sessions_revoked", tokenId?: string): Envelope {
  return {
    id, name, version: 1, occurred_at: new Date().toISOString(), tenant_id: fixtures.tenant_a,
    audience: { type: "users", user_ids: [fixtures.a1.id] }, subject: { type: "user", id: fixtures.a1.id },
    payload: name === "security.session_revoked"
      ? { user_id: fixtures.a1.id, email: fixtures.a1.email, session_id: crypto.randomUUID(), token_id: tokenId ?? null }
      : { user_id: fixtures.a1.id, email: fixtures.a1.email },
    metadata: { correlation_id: id, causation_id: null, trace_id: null }
  };
}

function communicationMessageEvent(id: string): Envelope {
  return {
    id, name: "communication.message.created", version: 1, occurred_at: new Date().toISOString(), tenant_id: fixtures.tenant_a,
    audience: { type: "resource", resource_type: "conversation", resource_id: fixtures.conversation },
    subject: { type: "message", id: crypto.randomUUID() },
    payload: { conversation_id: fixtures.conversation, message_id: crypto.randomUUID(), sequence: 1, kind: "text", revision: 1, author_actor_id: fixtures.a1.id },
    metadata: { correlation_id: id, causation_id: null, trace_id: null }
  };
}

function membershipRemovedEvent(id: string): Envelope {
  return {
    id, name: "communication.membership.removed", version: 1, occurred_at: new Date().toISOString(), tenant_id: fixtures.tenant_a,
    audience: { type: "resource", resource_type: "conversation", resource_id: fixtures.conversation },
    subject: { type: "membership", id: fixtures.a2.id },
    payload: { conversation_id: fixtures.conversation, actor_id: fixtures.a2.id, conversation_version: 2, state: "left" },
    metadata: { correlation_id: id, causation_id: null, trace_id: null }
  };
}

async function eventually(assertion: () => void | Promise<void>, timeoutMs = 8_000): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  let lastError: unknown;
  while (Date.now() < deadline) {
    try { await assertion(); return; } catch (error) { lastError = error; await delay(50); }
  }
  throw lastError;
}

async function assertStable(clients: TestClient[], counts: number[]): Promise<void> {
  await delay(1_000);
  clients.forEach((client, index) => expect(client.events).toHaveLength(counts[index]));
}

async function waitReady(url: string): Promise<void> {
  await eventually(async () => expect((await fetch(`${url}/health/ready`)).status).toBe(200));
}

clusterDescribe("two-node Socket.IO delivery gate", () => {
  let a1: TestClient;
  let a1Second: TestClient;
  let a2: TestClient;
  let b1: TestClient;

  beforeAll(async () => {
    expect(fixtures.a1?.token).toBeTypeOf("string");
    expect(fixtures.conversation).toBeTypeOf("string");
    a1 = new TestClient("http://127.0.0.1:6101", fixtures.a1.token);
    a1Second = new TestClient("http://127.0.0.1:6102", fixtures.a1.second_token!);
    a2 = new TestClient("http://127.0.0.1:6102", fixtures.a2.token);
    b1 = new TestClient("http://127.0.0.1:6102", fixtures.b1.token);
    await Promise.all([a1.waitReady(), a1Second.waitReady(), a2.waitReady(), b1.waitReady()]);
  }, 30_000);

  afterAll(() => { [a1, a1Second, a2, b1].filter(Boolean).forEach((client) => client.close()); });

  it("proves cross-instance delivery, dedupe, isolation, recovery, and revocation", async () => {
    expect(a1.nodeId).toBe("realtime-a");
    expect(a2.nodeId).toBe("realtime-b");
    expect(a1.nodeId).not.toBe(a2.nodeId);
    await expect(a1.subscribe("wallet", fixtures.a1.wallet!)).resolves.toEqual({ ok: true });
    await expect(a2.subscribe("wallet", fixtures.a1.wallet!)).resolves.toEqual({ ok: true });
    await expect(b1.subscribe("wallet", fixtures.a1.wallet!)).resolves.toEqual({ ok: false, code: "RESOURCE_FORBIDDEN" });
    await expect(a2.subscribe("wallet", fixtures.b1.wallet!)).resolves.toEqual({ ok: false, code: "RESOURCE_FORBIDDEN" });

    const tenantEventId = crypto.randomUUID();
    publish(walletEvent(tenantEventId, { type: "tenant" }));
    await eventually(() => { expect(a1.events).toHaveLength(1); expect(a2.events).toHaveLength(1); });
    expect(b1.events).toHaveLength(0);
    expect(a1.events[0]).toMatchObject({ id: tenantEventId, version: 1, tenant_id: fixtures.tenant_a, subject: { id: fixtures.a1.wallet } });
    await assertStable([a1, a2, b1], [1, 1, 0]);

    publish(walletEvent(tenantEventId, { type: "tenant" }));
    await assertStable([a1, a2, b1], [1, 1, 0]);

    const newId = crypto.randomUUID();
    publish(walletEvent(newId, { type: "tenant" }));
    await eventually(() => { expect(a1.events).toHaveLength(2); expect(a2.events).toHaveLength(2); });
    await assertStable([a1, a2, b1], [2, 2, 0]);

    const userId = crypto.randomUUID();
    publish(walletEvent(userId, { type: "user", user_id: fixtures.a2.id }));
    await eventually(() => expect(a2.events).toHaveLength(3));
    await assertStable([a1, a2, b1], [2, 3, 0]);

    const resourceId = crypto.randomUUID();
    publish(walletEvent(resourceId, { type: "resource", resource_type: "wallet", resource_id: fixtures.a1.wallet! }));
    await eventually(() => { expect(a1.events).toHaveLength(3); expect(a2.events).toHaveLength(4); });
    await assertStable([a1, a2, b1], [3, 4, 0]);

    const concurrentId = crypto.randomUUID();
    const concurrentEnvelope = walletEvent(concurrentId, { type: "tenant" });
    await Promise.all(Array.from({ length: 5 }, () => publishConcurrently(concurrentEnvelope)));
    await eventually(() => { expect(a1.events).toHaveLength(4); expect(a2.events).toHaveLength(5); });
    await assertStable([a1, a2, b1], [4, 5, 0]);

    compose(["stop", "realtime-a"]);
    await a1.disconnected;
    expect(a2.socket.connected).toBe(true);
    const afterStopId = crypto.randomUUID();
    publish(walletEvent(afterStopId, { type: "tenant" }));
    await eventually(() => expect(a2.events).toHaveLength(6));
    expect(b1.events).toHaveLength(0);
    compose(["start", "realtime-a"]);
    await waitReady("http://127.0.0.1:6101");
    a1 = new TestClient("http://127.0.0.1:6101", fixtures.a1.token);
    await a1.waitReady();
    expect(a1.nodeId).toBe("realtime-a");
    const afterRestartId = crypto.randomUUID();
    publish(walletEvent(afterRestartId, { type: "tenant" }));
    await eventually(() => { expect(a1.events).toHaveLength(1); expect(a2.events).toHaveLength(7); });

    compose(["stop", "redis"]);
    await eventually(async () => expect((await fetch("http://127.0.0.1:6102/health/ready")).status).toBe(503));
    compose(["start", "redis"]);
    await waitReady("http://127.0.0.1:6101");
    await waitReady("http://127.0.0.1:6102");
    const afterRedisId = crypto.randomUUID();
    publish(walletEvent(afterRedisId, { type: "tenant" }));
    await eventually(() => { expect(a1.events).toHaveLength(2); expect(a2.events).toHaveLength(8); });

    await expect(a1.subscribe("conversation", fixtures.conversation)).resolves.toEqual({ ok: true });
    await expect(a2.subscribe("conversation", fixtures.conversation)).resolves.toEqual({ ok: true });
    await expect(b1.subscribe("conversation", fixtures.conversation)).resolves.toEqual({ ok: false, code: "RESOURCE_FORBIDDEN" });
    const communicationEventId = crypto.randomUUID();
    publish(communicationMessageEvent(communicationEventId));
    await eventually(() => { expect(a1.events).toHaveLength(3); expect(a2.events).toHaveLength(9); });
    expect(a1.events[2]).toMatchObject({ id: communicationEventId, name: "communication.message.created", payload: { conversation_id: fixtures.conversation } });
    expect(b1.events).toHaveLength(0);

    removeConversationMember(fixtures.conversation, fixtures.a2.id);
    publish(membershipRemovedEvent(crypto.randomUUID()));
    await eventually(() => expect(a2.resyncCodes).toContain("MEMBERSHIP_REMOVED"));
    await expect(a2.subscribe("conversation", fixtures.conversation)).resolves.toEqual({ ok: false, code: "RESOURCE_FORBIDDEN" });
    const afterRemovalId = crypto.randomUUID();
    publish(communicationMessageEvent(afterRemovalId));
    await eventually(() => expect(a1.events).toHaveLength(5));
    await assertStable([a1, a2, b1], [5, 9, 0]);

    revokeToken(fixtures.a1.token_id!);
    publish(securityEvent(crypto.randomUUID(), "security.session_revoked", fixtures.a1.token_id));
    await a1.disconnected;
    expect(a1Second.socket.connected).toBe(true);
    await expectConnectionRejected("http://127.0.0.1:6101", fixtures.a1.token);
    revokeUserTokens(fixtures.a1.id);
    publish(securityEvent(crypto.randomUUID(), "security.all_sessions_revoked"));
    await a1Second.disconnected;
    await expectConnectionRejected("http://127.0.0.1:6102", fixtures.a1.second_token!);
    expect(a2.socket.connected).toBe(true);
    expect(b1.socket.connected).toBe(true);
  }, 120_000);
});
