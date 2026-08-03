import type { RealtimeEvent, ResourceType, SocketIdentity } from "./types/realtime.js";

const safe = (value: string) => value.toLowerCase();
export const Room = {
  user: (tenantId: string, userId: string) => `tenant:${safe(tenantId)}:user:${safe(userId)}`,
  tenant: (tenantId: string) => `tenant:${safe(tenantId)}`,
  resource: (tenantId: string, type: ResourceType, id: string) => "tenant:" + safe(tenantId) + ":" + type + ":" + safe(id)
};

export function automaticRooms(identity: SocketIdentity): string[] {
  return [Room.tenant(identity.tenant_id), Room.user(identity.tenant_id, identity.sub)];
}

export function roomsForAudience(event: RealtimeEvent): string[] {
  const { audience, tenant_id } = event;
  if (audience.type === "tenant") return [Room.tenant(tenant_id)];
  if (audience.type === "user") return [Room.user(tenant_id, audience.user_id)];
  if (audience.type === "users") return audience.user_ids.map((id) => Room.user(tenant_id, id));
  return [Room.resource(tenant_id, audience.resource_type, audience.resource_id)];
}
