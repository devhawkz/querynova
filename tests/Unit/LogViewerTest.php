<?php
/**
 * The log viewer filters stored rows and does not return secrets.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Logging\LogViewer;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Core\CoreModule;

final class LogViewerTest extends TestCase {

    public function testFiltersKeepOneRowAndScrubTheMessage(): void {
        $report = LogViewer::filter(
            [
                [
                    'level'           => 'error',
                    'channel'         => 'jobs',
                    'module'          => 'seo',
                    'provider'        => '',
                    'logged_at'       => '2026-09-28 10:00:00',
                    'error_reference' => 'ref-1',
                    'correlation_id'  => 'corr-1',
                    'message'         => 'failed api_key=abc user@example.com',
                    'context_json'    => '{"token":"secret"}',
                ],
                [
                    'level'           => 'info',
                    'channel'         => 'core',
                    'module'          => 'seo',
                    'logged_at'       => '2026-09-27 10:00:00',
                    'error_reference' => 'ref-2',
                    'correlation_id'  => 'corr-1',
                    'message'         => 'other',
                ],
            ],
            [
                'level'           => 'error',
                'channel'         => 'jobs',
                'module'          => 'seo',
                'provider'        => '',
                'date'            => '2026-09-28',
                'error_reference' => 'ref-1',
                'correlation_id'  => 'corr-1',
            ]
        );
        $routes = new RestRegistrar();
        ( new CoreModule() )->registerRoutes( $routes );

        self::assertCount( 1, $report['rows'] );
        self::assertStringNotContainsString( 'abc', $report['rows'][0]['message'] );
        self::assertStringNotContainsString( 'user@example.com', $report['rows'][0]['message'] );
        self::assertArrayNotHasKey( 'context_json', $report['rows'][0] );
        self::assertFalse( $report['secrets'] );
        self::assertContains( '/logs', array_column( $routes->routes(), 'route' ) );
    }
}
