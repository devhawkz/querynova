<?php
/**
 * Synthetic catalogs stay paged. This is not a production timing guarantee.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Performance;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Logging\CorrelationContext;
use QueryNova\Core\Support\FrozenClock;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRepository;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Queue\JobStatus;
use QueryNova\Infrastructure\Queue\RetryPolicy;
use QueryNova\Modules\Core\ProductScreen;

final class CatalogScaleTest extends TestCase {

    public function testLargeCatalogsReturnAPageAndOneAdminProduct(): void {
        foreach ( [ 1000, 10000, 100000 ] as $count ) {
            $database = new ArrayDatabase();
            $before   = memory_get_usage();
            $started  = hrtime( true );
            for ( $i = 1; $i <= $count; $i++ ) {
                $database->insert(
                    'wp_qn_products',
                    [
                        'product_id' => $i,
                        'sku'        => 'SKU' . $i,
                        'price'      => null,
                    ]
                );
            }
            $page    = $database->select( 'wp_qn_products', [], 20, 0 );
            $screen  = ( new ProductScreen() )->fromDatabase( $database );
            $elapsed = ( hrtime( true ) - $started ) / 1_000_000_000;
            $bytes   = memory_get_usage() - $before;

            self::assertCount( 20, $page );
            self::assertSame( 'SKU' . $count, $screen['title'] );
            self::assertLessThan( 100, $this->itemCount( $screen['tabs'] ) );
            self::assertSame( [], $database->statements() );
            self::assertLessThan( 256 * 1024 * 1024, $bytes );
            self::assertLessThan( 60, $elapsed );
            unset( $database, $page, $screen );
        }
    }

    public function testAJobBatchDoesNotRunTheWholeQueue(): void {
        $database  = new ArrayDatabase();
        $registrar = new JobRegistrar();
        $registrar->register(
            'demo',
            static function (): void {
            }
        );
        $runner = new JobRunner(
            new JobRepository( $database ),
            $registrar,
            new RetryPolicy(),
            new FrozenClock( new \DateTimeImmutable( '2026-09-27 12:00:00', new \DateTimeZone( 'UTC' ) ) ),
            CorrelationContext::fresh()
        );
        for ( $i = 0; $i < 200; $i++ ) {
            $runner->enqueue( 'demo', [], 'job-' . $i );
        }

        self::assertSame( 20, $runner->workDue( 20 ) );
        $counts = ( new JobRepository( $database ) )->statusCounts();
        self::assertSame( 180, $counts[ JobStatus::Pending->value ] );
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tabs
     */
    private function itemCount( array $tabs ): int {
        $count = 0;
        foreach ( $tabs as $items ) {
            $count += count( $items );
        }

        return $count;
    }
}
