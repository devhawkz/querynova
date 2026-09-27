<?php
/**
 * Paged WordPress catalog for sitemap channels.
 *
 * Queries stay paged. Product attribute taxonomies (pa_*) are left out of the
 * taxonomy channel so faceted archives are not submitted before facet rules exist.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Sitemap\Infrastructure;

use QueryNova\Modules\Sitemap\Domain\ContentCatalog;
use QueryNova\Modules\Sitemap\Domain\MediaExtractor;
use QueryNova\Modules\Sitemap\Domain\SitemapCandidate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WordPressContentCatalog implements ContentCatalog {

    private const BRAND_TAXONOMIES = [ 'product_brand', 'pwb-brand', 'yith_product_brand' ];

    public function __construct( private readonly MediaExtractor $media ) {
    }

    public function types(): array {
        $types = [ 'post', 'page' ];
        if ( $this->postTypeExists( 'product' ) ) {
            $types[] = 'product';
        }
        if ( $this->taxonomyExists( 'category' ) ) {
            $types[] = 'category';
        }
        if ( $this->brandTaxonomies() !== [] ) {
            $types[] = 'brand';
        }
        if ( $this->customPostTypes() !== [] ) {
            $types[] = 'cpt';
        }
        if ( $this->extraTaxonomies() !== [] ) {
            $types[] = 'taxonomy';
        }
        $types[] = 'image';
        $types[] = 'video';
        $types[] = 'news';

        return $types;
    }

    public function count( string $type ): int {
        if ( $this->isTermChannel( $type ) ) {
            return $this->termCount( $this->taxonomiesFor( $type ) );
        }
        $query = $this->query( $type, 1, 1 );

        return $query instanceof \WP_Query ? (int) $query->found_posts : 0;
    }

    public function page( string $type, int $page, int $perPage ): array {
        if ( $this->isTermChannel( $type ) ) {
            return $this->termPage( $type, $page, $perPage );
        }
        $query = $this->query( $type, $page, $perPage );
        if ( ! $query instanceof \WP_Query ) {
            return [];
        }
        $entries = [];
        foreach ( $query->posts as $postId ) {
            $id        = $postId instanceof \WP_Post ? (int) $postId->ID : (int) $postId;
            $candidate = $this->candidateFromPost( $id, $type );
            if ( $candidate instanceof SitemapCandidate ) {
                $entries[] = $candidate;
            }
        }

        return $entries;
    }

    /**
     * @param list<string> $taxonomies
     */
    private function termCount( array $taxonomies ): int {
        if ( $taxonomies === [] || ! function_exists( 'wp_count_terms' ) ) {
            return 0;
        }
        $count = wp_count_terms(
            [
                'taxonomy'   => $taxonomies,
                'hide_empty' => true,
            ]
        );
        if ( is_wp_error( $count ) || ! is_numeric( $count ) ) {
            return 0;
        }

        return (int) $count;
    }

    /**
     * @return list<SitemapCandidate>
     */
    private function termPage( string $type, int $page, int $perPage ): array {
        $taxonomies = $this->taxonomiesFor( $type );
        if ( $taxonomies === [] || ! function_exists( 'get_terms' ) ) {
            return [];
        }
        $terms = get_terms(
            [
                'taxonomy'   => $taxonomies,
                'hide_empty' => true,
                'number'     => $perPage,
                'offset'     => max( 0, ( $page - 1 ) * $perPage ),
                'orderby'    => 'term_id',
                'order'      => 'ASC',
            ]
        );
        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return [];
        }
        $entries = [];
        foreach ( $terms as $term ) {
            if ( ! $term instanceof \WP_Term ) {
                continue;
            }
            $candidate = $this->candidateFromTerm( $term, $type );
            if ( $candidate instanceof SitemapCandidate ) {
                $entries[] = $candidate;
            }
        }

        return $entries;
    }

    private function query( string $type, int $page, int $perPage ): ?\WP_Query {
        if ( ! class_exists( \WP_Query::class ) ) {
            return null;
        }
        $postTypes = $this->postTypesFor( $type );
        if ( $postTypes === [] ) {
            return null;
        }
        $args = [
            'post_type'           => $postTypes,
            'post_status'         => 'publish',
            'has_password'        => false,
            'posts_per_page'      => $perPage,
            'paged'               => max( 1, $page ),
            'fields'              => 'ids',
            'orderby'             => 'ID',
            'order'               => 'ASC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => false,
        ];
        if ( $type === 'image' ) {
            // Paged on the featured-image meta key. Inline-only images still ship on the post sitemap.
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            $args['meta_query'] = [
                [
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ],
            ];
        }
        if ( $type === 'news' ) {
            $args['date_query'] = [
                [
                    'after'     => gmdate( 'Y-m-d H:i:s', time() - 172800 ),
                    'inclusive' => true,
                    'column'    => 'post_date_gmt',
                ],
            ];
        }
        $filter = null;
        if ( $type === 'video' ) {
            $filter = function ( string $where ): string {
                return $this->restrictToVideoContent( $where );
            };
            add_filter( 'posts_where', $filter );
        }
        try {
            $query = new \WP_Query( $args );
        } finally {
            if ( $filter !== null && function_exists( 'remove_filter' ) ) {
                remove_filter( 'posts_where', $filter );
            }
        }

        return $query;
    }

    private function restrictToVideoContent( string $where ): string {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! isset( $wpdb->posts ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'esc_like' ) ) {
            return $where;
        }
        $posts = (string) $wpdb->posts;
        if ( preg_match( '/^[A-Za-z0-9_]+$/', $posts ) !== 1 ) {
            return $where;
        }
        $values = [];
        foreach ( [ 'youtube.com', 'youtu.be', 'vimeo.com', '<video', 'wp:video', 'wp:embed' ] as $needle ) {
            $values[] = '%' . $wpdb->esc_like( $needle ) . '%';
        }
        // The posts table name is validated above. Values stay in prepare() placeholders.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $prepared = $wpdb->prepare(
            " AND ({$posts}.post_content LIKE %s OR {$posts}.post_content LIKE %s OR {$posts}.post_content LIKE %s OR {$posts}.post_content LIKE %s OR {$posts}.post_content LIKE %s OR {$posts}.post_content LIKE %s)",
            $values[0],
            $values[1],
            $values[2],
            $values[3],
            $values[4],
            $values[5]
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! is_string( $prepared ) ) {
            return $where;
        }

        return $where . $prepared;
    }

    private function candidateFromPost( int $postId, string $type ): ?SitemapCandidate {
        if ( $postId <= 0 || ! function_exists( 'get_post' ) || ! function_exists( 'get_permalink' ) ) {
            return null;
        }
        $post = get_post( $postId );
        $url  = get_permalink( $postId );
        if ( ! $post instanceof \WP_Post || ! is_string( $url ) || $url === '' ) {
            return null;
        }
        $robots    = get_post_meta( $postId, '_querynova_robots_index', true );
        $canonical = get_post_meta( $postId, '_querynova_canonical', true );
        $modified  = get_post_modified_time( 'c', true, $postId );
        $title     = wp_strip_all_tags( $post->post_title );
        $images    = $this->imagesForPost( $postId, (string) $post->post_content );
        $videos    = $this->media->videos( (string) $post->post_content, $title );
        $newsAt    = '';
        $newsTitle = '';
        if ( $type === 'news' ) {
            $published = get_post_time( 'c', true, $postId );
            $newsAt    = is_string( $published ) ? $published : '';
            $newsTitle = $title;
        }

        return new SitemapCandidate(
            $url,
            $this->channelForPostType( (string) $post->post_type ),
            'publish',
            'public',
            $post->post_password !== '',
            $robots !== 'noindex',
            is_string( $canonical ) ? $canonical : '',
            is_string( $modified ) ? $modified : '',
            $images,
            $videos,
            $newsAt,
            $newsTitle
        );
    }

    /**
     * @return list<string>
     */
    private function imagesForPost( int $postId, string $html ): array {
        $images = $this->media->images( $html );
        if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
            return $images;
        }
        $thumb = get_the_post_thumbnail_url( $postId, 'full' );
        if ( ! is_string( $thumb ) || $thumb === '' || in_array( $thumb, $images, true ) ) {
            return $images;
        }
        array_unshift( $images, $thumb );

        return $images;
    }

    private function candidateFromTerm( \WP_Term $term, string $channel ): ?SitemapCandidate {
        if ( ! function_exists( 'get_term_link' ) ) {
            return null;
        }
        $url = get_term_link( $term );
        if ( is_wp_error( $url ) || ! is_string( $url ) || $url === '' ) {
            return null;
        }
        $robots    = function_exists( 'get_term_meta' ) ? get_term_meta( (int) $term->term_id, '_querynova_robots_index', true ) : '';
        $canonical = function_exists( 'get_term_meta' ) ? get_term_meta( (int) $term->term_id, '_querynova_canonical', true ) : '';

        return new SitemapCandidate(
            $url,
            $channel,
            'publish',
            'public',
            false,
            $robots !== 'noindex',
            is_string( $canonical ) ? $canonical : '',
            '',
            [],
            [],
            '',
            ''
        );
    }

    /**
     * @return list<string>
     */
    private function postTypesFor( string $type ): array {
        if ( $type === 'post' || $type === 'page' || $type === 'product' ) {
            return $this->postTypeExists( $type ) ? [ $type ] : [];
        }
        if ( $type === 'cpt' ) {
            return $this->customPostTypes();
        }
        if ( in_array( $type, [ 'image', 'video', 'news' ], true ) ) {
            return $this->contentPostTypes();
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function contentPostTypes(): array {
        $types = [];
        foreach ( [ 'post', 'page', 'product' ] as $type ) {
            if ( $this->postTypeExists( $type ) ) {
                $types[] = $type;
            }
        }

        return array_merge( $types, $this->customPostTypes() );
    }

    /**
     * @return list<string>
     */
    private function customPostTypes(): array {
        if ( ! function_exists( 'get_post_types' ) ) {
            return [];
        }
        $names = get_post_types( [ 'public' => true ], 'names' );
        $skip  = [ 'post', 'page', 'product', 'attachment' ];
        $found = [];
        foreach ( $names as $name ) {
            if ( is_string( $name ) && ! in_array( $name, $skip, true ) ) {
                $found[] = $name;
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function taxonomiesFor( string $type ): array {
        if ( $type === 'category' ) {
            return $this->taxonomyExists( 'category' ) ? [ 'category' ] : [];
        }
        if ( $type === 'brand' ) {
            return $this->brandTaxonomies();
        }
        if ( $type === 'taxonomy' ) {
            return $this->extraTaxonomies();
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function brandTaxonomies(): array {
        $found = [];
        foreach ( self::BRAND_TAXONOMIES as $taxonomy ) {
            if ( $this->taxonomyExists( $taxonomy ) ) {
                $found[] = $taxonomy;
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function extraTaxonomies(): array {
        if ( ! function_exists( 'get_taxonomies' ) ) {
            return [];
        }
        $names   = get_taxonomies( [ 'public' => true ], 'names' );
        $blocked = array_merge( [ 'category', 'post_format' ], self::BRAND_TAXONOMIES );
        $found   = [];
        foreach ( $names as $name ) {
            if ( ! is_string( $name ) || in_array( $name, $blocked, true ) || str_starts_with( $name, 'pa_' ) ) {
                continue;
            }
            $found[] = $name;
        }

        return $found;
    }

    private function channelForPostType( string $postType ): string {
        if ( in_array( $postType, [ 'post', 'page', 'product' ], true ) ) {
            return $postType;
        }

        return 'cpt';
    }

    private function isTermChannel( string $type ): bool {
        return in_array( $type, [ 'category', 'brand', 'taxonomy' ], true );
    }

    private function postTypeExists( string $type ): bool {
        if ( $type === 'post' || $type === 'page' ) {
            return true;
        }

        return function_exists( 'post_type_exists' ) && post_type_exists( $type );
    }

    private function taxonomyExists( string $taxonomy ): bool {
        return function_exists( 'taxonomy_exists' ) && taxonomy_exists( $taxonomy );
    }
}
