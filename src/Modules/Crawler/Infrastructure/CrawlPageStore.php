<?php
/**
 * Writes crawled URLs as each batch finishes. Issues wait until the run is done.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Crawler\Application\CrawlAnalyzer;
use QueryNova\Modules\Crawler\Application\CrawlRun;
use QueryNova\Modules\Crawler\Domain\PageObservation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CrawlPageStore {

    public const SUMMARY = 'querynova_crawl_summary';

    public function __construct(
        private readonly DatabaseConnection $database,
        private readonly CrawlAnalyzer $analyzer = new CrawlAnalyzer(),
    ) {
    }

    public function persist( CrawlRun $run ): void {
        $now = gmdate( 'Y-m-d H:i:s' );
        foreach ( $run->pages() as $page ) {
            $this->upsert( $page, $now );
        }
        $issueCount = null;
        if ( $run->done() ) {
            $issueCount = $this->replaceIssues( $run, $now );
        }
        update_option(
            self::SUMMARY,
            [
                'run_id'        => $run->runId(),
                'origin'        => $run->origin(),
                'pages_crawled' => count( $run->pages() ),
                'queued'        => count( $run->queue() ),
                'max_depth'     => $this->maxDepth( $run ),
                'done'          => $run->done(),
                'issue_count'   => $issueCount,
                'schema_types'  => $run->done() ? $this->schemaTypes( $run ) : null,
                'updated_at'    => $now,
            ],
            false
        );
    }

    private function upsert( PageObservation $page, string $now ): void {
        $hash     = hash( 'sha256', $page->url() );
        $data     = [
            'object_type'     => 'url',
            'object_id'       => 0,
            'url'             => $page->url(),
            'url_hash'        => $hash,
            'indexability'    => $this->indexability( $page ),
            'canonical'       => $page->canonical() === '' ? null : $page->canonical(),
            'http_status'     => $page->status(),
            'last_crawled_at' => $now,
            'updated_at'      => $now,
        ];
        $existing = $this->database->select( $this->pagesTable(), [ 'url_hash' => $hash ], 1 );
        if ( $existing === [] ) {
            $data['created_at'] = $now;
            $this->database->insert( $this->pagesTable(), $data );

            return;
        }
        $this->database->update( $this->pagesTable(), $data, [ 'url_hash' => $hash ] );
    }

    private function indexability( PageObservation $page ): string {
        if ( $page->status() === 0 ) {
            return 'unknown';
        }

        return $page->indexable() ? 'index' : 'noindex';
    }

    private function replaceIssues( CrawlRun $run, string $now ): int {
        $this->database->delete( $this->issuesTable(), [ 'module' => 'crawler' ] );
        $issues = $this->analyzer->issues( $run->pages(), $run->sitemap(), $run->origin() );
        foreach ( $issues as $issue ) {
            $details = wp_json_encode(
                [
                    'url'    => $issue->url(),
                    'detail' => $issue->detail(),
                ]
            );
            $this->database->insert(
                $this->issuesTable(),
                [
                    'module'       => 'crawler',
                    'object_type'  => 'url',
                    'object_id'    => 0,
                    'issue_code'   => $issue->code(),
                    'severity'     => $issue->severity(),
                    'title'        => $issue->title(),
                    'details_json' => is_string( $details ) ? $details : '{}',
                    'status'       => 'open',
                    'first_seen'   => $now,
                    'last_seen'    => $now,
                ]
            );
        }

        return count( $issues );
    }

    private function maxDepth( CrawlRun $run ): int {
        $depth = 0;
        foreach ( $run->pages() as $page ) {
            $depth = max( $depth, $page->depth() );
        }

        return $depth;
    }

    /**
     * @return list<string>
     */
    private function schemaTypes( CrawlRun $run ): array {
        $types = [];
        foreach ( $run->pages() as $page ) {
            foreach ( $page->schemaTypes() as $type ) {
                $types[ $type ] = $type;
            }
        }

        return array_values( $types );
    }

    private function pagesTable(): string {
        return $this->database->prefix() . 'qn_pages';
    }

    private function issuesTable(): string {
        return $this->database->prefix() . 'qn_issues';
    }
}
