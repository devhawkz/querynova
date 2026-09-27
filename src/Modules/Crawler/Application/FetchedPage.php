<?php
/**
 * One HTTP result. Status 0 means the fetch did not complete.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FetchedPage {

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
        public readonly string $error = '',
    ) {
    }
}
