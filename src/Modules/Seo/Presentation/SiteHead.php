<?php
/**
 * Webmaster meta, RSS text, and breadcrumb shortcode and block.
 *
 * Breadcrumbs are not inserted into every page.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Modules\Seo\Application\Breadcrumbs;
use QueryNova\Modules\Seo\Application\RobotsEditor;
use QueryNova\Modules\Seo\Application\RssSupplement;
use QueryNova\Modules\Seo\Application\WebmasterCodes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SiteHead implements HookSubscriberInterface {

    public function hooks(): array {
        return [
            'wp_head'          => [ 'renderHead', 20, 0 ],
            'the_content_feed' => [ 'feed', 10, 1 ],
            'init'             => [ 'registerPresentation', 10, 0 ],
            'robots_txt'       => [ 'robots', 9, 2 ],
        ];
    }

    public function hookType( string $hook ): string {
        return in_array( $hook, [ 'the_content_feed', 'robots_txt' ], true ) ? 'filter' : 'action';
    }

    public function renderHead(): void {
        $meta = WebmasterCodes::meta( WebmasterCodes::read() );
        if ( $meta === '' ) {
            return;
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WebmasterCodes::meta escapes attributes.
        echo $meta;
    }

    public function feed( string $content ): string {
        return RssSupplement::wrap( $content );
    }

    public function registerPresentation(): void {
        if ( function_exists( 'add_shortcode' ) ) {
            add_shortcode( 'querynova_breadcrumbs', [ $this, 'shortcode' ] );
        }
        if ( function_exists( 'register_block_type' ) ) {
            register_block_type(
                'querynova/breadcrumbs',
                [
                    'render_callback' => [ $this, 'block' ],
                    'attributes'      => [
                        'label' => [
                            'type'    => 'string',
                            'default' => '',
                        ],
                        'url'   => [
                            'type'    => 'string',
                            'default' => '',
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * @param array<string, mixed>|string $attributes
     */
    public function shortcode( array|string $attributes ): string {
        $attributes = is_array( $attributes ) ? $attributes : [];

        return $this->oneCrumb(
            is_string( $attributes['label'] ?? null ) ? $attributes['label'] : '',
            is_string( $attributes['url'] ?? null ) ? $attributes['url'] : ''
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function block( array $attributes ): string {
        return $this->oneCrumb(
            is_string( $attributes['label'] ?? null ) ? $attributes['label'] : '',
            is_string( $attributes['url'] ?? null ) ? $attributes['url'] : ''
        );
    }

    /**
     * A stored robots.txt replaces the virtual file. An empty store leaves the current output alone.
     */
    public function robots( string $output, bool $isPublic ): string {
        unset( $isPublic );
        $stored  = RobotsEditor::read();
        $content = $stored['content'];
        if ( ! is_string( $content ) || trim( $content ) === '' ) {
            return $output;
        }

        return $content;
    }

    private function oneCrumb( string $label, string $url ): string {
        if ( trim( $label ) === '' ) {
            return '';
        }

        return Breadcrumbs::html(
            [
                [
                    'label' => $label,
                    'url'   => $url,
                ],
            ]
        );
    }
}
