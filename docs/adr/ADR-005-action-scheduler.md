# ADR-005: Action Scheduler

## Status

Accepted

## Decision

Use Action Scheduler as the primary job runner and WP-Cron every 15 minutes as the fallback that drains `{prefix}qn_jobs`. The plugin loads Action Scheduler from Composer only when WordPress is running and the library is not already present.

## Consequences

WooCommerce and QueryNova share one Action Scheduler instead of two prefixed copies. A site without WooCommerce still gets the bundled library. Job state that operators need to inspect lives in QueryNova's own job table, including when Action Scheduler fires the hook.
