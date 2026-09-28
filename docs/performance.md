# Performance

This document records what QueryNova 0.1.0 measures about its own work, and what those measurements are. It is not a production timing guarantee.

## Synthetic catalog

`tests/Performance/CatalogScaleTest.php` builds an in-memory catalog of 1,000, 10,000, and 100,000 product rows. For each size the test asserts:

- A page read returns 20 rows.
- The admin product screen resolves one stored product, the last inserted SKU.
- The screen’s tab items stay under 100.
- That in-memory store records zero raw SQL statements.
- The memory increase for the insert, page, and screen stays under 256 MB.
- The elapsed time for that work stays under 60 seconds.

Those ceilings are test bounds on `ArrayDatabase`. They were part of the default PHPUnit suite on the `php (8.1)` and `php (8.3)` jobs in [Actions run 36388914008](https://github.com/devhawkz/querynova/actions/runs/36388914008), commit `dbe8ec5edaed5782802597d17b3a1394c0fff0a5`. They were not measured against the MySQL database of the WordPress 7.1.2 boot or the WooCommerce 11.1.2 boot.

## Queue batches

The same test enqueues 200 jobs and runs one batch of 20. 180 jobs stay pending. A public request does not drain the queue.

Crawl, analytics sync, SERP refresh, rank refresh, backlink refresh, and AI observation jobs are enqueued. The request that enqueues them does not fetch the provider or the site. WP-CLI `crawl run` and `analytics sync` follow the same rule. `jobs retry` requeues. It does not run the jobs.

A crawl batch is capped at 100 pages.

## Other bounds in the product

- SEO import copies 50 posts or terms at a time.
- What Matters Now lists at most ten actions and five rows in each section.
- Advanced lists at most twenty rows in each section.
- File logs rotate at 5 MB and keep five files under the uploads `querynova/logs` directory.
- Log retention defaults are 14 days in production, 30 days in staging, and 7 days in local and development.
- A catalog of 100,000 products is not loaded into one PHP array by the product screen. The screen reads the latest stored product.

## What the release boots measured

The WordPress 7.1.2 job activated QueryNova with the WordPress test library. The WooCommerce 11.1.2 job activated WooCommerce and QueryNova on that WordPress. The admin browser job opened the QueryNova admin and checked the What Matters Now heading. Those jobs did not publish page-load budgets, query counts on MySQL, or crawler throughput.

## What is left empty on purpose

Page experience stores LCP, INP, CLS, and TTFB only when a provider supplies them. A missing timing stays empty. QueryNova does not compute a performance score or an SEO score. A page under 100 words is the crawler’s thin-page rule, not a search-engine score.
