<?php
/**
 * Storage port for per-object SEO fields.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface MetaStoreInterface {

    public function get( string $objectType, int $objectId, string $key ): string;

    public function set( string $objectType, int $objectId, string $key, string $value ): void;
}
