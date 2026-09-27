<?php
/**
 * Post and term meta adapter. Values are stored under the _querynova_ prefix.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Infrastructure;

use QueryNova\Modules\Seo\Domain\MetaStoreInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WordPressMetaStore implements MetaStoreInterface {

    public function get( string $objectType, int $objectId, string $key ): string {
        $stored = $objectType === 'term' ? get_term_meta( $objectId, $this->metaKey( $key ), true ) : get_post_meta( $objectId, $this->metaKey( $key ), true );

        return is_string( $stored ) ? $stored : '';
    }

    public function set( string $objectType, int $objectId, string $key, string $value ): void {
        if ( $objectType === 'term' ) {
            update_term_meta( $objectId, $this->metaKey( $key ), $value );

            return;
        }
        update_post_meta( $objectId, $this->metaKey( $key ), $value );
    }

    private function metaKey( string $key ): string {
        return '_querynova_' . $key;
    }
}
