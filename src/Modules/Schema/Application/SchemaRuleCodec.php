<?php
/**
 * Validates and converts schema builder rules to plain arrays.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Application;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Schema\Domain\PropertyMapping;
use QueryNova\Modules\Schema\Domain\RuleCondition;
use QueryNova\Modules\Schema\Domain\SchemaRule;
use QueryNova\Modules\Schema\Domain\SchemaTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaRuleCodec {

    private const SOURCES    = [ 'wordpress', 'woocommerce', 'custom', 'template', 'literal', 'link' ];
    private const CONDITIONS = [ 'wordpress', 'woocommerce', 'custom', 'template', 'literal' ];
    private const OPERATORS  = [ 'exists', 'missing', 'equals', 'not_equals' ];

    /**
     * @param list<SchemaRule> $rules
     * @return list<array<string, mixed>>
     */
    public function export( array $rules ): array {
        $rows = [];
        foreach ( $rules as $rule ) {
            $conditions = [];
            foreach ( $rule->conditions() as $condition ) {
                $conditions[] = [
                    'source'   => $condition->source(),
                    'key'      => $condition->key(),
                    'operator' => $condition->operator(),
                    'expected' => $condition->expected(),
                ];
            }
            $mappings = [];
            foreach ( $rule->mappings() as $mapping ) {
                $mappings[] = [
                    'property' => $mapping->property(),
                    'source'   => $mapping->source(),
                    'key'      => $mapping->key(),
                ];
            }
            $rows[] = [
                'id'          => $rule->id(),
                'type'        => $rule->type(),
                'id_template' => $rule->idTemplate(),
                'conditions'  => $conditions,
                'mappings'    => $mappings,
            ];
        }

        return $rows;
    }

    /**
     * @param mixed $rows
     * @return list<SchemaRule>
     */
    public function import( mixed $rows ): array {
        if ( ! is_array( $rows ) || ! array_is_list( $rows ) ) {
            throw new ValidationException( 'Schema rules must be a list.' );
        }
        if ( count( $rows ) > 50 ) {
            throw new ValidationException( 'Schema rules are limited to 50.' );
        }
        $rules = [];
        foreach ( $rows as $index => $row ) {
            if ( ! is_array( $row ) ) {
                throw new ValidationException( 'Each schema rule must be an object.' );
            }
            $rules[] = $this->rule( $row, (int) $index );
        }

        return $rules;
    }

    /**
     * @param array<mixed> $row
     */
    private function rule( array $row, int $index ): SchemaRule {
        $type = $this->text( $row['type'] ?? '', 40 );
        if ( ! SchemaTypes::allows( $type ) ) {
            throw new ValidationException( 'Unknown schema type.' );
        }
        $id = $this->text( $row['id'] ?? '', 64 );
        if ( $id === '' ) {
            $id = 'rule-' . $index;
        }
        if ( preg_match( '/^[a-z0-9_-]{1,64}$/', $id ) !== 1 ) {
            throw new ValidationException( 'Schema rule ids must use lowercase letters, numbers, dashes, or underscores.' );
        }
        $template = $this->text( $row['id_template'] ?? '', 300 );
        if ( $template === '' ) {
            throw new ValidationException( 'Each schema rule needs an @id template.' );
        }
        $conditions = $row['conditions'] ?? [];
        $mappings   = $row['mappings'] ?? [];
        if ( ! is_array( $conditions ) || ! array_is_list( $conditions ) || count( $conditions ) > 10 ) {
            throw new ValidationException( 'Schema conditions must be a list of at most 10.' );
        }
        if ( ! is_array( $mappings ) || ! array_is_list( $mappings ) || count( $mappings ) > 30 ) {
            throw new ValidationException( 'Schema mappings must be a list of at most 30.' );
        }

        return new SchemaRule(
            $id,
            $type,
            $template,
            $this->conditions( $conditions ),
            $this->mappings( $mappings )
        );
    }

    /**
     * @param list<mixed> $rows
     * @return list<RuleCondition>
     */
    private function conditions( array $rows ): array {
        $conditions = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                throw new ValidationException( 'Each schema condition must be an object.' );
            }
            $source   = $this->text( $row['source'] ?? '', 20 );
            $operator = $this->text( $row['operator'] ?? '', 20 );
            if ( ! in_array( $source, self::CONDITIONS, true ) || ! in_array( $operator, self::OPERATORS, true ) ) {
                throw new ValidationException( 'Schema condition source or operator is not supported.' );
            }
            $conditions[] = new RuleCondition(
                $source,
                $this->text( $row['key'] ?? '', 200 ),
                $operator,
                $this->text( $row['expected'] ?? '', 200 )
            );
        }

        return $conditions;
    }

    /**
     * @param list<mixed> $rows
     * @return list<PropertyMapping>
     */
    private function mappings( array $rows ): array {
        $mappings = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                throw new ValidationException( 'Each schema mapping must be an object.' );
            }
            $property = $this->text( $row['property'] ?? '', 80 );
            $source   = $this->text( $row['source'] ?? '', 20 );
            if ( preg_match( '/^[A-Za-z][A-Za-z0-9]*$/', $property ) !== 1 ) {
                throw new ValidationException( 'Schema property names must be plain words.' );
            }
            if ( ! in_array( $source, self::SOURCES, true ) ) {
                throw new ValidationException( 'Schema mapping source is not supported.' );
            }
            $mappings[] = new PropertyMapping( $property, $source, $this->text( $row['key'] ?? '', 500 ) );
        }

        return $mappings;
    }

    private function text( mixed $value, int $limit ): string {
        $text = is_string( $value ) ? trim( wp_strip_all_tags( $value ) ) : '';
        if ( strlen( $text ) > $limit ) {
            $text = substr( $text, 0, $limit );
        }

        return $text;
    }
}
