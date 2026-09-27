<?php
/**
 * Array meta store for tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Infrastructure;

use QueryNova\Modules\Seo\Domain\MetaStoreInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ArrayMetaStore implements MetaStoreInterface {

    /** @var array<string, string> */
    private array $values = [];

    public function get( string $objectType, int $objectId, string $key ): string {
        return $this->values[ $this->id( $objectType, $objectId, $key ) ] ?? '';
    }

    public function set( string $objectType, int $objectId, string $key, string $value ): void {
        $this->values[ $this->id( $objectType, $objectId, $key ) ] = $value;
    }

    private function id( string $objectType, int $objectId, string $key ): string {
        return $objectType . ':' . $objectId . ':' . $key;
    }
}
