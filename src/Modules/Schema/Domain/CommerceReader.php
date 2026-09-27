<?php
/**
 * Reads commerce facts without the schema graph depending on WooCommerce classes.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface CommerceReader {

    public function read( int $productId ): ?CommerceFacts;
}
