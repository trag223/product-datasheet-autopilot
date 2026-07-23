# Product Datasheet Autopilot for WooCommerce

Product Datasheet Autopilot produces a branded, printable single-product PDF
from WooCommerce data. The free edition generates up to three manually selected
products locally. Pro enables automatic regeneration, audits, and optional
AI-assisted field organization. The AI can only organize existing field IDs;
the PHP plugin always renders the original store values.

## Development

Requirements: PHP 8.1+, Composer, Node 20+, Docker (for `wp-env`), WordPress
6.9+, and WooCommerce 10.8+.

```powershell
composer install
npm install
npm test
vendor/bin/phpunit
npm run build:free
npm run build:pro
```

Copy `.env.example` to `.env` for local gateway work. Do not commit it. The
Worker receives the OpenAI key; the plugin never does.

## Releases

`npm run package:free` and `npm run package:pro` create ZIPs plus SHA-256 files
in `dist/`. The free package excludes `plugin/premium/`. See
[`scripts/svn-deploy.sh`](scripts/svn-deploy.sh) for the WordPress.org staging
workflow. Do not deploy until the security checklist has passed human review.

## Scope and data handling

No product title, product value, prompt, or model response is stored by the
gateway. AI organization is disabled by default and has a clear opt-in. The
plugin offers no claims about ROI; release claims must be backed by tests and
directory metrics.
