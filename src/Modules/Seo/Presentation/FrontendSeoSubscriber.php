<?php
/**
 * Prints resolved SEO tags. It does not call providers.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\SeoDocument;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FrontendSeoSubscriber implements HookSubscriberInterface {

    private ?SeoDocument $document = null;

    public function __construct( private readonly SeoMetaService $seo ) {
    }

    public function hooks(): array {
        return [
            'pre_get_document_title' => [ 'filterTitle', 15, 1 ],
            'wp_head'                => [ 'render', 1, 0 ],
        ];
    }

    public function hookType( string $hook ): string {
        return $hook === 'pre_get_document_title' ? 'filter' : 'action';
    }

    public function filterTitle( string $title ): string {
        $document = $this->document();
        if ( $document === null || $document->title() === '' ) {
            return $title;
        }

        return $document->title();
    }

    public function render(): void {
        $document = $this->document();
        if ( $document === null ) {
            return;
        }
        if ( $document->description() !== '' ) {
            echo '<meta name="description" content="' . esc_attr( $document->description() ) . '" />' . "\n";
        }
        if ( $document->canonical() !== '' ) {
            echo '<link rel="canonical" href="' . esc_url( $document->canonical() ) . '" />' . "\n";
        }
        echo '<meta name="robots" content="' . esc_attr( $document->robots()->content() ) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $document->openGraphTitle() ) . '" />' . "\n";
        if ( $document->openGraphDescription() !== '' ) {
            echo '<meta property="og:description" content="' . esc_attr( $document->openGraphDescription() ) . '" />' . "\n";
        }
        if ( $document->openGraphImage() !== '' ) {
            echo '<meta property="og:image" content="' . esc_url( $document->openGraphImage() ) . '" />' . "\n";
        }
        echo '<meta name="twitter:card" content="' . esc_attr( $document->twitterCard() ) . '" />' . "\n";
    }

    private function document(): ?SeoDocument {
        if ( $this->document !== null ) {
            return $this->document;
        }
        if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
            return null;
        }
        $postId = (int) get_queried_object_id();
        if ( $postId <= 0 ) {
            return null;
        }
        $post           = get_post( $postId );
        $tokens         = [
            'title'    => $post instanceof \WP_Post ? $post->post_title : '',
            'excerpt'  => $post instanceof \WP_Post ? wp_strip_all_tags( $post->post_excerpt ) : '',
            'sep'      => '-',
            'sitename' => (string) get_bloginfo( 'name' ),
        ];
        $this->document = $this->seo->resolve( 'post', $postId, $tokens, [] );

        return $this->document;
    }
}
