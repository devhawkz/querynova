<?php
/**
 * Maps one schema property to a WordPress, commerce, custom, template, literal, or @id source.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PropertyMapping {

    public function __construct(
        private readonly string $property,
        private readonly string $source,
        private readonly string $key,
    ) {
    }

    public function property(): string {
        return $this->property;
    }

    public function source(): string {
        return $this->source;
    }

    public function key(): string {
        return $this->key;
    }
}
