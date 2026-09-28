<?php
/**
 * Plugin, schema, API, and methodology versions are reported separately.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\ProductVersions;
use QueryNova\Modules\Ai\Application\AiVisibility;
use QueryNova\Modules\Content\Infrastructure\ContentRepository;
use QueryNova\Modules\Core\CoreModule;
use QueryNova\Modules\Keywords\Application\KeywordIntelligence;

final class ProductVersionsTest extends TestCase {

    public function testVersionsStaySeparate(): void {
        $versions = ( new ProductVersions() )->describe();

        self::assertSame( QUERYNOVA_VERSION, $versions['plugin'] );
        self::assertSame( QUERYNOVA_DB_VERSION, $versions['schema'] );
        self::assertSame( 'v1', $versions['api'] );
        self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $versions['plugin'] );
        self::assertNotSame( $versions['plugin'], $versions['api'] );
        self::assertSame( KeywordIntelligence::DIFFICULTY_VERSION, $versions['methodologies'][ KeywordIntelligence::DIFFICULTY_METHOD ] );
        self::assertSame( ContentRepository::METHODOLOGY_VERSION, $versions['methodologies'][ ContentRepository::METHODOLOGY ] );
        self::assertSame( AiVisibility::VERSION, $versions['methodologies'][ AiVisibility::METHODOLOGY ] );
        self::assertArrayNotHasKey( 'querynova.ctr_revenue_gap', $versions['methodologies'] );
    }

    public function testChannelFollowsTheInstalledBuild(): void {
        $path = tempnam( sys_get_temp_dir(), 'qn-channel-' );
        self::assertIsString( $path );
        file_put_contents( $path, '{"channel":"staging"}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local temp manifest, not a remote request.
        $GLOBALS['querynova_environment'] = 'production';

        try {
            self::assertSame( 'beta', ( new ProductVersions( $path ) )->channel() );
            self::assertSame( 'production', ( new WordPressEnvironment() )->getName() );
        } finally {
            unset( $GLOBALS['querynova_environment'] );
            unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- local temp manifest, not a WordPress upload.
        }
    }

    public function testStatusIncludesTheVersionSet(): void {
        $status = ( new CoreModule() )->status();

        self::assertSame( QUERYNOVA_VERSION, $status['versions']['plugin'] );
        self::assertSame( 'v1', $status['versions']['api'] );
        self::assertArrayHasKey( 'schema_version', $status );
    }
}
