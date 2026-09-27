<?php
/**
 * Stored instants stay in UTC. Display uses the WordPress timezone.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Support\SystemClock;
use QueryNova\Core\Support\TimezoneFormatter;

final class TimezoneTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['qn_timezone'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores the timezone under this key.
        parent::tearDown();
    }

    public function testTheClockStoresUtc(): void {
        $now = ( new SystemClock() )->now();

        self::assertSame( 'UTC', $now->getTimezone()->getName() );
    }

    public function testDisplayUsesTheWordPressTimezoneAndLeavesTheStoredInstant(): void {
        $GLOBALS['qn_timezone'] = 'Europe/Belgrade'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores the timezone under this key.
        $stored                 = new \DateTimeImmutable( '2026-01-15 12:00:00', new \DateTimeZone( 'UTC' ) );
        $summer                 = new \DateTimeImmutable( '2026-07-15 12:00:00', new \DateTimeZone( 'UTC' ) );

        self::assertSame( '2026-01-15 13:00', ( new TimezoneFormatter() )->display( $stored ) );
        self::assertSame( '2026-07-15 14:00', ( new TimezoneFormatter() )->display( $summer ) );
        self::assertSame( 'UTC', $stored->getTimezone()->getName() );
        self::assertSame( '2026-01-15 12:00:00', $stored->format( 'Y-m-d H:i:s' ) );
    }

    public function testDisplayStaysUtcWhenWordPressHasNoTimezone(): void {
        $stored = new \DateTimeImmutable( '2026-01-15 12:00:00', new \DateTimeZone( 'UTC' ) );

        self::assertSame( '2026-01-15 12:00', ( new TimezoneFormatter() )->display( $stored ) );
    }
}
