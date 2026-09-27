<?php
/**
 * Plugin, schema, API, and methodology versions are reported separately.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
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

    public function testChannelFollowsTheWordPressEnvironment(): void {
        $versions                         = new ProductVersions();
        $GLOBALS['querynova_environment'] = 'staging';
        self::assertSame( 'beta', $versions->channel() );
        $GLOBALS['querynova_environment'] = 'local';
        self::assertSame( 'development', $versions->channel() );
        $GLOBALS['querynova_environment'] = 'production';
        self::assertSame( 'stable', $versions->channel() );
        unset( $GLOBALS['querynova_environment'] );
    }

    public function testStatusIncludesTheVersionSet(): void {
        $status = ( new CoreModule() )->status();

        self::assertSame( QUERYNOVA_VERSION, $status['versions']['plugin'] );
        self::assertSame( 'v1', $status['versions']['api'] );
        self::assertArrayHasKey( 'schema_version', $status );
    }
}
