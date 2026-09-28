<?php
/**
 * On-page checklist and the background SEO audit.
 *
 * The audit request only enqueues stored work. It does not fetch a URL.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Modules\Crawler\Infrastructure\CrawlPageStore;
use QueryNova\Modules\Seo\Application\MetaDefaults;
use QueryNova\Modules\Seo\Application\OnPageChecklist;
use QueryNova\Modules\Seo\Application\SeoAnalyzer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OnPageController {

    public const AUDIT_OPTION = 'querynova_seo_audit';

    public function __construct( private readonly ?JobRunner $runner = null ) {
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function checklist( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            return new \WP_Error( 'querynova_invalid_checklist', 'A document payload is required.', [ 'status' => 400 ] );
        }
        unset( $params['url'], $params['urls'] );

        return OnPageChecklist::assess( $params );
    }

    /**
     * @return array<string, mixed>
     */
    public function templates( \WP_REST_Request $request ): array {
        unset( $request );

        return MetaDefaults::read();
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function saveTemplates( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            return new \WP_Error( 'querynova_invalid_templates', 'Template settings are required.', [ 'status' => 400 ] );
        }
        unset( $params['object_id'], $params['object_ids'], $params['posts'] );

        return MetaDefaults::save( $params );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function startAudit( \WP_REST_Request $request ): array|\WP_Error {
        unset( $request );
        if ( ! $this->runner instanceof JobRunner ) {
            return new \WP_Error( 'querynova_audit_unavailable', 'The SEO audit queue is unavailable.', [ 'status' => 500 ] );
        }
        try {
            $key  = 'seo-audit-' . bin2hex( random_bytes( 8 ) );
            $plan = SeoAnalyzer::plan( $key );
            $id   = $this->runner->enqueue( $plan['type'], $plan['payload'], $plan['idempotency_key'] );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_audit', $exception->getMessage(), [ 'status' => 400 ] );
        }

        return [
            'job_id' => $id,
            'status' => 'queued',
            'note'   => $plan['note'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showAudit( \WP_REST_Request $request ): array {
        unset( $request );
        $stored = get_option( self::AUDIT_OPTION, null );
        if ( ! is_array( $stored ) ) {
            return [
                'name'     => 'SEO Analyzer',
                'note'     => 'These findings do not estimate ranking impact.',
                'status'   => 'unavailable',
                'findings' => [],
            ];
        }

        return $stored;
    }

    /**
     * Background handler. The payload is ignored so a URL cannot start a fetch.
     *
     * @param array<string, mixed> $payload
     */
    public function runAudit( array $payload ): void {
        unset( $payload );
        $pages   = null;
        $summary = get_option( CrawlPageStore::SUMMARY, null );
        if ( is_array( $summary ) && isset( $summary['pages_crawled'] ) && is_numeric( $summary['pages_crawled'] ) ) {
            $pages = (int) $summary['pages_crawled'];
        }
        $report = SeoAnalyzer::report( $this->storedIssues(), $pages );
        update_option( self::AUDIT_OPTION, $report, false );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function storedIssues(): array {
        if ( ! isset( $GLOBALS['wpdb'] ) ) {
            return [];
        }
        try {
            $database = new WpdbConnection();
            $rows     = $database->select( $database->prefix() . 'qn_issues', [ 'module' => 'crawler' ], 100 );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return [];
        }
        $issues = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $details  = json_decode( (string) ( $row['details_json'] ?? '' ), true );
            $issues[] = [
                'code'     => is_string( $row['issue_code'] ?? null ) ? $row['issue_code'] : '',
                'severity' => is_string( $row['severity'] ?? null ) ? $row['severity'] : 'info',
                'title'    => is_string( $row['title'] ?? null ) ? $row['title'] : '',
                'url'      => is_array( $details ) && is_string( $details['url'] ?? null ) ? $details['url'] : '',
                'detail'   => is_array( $details ) && is_string( $details['detail'] ?? null ) ? $details['detail'] : '',
            ];
        }

        return $issues;
    }
}
