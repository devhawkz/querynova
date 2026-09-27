<?php
/**
 * Migration exception.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MigrationException extends DatabaseException {

}
