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
use QueryNova\Modules\Sitemap\Application\EnabledChannels;
use QueryNova\Modules\Sitemap\Application\RobotsSitemapLine;
use QueryNova\Modules\Sitemap\Application\SitemapBuilder;
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
}
