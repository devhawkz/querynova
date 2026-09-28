<?php
/**
 * Turns stored crawl issues into audit findings.
 *
 * Findings do not estimate ranking impact, and building them does not fetch a URL.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SeoAnalyzer {

    /**
     * @param list<array<string, mixed>> $issues
     * @return array<string, mixed>
     */
    public static function report( array $issues, ?int $pagesCrawled = null ): array {
        $findings = [];
        foreach ( array_slice( $issues, 0, 100 ) as $issue ) {
            if ( ! is_array( $issue ) ) {
                continue;
            }
            $code       = is_string( $issue['code'] ?? null ) ? $issue['code'] : '';
            $severity   = is_string( $issue['severity'] ?? null ) ? $issue['severity'] : 'info';
            $detail     = is_string( $issue['detail'] ?? null ) ? $issue['detail'] : '';
            $url        = is_string( $issue['url'] ?? null ) ? $issue['url'] : '';
            $title      = is_string( $issue['title'] ?? null ) ? $issue['title'] : $code;
            $findings[] = [
                'status'      => self::status( $severity ),
                'code'        => $code,
                'url'         => $url,
                'explanation' => $title !== '' ? $title : 'Stored crawl issue.',
                'evidence'    => $detail !== '' ? $detail : $url,
                'how_to_fix'  => self::fix( $code ),
            ];
        }
        if ( $findings === [] && $pagesCrawled !== null && $pagesCrawled > 0 ) {
            $findings[] = [
                'status'      => 'passed',
                'code'        => 'stored_crawl_clear',
                'url'         => '',
                'explanation' => 'The stored crawl reported no issues.',
                'evidence'    => 'Pages crawled: ' . $pagesCrawled . '.',
                'how_to_fix'  => 'Rerun the audit after the next crawl if the site changes. Rerun does not crawl during this request.',
            ];
        }
        if ( $findings === [] ) {
            $findings[] = [
                'status'      => 'info',
                'code'        => 'no_stored_crawl',
                'url'         => '',
                'explanation' => 'No stored crawl issues are available.',
                'evidence'    => 'The audit reads stored crawl issues only.',
                'how_to_fix'  => 'Run the crawler first, then rerun this audit. This request does not crawl the site.',
            ];
        }

        return [
            'name'      => 'SEO Analyzer',
            'note'      => 'These findings do not estimate ranking impact.',
            'status'    => 'ready',
            'findings'  => $findings,
            'truncated' => count( $issues ) > 100,
        ];
    }

    /**
     * @return array{type: string, payload: array{source: string}, status: string, note: string, idempotency_key: string}
     */
    public static function plan( string $idempotencyKey ): array {
        return [
            'type'            => 'querynova.seo.audit',
            'payload'         => [ 'source' => 'stored' ],
            'status'          => 'queued',
            'note'            => 'This request does not crawl the site.',
            'idempotency_key' => $idempotencyKey,
        ];
    }

    private static function status( string $severity ): string {
        return match ( $severity ) {
            'error'   => 'failed',
            'warning' => 'warning',
            'passed'  => 'passed',
            default   => 'info',
        };
    }

    private static function fix( string $code ): string {
        return match ( $code ) {
            'missing_title'       => 'Add a title element. This audit does not rewrite titles.',
            'missing_description' => 'Add a meta description. This audit does not rewrite descriptions.',
            'missing_h1'          => 'Add one H1 in the document body.',
            'multiple_h1'         => 'Review the extra H1 elements.',
            'thin_page'           => 'Add useful content. QueryNova flags pages under 100 words. This is not a ranking score.',
            'http_error'          => 'Restore the URL or remove the link. This audit does not create a redirect.',
            'canonical_mismatch'  => 'Review the canonical URL before changing it.',
            'not_https'           => 'Serve the URL over HTTPS.',
            'duplicate_title'     => 'Give each URL its own title. This audit does not change titles.',
            'duplicate_body'      => 'Rewrite one of the pages so the visible text is not the same.',
            'orphan'              => 'Link to this URL from another page, or leave it if it should stand alone.',
            'broken_link'         => 'Update or remove the link. This audit does not insert links.',
            'noindex'             => 'noindex can remove this URL from search results. Change it only if the URL should be indexed.',
            'not_in_sitemap'      => 'Add the URL to the sitemap if it should be listed.',
            'redirect'            => 'Follow the redirect target. This audit does not create redirects.',
            default               => 'Review the evidence. This audit does not change the page.',
        };
    }
}
