<?php
/**
 * Connected JSON-LD graph. Entities point at each other by @id.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaGraph {

    /** @var array<string, SchemaNode> */
    private array $nodes = [];

    public function add( SchemaNode $node ): void {
        $this->nodes[ $node->id() ] = $node;
    }

    public function find( string $id ): ?SchemaNode {
        return $this->nodes[ $id ] ?? null;
    }

    public function findByType( string $type ): ?SchemaNode {
        foreach ( $this->nodes as $node ) {
            if ( $node->type() === $type ) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function types(): array {
        $types = [];
        foreach ( $this->nodes as $node ) {
            $types[] = $node->type();
        }

        return $types;
    }

    /**
     * @return array{'@context': string, '@graph': list<array<string, mixed>>}
     */
    public function toArray(): array {
        $graph = [];
        foreach ( $this->nodes as $node ) {
            $graph[] = $node->document();
        }

        return [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ];
    }

    public function json(): string {
        $encoded = wp_json_encode(
            $this->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return is_string( $encoded ) ? $encoded : '{}';
    }
}
