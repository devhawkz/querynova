<?php
/**
 * The job monitor lists stored jobs and retry does not run the handler.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Queue\JobMonitor;
use QueryNova\Infrastructure\Queue\JobRepository;
use QueryNova\Infrastructure\Queue\JobStatus;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Core\CoreModule;

final class JobMonitorTest extends TestCase {

    public function testAMissingDurationStaysEmptyAndThePayloadIsOmitted(): void {
        $row = JobMonitor::row(
            [
                'job_type'       => 'querynova.seo.audit',
                'status'         => 'PENDING',
                'created_at'     => '2026-09-28 10:00:00',
                'correlation_id' => 'corr-1',
                'payload'        => '{"api_key":"secret"}',
            ]
        );

        self::assertSame( 'querynova.seo.audit', $row['job'] );
        self::assertSame( 'seo', $row['module'] );
        self::assertNull( $row['started'] );
        self::assertNull( $row['duration'] );
        self::assertNull( $row['attempts'] );
        self::assertSame( 'corr-1', $row['correlation_id'] );
        self::assertArrayNotHasKey( 'payload', $row );
    }

    public function testRetryRequeuesADeadJobAndDoesNotRunIt(): void {
        $database = new ArrayDatabase();
        $jobs     = new JobRepository( $database );
        $id       = $jobs->create( 'querynova.analytics.sync', [], 'job-1', 'corr-2', new \DateTimeImmutable( '2026-09-28 10:00:00 UTC' ) );
        $jobs->claim( $id, new \DateTimeImmutable( '2026-09-28 10:01:00 UTC' ) );
        $jobs->markDead( $id, 'failed', 'ref-1', new \DateTimeImmutable( '2026-09-28 10:02:00 UTC' ) );
        $ran    = false;
        $result = JobMonitor::retry( $jobs, $id, new \DateTimeImmutable( '2026-09-28 10:03:00 UTC' ) );
        $again  = $jobs->find( $id );
        $routes = new RestRegistrar();
        ( new CoreModule() )->registerRoutes( $routes );

        self::assertTrue( $result['requeued'] );
        self::assertFalse( $result['ran'] );
        self::assertFalse( $ran );
        self::assertSame( JobStatus::Pending->value, $again['status'] ?? null );
        self::assertContains( '/jobs', array_column( $routes->routes(), 'route' ) );
        self::assertContains( '/jobs/retry', array_column( $routes->routes(), 'route' ) );
    }
}
