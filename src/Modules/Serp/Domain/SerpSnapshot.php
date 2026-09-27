<?php
/**
 * A provider payload. Null from the provider means the snapshot is unavailable, not an empty SERP.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SerpSnapshot {

    /**
     * @param list<SerpHit>    $hits
     * @param list<string>|null $features
     */
    public function __construct(
        public readonly array $hits,
        public readonly ?array $features,
        public readonly string $capturedAt,
    ) {
    }
}
