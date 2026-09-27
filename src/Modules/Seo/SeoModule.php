<?php
/**
 * Core SEO module.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Hooks\HookRegistrar;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\WordPressMetaStore;
use QueryNova\Modules\Seo\Presentation\FrontendSeoSubscriber;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SeoModule extends AbstractModule {

    public function getName(): string {
        return 'seo';
    }

    public function register( ContainerInterface $container ): void {
        $container->set( SeoMetaService::class, new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() ) );
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.seo',
                    'Core SEO',
                    'seo',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::MANAGE_SEO ],
                )
            );
        }
    }

    public function registerHooks( HookRegistrar $hooks ): void {
        $hooks->add( new FrontendSeoSubscriber( new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() ) ) );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'GET', '/seo/meta', [ $this, 'show' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/seo/meta', [ $this, 'update' ], Capability::MANAGE_SEO );
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        $objectId = (int) $request->get_param( 'object_id' );
        $service  = new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() );
        $document = $service->resolve(
            'post',
            $objectId,
            [
				'title'    => '',
				'sep'      => '-',
				'sitename' => '',
				'excerpt'  => '',
			],
			[]
        );

        return $document->toArray();
    }

    /**
     * @return array<string, string>
     */
    public function update( \WP_REST_Request $request ): array {
        $objectId = (int) $request->get_param( 'object_id' );
        $fields   = $request->get_json_params();
        if ( ! is_array( $fields ) ) {
            $fields = [];
        }
        $clean = [];
        foreach ( $fields as $key => $value ) {
            if ( is_string( $key ) && is_string( $value ) ) {
                $clean[ $key ] = $value;
            }
        }
        $service = new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() );
        $service->save( 'post', $objectId, $clean );

        return [ 'status' => 'saved' ];
    }
}
