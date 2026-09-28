<?php
/**
 * Optional local SEO module. It is omitted unless the option is exactly true.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Local;

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

final class LocalModule extends AbstractModule {

    public function getName(): string {
        return 'local';
    }

    public function isOptional(): bool {
        return true;
    }

    public function register( ContainerInterface $container ): void {
        if ( ! LocalGate::enabled() ) {
            return;
        }
        $features = $container->get( FeatureRegistry::class );
        if ( ! $features instanceof FeatureRegistry ) {
            return;
        }
        $features->register(
            new Feature(
                'querynova.local',
                'Local SEO',
                'local',
                QUERYNOVA_VERSION,
                [],
                LicenseTier::Free,
                [],
                FeatureFlagState::On,
                [ Capability::MANAGE_SEO ],
            )
        );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        if ( ! LocalGate::enabled() ) {
            return;
        }
        $rest->route( 'GET', '/local/status', [ $this, 'status' ], Capability::MANAGE_SEO );
    }

    /**
     * @return array<string, mixed>
     */
    public function status( \WP_REST_Request $request ): array {
        unset( $request );

        return LocalGate::present();
    }
}
