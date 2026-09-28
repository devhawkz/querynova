# Logging

The logger implements `Psr\Log\LoggerInterface` and supports emergency, alert, critical, error, warning, notice, info, and debug.

## Channels

`core`, `database`, `migrations`, `rest`, `jobs`, `cron`, `providers`, `search_console`, `analytics`, `woocommerce`, `commerce`, `keywords`, `serp`, `rankings`, `backlinks`, `content`, `ai`, `recommendations`, `security`, `performance`.

## Record

Each record can carry timestamp, level, channel, message, environment, plugin version, WordPress version, WooCommerce version, request id, correlation id, job id, module, provider, context, exception class, exception code, and error reference. The record `environment` field is the WordPress environment. Context also includes `wordpress_environment`, `querynova_build`, `release_channel`, `release_status`, and `release_notice` when the build and the WordPress environment do not match.

Correlation ids are UUID v4 values. Error references look like `QN-AB12CD34` and are what the admin shows instead of a raw exception.

## Redaction

`LogSanitizer` removes API keys, tokens, authorization headers, cookies, passwords, emails, customer names, addresses, and payment fields before a handler stores the record. Product filenames and SKUs are not treated as secrets.

## Handlers

- `FileLogHandler` writes JSON lines under the uploads `querynova/logs` directory and rotates at 5 MB, keeping 5 files.
- `DatabaseLogHandler` stores records in `{prefix}qn_logs` for the log viewer.
- `WordPressDebugHandler` writes `error_log` outside production.
- `NullHandler` discards records.
- `FutureCloudLogHandler` is present and inactive until a cloud adapter is enabled. Core SEO does not depend on it.

Production DEBUG is off unless an administrator enables a temporary window. Retention defaults are 14 days in production, 30 days in staging, and 7 days in local and development.
