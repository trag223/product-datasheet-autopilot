=== Product Datasheet Autopilot for WooCommerce ===
Contributors: product-datasheet-autopilot
Tags: woocommerce pdf, product datasheet, product pdf, specification sheet
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create branded, printable PDF datasheets from existing WooCommerce product data.

== Description ==

The free edition generates a fixed professional PDF locally for up to three
products. It reads the product title, SKU, dimensions, weight, visible
attributes, selected custom fields, product image, and store branding.

Optional AI-assisted section organization is a Pro-only opt-in. It can only
return section assignments for existing field IDs; the plugin always inserts
the original product values. PDFs include a reminder to verify specifications
with the seller.

== Installation ==

1. Install and activate WooCommerce 10.8 or later.
2. Upload and activate this plugin.
3. Go to WooCommerce → Datasheets and generate a preview.

== Privacy ==

The free edition has no external API dependency. AI organization is disabled by
default. When a Pro merchant explicitly enables it, a product snapshot is sent
to the configured gateway for field-ID organization only. The gateway does not
store product content, prompts, or model responses.

== Changelog ==

= 1.0.0 =
* Initial release.
