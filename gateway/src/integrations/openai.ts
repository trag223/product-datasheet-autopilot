import { estimateCost, type Usage } from "../lib/cost";
import { MAP_JSON_SCHEMA, type FieldMap, type Snapshot } from "../lib/schema";

export interface OpenAiEnv {
  OPENAI_API_KEY: string;
  OPENAI_MODEL?: string;
}

export interface MapResult { map: FieldMap; usage: Usage }

const SYSTEM_PROMPT = [
  "You organize an existing product field list into fixed datasheet sections.",
  "Return only the JSON schema result.",
  "You may only use existing field IDs. Never write, copy, paraphrase, infer, calculate, or invent product values.",
  "Every input field ID must appear exactly once in sections. hero_field_ids must be input IDs only. Use ambiguous_section warnings only when necessary.",
].join(" ");

export async function requestMap(env: OpenAiEnv, snapshot: Snapshot): Promise<MapResult> {
  const response = await fetch("https://api.openai.com/v1/responses", {
    method: "POST",
    headers: { "Authorization": `Bearer ${env.OPENAI_API_KEY}`, "Content-Type": "application/json" },
    body: JSON.stringify({
      model: env.OPENAI_MODEL || "gpt-5-nano-2025-08-07",
      reasoning: { effort: "minimal" },
      max_output_tokens: 500,
      input: [
        { role: "system", content: [{ type: "input_text", text: SYSTEM_PROMPT }] },
        { role: "user", content: [{ type: "input_text", text: JSON.stringify({ layout_version: "1", fields: snapshot.fields }) }] },
      ],
      text: { format: { type: "json_schema", name: "pda_field_map", strict: true, schema: MAP_JSON_SCHEMA } },
    }),
  });
  if (!response.ok) throw new OpenAiError(response.status);
  const payload = await response.json() as OpenAiResponse;
  const output = payload.output_text ?? payload.output?.flatMap((item) => item.content ?? []).find((item) => item.type === "output_text")?.text;
  if (typeof output !== "string") throw new OpenAiError(502);
  let map: FieldMap;
  try { map = JSON.parse(output) as FieldMap; } catch { throw new OpenAiError(502); }
  const inputTokens = boundedInteger(payload.usage?.input_tokens, 4000);
  const outputTokens = boundedInteger(payload.usage?.output_tokens, 500);
  return { map, usage: { inputTokens, outputTokens, costUsd: estimateCost(inputTokens, outputTokens) } };
}

export class OpenAiError extends Error {
  constructor(public readonly status: number) { super(`openai_${status}`); }
}

interface OpenAiResponse {
  output_text?: string;
  output?: Array<{ content?: Array<{ type?: string; text?: string }> }>;
  usage?: { input_tokens?: number; output_tokens?: number };
}

function boundedInteger(value: unknown, maximum: number): number {
  return typeof value === "number" && Number.isInteger(value) && value >= 0 ? Math.min(value, maximum) : maximum;
}
