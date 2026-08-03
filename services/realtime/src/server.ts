import { loadConfig } from "./config.js";
import { createRealtimeApp } from "./app.js";

const config = loadConfig();
const app = await createRealtimeApp(config);
app.httpServer.listen(config.REALTIME_PORT, config.REALTIME_HOST, () => app.log.info("server.started", { host: config.REALTIME_HOST, port: config.REALTIME_PORT }));

let stopping = false;
const shutdown = async (signal: string) => {
  if (stopping) return;
  stopping = true;
  app.log.info("server.stopping", { signal });
  const force = setTimeout(() => process.exit(1), config.REALTIME_SHUTDOWN_TIMEOUT_MS).unref();
  await app.close();
  clearTimeout(force);
  process.exit(0);
};
process.on("SIGTERM", () => void shutdown("SIGTERM"));
process.on("SIGINT", () => void shutdown("SIGINT"));
