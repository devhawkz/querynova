<?php
/**
 * Missing service.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Container;

use QueryNova\Core\Exceptions\QueryNovaException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NotFoundException extends QueryNovaException {

    public function __construct( string $id ) {
        parent::__construct( sprintf( 'Service "%s" is not registered.', $id ) );
    }
}
