<?php
/**
 * Provider rate limit. Retry after the supplied delay.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProviderRateLimitException extends ProviderException {

    public function __construct(
        string $message,
        private readonly int $retryAfterSeconds = 60,
    ) {
        parent::__construct( $message );
    }

    public function retryAfterSeconds(): int {
        return $this->retryAfterSeconds;
    }
}
