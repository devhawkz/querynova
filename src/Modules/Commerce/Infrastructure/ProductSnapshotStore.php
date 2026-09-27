<?php
/**
 * Stores measured product fields. Unknown prices and margins stay null.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Commerce\Domain\CatalogProduct;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProductSnapshotStore {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    public function save( CatalogProduct $product ): void {
        $now      = gmdate( 'Y-m-d H:i:s' );
        $data     = [
            'product_id'         => $product->id,
            'sku'                => $product->sku,
            'brand'              => $product->brand,
            'gtin'               => $product->identifier(),
            'mpn'                => $product->mpn,
            'price'              => $product->price,
            'currency'           => $product->currency,
            'availability'       => $product->availability,
            'stock_status'       => $product->availability,
            'indexability'       => $product->indexability ?? 'unknown',
            'primary_keyword_id' => 0,
            'updated_at'         => $now,
        ];
        $existing = $this->database->select( $this->table(), [ 'product_id' => $product->id ], 1 );
        if ( $existing === [] ) {
            $data['created_at'] = $now;
            $this->database->insert( $this->table(), $data );

            return;
        }
        $this->database->update( $this->table(), $data, [ 'product_id' => $product->id ] );
    }

    private function table(): string {
        return $this->database->prefix() . 'qn_products';
    }
}
