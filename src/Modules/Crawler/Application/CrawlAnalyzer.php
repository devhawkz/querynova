<?php
/**
 * Compares a finished crawl. Uncrawled links stay unknown, not broken.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Application;

use QueryNova\Modules\Crawler\Domain\CrawlIssue;
use QueryNova\Modules\Crawler\Domain\PageObservation;
use QueryNova\Modules\Crawler\Domain\SiteUrl;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CrawlAnalyzer {

    /**
     * @param list<PageObservation> $pages
     * @param list<string>          $sitemap
     * @return list<CrawlIssue>
     */
    public function issues( array $pages, array $sitemap, string $origin ): array {
        $issues  = [];
        $byUrl   = [];
        $inbound = [];
        $titles  = [];
        $bodies  = [];
        $listed  = [];
        foreach ( $sitemap as $url ) {
            $normalized = SiteUrl::normalize( $url );
            if ( $normalized !== '' ) {
                $listed[ $normalized ] = true;
            }
        }
        $origin = SiteUrl::normalize( $origin );
        foreach ( $pages as $page ) {
            $byUrl[ $page->url() ] = $page;
            foreach ( $page->internalLinks() as $link ) {
                $inbound[ $link ] = true;
            }
            if ( $page->redirectTo() !== '' ) {
                $inbound[ $page->redirectTo() ] = true;
            }
        }
        foreach ( $pages as $page ) {
            $issues = array_merge( $issues, $this->pageIssues( $page, $listed, $sitemap !== [] ) );
            if ( $page->title() !== '' && $page->indexable() ) {
                $titles[ strtolower( $page->title() ) ][] = $page->url();
            }
            if ( $page->bodyHash() !== '' && $page->indexable() ) {
                $bodies[ $page->bodyHash() ][] = $page->url();
            }
            if ( $page->status() === 200 && $page->indexable() && $page->url() !== $origin && ! isset( $inbound[ $page->url() ] ) ) {
                $issues[] = $this->issue( 'orphan', 'warning', $page->url(), 'No crawled page links here', 'This URL was crawled and no other crawled URL links to it.' );
            }
            foreach ( $page->internalLinks() as $link ) {
                $target = $byUrl[ $link ] ?? null;
                if ( $target instanceof PageObservation && $target->status() >= 400 ) {
                    $issues[] = $this->issue( 'broken_link', 'warning', $page->url(), 'Broken internal link', $link . ' returned HTTP ' . $target->status() . '.' );
                }
            }
        }
        foreach ( $titles as $title => $urls ) {
            if ( count( $urls ) < 2 ) {
                continue;
            }
            foreach ( $urls as $url ) {
                $issues[] = $this->issue( 'duplicate_title', 'warning', $url, 'Duplicate title', 'Title "' . $title . '" is used on ' . count( $urls ) . ' crawled pages.' );
            }
        }
        foreach ( $bodies as $urls ) {
            if ( count( $urls ) < 2 ) {
                continue;
            }
            foreach ( $urls as $url ) {
                $issues[] = $this->issue( 'duplicate_body', 'warning', $url, 'Duplicate body', 'The visible text matches ' . count( $urls ) . ' crawled pages.' );
            }
        }

        return $issues;
    }

    /**
     * @param array<string, true> $sitemap
     * @return list<CrawlIssue>
     */
    private function pageIssues( PageObservation $page, array $sitemap, bool $checkSitemap ): array {
        $issues = [];
        if ( $page->status() >= 400 ) {
            $issues[] = $this->issue( 'http_error', $page->status() >= 500 ? 'error' : 'warning', $page->url(), 'HTTP ' . $page->status(), 'The crawler measured HTTP ' . $page->status() . '.' );
        }
        if ( $page->redirectTo() !== '' ) {
            $issues[] = $this->issue( 'redirect', 'info', $page->url(), 'Redirect', 'Location ' . $page->redirectTo() . '.' );
        }
        if ( $page->status() !== 200 ) {
            return $issues;
        }
        if ( ! $page->indexable() ) {
            $issues[] = $this->issue( 'noindex', 'info', $page->url(), 'Not indexable', $page->robots() === '' ? 'Blocked by robots.txt.' : $page->robots() );
        }
        if ( ! $page->https() ) {
            $issues[] = $this->issue( 'not_https', 'warning', $page->url(), 'Not HTTPS', 'The crawled URL uses HTTP.' );
        }
        if ( $page->canonical() !== '' && SiteUrl::normalize( $page->canonical() ) !== $page->url() ) {
            $issues[] = $this->issue( 'canonical_mismatch', 'warning', $page->url(), 'Canonical points elsewhere', $page->canonical() );
        }
        if ( ! $page->indexable() ) {
            return $issues;
        }
        if ( $page->title() === '' ) {
            $issues[] = $this->issue( 'missing_title', 'warning', $page->url(), 'Missing title', 'No title element was found.' );
        }
        if ( $page->description() === '' ) {
            $issues[] = $this->issue( 'missing_description', 'warning', $page->url(), 'Missing meta description', 'No meta description was found.' );
        }
        if ( $page->h1() === [] ) {
            $issues[] = $this->issue( 'missing_h1', 'warning', $page->url(), 'Missing H1', 'No H1 was found.' );
        } elseif ( count( $page->h1() ) > 1 ) {
            $issues[] = $this->issue( 'multiple_h1', 'info', $page->url(), 'Multiple H1s', (string) count( $page->h1() ) . ' H1 elements were found.' );
        }
        if ( $page->wordCount() > 0 && $page->wordCount() < HtmlInspector::THIN_WORDS ) {
            $issues[] = $this->issue( 'thin_page', 'warning', $page->url(), 'Thin page', $page->wordCount() . ' words. QueryNova flags pages under ' . HtmlInspector::THIN_WORDS . ' words. This is not a search-engine score.' );
        }
        if ( $checkSitemap && ! isset( $sitemap[ $page->url() ] ) ) {
            $issues[] = $this->issue( 'not_in_sitemap', 'info', $page->url(), 'Not in the sitemap', 'This indexable URL was not in the sitemap list given to the crawl.' );
        }

        return $issues;
    }

    private function issue( string $code, string $severity, string $url, string $title, string $detail ): CrawlIssue {
        return new CrawlIssue( $code, $severity, $url, $title, $detail );
    }
}
