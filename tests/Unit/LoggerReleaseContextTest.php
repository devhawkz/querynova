<?php
/**
 * Log context records the WordPress environment and the installed build.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\Logging\CorrelationContext;
use QueryNova\Core\Logging\Handler\LogHandlerInterface;
use QueryNova\Core\Logging\Logger;
use QueryNova\Core\Logging\LogRecord;
use QueryNova\Core\Logging\LogSanitizer;
use QueryNova\Core\ReleaseProfile;
use QueryNova\Core\Support\FrozenClock;

final class LoggerReleaseContextTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_environment'] );
    }

    public function testContextKeepsProductionWhenTheBuildIsStaging(): void {
        $GLOBALS['querynova_environment'] = 'production';
        $handler                          = new class() implements LogHandlerInterface {
            public ?LogRecord $record = null;

            public function handle( LogRecord $record ): void {
                $this->record = $record;
            }
        };
        $logger                           = new Logger(
            [ $handler ],
            new LogSanitizer(),
            new WordPressEnvironment(),
            new FrozenClock( new \DateTimeImmutable( '2026-09-28 12:00:00', new \DateTimeZone( 'UTC' ) ) ),
            CorrelationContext::fresh(),
            'debug',
            ReleaseProfile::assess( 'production', 'staging' ),
        );

        $logger->info( 'Diagnostics opened' );
        self::assertInstanceOf( LogRecord::class, $handler->record );
        self::assertSame( 'production', $handler->record->environment );
        self::assertSame( 'production', $handler->record->context['wordpress_environment'] );
        self::assertSame( 'staging', $handler->record->context['querynova_build'] );
        self::assertSame( 'beta', $handler->record->context['release_channel'] );
        self::assertSame( 'warning', $handler->record->context['release_status'] );
        self::assertSame( ReleaseProfile::STAGING_ON_PRODUCTION, $handler->record->context['release_notice'] );
    }
}
