<?php
/**
 * Redirect chains, loops, import, and 404 monitor tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Redirects\Application\RedirectCsv;
use QueryNova\Modules\Redirects\Application\RedirectEngine;
use QueryNova\Modules\Redirects\Domain\RedirectRule;
use QueryNova\Modules\Redirects\Infrastructure\NotFoundRepository;
use QueryNova\Modules\Redirects\Infrastructure\RedirectRepository;

final class RedirectTest extends TestCase {

    private RedirectEngine $engine;

    protected function setUp(): void {
        $this->engine = new RedirectEngine();
    }

    public function testExactRedirectAndChainCollapseToOneTarget(): void {
        $rules    = [
            new RedirectRule( 1, '/a', '/b', 301, false ),
            new RedirectRule( 2, '/b', '/c', 301, false ),
        ];
        $decision = $this->engine->resolve( '/a', $rules );

        self::assertTrue( $decision->matched() );
        self::assertSame( 301, $decision->status() );
        self::assertSame( '/c', $decision->location() );
        self::assertSame( 1, $decision->ruleId() );
        self::assertFalse( $decision->loop() );
    }

    public function testLoopIsRejectedAndGoneHasNoLocation(): void {
        $first = new RedirectRule( 1, '/a', '/b', 301, false );
        $loop  = new RedirectRule( 2, '/b', '/a', 302, false );
        $this->expectException( ValidationException::class );
        try {
            $this->engine->assertSafe( [ $first ], $loop );
        } finally {
            $gone = $this->engine->resolve( '/retired', [ new RedirectRule( 3, '/retired', '', 410, false ) ] );
            self::assertTrue( $gone->matched() );
            self::assertSame( 410, $gone->status() );
            self::assertSame( '', $gone->location() );
        }
    }

    public function testRegexReplacesAndUnsafeTargetsAreRejected(): void {
        $rule     = new RedirectRule( 4, '^/shop/(.*)$', '/store/$1', 307, true );
        $decision = $this->engine->resolve( '/shop/welder', [ $rule ] );

        self::assertSame( '/store/welder', $decision->location() );
        self::assertSame( 307, $decision->status() );
        $this->expectException( ValidationException::class );
        $this->engine->normalizeTarget( 'javascript:alert(1)', 301 );
    }

    public function testCsvRoundTripAndHitCount(): void {
        $database = new ArrayDatabase();
        $store    = new RedirectRepository( $database );
        $csv      = new RedirectCsv( $this->engine );
        $rules    = $csv->import( "source,target,status,regex\n/old,/new,301,0\n^/shop/(.*)$,/store/$1,302,1\n", [] );
        foreach ( $rules as $rule ) {
            $store->save( $rule );
        }
        $exported = $csv->export( $store->all() );
        $store->increment( 1 );

        self::assertStringContainsString( '/old,/new,301,0', $exported );
        self::assertStringContainsString( '^/shop/(.*)$,/store/$1,302,1', $exported );
        self::assertSame( 1, $store->all()[0]->hits() );
    }

    public function testNotFoundMonitorSuggestsWithoutCreatingARedirect(): void {
        $database  = new ArrayDatabase();
        $redirects = new RedirectRepository( $database );
        $missing   = new NotFoundRepository( $database );
        $redirects->save( new RedirectRule( 0, '/blog/old-page', '/blog/new-page', 301, false ) );
        $rules = $redirects->enabled();
        $missing->record( '/old-page', 'https://example.test/referrer', 'Browser/1.0', $this->engine->suggest( '/old-page', $rules ) );
        $missing->record( '/old-page', '', 'Browser/1.0', $this->engine->suggest( '/old-page', $rules ) );
        $rows = $missing->recent();

        self::assertCount( 1, $redirects->all() );
        self::assertSame( 2, (int) $rows[0]['hits'] );
        self::assertSame( '/blog/new-page', $rows[0]['suggested_target'] );
        self::assertSame( 'Browser/1.0', $rows[0]['user_agent'] );
        self::assertSame( '', $this->engine->suggest( '/something-else', $rules ) );
    }

    public function testStatusMustBeOneOfTheSupportedCodes(): void {
        $this->expectException( ValidationException::class );
        $this->engine->normalizeTarget( '/new', 200 );
    }
}
