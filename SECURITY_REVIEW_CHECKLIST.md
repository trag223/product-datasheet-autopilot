# Security review checklist

Status: **human review required before deployment or WordPress.org submission**.

This repository intentionally has no deployment performed by the build. Review
the following items against the configured Cloudflare, Freemius, GitHub, and
WordPress accounts before approving the `security-review` GitHub environment.

## Plugin boundary

- [ ] Confirm the free ZIP has no `premium/` PHP files and its contents match the
  reviewed source.
- [ ] Confirm activation with WooCommerce absent stays inert and displays only
  the compatibility notice.
- [ ] Confirm there is no outgoing HTTP call before the merchant separately
  acknowledges consent and enables AI organization.
- [ ] Confirm admin generation and checkout actions require `manage_woocommerce`
  and a verified nonce.
- [ ] Confirm the public route only streams an existing, non-stale hashed PDF;
  it must never generate synchronously.
- [ ] Confirm download responses send PDF content type, `X-Robots-Tag: noindex,
  nofollow`, and one-hour public cache headers.
- [ ] Confirm upload file paths are constrained to the resolved
  `product-datasheet-autopilot/{product_id}/{sha256}.pdf` tree and publication
  is atomic.
- [ ] Confirm diagnostics and telemetry contain event codes/counters only—never
  values, product titles, prompts, model responses, signatures, or secrets.
- [ ] Confirm local telemetry is a no-op until the merchant explicitly enables
  anonymous counters; pre-consent diagnostics must expose an empty event list.

## Gateway boundary

- [ ] Set Worker secrets using `wrangler secret put`; do not place API keys,
  `INSTALL_ROOT_KEY`, or the Freemius webhook secret in `wrangler.jsonc`, Git,
  plugin options, or logs.
- [ ] Keep `OPENAI_BALANCE_ESTIMATE_USD` below `$1` until the reviewer has
  independently checked the funded OpenAI project. The default `0` deliberately
  blocks all AI calls.
- [ ] Verify `/v1/map` rejects body sizes over 64 KB, timestamps beyond five
  minutes, reused nonces, invalid HMACs, inactive licenses, and a second site
  registration for a license.
- [ ] Verify D1 contains only the five intended table families and that no D1
  query or Worker log stores product content or raw model traffic.
- [ ] Send a signed test webhook, a duplicate of it, and an invalid-signature
  webhook. Confirm idempotency, rejection, and immediate refund/chargeback
  revocation behavior.
- [ ] Verify monthly 1,000-call, $0.25 daily, 8%-ARR, balance-reserve, and
  two-failed-canary circuit breakers fall back to local rendering without
  exposing an error to public product pages.

## Release and supply chain

- [ ] Verify GitHub Actions is the only identity allowed to release artifacts;
  retain the protected `security-review` environment required by the tag job.
- [ ] Review package checksums and inspect both generated ZIPs before upload.
- [ ] Confirm the pinned OpenAI model remains `gpt-5-nano-2025-08-07` or approve
  a reviewed model-drift pull request. Do not silently change it.
- [ ] Review the Freemius product, annual $59 plan, license limit, checkout URL,
  and webhook signing secret against the dashboard.

## Known safe limitation requiring product decision

The bundled FPDF 1.9 WinAnsi embedding path preserves supported characters
exactly but rejects a product with characters outside its safe map rather than
transliterating or changing a specification. The fixture corpus contains this
case, so it is an explicit visible generation failure, not a silent data change.
Approve this behavior only if that truth-preserving limitation is acceptable for
the first WordPress.org release.

Reviewer: ____________________

Date: ____________________

Approval / required changes: ________________________________________________
