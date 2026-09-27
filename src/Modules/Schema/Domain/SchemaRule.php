<?php
/**
 * A builder rule: type, @id template, conditions, and property mappings.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaRule {

    /**
     * @param list<RuleCondition>   $conditions
     * @param list<PropertyMapping> $mappings
     */
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly string $idTemplate,
        private readonly array $conditions,
        private readonly array $mappings,
    ) {
    }

    public function id(): string {
        return $this->id;
    }

    public function type(): string {
        return $this->type;
    }

    public function idTemplate(): string {
        return $this->idTemplate;
    }

    /**
     * @return list<RuleCondition>
     */
    public function conditions(): array {
        return $this->conditions;
    }

    /**
     * @return list<PropertyMapping>
     */
    public function mappings(): array {
        return $this->mappings;
    }
}
