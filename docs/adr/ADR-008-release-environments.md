# ADR-008: Release environments run on CI

## Status

Accepted

## Decision

Pavle chose Option B. A real WordPress release, a real WooCommerce release, and an end-to-end browser pass of the admin app run on a GitHub Actions runner. This Mac does not get Docker, WordPress, MySQL, or a browser installed for those runs.

The runner provides MySQL, the WordPress test library, the WooCommerce plugin, and a browser driver. The existing PHPUnit and Vitest suites stay in place. They do not count as those three runs.

The jobs are `wordpress`, `woocommerce`, and `admin-browser` in `.github/workflows/ci.yml`. A release is recorded as tested only after the job that boots it has passed. Adding the workflow is not a passing run.

## Consequences

The Definition of Done still includes all three runs. QueryNova stays incomplete until those jobs have passed. The support matrix keeps WordPress and WooCommerce observed lists empty until then.
