import { describe, expect, it } from "vitest";
import { automaticRooms, Room, roomsForAudience } from "../src/rooms.js";

describe("room construction", () => {
  const tenant = "11111111-1111-4111-8111-111111111111";
  const user = "22222222-2222-4222-8222-222222222222";

  it("never accepts a client-selected tenant for automatic rooms", () => {
    expect(automaticRooms({ sub: user, tenant_id: tenant, roles: [], permissions: [], token_id: "1" }))
      .toEqual([Room.tenant(tenant), Room.user(tenant, user)]);
  });

  it("maps a users audience only into same-tenant rooms", () => {
    const rooms = roomsForAudience({ id: "33333333-3333-4333-8333-333333333333", name: "payment.succeeded", version: 1, occurred_at: "2026-08-03T00:00:00.000Z", tenant_id: tenant, audience: { type: "users", user_ids: [user] }, subject: { type: "payment", id: "44444444-4444-4444-8444-444444444444" }, payload: {}, metadata: { correlation_id: null, causation_id: null, trace_id: null } });
    expect(rooms).toEqual([Room.user(tenant, user)]);
  });
});
