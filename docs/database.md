# Database

Large and time-series data lives in custom tables, not in serialized `wp_options` blobs. Per-post SEO fields will use post meta. Analytics, SERP, jobs, and logs use the tables below.

The physical name is `{wpdb prefix}qn_{name}`. Plugin version and schema version are separate. The schema version is stored in the `querynova_db_version` option.

## Tables required by the specification

`queries`, `keywords`, `keyword_clusters`, `pages`, `products`, `categories`, `query_pages`, `serp_snapshots`, `serp_results`, `rank_history`, `competitors`, `backlink_snapshots`, `gsc_metrics`, `ga_metrics`, `commerce_metrics`, `product_metrics`, `category_metrics`, `revenue_metrics`, `content_analysis`, `entities`, `internal_links`, `ai_prompts`, `ai_runs`, `ai_mentions`, `issues`, `recommendations`, `actions`, `experiments`, `jobs`, `audit_log`.

## Additional operational tables

`keyword_cluster_members`, `entity_relations`, `logs`, `redirects`, `not_found`, `alerts`, `migrations`, `provider_usage`.

Indexes cover date, product, category, query, keyword, url hash, domain, provider, source, status, and environment where those columns exist. Metric grains use unique keys so retries update one row instead of inserting duplicates. Nullable measures stay null when the provider did not return data.

Raw metric tables are not overwritten with derived scores. Derived values carry methodology and methodology version.

Revenue columns are split into measured, attributed, and estimated. A query-level revenue figure is not stored as measured unless the source actually measured it.

Uninstall keeps these tables unless `querynova_delete_data_on_uninstall` is `yes`.
