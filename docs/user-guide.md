# User guide

QueryNova 0.1.0 is a WordPress plugin for search, commerce, and AI visibility. It answers what to work on next, and whether that work is tied to organic growth or organic revenue. Empty measurements stay empty. An estimate is labeled Estimated.

## Requirements

- PHP 8.1 or newer
- WordPress 6.4 or newer
- WooCommerce is optional. Commerce screens use WooCommerce when it is active. The README names WooCommerce 8.2 or newer when those features are used.
- The observed boots are WordPress 7.1.2 and WooCommerce 11.1.2 on that WordPress, from [Actions run 36388914008](https://github.com/devhawkz/querynova/actions/runs/36388914008) on commit `dbe8ec5edaed5782802597d17b3a1394c0fff0a5`. See `docs/known-limitations.md`.

## Install and activate

1. Build a release ZIP with `npm run package`, or install the plugin directory after `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`.
2. Copy the plugin to `wp-content/plugins/querynova`.
3. Activate QueryNova. Activation checks PHP 8.1, WordPress 6.4, a database prefix, and OpenSSL. It runs migrations, registers capabilities, sets the retain-data default, and schedules jobs.

Deactivation keeps stored options and clears the cron hook, locks, and QueryNova transients. Uninstall keeps data unless the option `querynova_delete_data_on_uninstall` is `yes`. When that option is `yes`, uninstall drops the QueryNova tables.

## Open the admin

In wp-admin, open **QueryNova**. The menu is registered for the site admin with `querynova_manage_settings`. The address is `wp-admin/admin.php?page=querynova`.

The screen is one app with these views:

| View | What it shows |
| --- | --- |
| What Matters Now | Up to ten actions, then Revenue Opportunities, Search Opportunities, Technical Risks, Commerce Risks, AI Opportunities, and Recent Changes. Each section shows at most five stored rows. |
| Schema | Stored schema rules. Save writes the rules. It does not publish a page. |
| Advanced | Stored SERPs, keywords, backlinks, methodologies, providers, confidence, and rank history. Lists are capped at twenty rows. Raw provider payloads are not reconstructed. |
| Product | The latest stored product, or “No stored product.” |
| Category | The latest stored category. A missing product count is left empty. |
| Diagnostics | Environment, versions, modules, providers, queue, migrations, and recent errors. |
| Setup | Site answers. Saving stores the answers. |

With WooCommerce active, What Matters Now introduces organic revenue opportunities. Without it, the introduction is organic growth opportunities.

A section with no rows says “Nothing recorded.” An action list with no rows says to connect Search Console or run an on-site audit before the next actions can be ranked. That sentence is the empty state. This version does not complete a provider connection from Setup.

Each metric is Measured, Attributed, Estimated, or Unavailable. Unavailable is not shown as zero.

## Setup

Setup stores site type, business type, the detected WooCommerce state, organization name, URL, and logo, Search Console and GA4 property strings, a title separator (`|`, `-`, or `–`), and yes/no choices for schema, sitemap, and the crawler, plus a crawler origin.

Saving records those answers. Property state stays not configured. Saving does not connect a provider, store an API key, start a crawl, or change a live feature flag. The confirmation is: “Setup answers are stored. Providers stay not configured and no crawl was started.”

## Product and category

Product tabs are Overview, Search, Keywords, Revenue, Conversion, Content, Schema, Links, Competitors, AI, and Recommendations.

Category tabs are Overview, Keywords, Revenue, Products, Content, SERP, Filters, Links, Competitors, AI, and Recommendations.

Both screens read stored rows. A tab with no rows says “Nothing recorded.”

## Schema

The builder stores rules for WebSite, Organization, Person, WebPage, CollectionPage, Article, BlogPosting, BreadcrumbList, Product, ProductGroup, Offer, AggregateOffer, AggregateRating, Review, Brand, Service, LocalBusiness, MedicalOrganization, Physician, SoftwareApplication, Course, Event, and VideoObject.

A rule has a type, an id template, conditions, and property mappings. Sources are WordPress, WooCommerce, custom fields, templates, literals, and links. Conditions can require a value to exist, be missing, equal a string, or differ from a string. Product offers and ratings are emitted only when those values are measured.

## What the plugin does on the site

- Titles, descriptions, canonicals, robots, and social tags follow stored templates. Invalid values are rejected.
- XML sitemaps can include posts, pages, products, categories, brands, custom post types, taxonomies, images, and video. The news sitemap stays off until a publication name is set.
- Redirects support 301, 302, 307, 410, and 451, including regex and CSV import and export. Chains collapse. Loops are rejected. A 404 records the hit, referrer, and user agent, and may suggest a target. A 404 does not create a redirect.
- The crawler runs in batches of at most 100 pages. A public request enqueues the next batch. A page under 100 words is QueryNova’s thin-page rule. Uncrawled links stay unknown.
- WooCommerce products, categories, and brands are read through WooCommerce APIs. Order totals use `wc_get_orders`, including HPOS. Out-of-stock and discontinued URLs are not changed automatically.
- Keyword research, discovery, clustering, gap, content mapping, and cannibalization use supplied data. Volume, CPC, rank, and difficulty stay unavailable until supplied. Organic difficulty is an equal-weight estimate and is labeled Estimated. Paid competition is not copied into it. QueryNova does not scrape search results.
- SERP snapshots, rank history, competitors, and backlinks come from provider interfaces. The connected adapters are null until a provider is configured. Authority and backlinks stay empty when the payload does not include them. The first backlink snapshot does not mark every link as new.
- Content analysis reads HTML you supply. It does not fetch the URL. Completeness, information gain, and E-E-A-T evidence have no score. Suggested internal links are not inserted.
- Opportunities and recommendations evaluate supplied inputs. There is no 0–100 score. Incremental revenue is estimated only from a stated CTR-gap method and is labeled Estimated. A suggestion is stored as suggested. Accepting it or marking it applied does not change the page.
- Experiments store before and after rank, CTR, clicks, traffic, and revenue for title, description, category content, internal links, and schema. Improved, declined, mixed, and inconclusive describe the movement. Causation is not claimed.
- Alerts are created from supplied evidence. A missing number does not create an alert. The same open alert is not stored twice.
- Reports render CSV and JSON for SEO, commerce, executive, keyword, competitor, and AI metrics. Missing values stay empty. PDF is not generated.
- AI prompt observations use a provider interface. The connected adapter is null. The visibility index is an equal-weight observational average, stays empty when an input is missing, and is not an official provider ranking. `llms.txt` is experimental and is generated only when enabled.
- Page experience stores LCP, INP, CLS, and TTFB for desktop and mobile from a provider interface. A missing timing stays empty. There is no performance score.

## Diagnostics

Diagnostics shows environment, QueryNova version, WordPress version, PHP version, WooCommerce version, database version, schema version, cron, cache, modules, providers, queue counts, pending migrations, and recent errors. A missing version stays empty. Copy or download writes `querynova-diagnostics.json` with secrets removed.

## WP-CLI

When WP-CLI is running, `wp querynova` accepts:

| Command | Result |
| --- | --- |
| `status` | Plugin version, schema version, and environment |
| `health` | Health checks |
| `modules` | Loaded module names |
| `migrate` | Applies pending migrations |
| `jobs list` | Up to 20 jobs |
| `jobs retry` | Requeues failed, dead, or retrying jobs, or one `--id`. The command does not run them |
| `cache clear` | Clears the QueryNova cache group |
| `crawl run --url=<url>` | Queues a crawl batch. It does not fetch the site |
| `analytics sync --property=<id> --start=<date> --end=<date>` | Queues a sync. It does not call a provider |
| `diagnostics` | The same snapshot as the diagnostics screen |

Add `--format=json` for JSON.

## Roles

Administrator receives every QueryNova capability. These roles are created when they do not already exist:

| Role | Capabilities |
| --- | --- |
| SEO Manager | Settings, analytics, SEO, WooCommerce SEO, analysis, integrations, logs |
| Content Editor | SEO and analysis |
| Commerce Manager | Analytics, WooCommerce SEO, and analysis |
| Developer | Settings, logs, debug, and analysis |

One capability does not grant another.

## Safe mode and staging

Safe mode loads only the core module. Enable it with the `QUERYNOVA_SAFE_MODE` constant in `wp-config.php`, or set the option `querynova_safe_mode` to `yes`.

On a staging site, a stored Search Console or GA4 property, cloud site id, or provider project raises an admin warning that the identifier may still point at production. Production stays quiet. The warning does not make an analytics request.

The release channel is development for local and development, beta for staging, and stable otherwise.

## Another SEO plugin

When Yoast, Rank Math, or AIOSEO is loaded, an admin notice warns that meta, schema, canonical, and sitemap output may be duplicated. QueryNova does not disable the other plugin. Import copies titles, descriptions, canonicals, robots, and focus keywords from supplied post or term meta, 50 objects at a time. An existing QueryNova value is kept unless replace is set.

## Further reading

- `docs/known-limitations.md`
- `docs/release-notes.md`
- `docs/performance.md`
- `docs/security.md`
- `docs/debugging.md`
