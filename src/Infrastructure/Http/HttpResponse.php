<?php
/**
 * HTTP response value.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Http;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HttpResponse {

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function json(): ?array {
        $decoded = json_decode( $this->body, true );

        return is_array( $decoded ) ? $decoded : null;
    }
}
