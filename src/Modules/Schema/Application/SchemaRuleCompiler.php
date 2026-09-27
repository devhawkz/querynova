<?php
/**
 * Adds builder rules to an existing graph.
 *
 * A link mapping becomes an @id reference. Empty resolved values are omitted.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Application;

use QueryNova\Modules\Schema\Domain\CommerceFacts;
use QueryNova\Modules\Schema\Domain\ContentSnapshot;
use QueryNova\Modules\Schema\Domain\PropertyMapping;
use QueryNova\Modules\Schema\Domain\RuleCondition;
use QueryNova\Modules\Schema\Domain\SchemaGraph;
use QueryNova\Modules\Schema\Domain\SchemaNode;
use QueryNova\Modules\Schema\Domain\SchemaRule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaRuleCompiler {

    /**
     * @param list<SchemaRule> $rules
     */
    public function apply( SchemaGraph $graph, ContentSnapshot $snapshot, array $rules ): void {
        $tokens = $this->tokens( $snapshot );
        foreach ( $rules as $rule ) {
            if ( ! $this->passes( $rule, $tokens, $snapshot ) ) {
                continue;
            }
            $id = $this->render( $rule->idTemplate(), $tokens );
            if ( $id === '' || str_contains( $id, '<' ) || str_contains( $id, '>' ) ) {
                continue;
            }
            $node = new SchemaNode( $id, $rule->type() );
            foreach ( $rule->mappings() as $mapping ) {
                $this->map( $node, $mapping, $tokens, $snapshot );
            }
            $graph->add( $node );
        }
    }

    /**
     * @param array<string, string> $tokens
     */
    private function passes( SchemaRule $rule, array $tokens, ContentSnapshot $snapshot ): bool {
        foreach ( $rule->conditions() as $condition ) {
            if ( ! $this->conditionPasses( $condition, $tokens, $snapshot ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, string> $tokens
     */
    private function conditionPasses( RuleCondition $condition, array $tokens, ContentSnapshot $snapshot ): bool {
        $value = $this->resolve( $condition->source(), $condition->key(), $tokens, $snapshot );
        if ( $condition->operator() === 'exists' ) {
            return $value !== '';
        }
        if ( $condition->operator() === 'missing' ) {
            return $value === '';
        }
        if ( $condition->operator() === 'equals' ) {
            return $value === $condition->expected();
        }
        if ( $condition->operator() === 'not_equals' ) {
            return $value !== $condition->expected();
        }

        return false;
    }

    /**
     * @param array<string, string> $tokens
     */
    private function map( SchemaNode $node, PropertyMapping $mapping, array $tokens, ContentSnapshot $snapshot ): void {
        if ( $mapping->source() === 'link' ) {
            $target = $this->render( $mapping->key(), $tokens );
            $node->reference( $mapping->property(), $target );

            return;
        }
        $node->text( $mapping->property(), $this->resolve( $mapping->source(), $mapping->key(), $tokens, $snapshot ) );
    }

    /**
     * @param array<string, string> $tokens
     */
    private function resolve( string $source, string $key, array $tokens, ContentSnapshot $snapshot ): string {
        if ( $source === 'literal' ) {
            return $key;
        }
        if ( $source === 'template' ) {
            return $this->render( $key, $tokens );
        }
        if ( $source === 'custom' ) {
            return $snapshot->custom()[ $key ] ?? '';
        }
        $token = $this->tokenName( $source, $key );
        if ( $token === '' ) {
            return '';
        }

        return $tokens[ $token ] ?? '';
    }

    private function tokenName( string $source, string $key ): string {
        $pageFields = [
            'title'     => 'title',
            'excerpt'   => 'excerpt',
            'permalink' => 'permalink',
            'published' => 'published',
            'modified'  => 'modified',
            'author'    => 'author',
            'image'     => 'image',
            'site_name' => 'site_name',
            'site_url'  => 'site_url',
        ];
        $commerce   = [
            'name'         => 'product_name',
            'sku'          => 'sku',
            'price'        => 'price',
            'currency'     => 'currency',
            'availability' => 'availability',
            'brand'        => 'brand',
            'rating_value' => 'rating_value',
            'review_count' => 'review_count',
        ];
        // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- Field source key, not the WordPress name.
        if ( $source === 'wordpress' ) {
            return $pageFields[ $key ] ?? '';
        }
        if ( $source === 'woocommerce' ) {
            return $commerce[ $key ] ?? '';
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function tokens( ContentSnapshot $snapshot ): array {
        $tokens   = [
            'title'     => $snapshot->title(),
            'excerpt'   => $snapshot->description(),
            'permalink' => $snapshot->permalink(),
            'site_name' => $snapshot->siteName(),
            'site_url'  => $snapshot->siteUrl(),
            'author'    => $snapshot->authorName(),
            'published' => $snapshot->publishedAt(),
            'modified'  => $snapshot->modifiedAt(),
            'image'     => $snapshot->image(),
        ];
        $commerce = $snapshot->commerce();
        if ( $commerce instanceof CommerceFacts ) {
            $tokens['product_name'] = $commerce->name();
            $tokens['sku']          = $commerce->sku();
            $tokens['price']        = $commerce->price();
            $tokens['currency']     = $commerce->currency();
            $tokens['availability'] = $commerce->availability();
            $tokens['brand']        = $commerce->brand();
            if ( $commerce->ratingValue() !== null ) {
                $tokens['rating_value'] = (string) $commerce->ratingValue();
            }
            if ( $commerce->reviewCount() !== null ) {
                $tokens['review_count'] = (string) $commerce->reviewCount();
            }
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $tokens
     */
    private function render( string $template, array $tokens ): string {
        $output = $template;
        foreach ( $tokens as $token => $value ) {
            $output = str_replace( '%%' . $token . '%%', $value, $output );
        }
        $output = preg_replace( '/%%[a-z0-9_]+%%/i', '', $output ) ?? $output;
        $output = preg_replace( '/\s+/', ' ', $output ) ?? $output;

        return trim( $output, " \t\n\r\0\x0B-|" );
    }
}
