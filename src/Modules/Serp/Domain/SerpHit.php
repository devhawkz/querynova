<?php
/**
 * One organic result supplied by a provider. Features are null when the provider omitted them.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SerpHit {

    /**
     * @param list<string>|null $features
     */
    public function __construct(
        public readonly int $position,
        public readonly string $url,
        public readonly string $domain,
        public readonly string $title,
        public readonly string $snippet,
        public readonly string $pageType,
        public readonly ?array $features,
    ) {
    }
}
