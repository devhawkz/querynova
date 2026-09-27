<?php
/**
 * Debug mode expiry.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Logging\DebugMode;
use QueryNova\Core\Support\FrozenClock;
use QueryNova\Infrastructure\WordPress\OptionStore;

final class DebugModeTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['querynova_options']     = [];
        $GLOBALS['querynova_environment'] = 'production';
    }

    public function testProductionDefaultsToInfoAndDebugExpires(): void {
        $clock       = new FrozenClock( new \DateTimeImmutable( '2026-09-27 12:00:00', new \DateTimeZone( 'UTC' ) ) );
        $debug       = new DebugMode( new OptionStore(), $clock );
        $environment = new WordPressEnvironment();

        self::assertSame( LogLevel::INFO, $debug->minimumLevel( $environment ) );
        $debug->enable( 900 );
        self::assertSame( LogLevel::DEBUG, $debug->minimumLevel( $environment ) );
        $clock->advance( '+901 seconds' );
        self::assertSame( LogLevel::INFO, $debug->minimumLevel( $environment ) );
    }

    public function testUnknownDurationIsRejected(): void {
        $debug = new DebugMode(
            new OptionStore(),
            new FrozenClock( new \DateTimeImmutable( '2026-09-27 12:00:00', new \DateTimeZone( 'UTC' ) ) ),
        );
        $this->expectException( ValidationException::class );
        $debug->enable( 10 );
    }
}
