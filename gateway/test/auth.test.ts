import { describe, expect, it } from "vitest";
import { hmacHex, installSecret, sha256Hex } from "../src/lib/auth";

describe("gateway request signing", () => {
  it("derives the per-install secret and canonical request signature", async () => {
    const secret = await installSecret("root", "license-1", "e2081528-b5b6-4ecb-8f30-5c91b60c2f16", "a".repeat(64));
    const signature = await hmacHex(secret, `1700000000\nnonce\n${await sha256Hex("{}")}`);
    expect(secret).toMatch(/^[a-f0-9]{64}$/);
    expect(signature).toMatch(/^[a-f0-9]{64}$/);
  });
});
