import { json } from "./lib/auth";
import { healthEvent, openBreaker } from "./lib/logging";
import { requestMap } from "./integrations/openai";
import { validateMap, type Snapshot } from "./lib/schema";
import { registerInstall } from "./routes/register";
import { mapRoute } from "./routes/map";
import { healthRoute } from "./routes/health";
import { freemiusWebhook } from "./routes/freemius-webhook";

export interface Env {
  DB: D1Database;
  OPENAI_API_KEY: string;
  INSTALL_ROOT_KEY: string;
  FREEMIUS_WEBHOOK_SECRET: string;
  OPENAI_MODEL?: string;
  PHASE1_GLOBAL_USD_PER_DAY?: string;
  OPENAI_BALANCE_ESTIMATE_USD?: string;
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);
    if (request.method === "GET" && url.pathname === "/v1/health") return healthRoute(env);
    if (request.method === "POST" && url.pathname === "/v1/install/register") return registerInstall(request, env);
    if (request.method === "POST" && url.pathname === "/v1/map") return mapRoute(request, env);
    if (request.method === "POST" && url.pathname === "/v1/freemius/webhook") return freemiusWebhook(request, env);
    return json({ error: "not_found" }, 404);
  },
  async scheduled(_controller: ScheduledController, env: Env): Promise<void> {
    await runCanary(env);
  },
} satisfies ExportedHandler<Env>;

export async function runCanary(env: Env): Promise<void> {
  const fixture: Snapshot = {
    title: "Canary product",
    fields: [
      { id: "core_sku", label: "SKU", value: "CANARY-001" },
      { id: "core_weight", label: "Weight", value: "1 kg" },
      { id: "attr_voltage", label: "Voltage", value: "230 V" },
    ],
  };
  try {
    const result = await requestMap(env, fixture);
    if (!validateMap(result.map, fixture)) throw new Error("invalid_map");
    await healthEvent(env, "canary", "info", { status: "passed" });
  } catch {
    await healthEvent(env, "canary", "error", { status: "failed" });
    const failures = await env.DB.prepare("SELECT COUNT(*) AS count FROM (SELECT severity FROM health_events WHERE kind = 'canary' ORDER BY id DESC LIMIT 2) WHERE severity = 'error'").first<{ count: number }>();
    if (Number(failures?.count ?? 0) >= 2) await openBreaker(env, "two_canary_failures");
  }
}
