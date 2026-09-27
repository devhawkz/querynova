<?php
/**
 * Provider authentication failed. Do not retry endlessly.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProviderAuthenticationException extends ProviderException {

}
