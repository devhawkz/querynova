<?php
/**
 * Crawls one batch. The next batch is a later job, never this request.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Application;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Crawler\Domain\PageObservation;
use QueryNova\Modules\Crawler\Domain\RobotsTxt;
use QueryNova\Modules\Crawler\Domain\SiteUrl;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CrawlBatch {

    public const SIZE = 10;

    public const MAX_DEPTH = 5;

    public const MAX_PAGES = 100;

    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly HtmlInspector $inspector = new HtmlInspector(),
        private readonly RobotsTxt $robots = new RobotsTxt(),
    ) {
    }

    /**
     * @param list<string> $sitemap
     */
    public function start( string $origin, array $sitemap = [], int $maxPages = self::MAX_PAGES ): CrawlRun {
        $origin = SiteUrl::normalize( $origin );
        if ( $origin === '' ) {
            throw new ValidationException( 'The crawl needs an http or https URL.' );
        }
        $listed = [];
        foreach ( $sitemap as $url ) {
            $normalized = SiteUrl::normalize( $url );
            if ( $normalized !== '' && SiteUrl::sameHost( $origin, $normalized ) ) {
                $listed[] = $normalized;
            }
        }

        return new CrawlRun(
            bin2hex( random_bytes( 8 ) ),
            $origin,
            $listed,
            max( 1, min( $maxPages, self::MAX_PAGES ) ),
            [
                [
                    'url'   => $origin,
                    'depth' => 0,
                ],
            ],
            [ $origin ],
            [],
            null,
            false
        );
    }

    public function step( CrawlRun $run ): CrawlRun {
        if ( $run->done() ) {
            return $run;
        }
        $disallow = $run->disallow() ?? $this->loadRobots( $run->origin() );
        $queue    = $run->queue();
        $seen     = $run->seen();
        $pages    = $run->pages();
        $taken    = 0;
        while ( $taken < self::SIZE && $queue !== [] ) {
            if ( count( $pages ) >= $run->maxPages() ) {
                break;
            }
            $item = array_shift( $queue );
            if ( ! is_array( $item ) ) {
                break;
            }
            $url     = (string) $item['url'];
            $depth   = (int) $item['depth'];
            $page    = $this->observe( $url, $depth, $disallow );
            $pages[] = $page;
            ++$taken;
            $this->discover( $page, $depth, $run->origin(), $run->maxPages(), $queue, $seen );
        }

        return new CrawlRun(
            $run->runId(),
            $run->origin(),
            $run->sitemap(),
            $run->maxPages(),
            $queue,
            $seen,
            $pages,
            $disallow,
            $queue === [] || count( $pages ) >= $run->maxPages()
        );
    }

    /**
     * @param list<string> $disallow
     */
    private function observe( string $url, int $depth, array $disallow ): PageObservation {
        $fetched = $this->fetcher->fetch( $url );
        $page    = $this->inspector->inspect( $url, $fetched->status, $fetched->headers, $fetched->body, $depth );
        if ( $fetched->error !== '' ) {
            $page = $page->withError( $fetched->error );
        }
        if ( $this->robots->blocks( SiteUrl::path( $url ), $disallow ) ) {
            $page = $page->withIndexable( false );
        }

        return $page;
    }

    /**
     * @return list<string>
     */
    private function loadRobots( string $origin ): array {
        $robotsUrl = SiteUrl::origin( $origin ) . '/robots.txt';
        if ( $robotsUrl === '/robots.txt' ) {
            return [];
        }
        $fetched = $this->fetcher->fetch( $robotsUrl );
        if ( $fetched->status !== 200 ) {
            return [];
        }

        return $this->robots->disallows( $fetched->body );
    }

    /**
     * @param list<array{url: string, depth: int}> $queue
     * @param list<string>                         $seen
     */
    private function discover( PageObservation $page, int $depth, string $origin, int $maxPages, array &$queue, array &$seen ): void {
        $nextDepth = $depth + 1;
        foreach ( $page->internalLinks() as $link ) {
            $this->enqueue( $link, $nextDepth, $origin, $maxPages, $queue, $seen );
        }
        $this->enqueue( $page->redirectTo(), $nextDepth, $origin, $maxPages, $queue, $seen );
        $this->enqueue( $page->nextUrl(), $nextDepth, $origin, $maxPages, $queue, $seen );
        $this->enqueue( $page->prevUrl(), $nextDepth, $origin, $maxPages, $queue, $seen );
    }

    /**
     * @param list<array{url: string, depth: int}> $queue
     * @param list<string>                         $seen
     */
    private function enqueue( string $url, int $depth, string $origin, int $maxPages, array &$queue, array &$seen ): void {
        if ( $url === '' || $depth > self::MAX_DEPTH || ! SiteUrl::sameHost( $origin, $url ) ) {
            return;
        }
        $normalized = SiteUrl::normalize( $url );
        if ( $normalized === '' || in_array( $normalized, $seen, true ) || count( $seen ) >= $maxPages ) {
            return;
        }
        $seen[]  = $normalized;
        $queue[] = [
            'url'   => $normalized,
            'depth' => $depth,
        ];
    }
}
