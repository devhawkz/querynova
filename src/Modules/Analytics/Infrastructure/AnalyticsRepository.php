<?php
/**
 * Stored analytics rows. A null metric is not written as zero.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Infrastructure;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Analytics\Domain\AnalyticsRow;
use QueryNova\Modules\Analytics\Domain\CommerceMetricRow;
use QueryNova\Modules\Analytics\Domain\SearchConsoleRow;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AnalyticsRepository {

    public function __construct( private readonly DatabaseConnection $database ) {
    }

    /**
     * @param list<SearchConsoleRow> $rows
     */
    public function saveSearch( string $property, array $rows ): int {
        $stored = 0;
        foreach ( $rows as $row ) {
            if ( $row->clicks === null || $row->impressions === null ) {
                continue;
            }
            $ctr = $row->ctr;
            if ( $ctr === null && $row->impressions > 0 ) {
                $ctr = round( $row->clicks / $row->impressions, 6 );
            }
            $where = [
                'metric_date' => $row->date,
                'page_id'     => $row->pageId,
                'query_id'    => $row->queryId,
                'country'     => $row->country,
                'device'      => $row->device,
            ];
            $data  = array_merge(
                $where,
                [
                    'clicks'      => $row->clicks,
                    'impressions' => $row->impressions,
                    'ctr'         => $row->impressions > 0 ? $ctr : null,
                    'position'    => $row->position,
                    'property'    => $property,
                    'source'      => 'SEARCH_CONSOLE',
                ]
            );
            $this->upsert( 'gsc_metrics', $where, $data );
            ++$stored;
        }

        return $stored;
    }

    /**
     * @param list<AnalyticsRow> $rows
     */
    public function saveTraffic( array $rows ): int {
        $stored = 0;
        foreach ( $rows as $row ) {
            if ( $row->sessions === null || $row->users === null || $row->organicSessions === null || $row->engagedSessions === null || $row->keyEvents === null ) {
                continue;
            }
            $where = [
                'metric_date' => $row->date,
                'page_id'     => $row->pageId,
                'source'      => $row->source,
                'medium'      => $row->medium,
            ];
            $data  = array_merge(
                $where,
                [
                    'sessions'            => $row->sessions,
                    'users'               => $row->users,
                    'organic_sessions'    => $row->organicSessions,
                    'engaged_sessions'    => $row->engagedSessions,
                    'engagement_rate'     => $row->engagementRate,
                    'avg_engagement_time' => $row->averageEngagementTime,
                    'key_events'          => $row->keyEvents,
                    'revenue'             => $row->revenue,
                    'data_source'         => 'GOOGLE_ANALYTICS',
                ]
            );
            $this->upsert( 'ga_metrics', $where, $data );
            ++$stored;
        }

        return $stored;
    }

    /**
     * @param list<CommerceMetricRow> $rows
     */
    public function saveCommerce( array $rows ): int {
        $stored = 0;
        foreach ( $rows as $row ) {
            if ( $row->views === null || $row->addToCart === null || $row->checkouts === null || $row->orders === null || $row->revenue === null || $row->refunds === null || $row->quantity === null ) {
                continue;
            }
            $where  = [
                'metric_date' => $row->date,
                'product_id'  => $row->productId,
                'category_id' => $row->categoryId,
                'source'      => 'WOOCOMMERCE',
            ];
            $orders = $row->orders;
            $data   = array_merge(
                $where,
                [
                    'views'           => $row->views,
                    'add_to_cart'     => $row->addToCart,
                    'checkouts'       => $row->checkouts,
                    'orders'          => $orders,
                    'revenue'         => $row->revenue,
                    'refunds'         => $row->refunds,
                    'aov'             => $orders > 0 ? round( $row->revenue / $orders, 4 ) : null,
                    'quantity'        => $row->quantity,
                    'conversion_rate' => $row->views > 0 ? round( $orders / $row->views, 6 ) : null,
                ]
            );
            $this->upsert( 'commerce_metrics', $where, $data );
            ++$stored;
        }

        return $stored;
    }

    public function hasSearch(): bool {
        return $this->database->select( $this->table( 'gsc_metrics' ), [], 1 ) !== [];
    }

    /**
     * @param array<string, mixed> $where
     * @param array<string, mixed> $data
     */
    private function upsert( string $name, array $where, array $data ): void {
        $table    = $this->table( $name );
        $existing = $this->database->select( $table, $where, 1 );
        if ( $existing === [] ) {
            $this->database->insert( $table, $data );
            return;
        }
        $this->database->update( $table, $data, $where );
    }

    private function table( string $name ): string {
        return $this->database->prefix() . 'qn_' . $name;
    }
}
