const port = process.env.REALTIME_PORT ?? "6001";
const response = await fetch(`http://127.0.0.1:${port}/health/live`, { signal: AbortSignal.timeout(2000) });
if (!response.ok) process.exit(1);
