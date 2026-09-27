# Modules

| Module | Status | Role |
| --- | --- | --- |
| core | Implemented | Bootstrap, settings boundary, status route, initial migration, capabilities |
| seo | Implemented | Titles, descriptions, canonical, robots, Open Graph, Twitter/X |
| technical-seo | Not started | Indexability and technical issues |
| schema | Implemented | Connected JSON-LD graph and schema builder |
| sitemap | Implemented | XML sitemaps. News is optional |
| redirects | Implemented | Redirects and 404 monitor. Misses are not auto-redirected |
| crawler | Implemented | Bounded internal crawl in batches. A public request does not crawl the site |
| keywords | Implemented | Research, discovery, clusters, gap, and estimated organic difficulty |
| serp | Implemented | Provider-backed SERP snapshots, page types, and stability |
| rankings | Implemented | Rank history from stored snapshots. A missing rank is null |
| competitors | Implemented | Competitor rows from stored SERP snapshots. Missing authority stays null |
| backlinks | Implemented | Provider-backed snapshots, new and lost links, and the gap |
| content-intelligence | Implemented | Supplied-document coverage, information gain, and entities. No content score |
| internal-links | Implemented | Link graph and commerce suggestions from supplied edges. Suggestions are not inserted |
| search-console | Implemented | Provider-backed search rows. A missing provider is unavailable, not zero |
| analytics | Implemented | GA4 and commerce aggregates behind providers. Query revenue is not assigned without a join |
| woocommerce | Implemented | Product, category, brand, facet, and merchant SEO through a WooCommerce gateway |
| commerce-analytics | Implemented | Orders, revenue, profit, and stock notes. Cost is not invented |
| ai-visibility | Implemented | Provider observations, crawler audit, and an observational index with the required disclaimer |
| page-experience | Implemented | Provider timings for desktop and mobile. Not an SEO score |
| opportunities | Implemented | Supplied-input opportunities. No numeric score. Missing money stays null |
| recommendations | Implemented | Today's suggested actions. Accepted and measured outcomes do not change the page by themselves |
| experiments | Implemented | Before/after movement. Causation is not claimed |
| reports | Not started | CSV, JSON, PDF |
| diagnostics | Not started | Health, logs, jobs, system report |
| audit | Implemented | Change history separate from operational logs. Safe rollback is title and description only |

Status values in `docs/implementation-status.md` are authoritative. This table is the map.
