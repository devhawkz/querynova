<?php
/**
 * Post list columns and Quick Edit for four SEO fields.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PostListColumns {

    /**
     * @var list<string>
     */
    public const KEYS = [ 'title', 'description', 'indexability', 'focus_keyword' ];

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public static function columns( array $columns ): array {
        $columns['querynova_title']         = __( 'SEO title', 'querynova' );
        $columns['querynova_description']   = __( 'SEO description', 'querynova' );
        $columns['querynova_indexability']  = __( 'Indexability', 'querynova' );
        $columns['querynova_focus_keyword'] = __( 'Focus keyword', 'querynova' );

        return $columns;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public static function quickEdit( int $postId, array $fields, bool $confirmed ): array {
        $clean = [];
        foreach ( self::KEYS as $key ) {
            if ( ! array_key_exists( $key, $fields ) || ! is_string( $fields[ $key ] ) ) {
                continue;
            }
            $clean[ $key ] = self::plain( $fields[ $key ] );
        }
        $result = [
            'post_id' => $postId,
            'fields'  => array_keys( $clean ),
            'written' => false,
            'note'    => 'Quick Edit writes title, description, indexability, and focus keyword only after you confirm.',
        ];
        if ( ! $confirmed || $postId < 1 || $clean === [] || ! function_exists( 'update_post_meta' ) ) {
            return $result;
        }
        foreach ( $clean as $key => $value ) {
            update_post_meta( $postId, '_querynova_' . $key, $value );
        }
        $result['written'] = true;
        $result['note']    = 'Quick Edit stored title, description, indexability, and focus keyword.';

        return $result;
    }

    private static function plain( string $value ): string {
        $text = trim( wp_strip_all_tags( $value ) );
        if ( strlen( $text ) > 180 ) {
            $text = substr( $text, 0, 180 );
        }

        return $text;
    }
}
