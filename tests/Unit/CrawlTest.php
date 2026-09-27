<?php
/**
 * Crawler signals, batch limits, and stored findings.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\JobException;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Core\Security\SsrfGuard;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Lock\TransientLock;
use QueryNova\Modules\Crawler\Application\CrawlAnalyzer;
use QueryNova\Modules\Crawler\Application\CrawlBatch;
use QueryNova\Modules\Crawler\Application\CrawlRun;
use QueryNova\Modules\Crawler\Application\CrawlWorker;
use QueryNova\Modules\Crawler\Application\FetchedPage;
use QueryNova\Modules\Crawler\Application\HtmlInspector;
use QueryNova\Modules\Crawler\Domain\PageObservation;
use QueryNova\Tests\Support\MapFetcher;
use QueryNova\Tests\Support\RecordingHttpClient;
use QueryNova\Modules\Crawler\Infrastructure\CrawlPageStore;
use QueryNova\Modules\Crawler\Infrastructure\GuardedPageFetcher;

final class CrawlTest extends TestCase {

    private HtmlInspector $inspector;

    protected function setUp(): void {
        $GLOBALS['querynova_options'] = [];
        $GLOBALS['qn_transients']     = []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores transients under this key.
        $this->inspector              = new HtmlInspector();
    }

    public function testInspectorReadsOnPageSignals(): void {
        $html = '<!doctype html><html><head><title>Shop</title>'
            . '<meta name="description" content="A shop page">'
            . '<meta name="robots" content="index, follow">'
            . '<link rel="canonical" href="https://example.com/shop">'
            . '<link rel="alternate" hreflang="en" href="https://example.com/en">'
            . '<link rel="next" href="/shop/2"><link rel="prev" href="/shop">'
            . '<script type="application/ld+json">{"@graph":[{"@type":"WebPage"},{"@type":"BreadcrumbList"}]}</script>'
            . '</head><body><h1>Shop</h1><h1>Also</h1><a href="/inside">Inside</a><a href="https://other.test/out">Out</a>'
            . '<p>one two three four five</p></body></html>';
        $page = $this->inspector->inspect(
            'http://example.com/shop',
            200,
            [ 'x-robots-tag' => 'max-image-preview:large' ],
            $html,
            1
        );

        self::assertSame( 'Shop', $page->title() );
        self::assertSame( 'A shop page', $page->description() );
        self::assertSame( [ 'Shop', 'Also' ], $page->h1() );
        self::assertSame( 'https://example.com/shop', $page->canonical() );
        self::assertStringContainsString( 'index, follow', $page->robots() );
        self::assertSame( 'en', $page->hreflang()[0]['lang'] );
        self::assertSame( 'http://example.com/shop/2', $page->nextUrl() );
        self::assertSame( 'http://example.com/shop', $page->prevUrl() );
        self::assertSame( [ 'WebPage', 'BreadcrumbList' ], $page->schemaTypes() );
        self::assertFalse( $page->https() );
        self::assertSame( 5, $page->wordCount() );
        self::assertSame( [ 'http://example.com/inside' ], $page->internalLinks() );
        self::assertSame( 1, $page->depth() );
    }

    public function testAnalyzerFlagsMeasuredProblemsAndSkipsAnEmptySitemap(): void {
        $origin = 'https://example.com/';
        $home   = $this->page( $origin, 200, 'Home', 40, [ 'https://example.com/missing' ] );
        $thin   = $this->page( 'https://example.com/thin', 200, 'Thin', 12, [] );
        $copy   = $this->page( 'https://example.com/copy', 200, 'Home', 40, [] );
        $copy   = $this->withHash( $home, $copy );
        $gone   = $this->page( 'https://example.com/missing', 404, '', 0, [] );
        $alone  = $this->page( 'https://example.com/orphan', 200, 'Orphan', 40, [] );
        $plain  = new CrawlAnalyzer();

        $withoutSitemap = $this->codes( $plain->issues( [ $home, $thin, $copy, $gone, $alone ], [], $origin ) );
        $withSitemap    = $this->codes( $plain->issues( [ $home, $thin ], [ 'https://example.com/' ], $origin ) );

        self::assertContains( 'duplicate_title', $withoutSitemap );
        self::assertContains( 'duplicate_body', $withoutSitemap );
        self::assertContains( 'broken_link', $withoutSitemap );
        self::assertContains( 'thin_page', $withoutSitemap );
        self::assertContains( 'orphan', $withoutSitemap );
        self::assertContains( 'http_error', $withoutSitemap );
        self::assertNotContains( 'not_in_sitemap', $withoutSitemap );
        self::assertContains( 'not_in_sitemap', $withSitemap );
    }

    public function testOneBatchFetchesTenAndLeavesTheRestQueued(): void {
        $map   = [
            'https://example.com/robots.txt' => new FetchedPage( 200, "User-agent: *\nDisallow:\n", [] ),
        ];
        $home  = 'https://example.com/';
        $links = [];
        for ( $i = 1; $i <= 14; $i++ ) {
            $url         = 'https://example.com/p/' . $i;
            $links[]     = '<a href="' . $url . '">Page ' . $i . '</a>';
            $map[ $url ] = new FetchedPage( 200, '<html><head><title>Page ' . $i . '</title></head><body><h1>Page</h1><p>' . str_repeat( 'word ', 30 ) . '</p></body></html>', [] );
        }
        $map[ $home ] = new FetchedPage(
            200,
            '<html><head><title>Home</title></head><body><h1>Home</h1>' . implode( '', $links ) . '<p>' . str_repeat( 'word ', 30 ) . '</p></body></html>',
            []
        );
        $fetcher      = new MapFetcher( $map );
        $batch        = new CrawlBatch( $fetcher );
        $run          = $batch->step( $batch->start( $home ) );

        self::assertCount( 10, $run->pages() );
        self::assertCount( 5, $run->queue() );
        self::assertFalse( $run->done() );
        self::assertNotContains( 'https://example.com/p/14', $fetcher->requested );
        self::assertContains( 'https://example.com/robots.txt', $fetcher->requested );
    }

    public function testRobotsDisallowMarksThePageNoindexAndKeepsOtherAgents(): void {
        $private = 'https://example.com/private';
        $open    = 'https://example.com/open';
        $home    = '<html><head><title>Home</title></head><body><h1>Home</h1>'
            . '<a href="' . $private . '">Private</a><a href="' . $open . '">Open</a>'
            . '<p>' . str_repeat( 'word ', 30 ) . '</p></body></html>';
        $fetcher = new MapFetcher(
            [
                'https://example.com/robots.txt' => new FetchedPage( 200, "User-agent: *\nDisallow: /private\n\nUser-agent: Other\nDisallow: /open\n", [] ),
                'https://example.com/'           => new FetchedPage( 200, $home, [] ),
                $private                         => new FetchedPage( 200, '<html><head><title>Private</title></head><body><h1>Private</h1><p>' . str_repeat( 'word ', 30 ) . '</p></body></html>', [] ),
                $open                            => new FetchedPage( 200, '<html><head><title>Open</title></head><body><h1>Open</h1><p>' . str_repeat( 'word ', 30 ) . '</p></body></html>', [] ),
            ]
        );
        $batch   = new CrawlBatch( $fetcher );
        $run     = $batch->step( $batch->start( 'https://example.com/' ) );
        $byUrl   = [];
        foreach ( $run->pages() as $page ) {
            $byUrl[ $page->url() ] = $page->indexable();
        }

        self::assertFalse( $byUrl[ $private ] );
        self::assertTrue( $byUrl[ $open ] );
    }

    public function testRedirectIsRecordedAndOnlyTheSameHostIsQueued(): void {
        $map   = [
            'https://example.com/old'  => new FetchedPage( 301, '', [ 'location' => 'https://example.com/new' ] ),
            'https://example.com/away' => new FetchedPage( 302, '', [ 'location' => 'https://other.test/leave' ] ),
        ];
        $queue = [
            [
                'url'   => 'https://example.com/old',
                'depth' => 0,
            ],
            [
                'url'   => 'https://example.com/away',
                'depth' => 0,
            ],
        ];
        $seen  = [ 'https://example.com/old', 'https://example.com/away' ];
        for ( $i = 1; $i <= 8; $i++ ) {
            $url         = 'https://example.com/filler/' . $i;
            $map[ $url ] = new FetchedPage( 204, '', [] );
            $queue[]     = [
                'url'   => $url,
                'depth' => 0,
            ];
            $seen[]      = $url;
        }
        $fetcher = new MapFetcher( $map );
        $next    = ( new CrawlBatch( $fetcher ) )->step(
            new CrawlRun( 'run', 'https://example.com/old', [], 20, $queue, $seen, [], [], false )
        );
        $queued  = array_column( $next->queue(), 'url' );

        self::assertSame( 301, $next->pages()[0]->status() );
        self::assertSame( 'https://example.com/new', $next->pages()[0]->redirectTo() );
        self::assertContains( 'https://example.com/new', $queued );
        self::assertNotContains( 'https://other.test/leave', $queued );
        self::assertNotContains( 'https://example.com/new', $fetcher->requested );
        self::assertNotContains( 'https://other.test/leave', $fetcher->requested );
    }

    public function testDepthCapDoesNotEnqueueAnotherHop(): void {
        $deep    = 'https://example.com/deep';
        $child   = 'https://example.com/deeper';
        $fetcher = new MapFetcher(
            [
                $deep => new FetchedPage( 200, '<html><head><title>Deep</title></head><body><a href="' . $child . '">Next</a><p>' . str_repeat( 'word ', 20 ) . '</p></body></html>', [] ),
            ]
        );
        $run     = new CrawlRun(
            'run',
            'https://example.com',
            [],
            20,
            [
				[
					'url'   => $deep,
					'depth' => 5,
				],
			],
			[ $deep ],
			[],
			[],
			false
        );
        $next    = ( new CrawlBatch( $fetcher ) )->step( $run );

        self::assertSame( [], $next->queue() );
        self::assertSame( 5, $next->pages()[0]->depth() );
        self::assertNotContains( $child, $fetcher->requested );
    }

    public function testGuardedFetcherBlocksLocalhostBeforeTheClient(): void {
        $client = new RecordingHttpClient();
        $page   = ( new GuardedPageFetcher( new SsrfGuard(), $client ) )->fetch( 'http://localhost/admin' );

        self::assertSame( 0, $page->status );
        self::assertSame( 0, $client->calls );
        self::assertNotSame( '', $page->error );
    }

    public function testGuardedFetcherDoesNotFollowRedirects(): void {
        $client = new RecordingHttpClient();
        $guard  = new SsrfGuard(
            static function ( string $host ): array {
                return $host === 'example.com' ? [ '8.8.8.8' ] : [];
            }
        );
        $page   = ( new GuardedPageFetcher( $guard, $client ) )->fetch( 'https://example.com/' );

        self::assertSame( 204, $page->status );
        self::assertSame( 0, $client->request->redirection );
        self::assertSame( 10, $client->request->timeout );
        self::assertSame( 'QueryNova Crawler', $client->request->headers['User-Agent'] );
    }

    public function testWorkerPersistsOneBatchAndRefusesASecondLock(): void {
        $fetcher = new MapFetcher(
            [
                'https://example.com/robots.txt' => new FetchedPage( 200, "User-agent: *\nDisallow: /no\n", [] ),
                'https://example.com/'           => new FetchedPage( 200, '<html><head><title>Home</title><meta name="description" content="Hi"></head><body><h1>Home</h1><p>' . str_repeat( 'word ', 30 ) . '</p></body></html>', [] ),
            ]
        );
        $batch   = new CrawlBatch( $fetcher );
        $store   = new CrawlPageStore( new ArrayDatabase() );
        $worker  = new CrawlWorker( $batch, $store, new TransientLock() );
        $started = $batch->start( 'https://example.com/', [ 'https://example.com/' ] );
        $queued  = null;
        $worker->handle(
            $started->toPayload(),
            static function ( CrawlRun $next ) use ( &$queued ): void {
                $queued = $next;
            }
        );
        $summary = get_option( CrawlPageStore::SUMMARY );

        self::assertNull( $queued );
        self::assertIsArray( $summary );
        self::assertTrue( $summary['done'] );
        self::assertSame( 1, $summary['pages_crawled'] );
        self::assertIsInt( $summary['issue_count'] );

        $lock = new TransientLock();
        $lock->acquire( 'crawl', 30 );
        $this->expectException( JobException::class );
        ( new CrawlWorker( $batch, $store, $lock ) )->handle( $started->toPayload(), static function (): void {} );
    }

    public function testUnfinishedCrawlDoesNotWriteIssues(): void {
        $database = new ArrayDatabase();
        $store    = new CrawlPageStore( $database );
        $page     = $this->page( 'https://example.com', 200, '', 5, [] );
        $running  = new CrawlRun(
            'run',
            'https://example.com',
            [],
            10,
            [
				[
					'url'   => 'https://example.com/next',
					'depth' => 1,
				],
			],
			[ 'https://example.com' ],
			[ $page ],
			[],
			false
        );
        $store->persist( $running );
        $summary = get_option( CrawlPageStore::SUMMARY );

        self::assertSame( [], $database->select( 'wp_qn_issues', [ 'module' => 'crawler' ], 20 ) );
        self::assertNull( $summary['issue_count'] );
        self::assertSame( 'index', $database->select( 'wp_qn_pages', [], 5 )[0]['indexability'] );
    }

    public function testSafeModeOmitsTheCrawler(): void {
        $names = static function ( bool $safe ): array {
            $found = [];
            foreach ( ModuleCatalog::modules( $safe ) as $module ) {
                $found[] = $module->getName();
            }

            return $found;
        };

        self::assertContains( 'crawler', $names( false ) );
        self::assertNotContains( 'crawler', $names( true ) );
    }

    public function testStartRejectsAUrlWithoutAHost(): void {
        $this->expectException( ValidationException::class );
        ( new CrawlBatch( new MapFetcher( [] ) ) )->start( 'not a url' );
    }

    /**
     * @param list<\QueryNova\Modules\Crawler\Domain\CrawlIssue> $issues
     * @return list<string>
     */
    private function codes( array $issues ): array {
        $codes = [];
        foreach ( $issues as $issue ) {
            $codes[] = $issue->code();
        }

        return $codes;
    }

    /**
     * @param list<string> $links
     */
    private function page( string $url, int $status, string $title, int $words, array $links ): PageObservation {
        return new PageObservation(
            $url,
            $status,
            $url === 'https://example.com/' ? 0 : 1,
            '',
            $title,
            $title === '' ? '' : 'Description',
            $url,
            '',
            $status === 200,
            $title === '' ? [] : [ $title ],
            $words,
            str_starts_with( $url, 'https://' ),
            [],
            [],
            '',
            '',
            $links,
            $words >= 20 ? hash( 'sha256', $url . '|' . $title ) : ''
        );
    }

    private function withHash( PageObservation $source, PageObservation $target ): PageObservation {
        $row              = $target->toArray();
        $row['body_hash'] = $source->bodyHash();

        return PageObservation::fromArray( $row );
    }
}
