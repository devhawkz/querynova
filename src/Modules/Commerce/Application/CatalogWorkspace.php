<?php
/**
 * Product and category workspace. Search and filters do not rewrite URLs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CatalogWorkspace {

    /**
     * @param list<array{id: int, name: string, sku: string, issues: list<string>, opportunity: string}> $rows
     * @return array{lead: string, entities: list<array{id: int, name: string, sku: string, issues: list<string>, opportunity: string}>, recent: list<array{id: int, name: string, sku: string, issues: list<string>, opportunity: string}>, total: int, rewritten: bool}
     */
    public static function present( array $rows, string $search, string $issue, string $opportunity, string $kind ): array {
        $search      = strtolower( trim( $search ) );
        $issue       = trim( $issue );
        $opportunity = trim( $opportunity );
        $matched     = [];
        foreach ( $rows as $row ) {
            $haystack = strtolower( $row['name'] . ' ' . $row['sku'] . ' ' . (string) $row['id'] );
            if ( $search !== '' && ! str_contains( $haystack, $search ) ) {
                continue;
            }
            if ( $issue !== '' && ! in_array( $issue, $row['issues'], true ) ) {
                continue;
            }
            if ( $opportunity !== '' && $row['opportunity'] !== $opportunity ) {
                continue;
            }
            $matched[] = $row;
        }
        usort( $matched, [ self::class, 'newerFirst' ] );

        return [
            'lead'      => self::lead( $kind, $matched === [] ),
            'entities'  => $matched,
            'recent'    => array_slice( $matched, 0, 8 ),
            'total'     => count( $matched ),
            'rewritten' => false,
        ];
    }

    public static function lead( string $kind, bool $isEmpty ): string {
        if ( $kind === 'category' ) {
            return $isEmpty
                ? 'Search by category name. Category URLs stay unchanged.'
                : 'Recent categories. Category URLs stay unchanged.';
        }

        return $isEmpty
            ? 'Search by name or SKU. Product URLs stay unchanged.'
            : 'Recent products. Product URLs stay unchanged.';
    }

    /**
     * @param array{id: int, name: string, sku: string, issues: list<string>, opportunity: string} $left
     * @param array{id: int, name: string, sku: string, issues: list<string>, opportunity: string} $right
     */
    private static function newerFirst( array $left, array $right ): int {
        return $right['id'] <=> $left['id'];
    }
}
