<?php
/**
 * Builds a content snapshot for the current singular view.
 *
 * Only custom keys requested by builder rules are copied. Other meta stays unread.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Infrastructure;

use QueryNova\Modules\Schema\Domain\CommerceReader;
use QueryNova\Modules\Schema\Domain\ContentSnapshot;
use QueryNova\Modules\Schema\Domain\SchemaRule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WordPressContentSnapshot {

    public function __construct( private readonly CommerceReader $commerce ) {
    }

    /**
     * @param list<SchemaRule> $rules
     */
    public function current( array $rules ): ?ContentSnapshot {
        if ( ! function_exists( 'is_singular' ) || ! is_singular() || ! function_exists( 'get_queried_object_id' ) ) {
            return null;
        }

        return $this->fromPost( (int) get_queried_object_id(), $this->customKeys( $rules ) );
    }

    /**
     * @param list<string> $customKeys
     */
    public function fromPost( int $postId, array $customKeys ): ?ContentSnapshot {
        if ( $postId <= 0 || ! function_exists( 'get_post' ) || ! function_exists( 'get_permalink' ) ) {
            return null;
        }
        $post = get_post( $postId );
        $url  = get_permalink( $postId );
        if ( ! $post instanceof \WP_Post || ! is_string( $url ) || $url === '' ) {
            return null;
        }
        $siteUrl = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
        $image   = '';
        if ( function_exists( 'get_the_post_thumbnail_url' ) ) {
            $thumb = get_the_post_thumbnail_url( $postId, 'full' );
            $image = is_string( $thumb ) ? $thumb : '';
        }
        $description = $this->meta( $postId, '_querynova_description' );
        if ( $description === '' ) {
            $description = wp_strip_all_tags( (string) $post->post_excerpt );
        }
        $video = $this->meta( $postId, '_querynova_video_url' );

        return new ContentSnapshot(
            $url,
            wp_strip_all_tags( (string) $post->post_title ),
            $description,
            function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '',
            $siteUrl,
            $this->author( $post ),
            $this->time( 'published', $postId ),
            $this->time( 'modified', $postId ),
            $image,
            $this->kind( (string) $post->post_type ),
            $this->custom( $postId, $customKeys ),
            $this->breadcrumbs( $post, $url, $siteUrl ),
            (string) $post->post_type === 'product' ? $this->commerce->read( $postId ) : null,
            $video,
            $video === '' ? '' : wp_strip_all_tags( (string) $post->post_title ),
            $image
        );
    }

    /**
     * @param list<SchemaRule> $rules
     * @return list<string>
     */
    private function customKeys( array $rules ): array {
        $keys = [];
        foreach ( $rules as $rule ) {
            foreach ( $rule->conditions() as $condition ) {
                if ( $condition->source() === 'custom' && $condition->key() !== '' ) {
                    $keys[] = $condition->key();
                }
            }
            foreach ( $rule->mappings() as $mapping ) {
                if ( $mapping->source() === 'custom' && $mapping->key() !== '' ) {
                    $keys[] = $mapping->key();
                }
            }
        }

        return array_values( array_unique( $keys ) );
    }

    /**
     * @param list<string> $keys
     * @return array<string, string>
     */
    private function custom( int $postId, array $keys ): array {
        $values = [];
        foreach ( $keys as $key ) {
            if ( preg_match( '/^[A-Za-z0-9_-]{1,80}$/', $key ) !== 1 ) {
                continue;
            }
            $values[ $key ] = $this->meta( $postId, $key );
        }

        return $values;
    }

    private function meta( int $postId, string $key ): string {
        if ( ! function_exists( 'get_post_meta' ) ) {
            return '';
        }
        $stored = get_post_meta( $postId, $key, true );

        return is_string( $stored ) ? trim( wp_strip_all_tags( $stored ) ) : '';
    }

    private function author( \WP_Post $post ): string {
        if ( ! function_exists( 'get_the_author_meta' ) ) {
            return '';
        }
        $name = get_the_author_meta( 'display_name', (int) $post->post_author );

        return is_string( $name ) ? wp_strip_all_tags( $name ) : '';
    }

    private function time( string $which, int $postId ): string {
        $value = null;
        if ( $which === 'published' && function_exists( 'get_post_time' ) ) {
            $value = get_post_time( 'c', true, $postId );
        }
        if ( $which === 'modified' && function_exists( 'get_post_modified_time' ) ) {
            $value = get_post_modified_time( 'c', true, $postId );
        }

        return is_string( $value ) ? $value : '';
    }

    private function kind( string $postType ): string {
        if ( $postType === 'product' ) {
            return 'product';
        }
        if ( $postType === 'post' ) {
            return 'post';
        }

        return 'page';
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function breadcrumbs( \WP_Post $post, string $url, string $siteUrl ): array {
        $crumbs = [];
        $site   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
        if ( $site !== '' && $siteUrl !== '' ) {
            $crumbs[] = [
                'name' => $site,
                'url'  => $siteUrl,
            ];
        }
        if ( function_exists( 'get_post_ancestors' ) ) {
            $ancestors = get_post_ancestors( $post );
            if ( is_array( $ancestors ) ) {
                foreach ( array_reverse( $ancestors ) as $ancestorId ) {
                    $ancestor = get_post( (int) $ancestorId );
                    $link     = get_permalink( (int) $ancestorId );
                    if ( $ancestor instanceof \WP_Post && is_string( $link ) && $link !== '' ) {
                        $crumbs[] = [
                            'name' => wp_strip_all_tags( (string) $ancestor->post_title ),
                            'url'  => $link,
                        ];
                    }
                }
            }
        }
        $crumbs[] = [
            'name' => wp_strip_all_tags( (string) $post->post_title ),
            'url'  => $url,
        ];

        return $crumbs;
    }
}
