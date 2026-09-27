<?php
/**
 * One link from a provider. Authority stays null when that provider did not send it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Backlinks\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Backlink {

    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $targetUrl,
        public readonly string $anchor,
        public readonly string $rel,
        public readonly ?string $firstSeen,
        public readonly ?string $lastSeen,
        public readonly ?float $authority,
        public readonly string $authorityMetric,
    ) {
    }

    public function sourceDomain(): string {
        $host = strtolower( (string) ( wp_parse_url( $this->sourceUrl, PHP_URL_HOST ) ?? '' ) );

        return str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
    }
}
