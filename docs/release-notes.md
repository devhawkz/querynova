# Release notes

## 0.1.3

Plugin header version `0.1.3`. Schema version stays `202609270002`.

This package is a staging build for production testing. The installed build channel stays staging, which is the beta channel, not production/stable. A staging package on a production WordPress site keeps the existing non-blocking warning. The plugin does not read or write `wp-config.php`. The WordPress environment still comes only from `wp_get_environment_type()`.

Admin sparklines are CSS and SVG. They draw stored rank position, clicks or impressions, and WooCommerce revenue. A missing point stays empty and is not drawn as zero. The labels stay Measured, Attributed, Estimated, and Unavailable. No chart library is included.

Observed WordPress and WooCommerce releases are unchanged: WordPress 7.1.2 and WooCommerce 11.1.2 on Actions run 36388914008, commit `dbe8ec5edaed5782802597d17b3a1394c0fff0a5`. This 0.1.3 package was not part of that run. Parity is not complete.

## 0.1.2

Plugin header version `0.1.2`. Schema version stays `202609270002`.

The WordPress environment and the QueryNova build channel are separate. The WordPress environment still comes only from `wp_get_environment_type()`. The installed package is read from `build/channel.json`: production is stable, staging is beta, and development is the development channel. Diagnostics, the system report, the downloadable diagnostic report, log context, and `wp querynova diagnostics` show both.

A staging build on a production WordPress environment shows a non-blocking warning and does not change the WordPress environment or `wp-config.php`. A production build on a staging WordPress environment shows an informational notice. The plugin keeps working in both cases.

Observed WordPress and WooCommerce releases are unchanged from 0.1.0: WordPress 7.1.2 and WooCommerce 11.1.2 on Actions run 36388914008, commit `dbe8ec5edaed5782802597d17b3a1394c0fff0a5`. This 0.1.2 change was not part of that run.

## 0.1.0

Plugin version `0.1.0`. Schema version `202609270002`. REST namespace `querynova/v1`. Text domain `querynova`.

Published methodology versions shipped with this plugin version:

| Methodology | Version |
| --- | --- |
| `querynova.organic_difficulty` | `1` |
| `querynova.content_observations` | `v1` |
| `querynova.ai_visibility_index` | `v1` |

A methodology without a published version is omitted from the version description. In 0.1.0 the release channel followed the WordPress environment: development for local and development, beta for staging, and stable otherwise. From 0.1.2 the release channel follows the installed build instead.

### Requirements

- PHP 8.1 or newer
- WordPress 6.4 or newer
- WooCommerce is not required. The README names WooCommerce 8.2 or newer when commerce features are used.

The plugin header states Requires at least 6.4 and Requires PHP 8.1. It does not state a tested-up-to range.

### Observed on CI

[Actions run 36388914008](https://github.com/devhawkz/querynova/actions/runs/36388914008) on commit `dbe8ec5edaed5782802597d17b3a1394c0fff0a5` passed:

- `php (8.1)` and `php (8.3)`, including PHPCS, PHPStan, PHPUnit, and the synthetic catalog checks
- `admin` (ESLint, TypeScript, Vitest, production build)
- `WordPress release` for WordPress 7.1.2
- `WooCommerce release` for WooCommerce 11.1.2 on that WordPress
- `Admin browser pass` of the QueryNova admin, which found the visible What Matters Now heading and recorded no page or console errors on that screen

The support matrix records WordPress 7.1.2 and WooCommerce 11.1.2 as the observed releases, plus that admin browser pass. PHP observed inside a process remains the interpreter running that process.

### What this version includes

- Activation, deactivation, and uninstall with retain-data as the default
- Admin views: What Matters Now, Schema, Advanced, Product, Category, Diagnostics, and Setup
- Core SEO metadata, sitemaps, schema rules, redirects, the 404 monitor, and the batched crawler
- WooCommerce product, category, brand, facet, and bulk SEO through WooCommerce APIs, including HPOS order reads
- Keyword, SERP, rank, competitor, backlink, content, opportunity, recommendation, experiment, alert, and report modules
- AI visibility and page-experience storage behind provider interfaces
- CSV and JSON reports
- Diagnostics and `wp querynova`
- Safe mode, staging identifier warning, and SEO plugin conflict notice
- Import from supplied Yoast, Rank Math, and AIOSEO meta
- Site Health registration of one direct QueryNova health test

Connected Search Console, GA4, SERP, backlink, page-experience, and model adapters are null until a provider is configured. Setup can store property strings. It does not connect a provider.

### Packages

`npm run build` writes the production admin bundle with diagnostics off, debug off, and no source map. `npm run build:staging` writes the staging bundle with diagnostics on and debug off. `npm run package` and `npm run package:staging` write `dist/querynova-production.zip` and `dist/querynova-staging.zip`.

### Data

Upgrading runs pending migrations through activation or `wp querynova migrate`. Deactivation does not delete stored options. Uninstall deletes QueryNova tables only when `querynova_delete_data_on_uninstall` is `yes`.
