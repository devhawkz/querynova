<?php
/**
 * Paged product and category SEO rows. Unmeasured analytics stay empty, not zero.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Application;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Commerce\Domain\CatalogProduct;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\MetaStoreInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class BulkCatalog {

    public const HEADER = 'entity_type,entity_id,seo_title,description,primary_keyword,indexability,clicks,impressions,revenue,opportunity,metric_provenance';

    public function __construct(
        private readonly SeoMetaService $seo,
        private readonly MetaStoreInterface $meta,
    ) {
    }

    /**
     * @param list<CatalogProduct> $products
     * @return list<array<string, mixed>>
     */
    public function rows( array $products ): array {
        $rows = [];
        foreach ( $products as $product ) {
            $rows[] = [
                'entity'            => 'product',
                'id'                => $product->id,
                'seo_title'         => $product->seoTitle,
                'description'       => $product->seoDescription,
                'primary_keyword'   => $this->keyword( 'product', $product->id ),
                'indexability'      => $product->indexability,
                'schema'            => null,
                'clicks'            => null,
                'impressions'       => null,
                'revenue'           => null,
                'opportunity'       => null,
                'metric_provenance' => 'unavailable',
            ];
        }

        return $rows;
    }

    /**
     * @param list<CatalogProduct> $products
     */
    public function export( array $products ): string {
        $lines = [ self::HEADER ];
        foreach ( $this->rows( $products ) as $row ) {
            $lines[] = implode(
                ',',
                [
                    'product',
                    (string) $row['id'],
                    $this->cell( $row['seo_title'] ),
                    $this->cell( $row['description'] ),
                    $this->cell( $row['primary_keyword'] ),
                    $this->cell( $row['indexability'] ),
                    '',
                    '',
                    '',
                    '',
                    'unavailable',
                ]
            );
        }

        return implode( "\n", $lines );
    }

    /**
     * Adds or updates rows. It does not delete products that are absent from the file.
     *
     * @return array{updated: int}
     */
    public function import( string $csv ): array {
        $lines = preg_split( '/\r\n|\n|\r/', trim( $csv ) );
        if ( ! is_array( $lines ) || $lines === [] ) {
            throw new ValidationException( 'The CSV is empty.' );
        }
        $header   = str_getcsv( (string) array_shift( $lines ), ',', '"', '\\' );
        $expected = explode( ',', self::HEADER );
        if ( array_slice( $header, 0, 6 ) !== array_slice( $expected, 0, 6 ) ) {
            throw new ValidationException( 'The CSV header must start with entity_type,entity_id,seo_title,description,primary_keyword,indexability.' );
        }
        $updated = 0;
        foreach ( $lines as $line ) {
            if ( trim( $line ) === '' ) {
                continue;
            }
            $cells = str_getcsv( $line, ',', '"', '\\' );
            $type  = (string) ( $cells[0] ?? '' );
            $id    = (int) ( $cells[1] ?? 0 );
            if ( ! in_array( $type, [ 'product', 'category' ], true ) || $id <= 0 ) {
                throw new ValidationException( 'Each row needs a product or category id.' );
            }
            $objectType = $type === 'category' ? 'term' : 'product';
            $fields     = [];
            if ( (string) ( $cells[2] ?? '' ) !== '' ) {
                $fields[ SeoMetaService::TITLE ] = (string) $cells[2];
            }
            if ( (string) ( $cells[3] ?? '' ) !== '' ) {
                $fields[ SeoMetaService::DESCRIPTION ] = (string) $cells[3];
            }
            if ( (string) ( $cells[5] ?? '' ) !== '' ) {
                $fields[ SeoMetaService::ROBOTS_INDEX ] = (string) $cells[5];
            }
            if ( $fields !== [] ) {
                $this->seo->save( $objectType, $id, $fields );
            }
            $keyword = trim( (string) ( $cells[4] ?? '' ) );
            if ( $keyword !== '' ) {
                $this->meta->set( $objectType, $id, 'primary_keyword', $keyword );
            }
            ++$updated;
        }

        return [ 'updated' => $updated ];
    }

    private function keyword( string $objectType, int $id ): ?string {
        $stored = $this->meta->get( $objectType, $id, 'primary_keyword' );

        return $stored === '' ? null : $stored;
    }

    private function cell( mixed $value ): string {
        if ( ! is_string( $value ) || $value === '' ) {
            return '';
        }
        if ( str_contains( $value, ',' ) || str_contains( $value, '"' ) ) {
            return '"' . str_replace( '"', '""', $value ) . '"';
        }

        return $value;
    }
}
