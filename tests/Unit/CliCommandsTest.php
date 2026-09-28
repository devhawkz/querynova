<?php
/**
 * WP-CLI queues work and does not invent missing diagnostics.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Health\HealthRegistry;
use QueryNova\Core\Health\ModuleHealthCheck;
use QueryNova\Core\Logging\CorrelationContext;
use QueryNova\Core\Logging\LogRecord;
use QueryNova\Core\Modules\ModuleRegistry;
use QueryNova\Core\Support\FrozenClock;
use QueryNova\Infrastructure\Cache\MemoryCache;
use QueryNova\Infrastructure\Cli\CliCommands;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\LogRepository;
use QueryNova\Infrastructure\Database\MigrationManager;
use QueryNova\Infrastructure\Database\MigrationRegistrar;
use QueryNova\Infrastructure\Database\Migrations\InitialSchemaMigration;
use QueryNova\Infrastructure\Database\Migrations\PageExperienceMigration;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRepository;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Queue\JobStatus;
use QueryNova\Infrastructure\Queue\RetryPolicy;
use QueryNova\Infrastructure\WordPress\OptionStore;
use QueryNova\Modules\Core\CoreModule;

final class CliCommandsTest extends TestCase {

    public function testStatusAndDiagnosticsLeaveMissingValuesEmpty(): void {
        $commands = $this->commands();
        $status   = $commands->execute( [ 'status' ] );
        $report   = $commands->execute( [ 'diagnostics' ] );
        $json     = $commands->render( $report, true );

        self::assertTrue( $status['ok'] );
        self::assertSame( 'QueryNova', $status['name'] );
        self::assertSame( 'local', $report['environment'] );
        self::assertArrayHasKey( 'querynova_build', $report );
        self::assertArrayHasKey( 'release_channel', $report );
        self::assertArrayHasKey( 'release_status', $report );
        self::assertNull( $report['db_version'] );
        self::assertNull( $report['woocommerce_version'] );
        self::assertNull( $report['cache']['hits'] );
        self::assertSame( 'not_configured', $report['providers'][0]['state'] );
        self::assertSame( [], $report['recent_errors'] );
        self::assertStringNotContainsString( '0.0.0', $json );
        self::assertStringContainsString( '"db_version":null', $json );
    }

    public function testDiagnosticsScrubSecrets(): void {
        $database = new ArrayDatabase();
        $logs     = new LogRepository( $database );
        $logs->insert(
            new LogRecord(
                new \DateTimeImmutable( '2026-09-27 12:00:00', new \DateTimeZone( 'UTC' ) ),
                'error',
                'core',
                'Failed for user@example.com Bearer abcdef123456',
                'local',
                '0.1.0',
                '6.6',
                '',
                'req',
                'corr',
                null,
                'core',
                '',
                [ 'api_key' => 'secret-value' ],
                '',
                '',
                'QN-1'
            )
        );
        $json = $this->commands( $database, $logs )->render( $this->commands( $database, $logs )->execute( [ 'diagnostics' ] ), true );

        self::assertStringNotContainsString( 'user@example.com', $json );
        self::assertStringNotContainsString( 'abcdef123456', $json );
        self::assertStringNotContainsString( 'secret-value', $json );
        self::assertStringContainsString( '[redacted]', $json );
    }

    public function testCrawlAndAnalyticsStayQueued(): void {
        $database = new ArrayDatabase();
        $commands = $this->commands( $database );
        $crawl    = $commands->execute( [ 'crawl', 'run' ], [ 'url' => 'https://example.test/' ] );
        $sync     = $commands->execute(
            [ 'analytics', 'sync' ],
            [
                'property' => 'sc-domain:example.test',
                'start'    => '2026-09-01',
                'end'      => '2026-09-27',
            ]
        );
        $jobs     = ( new JobRepository( $database ) )->list( '', 10, 0 );

        self::assertTrue( $crawl['ok'] );
        self::assertSame( 'queued', $crawl['status'] );
        self::assertTrue( $sync['ok'] );
        self::assertSame( 'queued', $sync['status'] );
        self::assertCount( 2, $jobs );
        foreach ( $jobs as $job ) {
            self::assertSame( JobStatus::Pending->value, $job['status'] );
        }
        self::assertFalse( $commands->execute( [ 'crawl', 'run' ], [ 'url' => '' ] )['ok'] );
        self::assertFalse( $commands->execute( [ 'analytics', 'sync' ] )['ok'] );
        $listed = $commands->render( $commands->execute( [ 'jobs', 'list' ] ), true );
        self::assertStringNotContainsString( 'https://example.test/', $listed );
    }

    public function testRetryRequeuesWithoutRunningAndCacheClears(): void {
        $database = new ArrayDatabase();
        $cache    = new MemoryCache();
        $cache->set( 'page', 'stored', 60 );
        $commands = $this->commands( $database, null, $cache );
        $runner   = $this->runner( $database );
        $id       = $runner->enqueue( 'querynova.crawl.batch', [ 'token' => 'secret-token' ], 'dead-job' );
        ( new JobRepository( $database ) )->markDead( $id, 'stopped', 'QN-DEAD', new \DateTimeImmutable( '2026-09-27 12:00:00', new \DateTimeZone( 'UTC' ) ) );

        $retry = $commands->execute( [ 'jobs', 'retry' ], [ 'id' => $id ] );
        $job   = ( new JobRepository( $database ) )->find( $id );
        $commands->execute( [ 'cache', 'clear' ] );

        self::assertSame( [ $id ], $retry['requeued'] );
        self::assertSame( JobStatus::Pending->value, $job['status'] ?? null );
        self::assertNull( $cache->get( 'page' ) );
        self::assertStringContainsString( 'Requeued jobs were not run', (string) $retry['note'] );
    }

    public function testMigrateAppliesPendingVersions(): void {
        $saved = $GLOBALS['querynova_options']['querynova_db_version'] ?? null;
        unset( $GLOBALS['querynova_options']['querynova_db_version'] );
        try {
            $result = $this->commands()->execute( [ 'migrate' ] );
            self::assertTrue( $result['ok'] );
            self::assertNotSame( [], $result['applied'] );
            self::assertSame( [], $this->commands()->execute( [ 'migrate' ] )['applied'] );
        } finally {
            if ( $saved === null ) {
                unset( $GLOBALS['querynova_options']['querynova_db_version'] );
            } else {
                $GLOBALS['querynova_options']['querynova_db_version'] = $saved;
            }
        }
    }

    private function commands( ?ArrayDatabase $database = null, ?LogRepository $logs = null, ?MemoryCache $cache = null ): CliCommands {
        $database  = $database ?? new ArrayDatabase();
        $logs      = $logs ?? new LogRepository( $database );
        $registrar = new MigrationRegistrar();
        $registrar->add( new InitialSchemaMigration() );
        $registrar->add( new PageExperienceMigration() );
        $modules = new ModuleRegistry();
        $modules->add( new CoreModule() );
        $health = new HealthRegistry();
        $health->add( new ModuleHealthCheck( new CoreModule() ) );

        return new CliCommands(
            '0.1.0',
            'local',
            null,
            null,
            new MigrationManager( $database, $registrar, new OptionStore(), new FrozenClock( new \DateTimeImmutable( '2026-09-27 12:00:00', new \DateTimeZone( 'UTC' ) ) ) ),
            $health,
            $modules,
            new JobRepository( $database ),
            $this->runner( $database ),
            $cache ?? new MemoryCache(),
            $logs,
            null
        );
    }

    private function runner( ArrayDatabase $database ): JobRunner {
        return new JobRunner(
            new JobRepository( $database ),
            new JobRegistrar(),
            new RetryPolicy(),
            new FrozenClock( new \DateTimeImmutable( '2026-09-27 12:00:00', new \DateTimeZone( 'UTC' ) ) ),
            CorrelationContext::fresh()
        );
    }
}
