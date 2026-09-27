<?php
/**
 * Site Health receives QueryNova results without a WordPress boot.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Contracts\HealthCheckInterface;
use QueryNova\Core\Health\HealthRegistry;
use QueryNova\Core\Health\HealthReport;
use QueryNova\Core\Health\HealthStatus;
use QueryNova\Core\Health\SiteHealthTests as QueryNovaSiteHealth;

final class SiteHealthTest extends TestCase {

    public function testAnUnhealthyCheckIsCriticalAndDetailsStayOut(): void {
        $registry = new HealthRegistry();
        $registry->add( $this->check( 'database', HealthStatus::Healthy, 'Database answered.' ) );
        $registry->add( $this->check( 'cron', HealthStatus::Unhealthy, 'Cron is not scheduled.', [ 'token' => 'secret-value' ] ) );
        $result = ( new QueryNovaSiteHealth( $registry ) )->run();

        self::assertSame( 'critical', $result['status'] );
        self::assertStringContainsString( 'Cron is not scheduled.', $result['description'] );
        self::assertStringNotContainsString( 'secret-value', $result['description'] );
        self::assertSame( 'querynova', $result['test'] );
    }

    public function testNoChecksStayUnclaimed(): void {
        $result = ( new QueryNovaSiteHealth( new HealthRegistry() ) )->run();

        self::assertSame( 'recommended', $result['status'] );
        self::assertStringContainsString( 'Nothing recorded.', $result['description'] );
    }

    public function testTheSiteHealthFilterReceivesTheDirectTest(): void {
        $bridge = new QueryNovaSiteHealth( new HealthRegistry() );
        $bridge->register();
        $tests = apply_filters( 'site_status_tests', [] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress Site Health hook.

        self::assertIsArray( $tests );
        self::assertArrayHasKey( 'direct', $tests );
        self::assertIsArray( $tests['direct'] );
        self::assertArrayHasKey( 'querynova', $tests['direct'] );
    }

    /**
     * @param array<string, scalar|null> $details
     */
    private function check( string $name, HealthStatus $status, string $summary, array $details = [] ): HealthCheckInterface {
        return new class( $name, $status, $summary, $details ) implements HealthCheckInterface {

            /**
             * @param array<string, scalar|null> $details
             */
            public function __construct(
                private readonly string $name,
                private readonly HealthStatus $status,
                private readonly string $summary,
                private readonly array $details,
            ) {
            }

            public function name(): string {
                return $this->name;
            }

            public function check(): HealthReport {
                return new HealthReport( $this->name, $this->status, $this->summary, $this->details );
            }
        };
    }
}
