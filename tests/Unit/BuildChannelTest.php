<?php
/**
 * Staging and production builds declare a channel. Debug stays off.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\BuildChannel;

final class BuildChannelTest extends TestCase {

    public function testAMissingManifestIsUnknownAndDebugStaysOff(): void {
        $channel = ( new BuildChannel( sys_get_temp_dir() . '/querynova-missing-channel.json' ) )->read();

        self::assertSame( 'unknown', $channel['channel'] );
        self::assertFalse( $channel['diagnostics'] );
        self::assertFalse( $channel['debugDefault'] );
    }

    public function testStagingEnablesDiagnosticsAndProductionDoesNot(): void {
        $staging    = $this->manifest(
            [
                'channel'      => 'staging',
                'diagnostics'  => false,
                'debugDefault' => true,
            ]
        );
        $production = $this->manifest(
            [
                'channel'      => 'production',
                'diagnostics'  => true,
                'debugDefault' => true,
            ]
        );

        self::assertSame( 'staging', $staging['channel'] );
        self::assertTrue( $staging['diagnostics'] );
        self::assertFalse( $staging['debugDefault'] );
        self::assertSame( 'production', $production['channel'] );
        self::assertFalse( $production['diagnostics'] );
        self::assertFalse( $production['debugDefault'] );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{channel: string, diagnostics: bool, debugDefault: false}
     */
    private function manifest( array $payload ): array {
        $path = tempnam( sys_get_temp_dir(), 'qn-channel-' );
        self::assertIsString( $path );
        file_put_contents( $path, (string) wp_json_encode( $payload ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local temp manifest, not a remote request.

        try {
            return ( new BuildChannel( $path ) )->read();
        } finally {
            unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- local temp manifest, not a WordPress upload.
        }
    }
}
