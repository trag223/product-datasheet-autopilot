export interface AuthEnv {
  DB: D1Database;
  INSTALL_ROOT_KEY: string;
}

export interface AuthenticatedInstall {
  licenseId: string;
  installId: string;
}

const encoder = new TextEncoder();

export async function sha256Hex(value: ArrayBuffer | string): Promise<string> {
  const bytes = typeof value === "string" ? encoder.encode(value) : value;
  const digest = await crypto.subtle.digest("SHA-256", bytes);
  return hex(digest);
}

export async function hmacHex(key: string, value: string): Promise<string> {
  const cryptoKey = await crypto.subtle.importKey("raw", encoder.encode(key), { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  return hex(await crypto.subtle.sign("HMAC", cryptoKey, encoder.encode(value)));
}

export async function installSecret(rootKey: string, licenseId: string, installId: string, siteHash: string): Promise<string> {
  return hmacHex(rootKey, `${licenseId}${installId}${siteHash}`);
}

export async function authenticate(request: Request, body: ArrayBuffer, env: AuthEnv, now = Date.now()): Promise<AuthenticatedInstall | Response> {
  const licenseId = request.headers.get("X-PDA-License") ?? "";
  const installId = request.headers.get("X-PDA-Install") ?? "";
  const timestamp = request.headers.get("X-PDA-Timestamp") ?? "";
  const nonce = request.headers.get("X-PDA-Nonce") ?? "";
  const signature = request.headers.get("X-PDA-Signature") ?? "";
  if (!/^[A-Za-z0-9_-]{1,128}$/.test(licenseId) || !/^[a-f0-9-]{36}$/i.test(installId) || !/^[a-f0-9-]{36}$/i.test(nonce) || !/^[a-f0-9]{64}$/i.test(signature) || !/^\d{10}$/.test(timestamp)) {
    return json({ error: "invalid_auth" }, 401);
  }
  if (Math.abs(Math.floor(now / 1000) - Number(timestamp)) > 300) return json({ error: "timestamp_skew" }, 401);
  const installation = await env.DB.prepare("SELECT site_hash, active, install_id FROM license_installations WHERE license_id = ?").bind(licenseId).first<{ site_hash: string; active: number; install_id: string }>();
  if (!installation || installation.active !== 1 || installation.install_id !== installId || !installation.site_hash) return json({ error: "inactive_license" }, 403);
  const secret = await installSecret(env.INSTALL_ROOT_KEY, licenseId, installId, installation.site_hash);
  const canonical = `${timestamp}\n${nonce}\n${await sha256Hex(body)}`;
  const expected = await hmacHex(secret, canonical);
  if (!constantTimeEqual(expected, signature.toLowerCase())) return json({ error: "invalid_signature" }, 401);
  try {
    await env.DB.prepare("DELETE FROM request_nonces WHERE expires_at < ?").bind(Math.floor(now / 1000)).run();
    await env.DB.prepare("INSERT INTO request_nonces (license_id, nonce, expires_at) VALUES (?, ?, ?)").bind(licenseId, nonce, Math.floor(now / 1000) + 300).run();
  } catch {
    return json({ error: "replayed_nonce" }, 401);
  }
  return { licenseId, installId };
}

export function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store" } });
}

function hex(value: ArrayBuffer): string {
  return Array.from(new Uint8Array(value), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

function constantTimeEqual(left: string, right: string): boolean {
  if (left.length !== right.length) return false;
  let difference = 0;
  for (let index = 0; index < left.length; index += 1) difference |= left.charCodeAt(index) ^ right.charCodeAt(index);
  return difference === 0;
}
