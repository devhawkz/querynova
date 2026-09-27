<?php
/**
 * Base plugin exception. User-facing output uses an error reference, not this message.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QueryNovaException extends \RuntimeException {

}
