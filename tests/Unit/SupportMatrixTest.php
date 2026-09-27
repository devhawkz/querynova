<?php
/**
 * The support matrix names PHP, WordPress, and WooCommerce without inventing runs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Requirements;
use QueryNova\Core\SupportMatrix;

final class SupportMatrixTest extends TestCase {

    public function testOnlyTheRunningPhpVersionIsObserved(): void {
        $matrix = ( new SupportMatrix() )->combinations();
        $header = (string) file_get_contents( QUERYNOVA_PATH . 'querynova.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin header, not a remote request.
        $ci     = (string) file_get_contents( QUERYNOVA_PATH . '.github/workflows/ci.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local workflow file, not a remote request.

        $running = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        self::assertSame( '8.1', $matrix['php']['minimum'] );
        self::assertSame( [ '8.1', '8.3' ], $matrix['php']['ci'] );
        self::assertSame( [ $running ], $matrix['php']['observed'] );
        foreach ( $matrix['php']['ci'] as $version ) {
            if ( $version !== $running ) {
                self::assertNotContains( $version, $matrix['php']['observed'] );
            }
        }
        self::assertStringContainsString( "php: ['8.1', '8.3']", $ci );
        self::assertStringContainsString( 'Requires PHP: 8.1', $header );
        self::assertSame( [], ( new Requirements() )->evaluate( '8.1.0', '6.4', true, true ) );
        self::assertNotSame( [], ( new Requirements() )->evaluate( '8.0.30', '6.4', true, true ) );
    }

    public function testWordPressAndWooCommerceReleasesAreNotMarkedTested(): void {
        $matrix = ( new SupportMatrix() )->combinations();
        $header = (string) file_get_contents( QUERYNOVA_PATH . 'querynova.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin header, not a remote request.

        self::assertSame( '6.4', $matrix['wp']['minimum'] );
        self::assertSame( [], $matrix['wp']['observed'] );
        self::assertFalse( $matrix['wc']['required'] );
        self::assertSame( 'api', $matrix['wc']['hpos'] );
        self::assertSame( [], $matrix['wc']['observed'] );
        self::assertStringContainsString( 'Requires at least: 6.4', $header );
        self::assertStringNotContainsString( 'WC tested up to', $header );
        self::assertNotSame( [], ( new Requirements() )->evaluate( '8.1.0', '6.3.2', true, true ) );
        self::assertFalse( ( new Requirements() )->requiresWooCommerce() );
    }
}
