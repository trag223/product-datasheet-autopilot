export interface LoggingEnv { DB: D1Database }

export async function healthEvent(env: LoggingEnv, kind: string, severity: "info" | "error", detail: Record<string, number | string | boolean> = {}): Promise<void> {
  // Do not trust callers to keep content out of a durable health record.
  await env.DB.prepare("INSERT INTO health_events (kind, severity, detail, created_at) VALUES (?, ?, ?, ?)").bind(safeKind(kind), severity, JSON.stringify(sanitizeHealthDetail(detail)), Math.floor(Date.now() / 1000)).run();
}

export async function breakerOpen(env: LoggingEnv): Promise<boolean> {
  const event = await env.DB.prepare("SELECT severity FROM health_events WHERE kind = 'ai_breaker' ORDER BY id DESC LIMIT 1").first<{ severity: string }>();
  return event?.severity === "error";
}

export async function openBreaker(env: LoggingEnv, reason: string): Promise<void> {
  await healthEvent(env, "ai_breaker", "error", { reason });
}

export function sanitizeHealthDetail(detail: Record<string, number | string | boolean>): Record<string, number | string | boolean> {
  const output: Record<string, number | string | boolean> = {};
  const reasons = new Set(["circuit_breaker", "balance_reserve", "monthly_quota", "daily_budget", "arr_cost_breaker", "two_canary_failures"]);
  if (typeof detail.reason === "string" && reasons.has(detail.reason)) output.reason = detail.reason;
  if (typeof detail.status === "number" && Number.isInteger(detail.status) && detail.status >= 100 && detail.status <= 599) output.status = detail.status;
  if (detail.status === "passed" || detail.status === "failed") output.status = detail.status;
  if (typeof detail.active === "boolean") output.active = detail.active;
  return output;
}

function safeKind(kind: string): string {
  return new Set(["ai_rejected", "model_invalid_map", "openai_failure", "freemius_webhook", "canary", "ai_breaker"]).has(kind) ? kind : "unclassified";
}
