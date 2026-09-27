<?php
/**
 * Feature flag tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;

final class FeatureRegistryTest extends TestCase {

    public function testSiteOverrideTurnsAFeatureOff(): void {
        $registry = $this->registry();
        $registry->overrideForSite( 'querynova.child', FeatureFlagState::Off );
        $GLOBALS['querynova_environment'] = 'production';

        self::assertFalse( $registry->isEnabled( 'querynova.child', new WordPressEnvironment() ) );
    }

    public function testDisabledDependencyDisablesTheChild(): void {
        $registry = $this->registry();
        $registry->overrideForSite( 'querynova.parent', FeatureFlagState::Off );
        $GLOBALS['querynova_environment'] = 'local';

        self::assertFalse( $registry->isEnabled( 'querynova.child', new WordPressEnvironment() ) );
    }

    private function registry(): FeatureRegistry {
        $registry = new FeatureRegistry();
        $registry->register(
            new Feature(
                'querynova.parent',
                'Parent',
                'core',
                '0.1.0',
                [],
                LicenseTier::Free,
                [],
                FeatureFlagState::On,
                [],
            )
        );
        $registry->register(
            new Feature(
                'querynova.child',
                'Child',
                'core',
                '0.1.0',
                [ 'querynova.parent' ],
                LicenseTier::Pro,
                [],
                FeatureFlagState::On,
                [],
            )
        );

        return $registry;
    }
}
