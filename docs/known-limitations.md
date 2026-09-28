# Known limitations

QueryNova 0.1.0 records these limits. They are the behavior of this tree.

## Releases that were booted

[Actions run 36388914008](https://github.com/devhawkz/querynova/actions/runs/36388914008) on commit `dbe8ec5edaed5782802597d17b3a1394c0fff0a5` is the observed run for:

- WordPress 7.1.2
- WooCommerce 11.1.2 on that WordPress
- The admin browser pass on that WordPress, which opened QueryNova and checked the visible What Matters Now heading

The supported floor remains PHP 8.1 and WordPress 6.4. The README names WooCommerce 8.2 or newer when commerce features are used. The plugin header does not claim a tested-up-to range. PHP 8.1 and PHP 8.3 ran the PHPUnit suite on that Actions run. The in-process observed PHP version is still only the interpreter running the process.

## Runs that were not executed

- A WordPress multisite install was not booted. Activation prepares the current site. There is no network settings screen.
- WPML was not installed. Polylang was not installed. The language resolver reads a request language, then WPML when its current-language filter is present, then Polylang when `pll_current_language` exists, then the site locale. A missing language stays empty.
- Tools → Site Health was not opened. QueryNova registers one direct Site Health test. Unit tests cover the registration and the status mapping. That is not a pass of the Site Health screen.

## Providers

Search Console, GA4, SERP, backlink, page-experience, and model adapters are null until a provider is configured. No paid vendor adapter is connected. Setup stores property strings and leaves them not configured. This version does not complete a Google OAuth connection from the admin. Fixture adapters cover tests. They do not call a remote API.

QueryNova does not scrape Google for rankings or keywords. Missing clicks, impressions, revenue, cost, volume, rank, difficulty, authority, backlinks, citations, and timings stay empty. They are not stored as zero. Profit is not invented. Query revenue is attributed only when a join and a methodology are supplied.

## Scores and page changes

There is no 0–100 SEO score, content score, E-E-A-T score, or performance score. Organic difficulty and the AI visibility index are labeled methods, and the visibility index says it is not an official provider ranking. A page under 100 words is the crawler’s thin-page rule.

Recommendations are stored as suggested. Accepting one or marking it applied does not change the page. Rollback restores a previous title or description. Canonical, robots, slugs, URLs, redirects, and indexability are not rolled back. A 404 does not create a redirect. Out-of-stock and discontinued URLs are not changed automatically. Suggested internal links are not inserted.

Content analysis uses supplied HTML. It does not fetch the URL. Experiments describe movement. They do not claim causation.

## Output that is absent or off

- PDF reports are not generated. Reports are CSV and JSON.
- The news sitemap stays off until a publication name is set.
- `llms.txt` is generated only when enabled, and it is experimental.
- Production debug stays off unless an administrator opens a timed window of 15 minutes, 1 hour, 6 hours, or 24 hours.
- The admin ships no custom colors. Contrast is the browser default. The accessibility checks are not a certified WCAG audit.
- Admin strings stay English until a `querynova` translation is loaded. Stored titles, keywords, and provider names are not translated.

## Performance bounds

The synthetic catalog test is an in-memory ceiling, described in `docs/performance.md`. It is not a production timing guarantee, and it was not run against the MySQL database of the WordPress 7.1.2 or WooCommerce 11.1.2 boots.

## Safe mode and uninstall

Safe mode loads only the core module. Optional modules stay unloaded until safe mode is off.

Uninstall keeps data unless `querynova_delete_data_on_uninstall` is `yes`.
