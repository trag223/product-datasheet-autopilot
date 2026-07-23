import { describe, expect, it } from "vitest";
import { parseSnapshot, validateMap } from "../src/lib/schema";
import { sanitizeHealthDetail } from "../src/lib/logging";

const input = {
  layout_version: "1",
  snapshot: {
    title: "Pump",
    fields: [
      { id: "core_sku", label: "SKU", value: "P-100" },
      { id: "attr_weight", label: "Weight", value: "1 kg" },
    ],
  },
};

describe("gateway strict map contract", () => {
  it("accepts a complete existing-ID map", () => {
    const snapshot = parseSnapshot(input);
    expect(snapshot).not.toBeNull();
    expect(validateMap({ layout_version: "1", sections: [{ section_id: "identity", field_ids: ["core_sku"] }, { section_id: "dimensions", field_ids: ["attr_weight"] }], hero_field_ids: ["core_sku"], warnings: [] }, snapshot!)).toBe(true);
  });

  it("rejects duplicate, missing, and invented IDs", () => {
    const snapshot = parseSnapshot(input)!;
    expect(validateMap({ layout_version: "1", sections: [{ section_id: "identity", field_ids: ["core_sku", "core_sku"] }], hero_field_ids: [], warnings: [] }, snapshot)).toBe(false);
    expect(validateMap({ layout_version: "1", sections: [{ section_id: "identity", field_ids: ["invented"] }, { section_id: "dimensions", field_ids: ["attr_weight"] }], hero_field_ids: [], warnings: [] }, snapshot)).toBe(false);
  });

  it("rejects an over-limit request before a model call", () => {
    const tooMany = structuredClone(input);
    tooMany.snapshot.fields = Array.from({ length: 51 }, (_, index) => ({ id: `attr_${index}`, label: "A", value: "B" }));
    expect(parseSnapshot(tooMany)).toBeNull();
  });

  it("redacts arbitrary values from durable health telemetry", () => {
    expect(sanitizeHealthDetail({ reason: "daily_budget", title: "secret product", raw_response: "secret" })).toEqual({ reason: "daily_budget" });
  });
});
