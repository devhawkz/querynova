<?php
/**
 * REST for robots, .htaccess, webmaster codes, RSS, ALT, IndexNow, and breadcrumbs.
 *
 * These routes do not write a physical file unless that class is called with an existing path.
 * The HTTP handlers never pass a path.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

use QueryNova\Modules\Content\Application\LinkBoard;
use QueryNova\Modules\Seo\Application\Breadcrumbs;
use QueryNova\Modules\Seo\Application\HtaccessEditor;
use QueryNova\Modules\Seo\Application\ImageAlt;
use QueryNova\Modules\Seo\Application\IndexNowPlan;
use QueryNova\Modules\Seo\Application\RobotsEditor;
use QueryNova\Modules\Seo\Application\RssSupplement;
use QueryNova\Modules\Seo\Application\WebmasterCodes;
use QueryNova\Modules\Sitemap\Application\SitemapChannels;
use QueryNova\Modules\Sitemap\Application\SitemapSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SiteToolsController {

    /**
     * Reads stored settings only. Opening the admin screen does not write files or options.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(): array {
        $news = ( new SitemapSettings() )->newsName();

        return [
            'sitemaps'    => SitemapChannels::read( SitemapChannels::localEnabled(), $news ),
            'robots'      => RobotsEditor::read(),
            'webmaster'   => WebmasterCodes::read(),
            'rss'         => RssSupplement::read(),
            'breadcrumbs' => Breadcrumbs::read(),
            'links'       => LinkBoard::present( [] ),
            'server'      => HtaccessEditor::serverSoftware(),
            'note'        => 'Opening this screen does not write files, ALT text, redirects, or sitemap rewrites.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function robots( \WP_REST_Request $request ): array {
        unset( $request );

        return RobotsEditor::read();
    }

    /**
     * @return array<string, mixed>
     */
    public function saveRobots( \WP_REST_Request $request ): array {
        $params = $this->json( $request );

        return RobotsEditor::save(
            is_string( $params['content'] ?? null ) ? $params['content'] : '',
            ( $params['allow_file'] ?? false ) === true,
            ( $params['confirm'] ?? false ) === true,
            ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function htaccess( \WP_REST_Request $request ): array {
        unset( $request );

        return [
            'server'     => HtaccessEditor::serverSoftware(),
            'visibility' => HtaccessEditor::visibility( HtaccessEditor::serverSoftware(), true ),
            'note'       => 'The physical .htaccess file is not read on this request.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saveHtaccess( \WP_REST_Request $request ): array {
        $params = $this->json( $request );

        return HtaccessEditor::save(
            is_string( $params['content'] ?? null ) ? $params['content'] : '',
            HtaccessEditor::serverSoftware(),
            ( $params['advanced'] ?? false ) === true,
            ( $params['confirm'] ?? false ) === true,
            ( $params['write_file'] ?? false ) === true,
            ''
        );
    }

    /**
     * @return array<string, string>
     */
    public function webmaster( \WP_REST_Request $request ): array {
        unset( $request );

        return WebmasterCodes::read();
    }

    /**
     * @return array<string, string>
     */
    public function saveWebmaster( \WP_REST_Request $request ): array {
        return WebmasterCodes::save( $this->json( $request ) );
    }

    /**
     * @return array{enabled: bool, before: string, after: string}
     */
    public function rss( \WP_REST_Request $request ): array {
        unset( $request );

        return RssSupplement::read();
    }

    /**
     * @return array{enabled: bool, before: string, after: string}
     */
    public function saveRss( \WP_REST_Request $request ): array {
        return RssSupplement::save( $this->json( $request ) );
    }

    /**
     * @return array{separator: string, home: string}
     */
    public function breadcrumbs( \WP_REST_Request $request ): array {
        unset( $request );

        return Breadcrumbs::read();
    }

    /**
     * @return array{separator: string, home: string}
     */
    public function saveBreadcrumbs( \WP_REST_Request $request ): array {
        return Breadcrumbs::save( $this->json( $request ) );
    }

    /**
     * @return array<string, mixed>
     */
    public function breadcrumbPreview( \WP_REST_Request $request ): array {
        $params = $this->json( $request );
        $crumbs = [];
        if ( isset( $params['crumbs'] ) && is_array( $params['crumbs'] ) ) {
            foreach ( $params['crumbs'] as $crumb ) {
                if ( ! is_array( $crumb ) ) {
                    continue;
                }
                $crumbs[] = [
                    'label' => is_string( $crumb['label'] ?? null ) ? $crumb['label'] : '',
                    'url'   => is_string( $crumb['url'] ?? null ) ? $crumb['url'] : '',
                ];
            }
        }

        return [
            'html'   => Breadcrumbs::html( $crumbs ),
            'schema' => Breadcrumbs::schema( $crumbs ),
            'saved'  => false,
            'note'   => 'This preview is not inserted into the page.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function imageAlt( \WP_REST_Request $request ): array {
        $params = $this->json( $request );
        $manual = array_key_exists( 'manual', $params ) ? $params['manual'] === true : true;

        return ImageAlt::suggest(
            is_string( $params['current'] ?? null ) ? $params['current'] : '',
            is_string( $params['suggestion'] ?? null ) ? $params['suggestion'] : '',
            $manual,
            ( $params['overwrite'] ?? false ) === true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function indexNow( \WP_REST_Request $request ): array {
        $params = $this->json( $request );

        return IndexNowPlan::plan( $this->strings( $params['urls'] ?? [] ), $this->strings( $params['noindex'] ?? [] ) );
    }

    /**
     * @return array<string, mixed>
     */
    private function json( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return is_array( $params ) ? $params : [];
    }

    /**
     * @return list<string>
     */
    private function strings( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }
        $rows = [];
        foreach ( $value as $item ) {
            if ( is_string( $item ) && $item !== '' ) {
                $rows[] = $item;
            }
        }

        return $rows;
    }
}
