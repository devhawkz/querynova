# Debugging

1. Confirm `wp_get_environment_type()`.
2. Open QueryNova diagnostics once that screen is available, or call `wp querynova diagnostics` once the CLI command is registered.
3. Search logs by error reference (`QN-` plus 8 hex characters), correlation id, request id, channel, or module.
4. If a background job failed, inspect `{prefix}qn_jobs`. Validation and authentication failures are not retried. Timeouts and rate limits are.
5. Provider data that is missing is `UNAVAILABLE`. Do not treat that as zero.
6. Safe mode keeps core, settings, diagnostics, and logs. Define `QUERYNOVA_SAFE_MODE` as true in `wp-config.php`, set the `querynova_safe_mode` option to `yes`, or use WP-CLI when that command exists.
7. Production does not show stack traces. Use the error reference to find the log line.
8. React admin errors, when the admin app is built, stay inside an error boundary with the same error reference and a retry action.

Temporary production debug durations are only 15 minutes, 1 hour, 6 hours, and 24 hours. The level returns to INFO when the window ends.
