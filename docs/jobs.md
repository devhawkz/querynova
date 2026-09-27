# Jobs

Action Scheduler is the primary runner when `as_enqueue_async_action` exists (bundled under `vendor/woocommerce/action-scheduler`, or already loaded by WooCommerce). The fallback is WP-Cron on the `querynova_quarter_hour` schedule, which processes due rows in `{prefix}qn_jobs`.

## Record

Each job stores id, type, payload, priority, attempt, max attempts, status, correlation id, idempotency key, scheduled time, start, completion, last error, and error reference.

## States

`PENDING`, `RUNNING`, `COMPLETED`, `FAILED`, `RETRYING`, `CANCELLED`, `DEAD`.

## Retries

Network timeouts and rate limits retry with exponential backoff. Rate limits honor the provider retry-after value. Invalid credentials and validation errors go to `DEAD` and are not retried forever. Creating a job with an existing idempotency key returns the original id.

Claiming a job updates it only while it is `PENDING` or `RETRYING`, so a second worker does not run the same row twice in a single process. Payload secrets are redacted before they are shown in logs.

SERP, Search Console, GA4, crawls, backlinks, AI prompts, recommendations, bulk analysis, analytics aggregation, and large migrations must be jobs. They must not run inside a public frontend request.
