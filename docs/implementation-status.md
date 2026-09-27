# Implementation status

COMPLETE means the behavior exists and has automated tests. IN_PROGRESS means code is landing. NOT_STARTED means it is not in the tree. BLOCKED means a decision in `docs/decisions-needed.md` stops it. That file is absent because nothing is blocked.

| Area | Status | Notes |
| --- | --- | --- |
| Repository tooling | COMPLETE | Composer, PHPCS, PHPStan, PHPUnit, ESLint, Vitest, Vite |
| Module registry and DI | COMPLETE | Cycle detection and boot order tested |
| Feature registry | COMPLETE | Site overrides and dependency gating tested |
| Environment | COMPLETE | Uses `wp_get_environment_type()` |
| Logging and redaction | COMPLETE | Levels, sanitizer, debug expiry tested |
| Health registry | COMPLETE | Optional check failures do not abort the run |
| Schema and migrations | COMPLETE | Required tables asserted. Runner covered by the migration classes |
| Jobs | COMPLETE | Idempotency and non-retry of validation tested |
| Cache, HTTP, locks, SSRF | COMPLETE | SSRF tests cover private and metadata addresses |
| REST permission model | IN_PROGRESS | Registrar exists. Feature routes are not all registered |
| Admin React app | IN_PROGRESS | Shell, provenance labels, schema builder, and asset enqueue exist. Feature screens are not all built |
| Core SEO metadata | COMPLETE | Titles, descriptions, canonical, robots, and social tags. Templates and validation are tested |
| Sitemaps | COMPLETE | Paged XML for posts, pages, products, categories, brands, CPTs, taxonomies, images, and video. News is optional and off until a publication name is set. Inclusion rules are tested |
| Schema graph | COMPLETE | Connected JSON-LD for the supported types, including product offers and ratings only when measured. The builder stores type, field mappings, WooCommerce fields, custom fields, conditions, and templates |
| Redirects and 404s | NOT_STARTED | |
| Crawler | NOT_STARTED | |
| WooCommerce SEO | NOT_STARTED | |
| Analytics and attribution | NOT_STARTED | Provenance type is in place and tested |
| Keyword intelligence | NOT_STARTED | |
| SERP and rank tracking | NOT_STARTED | |
| Competitors and backlinks | NOT_STARTED | |
| Content intelligence | NOT_STARTED | |
| Opportunity and recommendations | NOT_STARTED | |
| AI visibility | NOT_STARTED | |
| Experiments, alerts, reports | NOT_STARTED | |
| Diagnostics UI and WP-CLI | NOT_STARTED | |
| Setup wizard and SEO import | NOT_STARTED | |
| Staging and production builds | NOT_STARTED | Docs describe the intended builds |
| Release ZIP | NOT_STARTED | |
| Definition of Done | NOT_STARTED | The product is not complete |

QueryNova is not complete.
