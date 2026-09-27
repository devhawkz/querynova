<?php
/**
 * One Search Console row. Null metrics are omitted rather than stored as zero.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SearchConsoleRow {

    public function __construct(
        public readonly string $date,
        public readonly int $pageId,
        public readonly int $queryId,
        public readonly string $query,
        public readonly string $country,
        public readonly string $device,
        public readonly ?int $clicks,
        public readonly ?int $impressions,
        public readonly ?float $ctr,
        public readonly ?float $position,
    ) {
    }
}
