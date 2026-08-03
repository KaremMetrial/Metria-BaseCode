# Frontend integration

```ts
import { io } from "socket.io-client";

const socket = io("https://api.example.com", {
  path: "/socket.io",
  auth: { token: sanctumToken },
  transports: ["websocket"],
  reconnection: true,
  reconnectionDelay: 1_000,
  reconnectionDelayMax: 10_000
});

socket.on("realtime:ready", () => refetchAuthoritativeApiState());
socket.on("realtime:resync_required", () => refetchAuthoritativeApiState());
socket.on("realtime:event", (event) => {
  if (seenEventIds.has(event.id)) return;
  seenEventIds.add(event.id);
  // Update local UI or refetch the affected payment/wallet.
});
socket.on("connect_error", (error) => {
  // Refresh a rejected or expired Sanctum token, then reconnect.
});
```

On logout, call `socket.disconnect()`. Do not store the token in the URL. On
mobile backgrounding or network changes, reconnect and refetch API state rather
than treating Socket.IO as a database replay mechanism.
