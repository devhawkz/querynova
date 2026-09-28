<?php
/**
 * Document panel for Gutenberg, classic, products, and public post types.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Presentation;

use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Security\Capability;
use QueryNova\Modules\Seo\Application\EditorAdapters;
use QueryNova\Modules\Seo\Application\EditorDocument;
use QueryNova\Modules\Seo\Application\EditorSave;
use QueryNova\Modules\Seo\Application\SeoMetaService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EditorPanel implements HookSubscriberInterface {

    public function __construct( private readonly SeoMetaService $seo ) {
    }

    public function hooks(): array {
        return [
            'admin_enqueue_scripts'           => 'enqueueAdmin',
            'enqueue_block_editor_assets'     => 'enqueueBlock',
            'add_meta_boxes'                  => [ 'addBoxes', 10, 2 ],
            'woocommerce_product_data_tabs'   => [ 'productTabs', 10, 1 ],
            'woocommerce_product_data_panels' => 'renderProduct',
            'save_post'                       => [ 'savePost', 10, 2 ],
        ];
    }

    public function hookType( string $hook ): string {
        return $hook === 'woocommerce_product_data_tabs' ? 'filter' : 'action';
    }

    public function enqueueAdmin( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || ! $this->canManage() ) {
            return;
        }
        $postId   = $this->requestedPostId();
        $postType = $this->currentPostType( $postId );
        if ( ! $this->supported( $postType ) || $this->blockEditor( $postType ) ) {
            return;
        }
        $surface = $postType === 'product' && class_exists( 'WooCommerce' ) ? 'woocommerce' : 'classic';
        $this->enqueue( $postId, $postType, $surface );
    }

    public function enqueueBlock(): void {
        if ( ! $this->canManage() ) {
            return;
        }
        $postId   = $this->requestedPostId();
        $postType = $this->currentPostType( $postId );
        if ( ! $this->supported( $postType ) ) {
            return;
        }
        $this->enqueue( $postId, $postType, 'gutenberg' );
    }

    public function addBoxes( string $postType, mixed $post = null ): void {
        unset( $post );
        if ( ! $this->canManage() || ! $this->supported( $postType ) || $this->blockEditor( $postType ) ) {
            return;
        }
        if ( $postType === 'product' && class_exists( 'WooCommerce' ) ) {
            return;
        }
        if ( ! function_exists( 'add_meta_box' ) ) {
            return;
        }
        add_meta_box(
            'querynova-editor',
            __( 'QueryNova', 'querynova' ),
            [ $this, 'renderClassic' ],
            $postType,
            'normal',
            'high'
        );
    }

    /**
     * @param array<string, mixed> $tabs
     * @return array<string, mixed>
     */
    public function productTabs( array $tabs ): array {
        if ( ! $this->canManage() || $this->blockEditor( 'product' ) ) {
            return $tabs;
        }
        $tabs['querynova'] = [
            'label'  => __( 'QueryNova', 'querynova' ),
            'target' => 'querynova-product-editor',
            'class'  => [],
        ];

        return $tabs;
    }

    public function renderClassic( mixed $post ): void {
        unset( $post );
        echo '<div id="querynova-editor" class="qn-editor-mount"></div>';
        $this->nonceField();
    }

    public function renderProduct(): void {
        echo '<div id="querynova-product-editor" class="panel woocommerce_options_panel"></div>';
        $this->nonceField();
    }

    public function savePost( int $postId, mixed $post = null ): void {
        unset( $post );
        if ( ! isset( $_POST['querynova_editor_nonce'] ) ) {
            return;
        }
        $nonce = sanitize_text_field( wp_unslash( (string) $_POST['querynova_editor_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'querynova_editor_save' ) ) {
            return;
        }
        $request = wp_unslash( $_POST );
        if ( ! is_array( $request ) ) {
            return;
        }
        try {
            $this->apply(
                $postId,
                $request,
                function_exists( 'wp_is_post_autosave' ) && (bool) wp_is_post_autosave( $postId ),
                function_exists( 'wp_is_post_revision' ) && (bool) wp_is_post_revision( $postId ),
                $this->canManage(),
                function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $postId ),
                true
            );
        } catch ( ValidationException $exception ) {
            unset( $exception );
        }
    }

    /**
     * @param array<string, mixed> $request
     */
    public function apply( int $postId, array $request, bool $autosave, bool $revision, bool $canManageSeo, bool $canEditPost, bool $nonceValid ): bool {
        if ( ! EditorSave::allowsForm( $postId, $autosave, $revision, $canManageSeo, $canEditPost, $nonceValid, $request ) ) {
            return false;
        }
        $this->seo->save( 'post', $postId, EditorSave::fields( $request ) );

        return true;
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function update( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = [];
        }
        $objectId = (int) ( $params['object_id'] ?? 0 );
        $canEdit  = function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $objectId );
        if ( ! EditorSave::allowsDocument( $objectId, $this->canManage(), $canEdit ) ) {
            return new \WP_Error( 'querynova_editor_forbidden', 'This document cannot be edited.', [ 'status' => 403 ] );
        }
        try {
            $this->seo->save( 'post', $objectId, EditorSave::fields( $params ) );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_seo', $exception->getMessage(), [ 'status' => 400 ] );
        }

        return [
            'status'    => 'saved',
            'object_id' => $objectId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function boot( int $postId, string $postType, string $surface, string $fallbackTitle, string $permalink ): array {
        return [
            'objectId'      => $postId,
            'postType'      => $postType,
            'surface'       => $surface,
            'fallbackTitle' => $fallbackTitle,
            'permalink'     => $permalink,
            'tabs'          => EditorAdapters::tabs(),
            'adapters'      => EditorAdapters::describe(),
            'document'      => EditorDocument::view( $this->seo, 'post', $postId, $fallbackTitle, $permalink ),
        ];
    }

    private function enqueue( int $postId, string $postType, string $surface ): void {
        $path = QUERYNOVA_PATH . 'build/editor.js';
        if ( ! is_file( $path ) || ! function_exists( 'wp_enqueue_script' ) ) {
            return;
        }
        $style = QUERYNOVA_PATH . 'build/editor.css';
        if ( is_file( $style ) && function_exists( 'wp_enqueue_style' ) ) {
            wp_enqueue_style( 'querynova-editor', QUERYNOVA_URL . 'build/editor.css', [], QUERYNOVA_VERSION );
        }
        wp_enqueue_script(
            'querynova-editor',
            QUERYNOVA_URL . 'build/editor.js',
            [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-i18n' ],
            QUERYNOVA_VERSION,
            true
        );
        $title     = $this->postTitle( $postId );
        $permalink = $this->permalink( $postId );
        wp_add_inline_script(
            'querynova-editor',
            'window.querynovaEditor = ' . wp_json_encode(
                array_merge(
                    $this->boot( $postId, $postType, $surface, $title, $permalink ),
                    [
                        'restUrl' => esc_url_raw( rest_url( 'querynova/v1' ) ),
                        'nonce'   => wp_create_nonce( 'wp_rest' ),
                    ]
                )
            ) . ';',
            'before'
        );
    }

    private function nonceField(): void {
        if ( ! function_exists( 'wp_create_nonce' ) ) {
            return;
        }
        echo '<input type="hidden" name="querynova_editor_nonce" value="' . esc_attr( wp_create_nonce( 'querynova_editor_save' ) ) . '" />';
    }

    private function canManage(): bool {
        return function_exists( 'current_user_can' ) && current_user_can( Capability::MANAGE_SEO );
    }

    private function requestedPostId(): int {
        $post = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );

        return is_int( $post ) && $post > 0 ? $post : 0;
    }

    private function currentPostType( int $postId ): string {
        if ( $postId > 0 && function_exists( 'get_post_type' ) ) {
            $type = get_post_type( $postId );
            if ( is_string( $type ) && $type !== '' ) {
                return $type;
            }
        }
        $requested = filter_input( INPUT_GET, 'post_type', FILTER_UNSAFE_RAW );
        if ( is_string( $requested ) ) {
            $type = strtolower( (string) preg_replace( '/[^a-z0-9_-]/', '', $requested ) );
            if ( $type !== '' ) {
                return $type;
            }
        }

        return 'post';
    }

    private function supported( string $postType ): bool {
        $public = true;
        $editor = true;
        if ( function_exists( 'get_post_type_object' ) ) {
            $object = get_post_type_object( $postType );
            if ( $object instanceof \WP_Post_Type ) {
                $public = $object->public;
                $editor = ! function_exists( 'post_type_supports' ) || post_type_supports( $postType, 'editor' );
            }
        }

        return EditorAdapters::supports( $postType, $public, $editor );
    }

    private function blockEditor( string $postType ): bool {
        return function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $postType );
    }

    private function postTitle( int $postId ): string {
        if ( $postId < 1 || ! function_exists( 'get_post' ) ) {
            return '';
        }
        $post = get_post( $postId );
        if ( ! $post instanceof \WP_Post ) {
            return '';
        }

        return $post->post_title;
    }

    private function permalink( int $postId ): string {
        if ( $postId < 1 || ! function_exists( 'get_permalink' ) ) {
            return '';
        }
        $url = get_permalink( $postId );

        return is_string( $url ) ? $url : '';
    }
}
