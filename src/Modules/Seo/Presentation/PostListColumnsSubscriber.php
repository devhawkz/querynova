<?php
/**
 * Posts and pages list columns, plus a Quick Edit box that does not write without confirmation.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Modules\Seo\Application\PostListColumns;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PostListColumnsSubscriber implements HookSubscriberInterface {

    public function hooks(): array {
        return [
            'manage_posts_columns'  => [ 'columns', 10, 1 ],
            'manage_pages_columns'  => [ 'columns', 10, 1 ],
            'quick_edit_custom_box' => [ 'box', 10, 2 ],
            'save_post'             => [ 'savePost', 10, 1 ],
        ];
    }

    public function hookType( string $hook ): string {
        return in_array( $hook, [ 'manage_posts_columns', 'manage_pages_columns' ], true ) ? 'filter' : 'action';
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function columns( array $columns ): array {
        return PostListColumns::columns( $columns );
    }

    public function box( string $column, string $postType ): void {
        if ( $column !== 'querynova_title' || ! in_array( $postType, [ 'post', 'page' ], true ) ) {
            return;
        }
        echo '<fieldset class="querynova-quick-edit">';
        $labels = [
            'title'         => __( 'SEO title', 'querynova' ),
            'description'   => __( 'SEO description', 'querynova' ),
            'indexability'  => __( 'Indexability', 'querynova' ),
            'focus_keyword' => __( 'Focus keyword', 'querynova' ),
        ];
        foreach ( $labels as $key => $label ) {
            echo '<label>' . esc_html( $label ) . ' <input type="text" name="querynova_column_' . esc_attr( $key ) . '" /></label>';
        }
        echo '<label><input type="checkbox" name="querynova_quick_edit_confirm" value="1" /> ' . esc_html__( 'Confirm Quick Edit', 'querynova' ) . '</label>';
        if ( function_exists( 'wp_nonce_field' ) ) {
            wp_nonce_field( 'querynova_quick_edit', 'querynova_quick_edit_nonce' );
        }
        echo '</fieldset>';
    }

    public function savePost( int $postId ): void {
        if ( ! isset( $_POST['querynova_quick_edit_nonce'] ) ) {
            return;
        }
        if ( ! function_exists( 'wp_verify_nonce' ) || ! function_exists( 'wp_unslash' ) || ! function_exists( 'sanitize_text_field' ) ) {
            return;
        }
        $nonce = sanitize_text_field( wp_unslash( (string) $_POST['querynova_quick_edit_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'querynova_quick_edit' ) ) {
            return;
        }
        $confirmed = isset( $_POST['querynova_quick_edit_confirm'] ) && (string) wp_unslash( $_POST['querynova_quick_edit_confirm'] ) === '1';
        $fields    = [];
        foreach ( PostListColumns::KEYS as $key ) {
            $name = 'querynova_column_' . $key;
            if ( ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) {
                continue;
            }
            $fields[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
        }
        PostListColumns::quickEdit( $postId, $fields, $confirmed );
    }
}
