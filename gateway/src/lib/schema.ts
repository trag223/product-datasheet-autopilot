export const SECTION_IDS = [
  "identity",
  "dimensions",
  "materials",
  "performance",
  "compatibility",
  "package",
  "compliance",
  "other",
] as const;

export type SectionId = (typeof SECTION_IDS)[number];

export interface InputField {
  id: string;
  label: string;
  value: string;
}

export interface Snapshot {
  title: string;
  fields: InputField[];
  product_id?: number;
  product_url?: string;
  branding_name?: string;
  image_attachment_id?: number;
}

export interface FieldMap {
  layout_version: string;
  sections: Array<{ section_id: SectionId; field_ids: string[] }>;
  hero_field_ids: string[];
  warnings: Array<{ field_id: string; code: "ambiguous_section" }>;
}

export const MAP_JSON_SCHEMA = {
  type: "object",
  additionalProperties: false,
  required: ["layout_version", "sections", "hero_field_ids", "warnings"],
  properties: {
    layout_version: { type: "string", const: "1" },
    sections: {
      type: "array",
      items: {
        type: "object",
        additionalProperties: false,
        required: ["section_id", "field_ids"],
        properties: {
          section_id: { type: "string", enum: SECTION_IDS },
          field_ids: { type: "array", items: { type: "string" } },
        },
      },
    },
    hero_field_ids: { type: "array", maxItems: 4, items: { type: "string" } },
    warnings: {
      type: "array",
      items: {
        type: "object",
        additionalProperties: false,
        required: ["field_id", "code"],
        properties: {
          field_id: { type: "string" },
          code: { type: "string", const: "ambiguous_section" },
        },
      },
    },
  },
} as const;

export function parseSnapshot(input: unknown): Snapshot | null {
  if (!input || typeof input !== "object") return null;
  const candidate = input as { snapshot?: unknown; layout_version?: unknown };
  const snapshot = candidate.snapshot as Snapshot | undefined;
  if (candidate.layout_version !== "1" || !snapshot || typeof snapshot.title !== "string" || snapshot.title.length === 0 || snapshot.title.length > 200 || !Array.isArray(snapshot.fields) || snapshot.fields.length > 50) {
    return null;
  }
  const ids = new Set<string>();
  let chars = snapshot.title.length;
  for (const field of snapshot.fields) {
    if (!field || typeof field.id !== "string" || !/^[a-z0-9_]{1,80}$/.test(field.id) || ids.has(field.id) || typeof field.label !== "string" || field.label.length === 0 || field.label.length > 100 || typeof field.value !== "string" || field.value.length > 300) return null;
    ids.add(field.id);
    chars += field.label.length + field.value.length;
  }
  return chars <= 16000 ? snapshot : null;
}

export function validateMap(candidate: unknown, snapshot: Snapshot): candidate is FieldMap {
  if (!candidate || typeof candidate !== "object") return false;
  const map = candidate as FieldMap;
  if (map.layout_version !== "1" || !Array.isArray(map.sections) || !Array.isArray(map.hero_field_ids) || !Array.isArray(map.warnings) || map.hero_field_ids.length > 4) return false;
  const inputIds = new Set(snapshot.fields.map((field) => field.id));
  const seen = new Set<string>();
  for (const section of map.sections) {
    if (!section || !SECTION_IDS.includes(section.section_id) || !Array.isArray(section.field_ids)) return false;
    for (const id of section.field_ids) {
      if (typeof id !== "string" || !inputIds.has(id) || seen.has(id)) return false;
      seen.add(id);
    }
  }
  if (seen.size !== inputIds.size) return false;
  if (new Set(map.hero_field_ids).size !== map.hero_field_ids.length || map.hero_field_ids.some((id) => !inputIds.has(id))) return false;
  return map.warnings.every((warning) => warning && warning.code === "ambiguous_section" && inputIds.has(warning.field_id));
}
