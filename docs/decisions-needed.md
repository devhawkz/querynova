# Release environments

Pavle chose Option B. The record is `docs/adr/ADR-008-release-environments.md`.

Those runs happen on GitHub Actions, with MySQL, the WordPress test library, WooCommerce, and a browser driver on the runner. This Mac does not install those tools.

Actions run 36388914008 on commit `dbe8ec5edaed5782802597d17b3a1394c0fff0a5` passed `wordpress` for WordPress 7.1.2, `woocommerce` for WooCommerce 11.1.2 on that WordPress, and `admin-browser`. The support matrix records those three results. No decision is pending for this choice.
