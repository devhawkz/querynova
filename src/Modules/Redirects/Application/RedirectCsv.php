<?php
/**
 * Imports and exports redirect rules as CSV.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Application;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Redirects\Domain\RedirectRule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RedirectCsv {

    public function __construct( private readonly RedirectEngine $engine ) {
    }

    /**
     * @param list<RedirectRule> $rules
     */
    public function export( array $rules ): string {
        $lines = [ 'source,target,status,regex' ];
        foreach ( $rules as $rule ) {
            $lines[] = $this->cell( $rule->source() ) . ',' . $this->cell( $rule->target() ) . ',' . $rule->status() . ',' . ( $rule->regex() ? '1' : '0' );
        }

        return implode( "\n", $lines ) . "\n";
    }

    /**
     * @param list<RedirectRule> $existing
     * @return list<RedirectRule>
     */
    public function import( string $csv, array $existing ): array {
        $rows = preg_split( '/\r\n|\n|\r/', trim( $csv ) );
        if ( ! is_array( $rows ) || $rows === [] || count( $rows ) > 5001 ) {
            throw new ValidationException( 'Redirect import must be a CSV of at most 5000 rules.' );
        }
        $header = str_getcsv( (string) array_shift( $rows ), ',', '"', '\\' );
        if ( $header !== [ 'source', 'target', 'status', 'regex' ] ) {
            throw new ValidationException( 'Redirect CSV header must be source,target,status,regex.' );
        }
        $rules = $existing;
        foreach ( $rows as $row ) {
            if ( trim( $row ) === '' ) {
                continue;
            }
            $cells = str_getcsv( $row, ',', '"', '\\' );
            if ( count( $cells ) !== 4 ) {
                throw new ValidationException( 'Each redirect row needs source, target, status, and regex.' );
            }
            $status = (int) $cells[2];
            $regex  = $cells[3] === '1';
            $rule   = new RedirectRule(
                0,
                $this->engine->normalizeSource( $cells[0], $regex ),
                $this->engine->normalizeTarget( $cells[1], $status ),
                $status,
                $regex
            );
            $this->engine->assertSafe( $rules, $rule );
            $rules[] = $rule;
        }

        return $rules;
    }

    private function cell( string $value ): string {
        if ( str_contains( $value, ',' ) || str_contains( $value, '"' ) || str_contains( $value, "\n" ) ) {
            return '"' . str_replace( '"', '""', $value ) . '"';
        }

        return $value;
    }
}
