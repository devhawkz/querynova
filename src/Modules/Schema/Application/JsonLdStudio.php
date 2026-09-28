<?php
/**
 * Schema studio helpers. Import previews a rule and does not save it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Application;

use QueryNova\Modules\Schema\Domain\SchemaTypes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class JsonLdStudio {

    /**
     * @return list<array{id: string, label: string, type: string, properties: list<string>}>
     */
    public static function templates(): array {
        return [
            [
                'id'         => 'article',
                'label'      => 'Article',
                'type'       => 'Article',
                'properties' => [ 'headline', 'description' ],
            ],
            [
                'id'         => 'product',
                'label'      => 'Product',
                'type'       => 'Product',
                'properties' => [ 'name', 'description' ],
            ],
            [
                'id'         => 'breadcrumb',
                'label'      => 'Breadcrumb',
                'type'       => 'BreadcrumbList',
                'properties' => [ 'itemListElement' ],
            ],
            [
                'id'         => 'faq',
                'label'      => 'FAQ',
                'type'       => 'FAQPage',
                'properties' => [ 'mainEntity' ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function import( string $json ): array {
        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            return self::invalid( 'JSON-LD could not be read. Nothing was saved.' );
        }
        $node = isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ? ( $decoded['@graph'][0] ?? null ) : $decoded;
        if ( ! is_array( $node ) ) {
            return self::invalid( 'JSON-LD did not contain an object. Nothing was saved.' );
        }
        $type = $node['@type'] ?? '';
        if ( is_array( $type ) ) {
            $type = (string) ( $type[0] ?? '' );
        }
        if ( ! is_string( $type ) || ! SchemaTypes::allows( $type ) ) {
            return self::invalid( 'That schema type is not available. Nothing was saved.' );
        }
        $mappings = [];
        foreach ( $node as $property => $value ) {
            if ( ! is_string( $property ) || str_starts_with( $property, '@' ) || ! is_string( $value ) || trim( $value ) === '' ) {
                continue;
            }
            if ( count( $mappings ) >= 8 ) {
                break;
            }
            $mappings[] = [
                'property' => $property,
                'source'   => 'literal',
                'key'      => trim( $value ),
            ];
        }

        return [
            'valid'    => true,
            'saved'    => false,
            'template' => false,
            'rule'     => [
                'id'          => 'import-preview',
                'type'        => $type,
                'id_template' => '%%permalink%%#entity',
                'conditions'  => [],
                'mappings'    => $mappings,
            ],
            'preview'  => [
                '@type' => $type,
            ],
            'note'     => 'Only supplied properties are shown. Empty fields are not added. Nothing was saved.',
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public static function validate( array $rule ): array {
        $type   = is_string( $rule['type'] ?? null ) ? $rule['type'] : '';
        $errors = [];
        if ( ! SchemaTypes::allows( $type ) ) {
            $errors[] = 'Choose a supported schema type.';
        }
        $template = trim( (string) ( $rule['id_template'] ?? '' ) );
        if ( $template === '' ) {
            $errors[] = 'An @id template is required.';
        }
        $preview  = [
            '@type' => $type,
        ];
        $mappings = is_array( $rule['mappings'] ?? null ) ? $rule['mappings'] : [];
        foreach ( $mappings as $mapping ) {
            if ( ! is_array( $mapping ) ) {
                continue;
            }
            $property = trim( (string) ( $mapping['property'] ?? '' ) );
            $value    = trim( (string) ( $mapping['key'] ?? '' ) );
            if ( $property !== '' && $value !== '' ) {
                $preview[ $property ] = $value;
            }
        }

        return [
            'valid'   => $errors === [],
            'errors'  => $errors,
            'preview' => $preview,
            'saved'   => false,
            'note'    => 'Validation does not save the rule.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function invalid( string $note ): array {
        return [
            'valid'    => false,
            'saved'    => false,
            'template' => false,
            'rule'     => null,
            'preview'  => null,
            'note'     => $note,
        ];
    }
}
