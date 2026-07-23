import { json } from "../lib/auth";
import { breakerOpen } from "../lib/logging";

export async function healthRoute(env: { DB: D1Database }): Promise<Response> {
  const last = await env.DB.prepare("SELECT severity, created_at FROM health_events WHERE kind = 'canary' ORDER BY id DESC LIMIT 1").first<{ severity: string; created_at: number }>();
  return json({ ok: !(await breakerOpen(env)), breaker_open: await breakerOpen(env), last_canary: last ? { severity: last.severity, at: last.created_at } : null });
}
