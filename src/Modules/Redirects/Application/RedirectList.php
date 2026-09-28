<?php
/**
 * Search, filter, and paginate redirect or 404 rows.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RedirectList {

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public static function slice( array $rows, string $search, string $status, int $page, int $perPage = 20 ): array {
        $search  = strtolower( trim( $search ) );
        $status  = trim( $status );
        $perPage = max( 1, min( 100, $perPage ) );
        $page    = max( 1, $page );
        $matched = [];
        foreach ( $rows as $row ) {
            $haystack = strtolower( (string) ( $row['source'] ?? '' ) . ' ' . (string) ( $row['target'] ?? '' ) . ' ' . (string) ( $row['url'] ?? '' ) );
            if ( $search !== '' && ! str_contains( $haystack, $search ) ) {
                continue;
            }
            if ( $status !== '' && (string) ( $row['status'] ?? '' ) !== $status ) {
                continue;
            }
            $matched[] = $row;
        }
        $total  = count( $matched );
        $offset = ( $page - 1 ) * $perPage;

        return [
            'rows'     => array_slice( $matched, $offset, $perPage ),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil( $total / $perPage ),
        ];
    }
}
