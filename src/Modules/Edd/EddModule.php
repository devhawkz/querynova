<?php
/**
 * Optional Easy Digital Downloads module. It does not load when EDD is absent.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Edd;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Rest\RestRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EddModule extends AbstractModule {

    public function getName(): string {
        return 'edd';
    }

    public function isOptional(): bool {
        return true;
    }

    public static function present(): bool {
        return class_exists( 'Easy_Digital_Downloads' ) || defined( 'EDD_VERSION' );
    }

    public function register( ContainerInterface $container ): void {
        if ( ! self::present() ) {
            return;
        }
        $features = $container->get( FeatureRegistry::class );
        if ( ! $features instanceof FeatureRegistry ) {
            return;
        }
        $features->register(
            new Feature(
                'querynova.edd',
                'Easy Digital Downloads',
                'edd',
                QUERYNOVA_VERSION,
                [],
                LicenseTier::Free,
                [],
                FeatureFlagState::On,
                [ Capability::MANAGE_WOOCOMMERCE_SEO ],
            )
        );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        if ( ! self::present() ) {
            return;
        }
        $rest->route( 'GET', '/edd/status', [ $this, 'status' ], Capability::MANAGE_WOOCOMMERCE_SEO );
    }

    /**
     * @return array<string, mixed>
     */
    public function status( \WP_REST_Request $request ): array {
        unset( $request );

        return [
            'active'    => true,
            'downloads' => null,
            'note'      => 'Easy Digital Downloads is active. Download URLs were not changed.',
        ];
    }
}
