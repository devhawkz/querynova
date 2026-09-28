<?php
/**
 * Analytics screen model. Missing metrics stay null. No chart series is built.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Application;

use QueryNova\Modules\Analytics\Domain\SearchConsoleRow;
use QueryNova\Modules\Serp\Application\IndexAvailability;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AnalyticsScreens {

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function screens(): array {
        return [
            [
                'id'    => 'overview',
                'label' => 'Overview',
            ],
            [
                'id'    => 'seo_performance',
                'label' => 'SEO performance',
            ],
            [
                'id'    => 'keywords',
                'label' => 'Keywords',
            ],
            [
                'id'    => 'content',
                'label' => 'Content',
            ],
            [
                'id'    => 'rank_tracker',
                'label' => 'Rank tracker',
            ],
            [
                'id'    => 'index_status',
                'label' => 'Index status',
            ],
            [
                'id'    => 'traffic',
                'label' => 'Traffic',
            ],
            [
                'id'    => 'commerce',
                'label' => 'Commerce',
            ],
            [
                'id'    => 'ai',
                'label' => 'AI',
            ],
        ];
    }

    /**
     * @param array<string, mixed>                $search
     * @param array<string, mixed>                $analytics
     * @param list<SearchConsoleRow>|null         $currentSearch
     * @param list<SearchConsoleRow>|null         $previousSearch
     * @param list<\QueryNova\Modules\Analytics\Domain\AnalyticsRow>|null $currentTraffic
     * @param list<\QueryNova\Modules\Analytics\Domain\AnalyticsRow>|null $previousTraffic
     * @param array<string, mixed>|null           $experience
     * @param array<string, mixed>                $indexStatus
     * @param array<string, mixed>                $trends
     * @return array<string, mixed>
     */
    public static function present( array $search, array $analytics, string $start, string $end, ?array $currentSearch, ?array $previousSearch, ?array $currentTraffic, ?array $previousTraffic, ?array $experience, array $indexStatus, array $trends ): array {
        $range       = self::periods( $start, $end );
        $workspace   = new AnalyticsWorkspace();
        $measured    = $workspace->search( $currentSearch );
        $movement    = $workspace->movement( $currentSearch, $previousSearch );
        $posts       = self::posts( $currentSearch, $previousSearch );
        $sessions    = self::sessions( $currentTraffic );
        $revenue     = self::revenue( $currentTraffic );
        $clicks      = is_int( $measured['clicks'] ?? null ) ? $measured['clicks'] : null;
        $impressions = is_int( $measured['impressions'] ?? null ) ? $measured['impressions'] : null;

        return [
            'name'           => 'Analytics',
            'screens'        => self::screens(),
            'range'          => $range,
            'search_console' => $search,
            'ga4'            => $analytics,
            'kpis'           => [
                self::kpi( 'clicks', 'Clicks', $clicks, $search ),
                self::kpi( 'impressions', 'Impressions', $impressions, $search ),
                self::kpi( 'sessions', 'Sessions', $sessions, $analytics ),
                self::kpi( 'revenue', 'Revenue', $revenue, $analytics ),
            ],
            'comparison'     => [
                'winning_keywords' => $movement['growing_queries'],
                'losing_keywords'  => $movement['declining_queries'],
                'winning_posts'    => $posts['winning_posts'],
                'losing_posts'     => $posts['losing_posts'],
                'previous_start'   => $range['previous_start'],
                'previous_end'     => $range['previous_end'],
                'note'             => is_string( $movement['note'] ?? null ) ? $movement['note'] : $posts['note'],
            ],
            'index_status'   => $indexStatus === [] ? IndexAvailability::report( '', null, 'Index status' ) : $indexStatus,
            'trends'         => $trends === [] ? IndexAvailability::report( '', null, 'Trends' ) : $trends,
            'experience'     => self::experience( $experience ),
            'commerce'       => [
                'revenue' => null,
                'orders'  => null,
                'state'   => 'Not connected',
                'note'    => 'Commerce metrics stay empty until a provider supplies them.',
            ],
            'ai'             => [
                'sessions' => null,
                'revenue'  => null,
                'state'    => 'Not available',
                'note'     => 'AI metrics are shown only when a provider supplies them.',
            ],
        ];
    }

    /**
     * @return array{valid: bool, start: string, end: string, previous_start: string|null, previous_end: string|null, note: string}
     */
    public static function periods( string $start, string $end ): array {
        $startDate = self::date( $start );
        $endDate   = self::date( $end );
        if ( ! $startDate instanceof \DateTimeImmutable || ! $endDate instanceof \DateTimeImmutable || $endDate < $startDate ) {
            return [
                'valid'          => false,
                'start'          => $start,
                'end'            => $end,
                'previous_start' => null,
                'previous_end'   => null,
                'note'           => 'Use dates as YYYY-MM-DD. The previous period stays empty until both dates are valid.',
            ];
        }
        $days = (int) $startDate->diff( $endDate )->days;
        if ( $days > 366 ) {
            return [
                'valid'          => false,
                'start'          => $start,
                'end'            => $end,
                'previous_start' => null,
                'previous_end'   => null,
                'note'           => 'Choose a range of 366 days or fewer. The previous period stays empty.',
            ];
        }
        $previousEnd   = $startDate->modify( '-1 day' );
        $previousStart = $previousEnd->modify( '-' . $days . ' days' );

        return [
            'valid'          => true,
            'start'          => $startDate->format( 'Y-m-d' ),
            'end'            => $endDate->format( 'Y-m-d' ),
            'previous_start' => $previousStart->format( 'Y-m-d' ),
            'previous_end'   => $previousEnd->format( 'Y-m-d' ),
            'note'           => 'The previous period is the same number of days immediately before this range.',
        ];
    }

    /**
     * @param list<SearchConsoleRow>|null $current
     * @param list<SearchConsoleRow>|null $previous
     * @return array{winning_posts: list<array{page_id: int, clicks: int, previous_clicks: int}>|null, losing_posts: list<array{page_id: int, clicks: int, previous_clicks: int}>|null, note: string}
     */
    public static function posts( ?array $current, ?array $previous ): array {
        if ( $current === null || $previous === null ) {
            return [
                'winning_posts' => null,
                'losing_posts'  => null,
                'note'          => 'Post movement needs both periods. A missing period is not zero.',
            ];
        }
        $now     = self::clicksByPage( $current );
        $then    = self::clicksByPage( $previous );
        $winning = [];
        $losing  = [];
        foreach ( array_unique( array_merge( array_keys( $now ), array_keys( $then ) ) ) as $pageId ) {
            $after  = $now[ $pageId ] ?? null;
            $before = $then[ $pageId ] ?? null;
            if ( $after === null || $before === null ) {
                continue;
            }
            $row = [
                'page_id'         => $pageId,
                'clicks'          => $after,
                'previous_clicks' => $before,
            ];
            if ( $after > $before ) {
                $winning[] = $row;
            }
            if ( $before > $after ) {
                $losing[] = $row;
            }
        }

        return [
            'winning_posts' => $winning,
            'losing_posts'  => $losing,
            'note'          => 'A page missing from either period is left out. It is not treated as zero.',
        ];
    }

    /**
     * @param list<SearchConsoleRow> $rows
     * @return array<int, int>
     */
    private static function clicksByPage( array $rows ): array {
        $grouped = [];
        foreach ( $rows as $row ) {
            if ( ! $row instanceof SearchConsoleRow || $row->clicks === null ) {
                continue;
            }
            $pageId             = $row->pageId;
            $grouped[ $pageId ] = ( $grouped[ $pageId ] ?? 0 ) + $row->clicks;
        }

        return $grouped;
    }

    /**
     * @param list<\QueryNova\Modules\Analytics\Domain\AnalyticsRow>|null $rows
     */
    private static function sessions( ?array $rows ): ?int {
        if ( $rows === null ) {
            return null;
        }
        $total = 0;
        foreach ( $rows as $row ) {
            if ( $row->sessions === null ) {
                return null;
            }
            $total += $row->sessions;
        }

        return $total;
    }

    /**
     * @param list<\QueryNova\Modules\Analytics\Domain\AnalyticsRow>|null $rows
     */
    private static function revenue( ?array $rows ): ?float {
        if ( $rows === null ) {
            return null;
        }
        $total = 0.0;
        foreach ( $rows as $row ) {
            if ( $row->revenue === null ) {
                return null;
            }
            $total += $row->revenue;
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $card
     * @return array{id: string, label: string, value: int|float|null, state: string}
     */
    private static function kpi( string $id, string $label, int|float|null $value, array $card ): array {
        $state = 'Not available';
        if ( $value === null && ( $card['status'] ?? '' ) !== 'connected' ) {
            $state = 'Not connected';
        }
        if ( $value !== null ) {
            $state = 'Measured';
        }

        return [
            'id'    => $id,
            'label' => $label,
            'value' => $value,
            'state' => $state,
        ];
    }

    /**
     * @param array<string, mixed>|null $experience
     * @return array<string, mixed>
     */
    private static function experience( ?array $experience ): array {
        return [
            'lcp'  => self::timing( $experience, 'lcp' ),
            'inp'  => self::timing( $experience, 'inp' ),
            'cls'  => self::timing( $experience, 'cls' ),
            'ttfb' => self::timing( $experience, 'ttfb' ),
            'note' => 'Page experience timings stay empty when they were not measured. They are not an SEO score.',
        ];
    }

    /**
     * @param array<string, mixed>|null $experience
     */
    private static function timing( ?array $experience, string $key ): mixed {
        if ( ! is_array( $experience ) ) {
            return null;
        }
        $metric = $experience[ $key ] ?? null;
        if ( ! is_array( $metric ) ) {
            return null;
        }

        return $metric['value'] ?? null;
    }

    private static function date( string $value ): ?\DateTimeImmutable {
        $date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
        if ( ! $date instanceof \DateTimeImmutable || $date->format( 'Y-m-d' ) !== $value ) {
            return null;
        }
        $errors = \DateTimeImmutable::getLastErrors();
        if ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
            return null;
        }

        return $date;
    }
}
