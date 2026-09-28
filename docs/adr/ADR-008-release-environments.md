# ADR-008: Release environments run on CI

## Status

Accepted

## Decision

Pavle chose Option B. A real WordPress release, a real WooCommerce release, and an end-to-end browser pass of the admin app run on a GitHub Actions runner. This Mac does not get Docker, WordPress, MySQL, or a browser installed for those runs.

The runner provides MySQL, the WordPress test library, the WooCommerce plugin, and a browser driver. The existing PHPUnit and Vitest suites stay in place. They do not count as those three runs.

The jobs are `wordpress`, `woocommerce`, and `admin-browser` in `.github/workflows/ci.yml`. A release is recorded as tested only after the job that boots it has passed. Adding the workflow is not a passing run.

## Consequences

Actions run 36388914008 on commit `dbe8ec5edaed5782802597d17b3a1394c0fff0a5` passed `wordpress`, `woocommerce`, and `admin-browser`. The support matrix records WordPress 7.1.2, WooCommerce 11.1.2 on that WordPress, and that admin browser pass. QueryNova stays incomplete while other Definition of Done items remain open. See `docs/implementation-status.md`.
