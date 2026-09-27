<?php
/**
 * A condition that must pass before a schema rule emits an entity.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RuleCondition {

    public function __construct(
        private readonly string $source,
        private readonly string $key,
        private readonly string $operator,
        private readonly string $expected,
    ) {
    }

    public function source(): string {
        return $this->source;
    }

    public function key(): string {
        return $this->key;
    }

    public function operator(): string {
        return $this->operator;
    }

    public function expected(): string {
        return $this->expected;
    }
}
