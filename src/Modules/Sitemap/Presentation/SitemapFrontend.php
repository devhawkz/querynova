<?php
/**
 * Serves sitemap XML and the robots.txt sitemap line.
 *
 * Rendering reads the local catalog only. It does not call providers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Presentation;

use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Infrastructure\Cache\CacheInterface;
use QueryNova\Modules\Sitemap\Application\EnabledChannels;
use QueryNova\Modules\Sitemap\Application\SitemapChannels;
use QueryNova\Modules\Sitemap\Application\RobotsSitemapLine;
use QueryNova\Modules\Sitemap\Application\SitemapBuilder;
use QueryNova\Modules\Sitemap\Application\SitemapRenderer;
use QueryNova\Modules\Sitemap\Application\SitemapSettings;
use QueryNova\Modules\Sitemap\Domain\ContentCatalog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SitemapFrontend implements HookSubscriberInterface {

    public function __construct(
        private readonly SitemapBuilder $builder,
        private readonly ContentCatalog $catalog,
        private readonly CacheInterface $cache,
        private readonly FeatureRegistry $features,
        private readonly EnvironmentInterface $environment,
        private readonly SitemapSettings $settings,
        private readonly EnabledChannels $channels,
        private readonly RobotsSitemapLine $robotsLine,
    ) {
    }

    public function hooks(): array {
        return [
            'init'                        => [ 'registerRewrites', 1, 0 ],
            'querynova_register_rewrites' => [ 'registerRewrites', 10, 0 ],
            'query_vars'                  => [ 'queryVars', 10, 1 ],
            'template_redirect'           => [ 'serve', 0, 0 ],
            'robots_txt'                  => [ 'robots', 10, 2 ],
            'save_post'                   => [ 'invalidatePost', 10, 1 ],
            'deleted_post'                => [ 'invalidatePost', 10, 1 ],
            'edited_term'                 => [ 'invalidateTerm', 10, 0 ],
        ];
    }

    public function hookType( string $hook ): string {
        return in_array( $hook, [ 'query_vars', 'robots_txt' ], true ) ? 'filter' : 'action';
    }

    public function registerRewrites(): void {
        if ( ! $this->enabled() || ! function_exists( 'add_rewrite_rule' ) ) {
            return;
        }
        add_rewrite_rule( '^querynova-sitemap\.xml$', 'index.php?querynova_sitemap=index', 'top' );
        add_rewrite_rule(
            '^querynova-sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$',
            'index.php?querynova_sitemap=$matches[1]&querynova_sitemap_page=$matches[2]',
            'top'
        );
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function queryVars( array $vars ): array {
        $vars[] = 'querynova_sitemap';
        $vars[] = 'querynova_sitemap_page';

        return $vars;
    }

    public function serve(): void {
        if ( ! $this->enabled() || ! function_exists( 'get_query_var' ) ) {
            return;
        }
        $kind = get_query_var( 'querynova_sitemap' );
        if ( ! is_string( $kind ) || $kind === '' ) {
            return;
        }
        $now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
        $xml = $kind === 'index'
            ? $this->renderer()->index( $this->baseUrl() )
            : $this->renderer()->urlset( $kind, (int) get_query_var( 'querynova_sitemap_page' ), $now );
        if ( ! is_string( $xml ) || $xml === '' ) {
            $this->respond( 404, '' );

            return;
        }
        $this->respond( 200, $xml );
    }

    public function robots( string $output, bool $isPublic ): string {
        $url = $this->baseUrl() . 'querynova-sitemap.xml';
        if ( function_exists( 'esc_url' ) ) {
            $url = esc_url( $url );
        }

        return $this->robotsLine->append( $output, $url, $isPublic, $this->enabled() );
    }

    public function invalidatePost( int $postId ): void {
        if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $postId ) ) {
            return;
        }
        if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $postId ) ) {
            return;
        }
        $this->settings->bumpGeneration();
    }

    public function invalidateTerm(): void {
        $this->settings->bumpGeneration();
    }

    private function renderer(): SitemapRenderer {
        $types = SitemapChannels::limit(
            $this->channels->types( $this->features, $this->environment, $this->settings->newsName() ),
            SitemapChannels::localEnabled(),
            $this->settings->newsName()
        );

        return new SitemapRenderer(
            $this->builder,
            $this->catalog,
            $this->cache,
            $this->settings->generation(),
            $types,
            $this->settings->newsName(),
            $this->settings->newsLanguage()
        );
    }

    private function enabled(): bool {
        return $this->features->isEnabled( 'querynova.sitemap', $this->environment );
    }

    private function baseUrl(): string {
        if ( ! function_exists( 'home_url' ) ) {
            return '';
        }
        $url = home_url( '/' );

        return is_string( $url ) ? $url : '';
    }

    private function respond( int $status, string $xml ): void {
        if ( function_exists( 'status_header' ) ) {
            status_header( $status );
        }
        if ( $status !== 200 ) {
            exit;
        }
        if ( ! headers_sent() ) {
            header( 'Content-Type: application/xml; charset=UTF-8' );
            header( 'X-Robots-Tag: noindex, follow' );
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SitemapXml escapes every text node.
        echo $xml;
        exit;
    }
}
