<?php
/**
 * Titles for Quick search. The list is read when the QueryNova screen loads.
 *
 * Search does not call this again. It does not call a provider, crawl, or write.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class QuickSearchIndex {

    public const LIMIT = 20;

    /**
     * Published titles only. A missing WordPress function leaves both lists empty.
     *
     * @return array{posts: list<array{id: int, title: string}>, pages: list<array{id: int, title: string}>, fetched: false, written: false, called: false, note: string}
     */
    public static function catalog(): array {
        if ( ! function_exists( 'get_posts' ) ) {
            return self::present( [], [] );
        }

        return self::present( self::stored( 'post' ), self::stored( 'page' ) );
    }

    /**
     * @param list<mixed> $posts
     * @param list<mixed> $pages
     * @return array{posts: list<array{id: int, title: string}>, pages: list<array{id: int, title: string}>, fetched: false, written: false, called: false, note: string}
     */
    public static function present( array $posts, array $pages ): array {
        return [
            'posts'   => self::rows( $posts ),
            'pages'   => self::rows( $pages ),
            'fetched' => false,
            'written' => false,
            'called'  => false,
            'note'    => 'Titles already stored in WordPress. This list does not call a provider, crawl, or write post meta.',
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function stored( string $type ): array {
        $rows = get_posts(
            [
                'post_type'              => $type,
                'post_status'            => 'publish',
                'posts_per_page'         => self::LIMIT,
                'orderby'                => 'modified',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * @param list<mixed> $rows
     * @return list<array{id: int, title: string}>
     */
    private static function rows( array $rows ): array {
        $clean = [];
        foreach ( $rows as $row ) {
            $item = self::row( $row );
            if ( $item === null ) {
                continue;
            }
            $clean[] = $item;
            if ( count( $clean ) === self::LIMIT ) {
                break;
            }
        }

        return $clean;
    }

    /**
     * @return array{id: int, title: string}|null
     */
    private static function row( mixed $row ): ?array {
        if ( $row instanceof \WP_Post ) {
            $id    = (int) $row->ID;
            $title = trim( wp_strip_all_tags( (string) $row->post_title ) );
        } elseif ( is_array( $row ) ) {
            $id    = (int) ( $row['id'] ?? $row['ID'] ?? 0 );
            $title = trim( wp_strip_all_tags( (string) ( $row['title'] ?? $row['post_title'] ?? '' ) ) );
        } else {
            return null;
        }
        if ( $id < 1 || $title === '' ) {
            return null;
        }

        return [
            'id'    => $id,
            'title' => $title,
        ];
    }
}
