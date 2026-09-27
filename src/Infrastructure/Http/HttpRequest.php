<?php
/**
 * HTTP request value.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Http;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HttpRequest {

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $json
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?array $json = null,
        public readonly int $timeout = 15,
    ) {
    }
}
