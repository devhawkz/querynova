<?php
/**
 * Settings catalog tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Container\ServiceContainer;
use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Modules\ModuleRegistry;
use QueryNova\Core\Security\Capability;
use QueryNova\Modules\Core\SettingsCatalog;

final class SettingsCatalogTest extends TestCase {

    public function testSnapshotKeepsEnvironmentAndBuildApartAndLeavesAnOffFeatureOff(): void {
        $options = $GLOBALS['querynova_options'] ?? null;
        $modules = new ModuleRegistry();
        $modules->add(
            new class() extends AbstractModule {
                public function getName(): string {
                    return 'core';
                }

                public function getDependencies(): array {
                    return [];
                }

                public function isOptional(): bool {
                    return false;
                }
            }
        );
        $modules->add(
            new class() extends AbstractModule {
                public function getName(): string {
                    return 'sitemap';
                }
            }
        );
        $modules->recordFailure( 'sitemap', new \RuntimeException( 'secret-trace' ) );

        $features = new FeatureRegistry();
        $features->register(
            new Feature(
                'querynova.sitemap',
                'XML sitemaps',
                'sitemap',
                '0.1.0',
                [],
                LicenseTier::Free,
                [],
                FeatureFlagState::On,
                [],
            )
        );
        $features->register(
            new Feature(
                'querynova.sitemap.news',
                'News sitemap',
                'sitemap',
                '0.1.0',
                [ 'querynova.sitemap' ],
                LicenseTier::Free,
                [],
                FeatureFlagState::Off,
                [],
            )
        );

        $snapshot = SettingsCatalog::describe( $modules, $features, $this->environment( 'production' ), 'staging' );
        $encoded  = (string) wp_json_encode( $snapshot );

        self::assertSame( 'production', $snapshot['wordpress_environment'] );
        self::assertSame( 'staging', $snapshot['querynova_build'] );
        self::assertSame( $options, $GLOBALS['querynova_options'] ?? null );
        self::assertStringNotContainsString( 'secret-trace', $encoded );

        $cards = $snapshot['modules'];
        self::assertIsArray( $cards );
        self::assertCount( 2, $cards );
        self::assertIsArray( $cards[0] );
        self::assertFalse( $cards[0]['optional'] );
        self::assertFalse( $cards[0]['failed'] );
        self::assertIsArray( $cards[1] );
        self::assertTrue( $cards[1]['failed'] );
        self::assertIsArray( $cards[1]['features'] );
        self::assertSame( 'ON', $cards[1]['features'][0]['state'] );
        self::assertTrue( $cards[1]['features'][0]['enabled'] );
        self::assertSame( 'OFF', $cards[1]['features'][1]['state'] );
        self::assertFalse( $cards[1]['features'][1]['enabled'] );
        self::assertArrayNotHasKey( 'message', $cards[1] );

        $developer = $this->role( $snapshot['roles'], 'querynova_developer' );
        self::assertNotContains( Capability::MANAGE_SEO, $developer );
        self::assertContains( Capability::MANAGE_SETTINGS, $developer );
    }

    public function testMissingRegistriesStayEmptyWithoutRewritingTheEnvironment(): void {
        $snapshot = SettingsCatalog::fromContainer( new ServiceContainer(), $this->environment( 'staging' ), 'production' );

        self::assertSame( 'staging', $snapshot['wordpress_environment'] );
        self::assertSame( 'production', $snapshot['querynova_build'] );
        self::assertSame( [], $snapshot['modules'] );
        self::assertIsArray( $snapshot['roles'] );
        self::assertNotEmpty( $snapshot['roles'] );
    }

    /**
     * @param mixed $roles
     * @return list<string>
     */
    private function role( mixed $roles, string $name ): array {
        self::assertIsArray( $roles );
        foreach ( $roles as $role ) {
            if ( is_array( $role ) && ( $role['role'] ?? '' ) === $name ) {
                $capabilities = $role['capabilities'] ?? [];
                self::assertIsArray( $capabilities );

                return array_values( $capabilities );
            }
        }

        self::fail( 'Missing role ' . $name );

        return [];
    }

    private function environment( string $name ): EnvironmentInterface {
        return new class( $name ) implements EnvironmentInterface {

            public function __construct( private readonly string $name ) {
            }

            public function getName(): string {
                return $this->name;
            }

            public function isLocal(): bool {
                return $this->name === 'local';
            }

            public function isDevelopment(): bool {
                return $this->name === 'development';
            }

            public function isStaging(): bool {
                return $this->name === 'staging';
            }

            public function isProduction(): bool {
                return $this->name === 'production';
            }

            public function allowsVerboseDiagnostics(): bool {
                return ! $this->isProduction();
            }
        };
    }
}
