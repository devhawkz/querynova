<?php
/**
 * WordPress environment and the QueryNova build stay separate.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\ReleaseProfile;
use QueryNova\Infrastructure\Cli\DiagnosticsReport;

final class ReleaseProfileTest extends TestCase {

    protected function tearDown(): void {
        unset( $GLOBALS['querynova_environment'] );
    }

    public function testMatchedBuildsAreNormal(): void {
        $production = ReleaseProfile::assess( 'production', 'production' );
        $staging    = ReleaseProfile::assess( 'staging', 'staging' );
        $local      = ReleaseProfile::assess( 'local', 'development' );

        self::assertSame( 'production', $production->wordpressEnvironment() );
        self::assertSame( 'production', $production->querynovaBuild() );
        self::assertSame( 'stable', $production->releaseChannel() );
        self::assertSame( ReleaseProfile::STATUS_NORMAL, $production->status() );
        self::assertNull( $production->notice() );

        self::assertSame( 'staging', $staging->wordpressEnvironment() );
        self::assertSame( 'beta', $staging->releaseChannel() );
        self::assertSame( ReleaseProfile::STATUS_NORMAL, $staging->status() );
        self::assertNull( $staging->notice() );

        self::assertSame( 'development', $local->releaseChannel() );
        self::assertSame( ReleaseProfile::STATUS_NORMAL, $local->status() );
        self::assertNull( $local->notice() );
    }

    public function testStagingBuildOnProductionWarnsWithoutChangingWordPress(): void {
        $GLOBALS['querynova_environment'] = 'production';
        $environment                      = new WordPressEnvironment();
        $profile                          = ReleaseProfile::assess( $environment->getName(), 'staging' );

        self::assertSame( 'production', $environment->getName() );
        self::assertFalse( $environment->allowsVerboseDiagnostics() );
        self::assertSame( 'production', $profile->wordpressEnvironment() );
        self::assertSame( 'staging', $profile->querynovaBuild() );
        self::assertSame( 'beta', $profile->releaseChannel() );
        self::assertSame( ReleaseProfile::STATUS_WARNING, $profile->status() );
        self::assertSame( ReleaseProfile::STAGING_ON_PRODUCTION, $profile->notice() );
        self::assertStringNotContainsString( 'staging', $profile->wordpressEnvironment() );
    }

    public function testProductionBuildOnStagingIsInformational(): void {
        $profile = ReleaseProfile::assess( 'staging', 'production' );

        self::assertSame( 'staging', $profile->wordpressEnvironment() );
        self::assertSame( 'production', $profile->querynovaBuild() );
        self::assertSame( 'stable', $profile->releaseChannel() );
        self::assertSame( ReleaseProfile::STATUS_INFO, $profile->status() );
        self::assertSame( ReleaseProfile::PRODUCTION_ON_STAGING, $profile->notice() );
        self::assertNotSame( ReleaseProfile::STAGING_ON_PRODUCTION, $profile->notice() );
    }

    public function testUnknownWordPressTypeStaysProduction(): void {
        $GLOBALS['querynova_environment'] = 'not-a-type';
        $profile                          = ReleaseProfile::assess( 'not-a-type', 'staging' );

        self::assertSame( 'production', ( new WordPressEnvironment() )->getName() );
        self::assertSame( 'production', $profile->wordpressEnvironment() );
        self::assertSame( 'staging', $profile->querynovaBuild() );
        self::assertSame( ReleaseProfile::STATUS_WARNING, $profile->status() );
        self::assertSame( ReleaseProfile::STAGING_ON_PRODUCTION, $profile->notice() );
    }

    public function testOtherMismatchesStayInformational(): void {
        $profile = ReleaseProfile::assess( 'production', 'development' );

        self::assertSame( 'production', $profile->wordpressEnvironment() );
        self::assertSame( 'development', $profile->querynovaBuild() );
        self::assertSame( ReleaseProfile::STATUS_INFO, $profile->status() );
        self::assertSame( ReleaseProfile::OTHER_MISMATCH, $profile->notice() );
        self::assertNotSame( ReleaseProfile::STAGING_ON_PRODUCTION, $profile->notice() );
    }

    public function testDiagnosticsKeepTheWordPressEnvironmentAndAnEmptyRead(): void {
        $empty  = DiagnosticsReport::build( [] );
        $report = DiagnosticsReport::build(
            [
                'environment'     => 'production',
                'querynova_build' => 'staging',
            ]
        );

        self::assertSame( '', $empty['environment'] );
        self::assertSame( '', $empty['querynova_build'] );
        self::assertNull( $empty['release_notice'] );
        self::assertSame( 'production', $report['environment'] );
        self::assertSame( 'staging', $report['querynova_build'] );
        self::assertSame( 'beta', $report['release_channel'] );
        self::assertSame( 'warning', $report['release_status'] );
        self::assertSame( ReleaseProfile::STAGING_ON_PRODUCTION, $report['release_notice'] );
    }
}
