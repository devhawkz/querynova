<?php
/**
 * Versioned table definitions. Names match the product specification.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Schema {

    /**
     * Tables required by the specification, plus the operational tables
     * redirects, not-found, logs, alerts, migrations, cluster membership,
     * entity relations, and provider usage.
     *
     * @return list<string>
     */
    public static function requiredTables(): array {
        return [
            'queries',
            'keywords',
            'keyword_clusters',
            'pages',
            'products',
            'categories',
            'query_pages',
            'serp_snapshots',
            'serp_results',
            'rank_history',
            'competitors',
            'backlink_snapshots',
            'gsc_metrics',
            'ga_metrics',
            'commerce_metrics',
            'product_metrics',
            'category_metrics',
            'revenue_metrics',
            'content_analysis',
            'entities',
            'internal_links',
            'ai_prompts',
            'ai_runs',
            'ai_mentions',
            'issues',
            'recommendations',
            'actions',
            'experiments',
            'jobs',
            'audit_log',
        ];
    }

    /**
     * @return list<string>
     */
    public function statements( string $prefix, string $charsetCollate ): array {
        $statements = [];
        foreach ( $this->definitions() as $name => $definition ) {
            $statements[] = $this->compile( $prefix . 'qn_' . $name, $definition, $charsetCollate );
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    public function tableNames(): array {
        return array_keys( $this->definitions() );
    }

    public function statementFor( string $prefix, string $charsetCollate, string $name ): string {
        $definitions = $this->definitions();
        if ( ! isset( $definitions[ $name ] ) ) {
            throw new \InvalidArgumentException( 'Unknown table.' );
        }

        return $this->compile( $prefix . 'qn_' . $name, $definitions[ $name ], $charsetCollate );
    }

    /**
     * @param array{columns: list<string>, keys: list<string>} $definition
     */
    private function compile( string $table, array $definition, string $charsetCollate ): string {
        $lines = array_merge( $definition['columns'], $definition['keys'] );

        return "CREATE TABLE {$table} (\n" . implode( ",\n", $lines ) . "\n) {$charsetCollate}";
    }

    /**
     * @return array<string, array{columns: list<string>, keys: list<string>}>
     */
    private function definitions(): array {
        $provenance = [
            'source varchar(32) NOT NULL',
            'provider varchar(64) NOT NULL DEFAULT \'\'',
            'confidence decimal(6,4) NULL',
            'methodology varchar(64) NULL',
            'methodology_version varchar(16) NULL',
        ];
        $timestamps = [
            'created_at datetime NOT NULL',
            'updated_at datetime NOT NULL',
        ];

        return [
            'queries'                 => [
                'columns' => array_merge(
                    [
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
						'query_hash char(64) NOT NULL',
						'query_text text NOT NULL',
						'language varchar(16) NOT NULL DEFAULT \'\'',
						'country varchar(8) NOT NULL DEFAULT \'\'',
						'intent varchar(64) NULL',
						'intent_secondary varchar(64) NULL',
						'intent_confidence decimal(6,4) NULL',
					],
                    $provenance,
                    $timestamps
                ),
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY query_hash (query_hash)',
                    'KEY intent (intent)',
                    'KEY source (source)',
                ],
            ],
            'keywords'                => [
                'columns' => array_merge(
                    [
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
						'query_id bigint(20) unsigned NOT NULL DEFAULT 0',
						'keyword_hash char(64) NOT NULL',
						'keyword varchar(191) NOT NULL',
						'country varchar(8) NOT NULL DEFAULT \'\'',
						'language varchar(16) NOT NULL DEFAULT \'\'',
						'device varchar(16) NOT NULL DEFAULT \'desktop\'',
						'volume int(11) NULL',
						'cpc decimal(10,4) NULL',
						'paid_competition decimal(6,4) NULL',
						'organic_difficulty tinyint(3) unsigned NULL',
						'trend_json longtext NULL',
						'traffic_potential int(11) NULL',
						'business_value varchar(16) NOT NULL DEFAULT \'\'',
						'commerce_potential varchar(16) NOT NULL DEFAULT \'\'',
						'observed_at datetime NULL',
					],
                    $provenance,
                    $timestamps
                ),
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY keyword_lookup (keyword_hash, country, language, device)',
                    'KEY query_id (query_id)',
                    'KEY keyword (keyword)',
                ],
            ],
            'keyword_clusters'        => [
                'columns' => array_merge(
                    [
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
						'name varchar(191) NOT NULL',
						'primary_keyword_id bigint(20) unsigned NOT NULL DEFAULT 0',
						'intent varchar(64) NULL',
						'recommended_page_type varchar(32) NULL',
						'volume int(11) NULL',
						'traffic_potential int(11) NULL',
					],
                    $provenance,
                    $timestamps
                ),
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY primary_keyword_id (primary_keyword_id)',
                ],
            ],
            'keyword_cluster_members' => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'cluster_id bigint(20) unsigned NOT NULL',
                    'keyword_id bigint(20) unsigned NOT NULL',
                    'created_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY membership (cluster_id, keyword_id)',
                    'KEY keyword_id (keyword_id)',
                ],
            ],
            'pages'                   => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'object_type varchar(32) NOT NULL',
                    'object_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'url text NOT NULL',
                    'url_hash char(64) NOT NULL',
                    'indexability varchar(32) NOT NULL DEFAULT \'unknown\'',
                    'canonical text NULL',
                    'http_status smallint(5) unsigned NULL',
                    'last_crawled_at datetime NULL',
                    'created_at datetime NOT NULL',
                    'updated_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY url_hash (url_hash)',
                    'KEY object_lookup (object_type, object_id)',
                    'KEY indexability (indexability)',
                ],
            ],
            'products'                => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'product_id bigint(20) unsigned NOT NULL',
                    'page_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'sku varchar(100) NOT NULL DEFAULT \'\'',
                    'brand varchar(191) NOT NULL DEFAULT \'\'',
                    'gtin varchar(64) NOT NULL DEFAULT \'\'',
                    'mpn varchar(64) NOT NULL DEFAULT \'\'',
                    'price decimal(18,4) NULL',
                    'currency varchar(8) NOT NULL DEFAULT \'\'',
                    'availability varchar(32) NOT NULL DEFAULT \'\'',
                    'stock_status varchar(32) NOT NULL DEFAULT \'\'',
                    'stock_qty decimal(18,4) NULL',
                    'cost decimal(18,4) NULL',
                    'margin decimal(8,4) NULL',
                    'indexability varchar(32) NOT NULL DEFAULT \'unknown\'',
                    'primary_keyword_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'created_at datetime NOT NULL',
                    'updated_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY product_id (product_id)',
                    'KEY page_id (page_id)',
                    'KEY stock_status (stock_status)',
                    'KEY brand (brand)',
                ],
            ],
            'categories'              => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'term_id bigint(20) unsigned NOT NULL',
                    'page_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'product_count int(11) NOT NULL DEFAULT 0',
                    'primary_keyword_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'created_at datetime NOT NULL',
                    'updated_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY term_id (term_id)',
                    'KEY page_id (page_id)',
                ],
            ],
            'query_pages'             => [
                'columns' => array_merge(
                    [
						'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
						'query_id bigint(20) unsigned NOT NULL',
						'page_id bigint(20) unsigned NOT NULL',
						'position decimal(8,2) NULL',
						'impressions int(11) NOT NULL DEFAULT 0',
						'clicks int(11) NOT NULL DEFAULT 0',
						'ctr decimal(8,6) NULL',
						'metric_date date NOT NULL',
                    ],
                    $provenance,
                    [
						'created_at datetime NOT NULL',
					]
                ),
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY query_page_day (query_id, page_id, metric_date, source)',
                    'KEY page_id (page_id)',
                    'KEY metric_date (metric_date)',
                ],
            ],
            'serp_snapshots'          => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'keyword_id bigint(20) unsigned NOT NULL',
                    'location varchar(64) NOT NULL DEFAULT \'\'',
                    'country varchar(8) NOT NULL DEFAULT \'\'',
                    'language varchar(16) NOT NULL DEFAULT \'\'',
                    'device varchar(16) NOT NULL DEFAULT \'desktop\'',
                    'captured_at datetime NOT NULL',
                    'provider varchar(64) NOT NULL DEFAULT \'\'',
                    'status varchar(32) NOT NULL',
                    'features_json longtext NULL',
                    'stability varchar(32) NULL',
                    'correlation_id char(36) NOT NULL DEFAULT \'\'',
                    'source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY keyword_id (keyword_id)',
                    'KEY captured_at (captured_at)',
                    'KEY provider (provider)',
                    'KEY status (status)',
                ],
            ],
            'serp_results'            => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'snapshot_id bigint(20) unsigned NOT NULL',
                    'position smallint(5) unsigned NOT NULL',
                    'url text NOT NULL',
                    'url_hash char(64) NOT NULL',
                    'domain varchar(191) NOT NULL DEFAULT \'\'',
                    'title text NULL',
                    'snippet text NULL',
                    'page_type varchar(32) NOT NULL DEFAULT \'\'',
                    'features_json longtext NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY snapshot_id (snapshot_id)',
                    'KEY domain (domain)',
                    'KEY url_hash (url_hash)',
                    'KEY page_type (page_type)',
                ],
            ],
            'rank_history'            => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'keyword_id bigint(20) unsigned NOT NULL',
                    'page_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'position decimal(8,2) NULL',
                    'url text NULL',
                    'country varchar(8) NOT NULL DEFAULT \'\'',
                    'device varchar(16) NOT NULL DEFAULT \'desktop\'',
                    'language varchar(16) NOT NULL DEFAULT \'\'',
                    'captured_at datetime NOT NULL',
                    'provider varchar(64) NOT NULL DEFAULT \'\'',
                    'source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY keyword_captured (keyword_id, captured_at)',
                    'KEY page_id (page_id)',
                    'KEY provider (provider)',
                ],
            ],
            'competitors'             => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'domain varchar(191) NOT NULL',
                    'url text NULL',
                    'keyword_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'page_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'authority decimal(8,2) NULL',
                    'referring_domains int(11) NULL',
                    'metrics_json longtext NULL',
                    'source varchar(32) NOT NULL',
                    'provider varchar(64) NOT NULL DEFAULT \'\'',
                    'observed_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY domain (domain)',
                    'KEY keyword_id (keyword_id)',
                    'KEY page_id (page_id)',
                ],
            ],
            'backlink_snapshots'      => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'target_url text NOT NULL',
                    'target_hash char(64) NOT NULL',
                    'source_url text NOT NULL',
                    'source_hash char(64) NOT NULL',
                    'source_domain varchar(191) NOT NULL DEFAULT \'\'',
                    'anchor text NULL',
                    'rel varchar(32) NOT NULL DEFAULT \'\'',
                    'first_seen datetime NULL',
                    'last_seen datetime NULL',
                    'provider varchar(64) NOT NULL DEFAULT \'\'',
                    'authority decimal(8,2) NULL',
                    'link_status varchar(16) NOT NULL DEFAULT \'active\'',
                    'source varchar(32) NOT NULL',
                    'observed_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY link_identity (target_hash, source_hash, provider)',
                    'KEY source_domain (source_domain)',
                    'KEY target_hash (target_hash)',
                    'KEY link_status (link_status)',
                ],
            ],
            'gsc_metrics'             => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'metric_date date NOT NULL',
                    'page_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'query_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'country varchar(8) NOT NULL DEFAULT \'\'',
                    'device varchar(16) NOT NULL DEFAULT \'\'',
                    'clicks int(11) NOT NULL DEFAULT 0',
                    'impressions int(11) NOT NULL DEFAULT 0',
                    'ctr decimal(8,6) NULL',
                    'position decimal(8,2) NULL',
                    'property varchar(191) NOT NULL DEFAULT \'\'',
                    'source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY gsc_grain (metric_date, page_id, query_id, country, device)',
                    'KEY page_id (page_id)',
                    'KEY query_id (query_id)',
                    'KEY metric_date (metric_date)',
                ],
            ],
            'ga_metrics'              => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'metric_date date NOT NULL',
                    'page_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'sessions int(11) NOT NULL DEFAULT 0',
                    'users int(11) NOT NULL DEFAULT 0',
                    'organic_sessions int(11) NOT NULL DEFAULT 0',
                    'engaged_sessions int(11) NOT NULL DEFAULT 0',
                    'engagement_rate decimal(8,6) NULL',
                    'avg_engagement_time decimal(10,2) NULL',
                    'key_events int(11) NOT NULL DEFAULT 0',
                    'revenue decimal(18,4) NULL',
                    'source varchar(64) NOT NULL DEFAULT \'\'',
                    'medium varchar(64) NOT NULL DEFAULT \'\'',
                    'data_source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY ga_grain (metric_date, page_id, source, medium)',
                    'KEY page_id (page_id)',
                    'KEY metric_date (metric_date)',
                ],
            ],
            'commerce_metrics'        => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'metric_date date NOT NULL',
                    'product_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'category_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'views int(11) NOT NULL DEFAULT 0',
                    'add_to_cart int(11) NOT NULL DEFAULT 0',
                    'checkouts int(11) NOT NULL DEFAULT 0',
                    'orders int(11) NOT NULL DEFAULT 0',
                    'revenue decimal(18,4) NOT NULL DEFAULT 0',
                    'refunds decimal(18,4) NOT NULL DEFAULT 0',
                    'aov decimal(18,4) NULL',
                    'quantity decimal(18,4) NOT NULL DEFAULT 0',
                    'conversion_rate decimal(8,6) NULL',
                    'source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY commerce_grain (metric_date, product_id, category_id, source)',
                    'KEY product_id (product_id)',
                    'KEY category_id (category_id)',
                    'KEY metric_date (metric_date)',
                ],
            ],
            'product_metrics'         => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'metric_date date NOT NULL',
                    'product_id bigint(20) unsigned NOT NULL',
                    'impressions int(11) NULL',
                    'clicks int(11) NULL',
                    'position decimal(8,2) NULL',
                    'organic_sessions int(11) NULL',
                    'views int(11) NULL',
                    'add_to_cart int(11) NULL',
                    'orders int(11) NULL',
                    'cvr decimal(8,6) NULL',
                    'revenue decimal(18,4) NULL',
                    'refund_adjusted_revenue decimal(18,4) NULL',
                    'margin decimal(8,4) NULL',
                    'profit decimal(18,4) NULL',
                    'ai_referrals int(11) NULL',
                    'opportunity decimal(8,2) NULL',
                    'provenance_json longtext NULL',
                    'source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY product_day (metric_date, product_id, source)',
                    'KEY product_id (product_id)',
                    'KEY metric_date (metric_date)',
                ],
            ],
            'category_metrics'        => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'metric_date date NOT NULL',
                    'category_id bigint(20) unsigned NOT NULL',
                    'impressions int(11) NULL',
                    'clicks int(11) NULL',
                    'ctr decimal(8,6) NULL',
                    'position decimal(8,2) NULL',
                    'sessions int(11) NULL',
                    'orders int(11) NULL',
                    'revenue decimal(18,4) NULL',
                    'cvr decimal(8,6) NULL',
                    'aov decimal(18,4) NULL',
                    'margin decimal(8,4) NULL',
                    'opportunity decimal(8,2) NULL',
                    'provenance_json longtext NULL',
                    'source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY category_day (metric_date, category_id, source)',
                    'KEY category_id (category_id)',
                    'KEY metric_date (metric_date)',
                ],
            ],
            'revenue_metrics'         => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'metric_date date NOT NULL',
                    'scope varchar(32) NOT NULL',
                    'scope_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'measured_revenue decimal(18,4) NULL',
                    'attributed_revenue decimal(18,4) NULL',
                    'estimated_revenue decimal(18,4) NULL',
                    'methodology varchar(64) NULL',
                    'methodology_version varchar(16) NULL',
                    'confidence decimal(6,4) NULL',
                    'confidence_band varchar(16) NOT NULL DEFAULT \'UNKNOWN\'',
                    'currency varchar(8) NOT NULL DEFAULT \'\'',
                    'source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY revenue_grain (metric_date, scope, scope_id, source)',
                    'KEY scope (scope, scope_id)',
                    'KEY metric_date (metric_date)',
                ],
            ],
            'content_analysis'        => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'page_id bigint(20) unsigned NOT NULL',
                    'analyzed_at datetime NOT NULL',
                    'completeness decimal(6,4) NULL',
                    'topics_json longtext NULL',
                    'entities_json longtext NULL',
                    'information_gain decimal(6,4) NULL',
                    'eeat_json longtext NULL',
                    'methodology varchar(64) NULL',
                    'methodology_version varchar(16) NULL',
                    'confidence decimal(6,4) NULL',
                    'source varchar(32) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY page_analyzed (page_id, analyzed_at)',
                ],
            ],
            'entities'                => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'entity_type varchar(32) NOT NULL',
                    'name varchar(191) NOT NULL',
                    'name_hash char(64) NOT NULL',
                    'external_id varchar(64) NOT NULL DEFAULT \'\'',
                    'attributes_json longtext NULL',
                    'created_at datetime NOT NULL',
                    'updated_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY entity_identity (entity_type, name_hash)',
                    'KEY entity_type (entity_type)',
                ],
            ],
            'entity_relations'        => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'from_entity_id bigint(20) unsigned NOT NULL',
                    'to_entity_id bigint(20) unsigned NOT NULL',
                    'relation_type varchar(32) NOT NULL',
                    'created_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY relation_identity (from_entity_id, to_entity_id, relation_type)',
                    'KEY to_entity_id (to_entity_id)',
                ],
            ],
            'internal_links'          => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'source_page_id bigint(20) unsigned NOT NULL',
                    'target_page_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'target_url text NULL',
                    'anchor text NULL',
                    'rel varchar(32) NOT NULL DEFAULT \'\'',
                    'discovered_at datetime NOT NULL',
                    'link_status varchar(16) NOT NULL DEFAULT \'ok\'',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY source_page_id (source_page_id)',
                    'KEY target_page_id (target_page_id)',
                    'KEY link_status (link_status)',
                ],
            ],
            'ai_prompts'              => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'prompt longtext NOT NULL',
                    'locale varchar(16) NOT NULL DEFAULT \'\'',
                    'provider varchar(64) NOT NULL DEFAULT \'\'',
                    'model varchar(64) NOT NULL DEFAULT \'\'',
                    'active tinyint(1) NOT NULL DEFAULT 1',
                    'created_at datetime NOT NULL',
                    'updated_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY active (active)',
                    'KEY provider (provider)',
                ],
            ],
            'ai_runs'                 => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'prompt_id bigint(20) unsigned NOT NULL',
                    'provider varchar(64) NOT NULL',
                    'model varchar(64) NOT NULL DEFAULT \'\'',
                    'ran_at datetime NOT NULL',
                    'status varchar(32) NOT NULL',
                    'response_excerpt text NULL',
                    'brand_mentioned tinyint(1) NOT NULL DEFAULT 0',
                    'correlation_id char(36) NOT NULL DEFAULT \'\'',
                    'error_reference varchar(16) NOT NULL DEFAULT \'\'',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY prompt_id (prompt_id)',
                    'KEY ran_at (ran_at)',
                    'KEY status (status)',
                    'KEY provider (provider)',
                ],
            ],
            'ai_mentions'             => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'run_id bigint(20) unsigned NOT NULL',
                    'mention_type varchar(32) NOT NULL',
                    'name varchar(191) NOT NULL DEFAULT \'\'',
                    'url text NULL',
                    'domain varchar(191) NOT NULL DEFAULT \'\'',
                    'cited tinyint(1) NOT NULL DEFAULT 0',
                    'created_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY run_id (run_id)',
                    'KEY mention_type (mention_type)',
                    'KEY domain (domain)',
                ],
            ],
            'issues'                  => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'module varchar(64) NOT NULL',
                    'object_type varchar(32) NOT NULL',
                    'object_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'issue_code varchar(64) NOT NULL',
                    'severity varchar(16) NOT NULL',
                    'title varchar(191) NOT NULL',
                    'details_json longtext NULL',
                    'status varchar(16) NOT NULL DEFAULT \'open\'',
                    'first_seen datetime NOT NULL',
                    'last_seen datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY object_lookup (object_type, object_id)',
                    'KEY issue_code (issue_code)',
                    'KEY status (status)',
                    'KEY severity (severity)',
                    'KEY module (module)',
                ],
            ],
            'recommendations'         => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'title varchar(191) NOT NULL',
                    'description text NOT NULL',
                    'url text NULL',
                    'target_query varchar(191) NOT NULL DEFAULT \'\'',
                    'impact varchar(16) NOT NULL',
                    'confidence varchar(16) NOT NULL',
                    'effort varchar(16) NOT NULL',
                    'evidence_json longtext NULL',
                    'data_sources_json longtext NULL',
                    'expected_kpi varchar(64) NOT NULL DEFAULT \'\'',
                    'rationale text NOT NULL',
                    'priority varchar(16) NOT NULL',
                    'status varchar(16) NOT NULL DEFAULT \'suggested\'',
                    'outcome varchar(16) NOT NULL DEFAULT \'\'',
                    'idempotency_key char(64) NOT NULL',
                    'created_at datetime NOT NULL',
                    'updated_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY idempotency_key (idempotency_key)',
                    'KEY priority (priority)',
                    'KEY status (status)',
                ],
            ],
            'actions'                 => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'recommendation_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'user_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'action varchar(64) NOT NULL',
                    'before_json longtext NULL',
                    'after_json longtext NULL',
                    'created_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY recommendation_id (recommendation_id)',
                    'KEY user_id (user_id)',
                    'KEY created_at (created_at)',
                ],
            ],
            'experiments'             => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'name varchar(191) NOT NULL',
                    'experiment_type varchar(64) NOT NULL',
                    'object_type varchar(32) NOT NULL',
                    'object_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'status varchar(16) NOT NULL',
                    'started_at datetime NULL',
                    'ended_at datetime NULL',
                    'before_json longtext NULL',
                    'after_json longtext NULL',
                    'result varchar(32) NOT NULL DEFAULT \'inconclusive\'',
                    'causation_note text NULL',
                    'created_at datetime NOT NULL',
                    'updated_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY object_lookup (object_type, object_id)',
                    'KEY status (status)',
                ],
            ],
            'jobs'                    => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'job_type varchar(64) NOT NULL',
                    'payload longtext NULL',
                    'priority smallint(5) unsigned NOT NULL DEFAULT 10',
                    'attempt smallint(5) unsigned NOT NULL DEFAULT 0',
                    'max_attempts smallint(5) unsigned NOT NULL DEFAULT 5',
                    'status varchar(16) NOT NULL',
                    'correlation_id char(36) NOT NULL DEFAULT \'\'',
                    'idempotency_key char(64) NOT NULL',
                    'scheduled_at datetime NOT NULL',
                    'started_at datetime NULL',
                    'completed_at datetime NULL',
                    'last_error text NULL',
                    'error_reference varchar(16) NOT NULL DEFAULT \'\'',
                    'created_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY idempotency_key (idempotency_key)',
                    'KEY status_schedule (status, scheduled_at)',
                    'KEY job_type (job_type)',
                    'KEY correlation_id (correlation_id)',
                ],
            ],
            'audit_log'               => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'user_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'action varchar(64) NOT NULL',
                    'object_type varchar(32) NOT NULL DEFAULT \'\'',
                    'object_id bigint(20) unsigned NOT NULL DEFAULT 0',
                    'before_json longtext NULL',
                    'after_json longtext NULL',
                    'ip_hash char(64) NOT NULL DEFAULT \'\'',
                    'environment varchar(16) NOT NULL DEFAULT \'\'',
                    'created_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY action (action)',
                    'KEY object_lookup (object_type, object_id)',
                    'KEY created_at (created_at)',
                    'KEY user_id (user_id)',
                ],
            ],
            'logs'                    => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'logged_at datetime NOT NULL',
                    'level varchar(16) NOT NULL',
                    'channel varchar(32) NOT NULL',
                    'message text NOT NULL',
                    'context_json longtext NULL',
                    'environment varchar(16) NOT NULL DEFAULT \'\'',
                    'plugin_version varchar(16) NOT NULL DEFAULT \'\'',
                    'request_id char(36) NOT NULL DEFAULT \'\'',
                    'correlation_id char(36) NOT NULL DEFAULT \'\'',
                    'job_id varchar(32) NOT NULL DEFAULT \'\'',
                    'module varchar(64) NOT NULL DEFAULT \'\'',
                    'provider varchar(64) NOT NULL DEFAULT \'\'',
                    'exception_class varchar(191) NOT NULL DEFAULT \'\'',
                    'exception_code varchar(32) NOT NULL DEFAULT \'\'',
                    'error_reference varchar(16) NOT NULL DEFAULT \'\'',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'KEY level (level)',
                    'KEY channel (channel)',
                    'KEY logged_at (logged_at)',
                    'KEY correlation_id (correlation_id)',
                    'KEY request_id (request_id)',
                    'KEY error_reference (error_reference)',
                    'KEY module (module)',
                    'KEY provider (provider)',
                ],
            ],
            'redirects'               => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'source text NOT NULL',
                    'source_hash char(64) NOT NULL',
                    'target text NOT NULL',
                    'status_code smallint(5) unsigned NOT NULL',
                    'is_regex tinyint(1) NOT NULL DEFAULT 0',
                    'hits bigint(20) unsigned NOT NULL DEFAULT 0',
                    'enabled tinyint(1) NOT NULL DEFAULT 1',
                    'created_at datetime NOT NULL',
                    'updated_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY source_hash (source_hash)',
                    'KEY enabled (enabled)',
                    'KEY status_code (status_code)',
                ],
            ],
            'not_found'               => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'url text NOT NULL',
                    'url_hash char(64) NOT NULL',
                    'hits bigint(20) unsigned NOT NULL DEFAULT 1',
                    'referrer text NULL',
                    'user_agent varchar(191) NOT NULL DEFAULT \'\'',
                    'user_agent_hash char(64) NOT NULL DEFAULT \'\'',
                    'first_seen datetime NOT NULL',
                    'last_seen datetime NOT NULL',
                    'suggested_target text NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY url_hash (url_hash)',
                    'KEY hits (hits)',
                    'KEY last_seen (last_seen)',
                ],
            ],
            'page_experience'         => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'url text NOT NULL',
                    'url_hash char(64) NOT NULL',
                    'strategy varchar(16) NOT NULL',
                    'lcp decimal(10,3) NULL',
                    'inp decimal(10,3) NULL',
                    'cls decimal(8,4) NULL',
                    'ttfb decimal(10,3) NULL',
                    'source varchar(32) NOT NULL',
                    'observed_at datetime NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY url_strategy (url_hash, strategy)',
                    'KEY strategy (strategy)',
                    'KEY observed_at (observed_at)',
                ],
            ],
            'alerts'                  => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'alert_type varchar(64) NOT NULL',
                    'severity varchar(16) NOT NULL',
                    'title varchar(191) NOT NULL',
                    'message text NOT NULL',
                    'status varchar(16) NOT NULL DEFAULT \'open\'',
                    'idempotency_key char(64) NOT NULL',
                    'created_at datetime NOT NULL',
                    'acknowledged_at datetime NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY idempotency_key (idempotency_key)',
                    'KEY status (status)',
                    'KEY alert_type (alert_type)',
                    'KEY created_at (created_at)',
                ],
            ],
            'migrations'              => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'version varchar(32) NOT NULL',
                    'description varchar(191) NOT NULL',
                    'executed_at datetime NOT NULL',
                    'status varchar(16) NOT NULL',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY version (version)',
                ],
            ],
            'provider_usage'          => [
                'columns' => [
                    'id bigint(20) unsigned NOT NULL AUTO_INCREMENT',
                    'usage_date date NOT NULL',
                    'provider varchar(64) NOT NULL',
                    'feature varchar(64) NOT NULL',
                    'units int(11) NOT NULL DEFAULT 0',
                    'estimated_cost decimal(12,4) NULL',
                    'environment varchar(16) NOT NULL DEFAULT \'\'',
                ],
                'keys'    => [
                    'PRIMARY KEY (id)',
                    'UNIQUE KEY usage_grain (usage_date, provider, feature, environment)',
                    'KEY provider (provider)',
                    'KEY usage_date (usage_date)',
                ],
            ],
        ];
    }
}
