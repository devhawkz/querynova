<?php
/**
 * Editor surfaces and the post types that receive the panel.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

use QueryNova\Modules\Seo\Presentation\BuiltinEditorAdapter;
use QueryNova\Modules\Seo\Presentation\EditorAdapter;
use QueryNova\Modules\Seo\Presentation\ElementorEditorAdapter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EditorAdapters {

    /**
     * @return list<string>
     */
    public static function tabs(): array {
        return [ 'General', 'Advanced', 'Schema', 'Social', 'AI/Content', 'Links' ];
    }

    /**
     * @return list<EditorAdapter>
     */
    public static function all(): array {
        return [
            new BuiltinEditorAdapter( 'gutenberg', 'Gutenberg', true, true ),
            new BuiltinEditorAdapter( 'classic', 'Classic', true, true ),
            new BuiltinEditorAdapter( 'woocommerce', 'WooCommerce product', true, true ),
            new ElementorEditorAdapter(),
        ];
    }

    /**
     * @return list<array{id: string, label: string, active: bool, mounted: bool}>
     */
    public static function describe(): array {
        $rows = [];
        foreach ( self::all() as $adapter ) {
            $rows[] = [
                'id'      => $adapter->id(),
                'label'   => $adapter->label(),
                'active'  => $adapter->isActive(),
                'mounted' => $adapter->isMounted(),
            ];
        }

        return $rows;
    }

    public static function supports( string $postType, bool $isPublic, bool $editor ): bool {
        if ( $postType === '' || in_array( $postType, [ 'attachment', 'revision', 'nav_menu_item' ], true ) ) {
            return false;
        }
        if ( in_array( $postType, [ 'post', 'page', 'product' ], true ) ) {
            return true;
        }

        return $isPublic && $editor;
    }
}
