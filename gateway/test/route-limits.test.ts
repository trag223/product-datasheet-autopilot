import { describe, expect, it } from "vitest";
import { registerInstall } from "../src/routes/register";
import { freemiusWebhook } from "../src/routes/freemius-webhook";

describe("gateway body limits", () => {
  it("rejects oversized installation registrations before database access", async () => {
    const response = await registerInstall(new Request("https://gateway.test/v1/install/register", { method: "POST", headers: { "Content-Length": "65537" } }), {} as never);
    expect(response.status).toBe(413);
  });

  it("rejects oversized Freemius webhooks before signature handling", async () => {
    const response = await freemiusWebhook(new Request("https://gateway.test/v1/freemius/webhook", { method: "POST", headers: { "Content-Length": "65537" } }), {} as never);
    expect(response.status).toBe(413);
  });
});
