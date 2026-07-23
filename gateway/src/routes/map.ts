import { authenticate, json } from "../lib/auth";
import { permitsAi, recordUsage } from "../lib/cost";
import { healthEvent } from "../lib/logging";
import { parseSnapshot, validateMap } from "../lib/schema";
import { OpenAiError, requestMap } from "../integrations/openai";

export interface MapEnv {
  DB: D1Database;
  INSTALL_ROOT_KEY: string;
  OPENAI_API_KEY: string;
  OPENAI_MODEL?: string;
  PHASE1_GLOBAL_USD_PER_DAY?: string;
  OPENAI_BALANCE_ESTIMATE_USD?: string;
}

export async function mapRoute(request: Request, env: MapEnv): Promise<Response> {
  const length = Number(request.headers.get("Content-Length") ?? "0");
  if (length > 65536) return json({ error: "body_too_large" }, 413);
  const body = await request.arrayBuffer();
  if (body.byteLength > 65536) return json({ error: "body_too_large" }, 413);
  const auth = await authenticate(request, body, env);
  if (auth instanceof Response) return auth;
  let input: unknown;
  try { input = JSON.parse(new TextDecoder().decode(body)); } catch { return json({ error: "invalid_json" }, 400); }
  const snapshot = parseSnapshot(input);
  if (!snapshot) return json({ error: "invalid_snapshot" }, 400);
  const permit = await permitsAi(env, auth.licenseId);
  if (!permit.allowed) {
    await healthEvent(env, "ai_rejected", "info", { reason: permit.reason });
    return json({ error: permit.reason }, 429);
  }
  try {
    const result = await requestMap(env, snapshot);
    if (!validateMap(result.map, snapshot)) {
      await healthEvent(env, "model_invalid_map", "error");
      return json({ error: "invalid_model_map" }, 502);
    }
    await recordUsage(env, auth.licenseId, result.usage);
    return json({ map: result.map });
  } catch (error) {
    const status = error instanceof OpenAiError ? error.status : 502;
    await healthEvent(env, "openai_failure", "error", { status });
    return json({ error: "ai_unavailable" }, 502);
  }
}
