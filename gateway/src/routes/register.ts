import { installSecret, json } from "../lib/auth";

export interface RegisterEnv { DB: D1Database; INSTALL_ROOT_KEY: string }

export async function registerInstall(request: Request, env: RegisterEnv): Promise<Response> {
  const length = Number(request.headers.get("Content-Length") ?? "0");
  if (length > 65536) return json({ error: "body_too_large" }, 413);
  const raw = await request.arrayBuffer();
  if (raw.byteLength > 65536) return json({ error: "body_too_large" }, 413);
  const body = safeJson(raw);
  if (!body || !validLicense(body.license_id) || !validUuid(body.install_id) || !/^[a-f0-9]{64}$/i.test(body.site_hash)) return json({ error: "invalid_registration" }, 400);
  const license = await env.DB.prepare("SELECT install_id, site_hash, active FROM license_installations WHERE license_id = ?").bind(body.license_id).first<{ install_id: string | null; site_hash: string | null; active: number }>();
  if (!license || license.active !== 1) return json({ error: "inactive_license" }, 403);
  if (license.install_id && (license.install_id !== body.install_id || license.site_hash !== body.site_hash)) return json({ error: "site_limit" }, 409);
  await env.DB.prepare("UPDATE license_installations SET install_id = ?, site_hash = ?, updated_at = ? WHERE license_id = ?").bind(body.install_id, body.site_hash, Math.floor(Date.now() / 1000), body.license_id).run();
  return json({ install_secret: await installSecret(env.INSTALL_ROOT_KEY, body.license_id, body.install_id, body.site_hash) });
}

interface RegisterBody { license_id: string; install_id: string; site_hash: string }
function safeJson(body: ArrayBuffer): RegisterBody | null { try { return JSON.parse(new TextDecoder().decode(body)) as RegisterBody; } catch { return null; } }
function validLicense(value: unknown): value is string { return typeof value === "string" && /^[A-Za-z0-9_-]{1,128}$/.test(value); }
function validUuid(value: unknown): value is string { return typeof value === "string" && /^[a-f0-9-]{36}$/i.test(value); }
