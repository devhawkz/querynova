<?php
/**
 * Persistence for schema builder rules.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface SchemaRuleStore {

    /**
     * @return list<SchemaRule>
     */
    public function all(): array;

    /**
     * @param list<SchemaRule> $rules
     */
    public function replace( array $rules ): void;
}
