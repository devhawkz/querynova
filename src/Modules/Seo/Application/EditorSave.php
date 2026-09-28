<?php
/**
 * Decides whether one document may be saved from the editor.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EditorSave {

    /**
     * @param array<string, mixed> $request
     */
    public static function allowsForm( int $postId, bool $autosave, bool $revision, bool $canManageSeo, bool $canEditPost, bool $nonceValid, array $request ): bool {
        $marker = $request['querynova_editor_fields'] ?? null;

        return $postId > 0
            && ! $autosave
            && ! $revision
            && $canManageSeo
            && $canEditPost
            && $nonceValid
            && ( $marker === '1' || $marker === 1 );
    }

    public static function allowsDocument( int $postId, bool $canManageSeo, bool $canEditPost ): bool {
        return $postId > 0 && $canManageSeo && $canEditPost;
    }

    /**
     * Prefixed form fields win over bare keys. Unknown keys are ignored.
     *
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    public static function fields( array $source ): array {
        $fields = [];
        foreach ( SeoMetaService::keys() as $key ) {
            $posted = $source[ 'querynova_' . $key ] ?? null;
            if ( ! is_string( $posted ) && array_key_exists( $key, $source ) ) {
                $posted = $source[ $key ];
            }
            if ( is_string( $posted ) ) {
                $fields[ $key ] = $posted;
            }
        }

        return $fields;
    }
}
