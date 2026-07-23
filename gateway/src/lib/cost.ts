import { breakerOpen } from "./logging";

export interface CostEnv {
  DB: D1Database;
  PHASE1_GLOBAL_USD_PER_DAY?: string;
  OPENAI_BALANCE_ESTIMATE_USD?: string;
}

export interface Usage { inputTokens: number; outputTokens: number; costUsd: number }

const MAX_CALL_COST = 0.0004;

export async function permitsAi(env: CostEnv, licenseId: string, now = new Date()): Promise<{ allowed: true } | { allowed: false; reason: string }> {
  if (await breakerOpen(env)) return { allowed: false, reason: "circuit_breaker" };
  if (numeric(env.OPENAI_BALANCE_ESTIMATE_USD) < 1) return { allowed: false, reason: "balance_reserve" };
  const day = utcDay(now);
  const month = `${day.slice(0, 7)}-01`;
  const [license, global, arr] = await env.DB.batch([
    env.DB.prepare("SELECT COALESCE(SUM(calls), 0) AS calls FROM usage_daily WHERE license_id = ? AND day >= ?").bind(licenseId, month),
    env.DB.prepare("SELECT COALESCE(SUM(cost_usd), 0) AS cost FROM usage_daily WHERE day = ?").bind(day),
    env.DB.prepare("SELECT COALESCE(SUM(recognized_arr_usd), 0) AS arr FROM license_installations WHERE active = 1"),
  ]);
  const calls = Number((license.results[0] as { calls: number } | undefined)?.calls ?? 0);
  const dayCost = Number((global.results[0] as { cost: number } | undefined)?.cost ?? 0);
  const recognizedArr = Number((arr.results[0] as { arr: number } | undefined)?.arr ?? 0);
  if (calls >= 1000) return { allowed: false, reason: "monthly_quota" };
  if (dayCost + MAX_CALL_COST > numeric(env.PHASE1_GLOBAL_USD_PER_DAY, 0.25)) return { allowed: false, reason: "daily_budget" };
  if (recognizedArr <= 0 || (dayCost + MAX_CALL_COST) * 365 > recognizedArr * 0.08) return { allowed: false, reason: "arr_cost_breaker" };
  return { allowed: true };
}

export async function recordUsage(env: CostEnv, licenseId: string, usage: Usage, now = new Date()): Promise<void> {
  await env.DB.prepare(
    "INSERT INTO usage_daily (license_id, day, calls, input_tokens, output_tokens, cost_usd) VALUES (?, ?, 1, ?, ?, ?) ON CONFLICT(license_id, day) DO UPDATE SET calls = calls + 1, input_tokens = input_tokens + excluded.input_tokens, output_tokens = output_tokens + excluded.output_tokens, cost_usd = cost_usd + excluded.cost_usd"
  ).bind(licenseId, utcDay(now), usage.inputTokens, usage.outputTokens, usage.costUsd).run();
}

export function estimateCost(inputTokens: number, outputTokens: number): number {
  return (inputTokens * 0.05 + outputTokens * 0.4) / 1_000_000;
}

function utcDay(now: Date): string { return now.toISOString().slice(0, 10); }
function numeric(value: string | undefined, fallback = 0): number { const parsed = Number(value); return Number.isFinite(parsed) ? parsed : fallback; }
