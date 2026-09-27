<?php
/**
 * CSV and JSON reports. Missing metrics stay empty, and PDF is not generated.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Reports\Application;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Ai\Application\AiVisibility;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ReportBuilder {

    /**
     * @var array<string, list<string>>
     */
    private const KINDS = [
        'seo'        => [ 'clicks', 'impressions', 'ctr', 'position' ],
        'commerce'   => [ 'revenue', 'orders', 'conversion' ],
        'executive'  => [ 'organic_revenue', 'organic_orders', 'organic_growth', 'visibility', 'top_gains', 'top_losses', 'top_opportunities', 'ai_visibility', 'completed_actions' ],
        'keyword'    => [ 'keyword', 'volume', 'difficulty' ],
        'competitor' => [ 'domain', 'overlap', 'authority' ],
        'ai'         => [ 'mentions', 'citations', 'index' ],
    ];

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function build( string $kind, array $input, string $format ): array {
        if ( ! isset( self::KINDS[ $kind ] ) ) {
            throw new ValidationException( 'Unknown report.' );
        }
        if ( $format === 'pdf' ) {
            return [
                'format' => 'pdf',
                'body'   => null,
                'note'   => 'PDF is not generated.',
            ];
        }
        if ( ! in_array( $format, [ 'csv', 'json' ], true ) ) {
            throw new ValidationException( 'Report format must be csv or json.' );
        }
        $values = [];
        foreach ( self::KINDS[ $kind ] as $key ) {
            $values[ $key ] = $this->value( $key, $input );
        }
        if ( $kind === 'executive' ) {
            $values['organic_growth'] = $this->growth( $input['previous_revenue'] ?? null, $input['organic_revenue'] ?? null );
        }
        $document = [
            'kind'       => $kind,
            'values'     => $values,
            'disclaimer' => $kind === 'ai' || $kind === 'executive' ? AiVisibility::DISCLAIMER : null,
        ];

        return [
            'format' => $format,
            'body'   => $format === 'json' ? (string) wp_json_encode( $document ) : $this->csv( $values ),
            'note'   => 'Empty cells are missing values, not zero.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function value( string $key, array $input ): mixed {
        if ( ! array_key_exists( $key, $input ) || $input[ $key ] === null || $input[ $key ] === '' ) {
            return null;
        }
        $value = $input[ $key ];
        if ( is_int( $value ) || is_float( $value ) || is_string( $value ) ) {
            return $value;
        }

        return null;
    }

    private function growth( mixed $previous, mixed $current ): ?float {
        if ( ! is_int( $previous ) && ! is_float( $previous ) ) {
            return null;
        }
        if ( ! is_int( $current ) && ! is_float( $current ) ) {
            return null;
        }

        return round( (float) $current - (float) $previous, 4 );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function csv( array $values ): string {
        $headers = [];
        $cells   = [];
        foreach ( $values as $key => $value ) {
            $headers[] = $key;
            $cells[]   = $this->cell( $value );
        }

        return $this->line( $headers ) . "\n" . $this->line( $cells ) . "\n";
    }

    private function cell( mixed $value ): string {
        if ( $value === null ) {
            return '';
        }
        if ( is_float( $value ) || is_int( $value ) ) {
            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * @param list<string> $cells
     */
    private function line( array $cells ): string {
        $escaped = [];
        foreach ( $cells as $cell ) {
            if ( str_contains( $cell, ',' ) || str_contains( $cell, '"' ) || str_contains( $cell, "\n" ) ) {
                $escaped[] = '"' . str_replace( '"', '""', $cell ) . '"';
                continue;
            }
            $escaped[] = $cell;
        }

        return implode( ',', $escaped );
    }
}
