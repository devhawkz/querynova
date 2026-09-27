<?php
/**
 * One entity in the schema graph.
 *
 * Empty strings are not stored. Missing numbers stay absent rather than becoming zero.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaNode {

    /** @var array<string, mixed> */
    private array $properties = [];

    public function __construct(
        private readonly string $id,
        private readonly string $type,
    ) {
    }

    public function id(): string {
        return $this->id;
    }

    public function type(): string {
        return $this->type;
    }

    public function text( string $property, string $value ): void {
        if ( $value === '' ) {
            return;
        }
        $this->properties[ $property ] = $value;
    }

    public function wholeNumber( string $property, int $value ): void {
        $this->properties[ $property ] = $value;
    }

    public function reference( string $property, string $id ): void {
        if ( $id === '' ) {
            return;
        }
        $this->properties[ $property ] = [ '@id' => $id ];
    }

    /**
     * @param list<string> $ids
     */
    public function references( string $property, array $ids ): void {
        $items = [];
        foreach ( $ids as $id ) {
            if ( $id !== '' ) {
                $items[] = [ '@id' => $id ];
            }
        }
        if ( $items !== [] ) {
            $this->properties[ $property ] = $items;
        }
    }

    /**
     * @param array<string, mixed> $value
     */
    public function object( string $property, array $value ): void {
        if ( $value === [] ) {
            return;
        }
        $this->properties[ $property ] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array {
        return [
            '@type' => $this->type,
            '@id'   => $this->id,
        ] + $this->properties;
    }
}
