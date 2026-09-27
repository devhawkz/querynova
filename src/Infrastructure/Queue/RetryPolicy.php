<?php
/**
 * Retry policy. Authentication and validation failures are not retried.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Queue;

use QueryNova\Core\Exceptions\ProviderAuthenticationException;
use QueryNova\Core\Exceptions\ProviderRateLimitException;
use QueryNova\Core\Exceptions\ValidationException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RetryPolicy {

    public function shouldRetry( \Throwable $exception, int $attempt, int $maxAttempts ): bool {
        if ( $exception instanceof ValidationException || $exception instanceof ProviderAuthenticationException ) {
            return false;
        }

        return $attempt < $maxAttempts;
    }

    public function delaySeconds( \Throwable $exception, int $attempt ): int {
        if ( $exception instanceof ProviderRateLimitException ) {
            return max( 1, $exception->retryAfterSeconds() );
        }
        $base   = 2 ** max( 0, $attempt - 1 );
        $jitter = ( $attempt * 3 ) % 5;

        return min( 3600, ( $base * 30 ) + $jitter );
    }
}
