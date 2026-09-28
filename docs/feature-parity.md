# Feature parity

Audit of the QueryNova tree at the commit that introduced this file. Rank Math is a feature and information-architecture benchmark only. This document does not copy its source, formulas, branding, or layout.

States:

- **MISSING** — not in this tree.
- **PARTIAL** — some of the behavior exists. The admin shell does not expose it, or the provider is null until configured.
- **IMPLEMENTED** — the behavior is in PHP or the admin and is covered by an automated test named in Notes.
- **VERIFIED** — the same, and the test asserts the behavior rather than only that a class exists.

A module name is not proof. WordPress 7.1.2, WooCommerce 11.1.2, and the admin browser pass remain the only observed release boots, from Actions run 36388914008 on `dbe8ec5edaed5782802597d17b3a1394c0fff0a5`. That browser pass opened What Matters Now. It did not open Schema.

| Feature | Rank Math benchmark | QueryNova module | State | Backend | UI | Tests | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Admin shell | Left nav, context bar, simple/advanced | `CoreModule` admin page | PARTIAL | One menu page | Left nav, context bar, WordPress environment badge, simple/advanced mode | `assets/admin/app/navigation.test.ts`, `tests/e2e/admin.spec.ts` | The browser pass predates this shell. Mode does not change stored data |
| Dashboard | What Matters Now, KPIs, next actions | Opportunities, `DashboardBriefing` | PARTIAL | Stored actions and sections | Empty sections say "Nothing recorded." | `assets/admin/features/dashboard/sections.test.ts` | No skeletons, toasts, or Connect/Not available KPI cards |
| Setup wizard | Multi-step site, business, search, analytics, SEO, sitemaps, commerce, review | `SetupWizard` | PARTIAL | Option store, providers stay not configured | Eight steps: Site, Business, Search engines, Analytics, SEO defaults, Sitemaps, WooCommerce, Review | `tests/Unit/SetupWizardTest.php`, `model.test.ts` | Saving does not connect a provider or start a crawl |
| Diagnostics | Cards for environment, health, jobs, providers | `DiagnosticsReport` | PARTIAL | Snapshot includes WordPress environment and build channel | Definition list, next-step empty states, and the JSON report | `tests/Unit/ReleaseProfileTest.php`, `model.test.ts` | Environment and build stay separate. Not a card grid yet |
| Settings IA | General through Tools | Settings screen | PARTIAL | Read-only catalog of modules, features, and roles | Seventeen sections. Tools keeps setup and diagnostics. Other sections do not save | `tests/Unit/SettingsCatalogTest.php`, `assets/admin/features/settings/model.test.ts` | Does not write robots, ALT, htaccess, roles, or URL rewrites |
| Module manager | Card grid of modules | `ModuleRegistry`, `FeatureRegistry` | IMPLEMENTED | `SettingsCatalog` reads both registries. An OFF feature stays off. A boot failure is a boolean | Card grid on Settings. Status text is On, Off, Experimental, or Disabled | `SettingsCatalogTest`, `model.test.ts` | This screen does not turn features off. Site overrides are not persisted |
| Editor panel | Gutenberg, Classic, product, CPT tabs | SEO meta service | PARTIAL | Meta resolve and save REST | No block, classic, or product editor panel | `tests/Unit/SeoMetaServiceTest.php` | Elementor adapter is absent |
| On-page SEO | Title, description, canonical, robots, focus keywords, checklist | `SeoModule` | PARTIAL | Title, description, canonical, robots, social tags | No editor checklist | `SeoMetaServiceTest` | No on-page readiness checklist. No score is claimed |
| Titles and meta templates | Homepage, post, page, product, archive variables | `TemplateRenderer` | PARTIAL | Template tokens for a document | No global template screen | `SeoMetaServiceTest` | |
| Site SEO analyzer | Passed, warning, failed, info, rerun | Crawler observations | PARTIAL | Crawl job stores page facts | No analyzer screen | `tests/Unit/CrawlTest.php` | A public request only enqueues |
| Search Console | OAuth, property, test, disconnect | Analytics provider interface | PARTIAL | Null adapter until configured. Sync enqueues | Setup stores a property string | `tests/Unit/AnalyticsTest.php` | Not connected. No OAuth |
| GA4 | OAuth, property, test, disconnect | Analytics provider interface | PARTIAL | Null adapter. Missing metrics stay null | Property string only | `AnalyticsTest` | No fake metrics |
| Analytics screens | Overview, keywords, content, rank, index, traffic, commerce, AI | Stored rows | PARTIAL | Read models for supplied rows | What Matters Now sections only | `sections.test.ts` | No date comparison UI. No charts |
| Rank tracker | Add, bulk, CSV, group, location, device, history | SERP snapshots | PARTIAL | Job stores snapshots. Missing rank stays null | Advanced list of stored rows | `tests/Unit/SerpTest.php` | No add/bulk/CSV UI. Index status and Trends need a real API |
| Page experience | LCP, INP, CLS, TTFB | `ExperienceModule` | IMPLEMENTED | Missing timing stays null | Not a dedicated screen | `tests/Unit/ExperienceTest.php` | |
| Schema graph | Product, Offer, FAQ, LocalBusiness, Video, breadcrumbs | `SchemaModule` | PARTIAL | Connected graph for the types in `SchemaTypes` | Builder form | `tests/Unit/SchemaGraphTest.php` | FAQ is not a supported type. Live Schema screen showed a load error; see the schema row below |
| Schema rules load | Rules open in the admin | `GET /schema/rules` | IMPLEMENTED | `querynova_manage_settings` or `querynova_manage_seo` can read and save. Stored rules are also on the admin boot payload | A failed refresh keeps stored rules and shows HTTP status, server message, and error reference | `tests/Unit/SchemaRulesAccessTest.php`, `assets/admin/features/schema/rules.test.ts`, `assets/admin/core/api/client.test.ts` | The old screen hid every failure behind one sentence |
| Sitemaps | Posts, pages, CPT, taxonomies, products, images, authors, HTML, news, video, KML | `SitemapModule` | PARTIAL | XML for posts, pages, products, categories, brands, CPTs, taxonomies, images, video. News off until a publication name | No sitemap settings screen | `tests/Unit/SitemapTest.php` | No HTML sitemap. No KML |
| Image SEO | ALT helper that keeps manual ALT | — | MISSING | — | — | — | |
| Link graph | Broken, orphans, suggestions default to suggest | Content internal links | PARTIAL | Suggestions are not inserted | No link screen | `tests/Unit/ContentTest.php` | |
| Redirects and 404s | Search, filters, pagination, confirm on permalink change | `RedirectModule` | PARTIAL | 301, 302, 307, 410, 451, regex, CSV, loop rejection. 404s do not create redirects | No manager UI | `tests/Unit/RedirectTest.php` | No auto-redirect on permalink change |
| robots.txt | Editor that does not touch a physical file without permission | — | MISSING | — | — | — | |
| .htaccess | Advanced, backup, confirm, hidden on Nginx | — | MISSING | — | — | — | |
| Webmaster verification | Meta verification fields | — | MISSING | — | — | — | |
| RSS before/after | Content added around the feed | — | MISSING | — | — | — | |
| IndexNow | Submit, skip noindex | — | MISSING | — | — | — | |
| Breadcrumbs | Settings, function, shortcode, block, BreadcrumbList | Schema `BreadcrumbList` | PARTIAL | Graph can emit BreadcrumbList | No breadcrumb settings, shortcode, or block | `SchemaGraphTest` | |
| WooCommerce product workspace | Search, recent, issue filters, identifiers, variations | `CommerceModule`, product screen | PARTIAL | Gateway, audit, variations, facets, bulk CSV | Latest stored product, or an empty product state | `tests/Unit/CommerceTest.php`, `model.test.ts` | No GTIN/MPN/ISBN editor. No URL-base rewrite |
| WooCommerce category workspace | Same for categories | Category screen | PARTIAL | Category analysis from stored rows | Latest stored category | `CategoryScreenTest`, `model.test.ts` | |
| EDD | Optional, absent plugin does not load | — | MISSING | — | — | — | |
| Content intelligence | Intent, coverage, gap, entities, information gain, E-E-A-T | `ContentModule` | IMPLEMENTED | Supplied HTML only. No score | No workspace | `ContentTest` | |
| Content AI drafts | Outline, titles, FAQ, rewrite. Never auto-publish | — | MISSING | — | — | — | |
| AI link and keyword suggestions | Suggest only | Internal link suggestions | PARTIAL | Not inserted | No UI | `ContentTest` | |
| AI Visibility | Prompt tracking, crawler list, no fake citations | `AiModule` | PARTIAL | Null adapter. Index stays empty when an input is missing. `llms.txt` only when enabled | No workspace | `tests/Unit/AiTest.php` | Experimental label is in the methodology, not a dedicated screen |
| MCP / assistant tools | Authorized read, explicit write permission | — | MISSING | — | — | — | |
| Reports | CSV, JSON, scheduled email, white label | `ReportModule` | PARTIAL | CSV and JSON from supplied metrics | No report screen | `tests/Unit/ReportTest.php` | PDF is not generated |
| Role manager | Edit role caps in the admin | `RoleMap` | PARTIAL | Fixed role map applied on init | No editor | `RestPermissionTest` | |
| Import / export | Yoast, Rank Math, AIOSEO preview, settings export | `SeoImporter` | PARTIAL | Import from supplied meta, 50 at a time | No preview UI | `tests/Unit/SeoImportTest.php` | |
| Local SEO | CPT, block, shortcode, locator, only when enabled | Schema `LocalBusiness` type | PARTIAL | Type can be emitted | No locations module | `SchemaGraphTest` | |
| Podcast | Only when enabled | — | MISSING | — | — | — | |
| Headless SEO REST | Secured metadata, canonical, robots, schema, social | `GET /seo/meta` | PARTIAL | Meta endpoint for a post id | No headless contract test beyond the service | `SeoMetaServiceTest` | |
| Post list columns and Quick Edit | Title, description, index, focus keyword | — | MISSING | — | — | — | |
| Content decay and cannibalization | Reports | Keyword cannibalization, content observations | PARTIAL | Detection from supplied rows | No report screen | `KeywordTest` | |
| Opportunity drawer | Accept, dismiss, mark applied, experiment. Does not change the page until applied | Outcomes | PARTIAL | Accept and apply endpoints. Apply does not change the page | Dashboard list, no drawer | `tests/Unit/OutcomeTest.php` | |
| Provider cards | Connected, not configured, degraded | Null adapters | PARTIAL | Diagnostics lists providers as not configured | Text list | `Diagnostics` model test | |
| Job monitor | List, retry | Jobs table, WP-CLI | PARTIAL | Retry requeues and does not run | No monitor | `tests/Unit/JobRunnerTest.php`, `CliCommandsTest` | |
| Log viewer | Secret-free | Log repository and sanitizer | PARTIAL | Redaction before storage | Diagnostics recent errors only | `LogSanitizerTest` | |
| Speakable | Only where valid | — | MISSING | — | — | — | |
| Video schema and video sitemap | VideoObject and video sitemap | Schema and sitemap | PARTIAL | Both can be emitted | No video settings | `SchemaGraphTest`, `SitemapTest` | |
| Global link settings | Safe defaults | — | MISSING | — | — | — | |
| DataTable | Server pagination, sort, filter, bulk | List endpoints with limits | PARTIAL | Several repositories page | No shared table component | Commerce bulk tests | |
| Notification center and toasts | In-plugin, not wp-admin spam | Admin notices for staging data, build channel, SEO conflict | PARTIAL | Those notices are non-blocking | No in-plugin center | `StagingDataWarningTest`, `BuildChannelNoticeTest` | |
| Quick search | Ctrl/Cmd-K | — | MISSING | — | — | — | Skipped until it is off the critical path |
| Charts | Trend charts | — | MISSING | — | — | — | No chart library. A decision is required before adding one |
| WordPress environment vs build | Separate | `ReleaseProfile` | VERIFIED | `wp_get_environment_type()` only. Build from `build/channel.json` | Diagnostics shows both. Staging on production warns | `ReleaseProfileTest`, `LoggerReleaseContextTest` | Does not write `wp-config.php` |

Parity is not complete. The rows marked MISSING or PARTIAL are still open.
