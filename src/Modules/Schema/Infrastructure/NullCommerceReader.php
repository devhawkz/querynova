<?php
/**
 * Commerce reader used when WooCommerce is not active.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema\Infrastructure;

use QueryNova\Modules\Schema\Domain\CommerceFacts;
use QueryNova\Modules\Schema\Domain\CommerceReader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullCommerceReader implements CommerceReader {

    public function read( int $productId ): ?CommerceFacts {
        unset( $productId );

        return null;
    }
}
