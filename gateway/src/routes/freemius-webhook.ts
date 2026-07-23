import { hmacHex, json } from "../lib/auth";
import { healthEvent } from "../lib/logging";

export interface FreemiusEnv { DB: D1Database; FREEMIUS_WEBHOOK_SECRET: string }

export async function freemiusWebhook(request: Request, env: FreemiusEnv): Promise<Response> {
  const length = Number(request.headers.get("Content-Length") ?? "0");
  if (length > 65536) return json({ error: "body_too_large" }, 413);
  const raw = await request.arrayBuffer();
  if (raw.byteLength > 65536) return json({ error: "body_too_large" }, 413);
  const body = new TextDecoder().decode(raw);
  const signature = request.headers.get("X-Freemius-Signature") ?? "";
  if (!env.FREEMIUS_WEBHOOK_SECRET || !constantTimeEqual(await hmacHex(env.FREEMIUS_WEBHOOK_SECRET, body), signature.toLowerCase())) return json({ error: "invalid_signature" }, 401);
  let event: unknown;
  try { event = JSON.parse(body); } catch { return json({ error: "invalid_json" }, 400); }
  const parsed = parseEvent(event);
  if (!parsed) return json({ error: "invalid_event" }, 400);
  try {
    await env.DB.prepare("INSERT INTO webhook_events (event_id, received_at) VALUES (?, ?)").bind(parsed.eventId, Math.floor(Date.now() / 1000)).run();
  } catch {
    return json({ ok: true, duplicate: true });
  }
  const current = await env.DB.prepare("SELECT install_id, site_hash FROM license_installations WHERE license_id = ?").bind(parsed.licenseId).first<{ install_id: string | null; site_hash: string | null }>();
  await env.DB.prepare(
    "INSERT INTO license_installations (license_id, install_id, site_hash, active, paid_through, recognized_arr_usd, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON CONFLICT(license_id) DO UPDATE SET active = excluded.active, paid_through = excluded.paid_through, recognized_arr_usd = excluded.recognized_arr_usd, updated_at = excluded.updated_at"
  ).bind(parsed.licenseId, current?.install_id ?? null, current?.site_hash ?? null, parsed.active ? 1 : 0, parsed.paidThrough, parsed.active ? 59 : 0, Math.floor(Date.now() / 1000)).run();
  await healthEvent(env, "freemius_webhook", "info", { active: parsed.active });
  return json({ ok: true });
}

interface ParsedEvent { eventId: string; licenseId: string; active: boolean; paidThrough: number; kind: string }
function parseEvent(input: unknown): ParsedEvent | null {
  if (!input || typeof input !== "object") return null;
  const event = input as Record<string, unknown>;
  const data = (event.data ?? event) as Record<string, unknown>;
  const license = (data.license ?? event.license) as Record<string, unknown> | undefined;
  const eventId = string(event.id ?? event.event_id);
  const licenseId = string(license?.id ?? data.license_id);
  const kind = string(event.type ?? event.event_type).toLowerCase();
  if (!/^[A-Za-z0-9_-]{1,128}$/.test(eventId) || !/^[A-Za-z0-9_-]{1,128}$/.test(licenseId) || !kind) return null;
  const paidThrough = toUnix(license?.expiration ?? license?.paid_through ?? data.paid_through);
  const immediateRevocation = /refund|chargeback|expired|deactivat/.test(kind);
  return { eventId, licenseId, active: !immediateRevocation && paidThrough >= Math.floor(Date.now() / 1000), paidThrough, kind };
}
function string(value: unknown): string { return typeof value === "string" ? value : typeof value === "number" ? String(value) : ""; }
function toUnix(value: unknown): number { if (typeof value === "number") return Math.floor(value); if (typeof value === "string") { const parsed = Date.parse(value); return Number.isNaN(parsed) ? 0 : Math.floor(parsed / 1000); } return 0; }
function constantTimeEqual(left: string, right: string): boolean { if (left.length !== right.length) return false; let diff = 0; for (let i = 0; i < left.length; i += 1) diff |= left.charCodeAt(i) ^ right.charCodeAt(i); return diff === 0; }
