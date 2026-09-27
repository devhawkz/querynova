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
| Redirects and 404s | COMPLETE | 301, 302, 307, 410, and 451, including regex, CSV import and export, chain collapsing, and loop rejection. 404s record hits, referrer, and user agent, and may suggest a target. They never create a redirect |
| Crawler | COMPLETE | Batched jobs record status, redirects, canonicals, robots, headings, titles, descriptions, broken links, orphans, duplicates, thin pages, depth, sitemap inclusion, schema, HTTPS, pagination, and hreflang. A public request only enqueues the next batch. A page under 100 words is QueryNova's thin-page rule, not a search-engine score. Uncrawled links stay unknown |
| WooCommerce SEO | COMPLETE | Gateway reads products, categories, and brands through WooCommerce APIs. Order totals use `wc_get_orders`, including HPOS, and do not query order tables. Product audit, image and feed checks, merchant readiness, variation and stock decisions, facet indexability, and bulk CSV are tested. Clicks, impressions, revenue, and opportunity stay unavailable until those sources exist. Out-of-stock and discontinued URLs are not changed automatically |
| Analytics and attribution | NOT_STARTED | Provenance type is in place and tested |
| Keyword intelligence | COMPLETE | Explorer, discovery, clustering, gap, winnable keywords, traffic potential, content mapping, and cannibalization. Volume, CPC, rank, and difficulty stay unavailable until supplied. Organic difficulty is the published equal-weight average and is labeled estimated. Paid competition is never copied into it. No search-result scraping |
| SERP and rank tracking | COMPLETE | Snapshots come from a provider interface. The connected adapter is null until a provider is configured, and a fixture adapter covers tests. Top 10, 20, and 100, page type, features, history, stability, competitor rows, and rank history are stored by a job. A public request only enqueues. Authority and backlinks stay null when the payload does not include them. Google is not scraped |
| Competitors and backlinks | COMPLETE | Competitor rows from a SERP snapshot leave authority and backlinks null. Backlink snapshots use a provider interface. Counts, dofollow, nofollow, new, and lost are measured only from returned links. The first snapshot does not mark every link as new. The gap lists domains that link to competitors and not to us, ordered by overlap. A public request only enqueues |
| Content intelligence | COMPLETE | Intent, on-page observations, information gain, product evidence, topic coverage, entity co-mentions, topical map, and internal-link suggestions. A request analyzes supplied HTML and does not fetch the URL. Completeness and information gain are not scores. E-E-A-T evidence has no score. Commerce link suggestions are not inserted |
| Opportunity and recommendations | NOT_STARTED | |
| AI visibility | NOT_STARTED | |
| Experiments, alerts, reports | NOT_STARTED | |
| Diagnostics UI and WP-CLI | NOT_STARTED | |
| Setup wizard and SEO import | NOT_STARTED | |
| Staging and production builds | NOT_STARTED | Docs describe the intended builds |
| Release ZIP | NOT_STARTED | |
| Definition of Done | NOT_STARTED | The product is not complete |

QueryNova is not complete.
