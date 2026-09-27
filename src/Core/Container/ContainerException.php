<?php
/**
 * Container configuration error.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Container;

use QueryNova\Core\Exceptions\QueryNovaException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContainerException extends QueryNovaException {

}
