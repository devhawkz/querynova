<?php
/**
 * XML sitemaps for public, indexable URLs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Hooks\HookRegistrar;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Cache\CacheInterface;
use QueryNova\Infrastructure\Cache\WordPressObjectCache;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Sitemap\Application\EnabledChannels;
use QueryNova\Modules\Sitemap\Application\RobotsSitemapLine;
use QueryNova\Modules\Sitemap\Application\SitemapBuilder;
use QueryNova\Modules\Sitemap\Application\SitemapChannels;
use QueryNova\Modules\Sitemap\Application\SitemapSettings;
use QueryNova\Modules\Sitemap\Application\SitemapXml;
use QueryNova\Modules\Sitemap\Domain\ContentCatalog;
use QueryNova\Modules\Sitemap\Domain\MediaExtractor;
use QueryNova\Modules\Sitemap\Domain\SitemapPolicy;
use QueryNova\Modules\Sitemap\Infrastructure\WordPressContentCatalog;
use QueryNova\Modules\Sitemap\Presentation\SitemapFrontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapModule extends AbstractModule {

    private ?SitemapFrontend $frontend = null;

    public function getName(): string {
        return 'sitemap';
    }

    public function getDependencies(): array {
        return [ 'core', 'seo' ];
    }

    public function register( ContainerInterface $container ): void {
        $settings    = new SitemapSettings();
        $builder     = new SitemapBuilder( new SitemapPolicy( $settings->newsName() ), new SitemapXml() );
        $catalog     = new WordPressContentCatalog( new MediaExtractor() );
        $cache       = $container->get( WordPressObjectCache::class );
        $features    = $container->get( FeatureRegistry::class );
        $environment = $container->get( WordPressEnvironment::class );
        $container->set( SitemapBuilder::class, $builder );
        $container->set( ContentCatalog::class, $catalog );

        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.sitemap',
                    'XML sitemaps',
                    'sitemap',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::MANAGE_SEO ],
                )
            );
            $features->register(
                new Feature(
                    'querynova.sitemap.news',
                    'News sitemap',
                    'sitemap',
                    QUERYNOVA_VERSION,
                    [ 'querynova.sitemap' ],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::Off,
                    [ Capability::MANAGE_SEO ],
                )
            );
        }

        if ( ! $features instanceof FeatureRegistry || ! $environment instanceof WordPressEnvironment || ! $cache instanceof CacheInterface ) {
            return;
        }

        $this->frontend = new SitemapFrontend(
            $builder,
            $catalog,
            $cache,
            $features,
            $environment,
            $settings,
            new EnabledChannels(),
            new RobotsSitemapLine()
        );
    }

    public function registerHooks( HookRegistrar $hooks ): void {
        if ( $this->frontend instanceof SitemapFrontend ) {
            $hooks->add( $this->frontend );
        }
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'GET', '/sitemap/channels', [ $this, 'channels' ], Capability::MANAGE_SEO );
        $rest->route( 'PUT', '/sitemap/channels', [ $this, 'saveChannels' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/sitemap/html', [ $this, 'html' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/sitemap/kml', [ $this, 'kml' ], Capability::MANAGE_SEO );
    }

    /**
     * @return array<string, mixed>
     */
    public function channels( \WP_REST_Request $request ): array {
        unset( $request );

        return SitemapChannels::read( SitemapChannels::localEnabled(), ( new SitemapSettings() )->newsName() );
    }

    /**
     * @return array<string, mixed>
     */
    public function saveChannels( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return SitemapChannels::save(
            is_array( $params ) ? $params : [],
            SitemapChannels::localEnabled(),
            ( new SitemapSettings() )->newsName()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function html( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $urls   = [];
        if ( is_array( $params ) && isset( $params['urls'] ) && is_array( $params['urls'] ) ) {
            foreach ( $params['urls'] as $url ) {
                if ( is_string( $url ) ) {
                    $urls[] = $url;
                }
            }
        }

        return [
            'html' => SitemapChannels::html( $urls ),
            'note' => 'This HTML sitemap is generated from the supplied URLs. Permalinks were not flushed.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function kml( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $places = [];
        if ( is_array( $params ) && isset( $params['places'] ) && is_array( $params['places'] ) ) {
            foreach ( $params['places'] as $place ) {
                if ( ! is_array( $place ) ) {
                    continue;
                }
                $places[] = [
                    'name'      => is_string( $place['name'] ?? null ) ? $place['name'] : '',
                    'latitude'  => (float) ( $place['latitude'] ?? 0 ),
                    'longitude' => (float) ( $place['longitude'] ?? 0 ),
                ];
            }
        }

        return SitemapChannels::kml( $places, SitemapChannels::localEnabled() );
    }
}
