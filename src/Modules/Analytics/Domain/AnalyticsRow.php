<?php
/**
 * One GA4 row. Revenue stays null when the property did not send it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AnalyticsRow {

    public function __construct(
        public readonly string $date,
        public readonly int $pageId,
        public readonly ?int $sessions,
        public readonly ?int $users,
        public readonly ?int $organicSessions,
        public readonly ?int $engagedSessions,
        public readonly ?float $engagementRate,
        public readonly ?float $averageEngagementTime,
        public readonly ?int $keyEvents,
        public readonly ?float $revenue,
        public readonly string $source,
        public readonly string $medium,
    ) {
    }
}
