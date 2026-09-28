<?php
/**
 * Headless SEO payload. Empty fields stay empty and the page is not fetched.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HeadlessDocument {

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public static function present( array $document ): array {
        return [
            'metadata'  => [
                'title'       => self::text( $document['title'] ?? null ),
                'description' => self::text( $document['description'] ?? null ),
            ],
            'canonical' => self::text( $document['canonical'] ?? null ),
            'robots'    => self::text( $document['robots'] ?? null ),
            'schema'    => null,
            'social'    => [
                'open_graph_title'       => self::text( $document['open_graph_title'] ?? null ),
                'open_graph_description' => self::text( $document['open_graph_description'] ?? null ),
                'open_graph_image'       => self::text( $document['open_graph_image'] ?? null ),
                'twitter_card'           => self::text( $document['twitter_card'] ?? null ),
            ],
            'fetched'   => false,
        ];
    }

    private static function text( mixed $value ): ?string {
        $text = trim( wp_strip_all_tags( is_string( $value ) ? $value : '' ) );

        return $text === '' ? null : $text;
    }
}
