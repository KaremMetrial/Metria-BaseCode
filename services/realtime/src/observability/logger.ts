import type { RealtimeConfig } from "../config.js";

type Fields = Record<string, unknown>;

export function logger(config: RealtimeConfig) {
  const write = (level: string, event: string, fields: Fields = {}) => {
    if (level === "debug" && config.REALTIME_LOG_LEVEL !== "debug") return;
    process.stdout.write(`${JSON.stringify({ timestamp: new Date().toISOString(), service: "metrial-realtime", level, event, ...fields })}\n`);
  };
  return { debug: (event: string, fields?: Fields) => write("debug", event, fields), info: (event: string, fields?: Fields) => write("info", event, fields), warn: (event: string, fields?: Fields) => write("warn", event, fields), error: (event: string, fields?: Fields) => write("error", event, fields) };
}
