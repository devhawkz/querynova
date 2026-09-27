# Release environments

Pavle chose Option B. The record is `docs/adr/ADR-008-release-environments.md`.

The Definition of Done still includes an end-to-end browser pass, a real WordPress release boot, and a real WooCommerce release boot. Those runs happen on GitHub Actions, with MySQL, the WordPress test library, WooCommerce, and a browser driver on the runner. This Mac does not install those tools.

A workflow entry is not a passing run. The three runs stay untested until the jobs have executed and passed. No decision is pending for this choice.
