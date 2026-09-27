<?php
/**
 * What Matters Now payload. Missing rows stay empty. Estimates are not labeled measured.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Infrastructure\Database\DatabaseConnection;
use QueryNova\Modules\Alerts\Infrastructure\AlertRepository;
use QueryNova\Modules\Audit\Infrastructure\AuditRepository;
use QueryNova\Modules\Opportunities\Infrastructure\RecommendationRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DashboardBriefing {

    private const SECTION_LIMIT = 5;

    private const ACTION_LIMIT = 10;

    /**
     * @var array<string, string>
     */
    private const ALERT_SECTIONS = [
        'deindexing'            => 'technical',
        'schema_failure'        => 'technical',
        'provider_failure'      => 'technical',
        'major_rank_loss'       => 'search',
        'traffic_anomaly'       => 'search',
        'revenue_loss'          => 'commerce',
        'conversion_collapse'   => 'commerce',
        'product_feed_issue'    => 'commerce',
        'ai_visibility_decline' => 'ai',
    ];

    /**
     * @var array<string, string>
     */
    private const CHANGE_TITLES = [
        'seo_changed'          => 'SEO change',
        'canonical_changed'    => 'Canonical change',
        'redirect_created'     => 'Redirect created',
        'integration_changed'  => 'Integration change',
        'debug_enabled'        => 'Debug enabled',
        'bulk_action'          => 'Bulk action',
        'feature_flag_changed' => 'Feature flag change',
    ];

    /**
     * @return array{
     *     actions: list<array<string, mixed>>,
     *     sections: array{
     *         revenue: list<array<string, mixed>>,
     *         search: list<array<string, mixed>>,
     *         technical: list<array<string, mixed>>,
     *         commerce: list<array<string, mixed>>,
     *         ai: list<array<string, mixed>>,
     *         recent: list<array<string, mixed>>
     *     }
     * }
     */
    public function fromDatabase( DatabaseConnection $database ): array {
        return $this->compose(
            ( new RecommendationRepository( $database ) )->today(),
            ( new AlertRepository( $database ) )->open( 20 ),
            ( new AuditRepository( $database ) )->recent( 20 )
        );
    }

    /**
     * @param list<array<string, mixed>> $recommendations
     * @param list<array<string, mixed>> $alerts
     * @param list<array<string, mixed>> $changes
     * @return array{
     *     actions: list<array<string, mixed>>,
     *     sections: array{
     *         revenue: list<array<string, mixed>>,
     *         search: list<array<string, mixed>>,
     *         technical: list<array<string, mixed>>,
     *         commerce: list<array<string, mixed>>,
     *         ai: list<array<string, mixed>>,
     *         recent: list<array<string, mixed>>
     *     }
     * }
     */
    public function compose( array $recommendations, array $alerts, array $changes ): array {
        $sections = self::emptySections();
        foreach ( $recommendations as $row ) {
            $placed = $this->recommendation( $row );
            if ( $placed === null ) {
                continue;
            }
            $this->push( $sections, $placed['section'], $placed['item'] );
        }
        foreach ( $alerts as $row ) {
            $placed = $this->alert( $row );
            if ( $placed === null ) {
                continue;
            }
            $this->push( $sections, $placed['section'], $placed['item'] );
        }
        foreach ( $changes as $row ) {
            $item = $this->change( $row );
            if ( $item === null ) {
                continue;
            }
            $this->push( $sections, 'recent', $item );
        }

        return [
            'actions'  => $this->actions( $recommendations ),
            'sections' => $sections,
        ];
    }

    /**
     * @return array{
     *     revenue: list<array<string, mixed>>,
     *     search: list<array<string, mixed>>,
     *     technical: list<array<string, mixed>>,
     *     commerce: list<array<string, mixed>>,
     *     ai: list<array<string, mixed>>,
     *     recent: list<array<string, mixed>>
     * }
     */
    public static function emptySections(): array {
        return [
            'revenue'   => [],
            'search'    => [],
            'technical' => [],
            'commerce'  => [],
            'ai'        => [],
            'recent'    => [],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{section: string, item: array<string, mixed>}|null
     */
    private function recommendation( array $row ): ?array {
        $title = trim( (string) ( $row['title'] ?? '' ) );
        if ( $title === '' ) {
            return null;
        }
        $summary = trim( (string) ( $row['rationale'] ?? '' ) );
        if ( $summary === '' ) {
            $summary = trim( (string) ( $row['description'] ?? '' ) );
        }

        return [
            'section' => $this->commerceSource( $row ) ? 'revenue' : 'search',
            'item'    => $this->item( 'recommendation-' . $this->identity( $row, $title ), $title, $summary, $this->impactMetric( $row ) ),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{section: string, item: array<string, mixed>}|null
     */
    private function alert( array $row ): ?array {
        $type = (string) ( $row['alert_type'] ?? $row['type'] ?? '' );
        if ( ! isset( self::ALERT_SECTIONS[ $type ] ) ) {
            return null;
        }
        $title = trim( (string) ( $row['title'] ?? '' ) );
        if ( $title === '' ) {
            return null;
        }

        return [
            'section' => self::ALERT_SECTIONS[ $type ],
            'item'    => $this->item( 'alert-' . $this->identity( $row, $title ), $title, trim( (string) ( $row['message'] ?? '' ) ), null ),
        ];
    }

    private function changeTitle( string $action ): string {
        return match ( $action ) {
            'seo_changed' => __( 'SEO change', 'querynova' ),
            'canonical_changed' => __( 'Canonical change', 'querynova' ),
            'redirect_created' => __( 'Redirect created', 'querynova' ),
            'integration_changed' => __( 'Integration change', 'querynova' ),
            'debug_enabled' => __( 'Debug enabled', 'querynova' ),
            'bulk_action' => __( 'Bulk action', 'querynova' ),
            'feature_flag_changed' => __( 'Feature flag change', 'querynova' ),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function change( array $row ): ?array {
        $action = (string) ( $row['action'] ?? '' );
        if ( ! isset( self::CHANGE_TITLES[ $action ] ) ) {
            return null;
        }
        $object    = trim( (string) ( $row['object_type'] ?? '' ) );
        $object_id = (int) ( $row['object_id'] ?? 0 );
        $summary   = $object;
        if ( $object_id > 0 ) {
            $summary = trim( $summary . ' ' . (string) $object_id );
        }

        return $this->item( 'change-' . $this->identity( $row, $action ), $this->changeTitle( $action ), trim( $summary ), null );
    }

    /**
     * @param list<array<string, mixed>> $recommendations
     * @return list<array<string, mixed>>
     */
    private function actions( array $recommendations ): array {
        $ranked = [];
        $index  = 0;
        foreach ( $recommendations as $row ) {
            $title = trim( (string) ( $row['title'] ?? '' ) );
            if ( $title === '' ) {
                continue;
            }
            $priority = (string) ( $row['priority'] ?? '' );
            $rank     = 3;
            if ( $priority === 'high' ) {
                $rank = 0;
            } elseif ( $priority === 'medium' ) {
                $rank = 1;
            } elseif ( $priority === 'low' ) {
                $rank = 2;
            }
            $impact   = (string) ( $row['impact'] ?? '' );
            $ranked[] = [
                'rank'  => $rank,
                'index' => $index,
                'item'  => [
                    'id'         => (int) ( $row['id'] ?? 0 ),
                    'title'      => $title,
                    'rationale'  => trim( (string) ( $row['rationale'] ?? '' ) ),
                    'impact'     => $impact === 'estimated' ? 'estimated' : 'unavailable',
                    'confidence' => trim( (string) ( $row['confidence'] ?? '' ) ),
                    'provenance' => $impact === 'estimated' ? 'ESTIMATED' : 'UNAVAILABLE',
                ],
            ];
            ++$index;
        }
        usort(
            $ranked,
            static function ( array $left, array $right ): int {
                $by_rank = (int) $left['rank'] <=> (int) $right['rank'];
                if ( $by_rank !== 0 ) {
                    return $by_rank;
                }

                return (int) $left['index'] <=> (int) $right['index'];
            }
        );
        $actions = [];
        foreach ( $ranked as $row ) {
            $actions[] = $row['item'];
            if ( count( $actions ) === self::ACTION_LIMIT ) {
                break;
            }
        }

        return $actions;
    }

    /**
     * @param array{
     *     revenue: list<array<string, mixed>>,
     *     search: list<array<string, mixed>>,
     *     technical: list<array<string, mixed>>,
     *     commerce: list<array<string, mixed>>,
     *     ai: list<array<string, mixed>>,
     *     recent: list<array<string, mixed>>
     * } $sections
     * @param array<string, mixed> $item
     */
    private function push( array &$sections, string $section, array $item ): void {
        if ( ! isset( $sections[ $section ] ) || count( $sections[ $section ] ) >= self::SECTION_LIMIT ) {
            return;
        }
        $sections[ $section ][] = $item;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function commerceSource( array $row ): bool {
        $sources = $row['data_sources'] ?? $row['data_sources_json'] ?? [];
        if ( is_string( $sources ) ) {
            $decoded = json_decode( $sources, true );
            $sources = is_array( $decoded ) ? $decoded : [];
        }
        if ( ! is_array( $sources ) ) {
            return false;
        }
        foreach ( $sources as $source ) {
            if ( $source === 'commerce' ) {
                return true;
            }
        }

        return false;
    }

    /**
     * A stored impact word is not a measured amount. Estimated stays unlabeled as a number.
     *
     * @param array<string, mixed> $row
     * @return array{value: null, kind: string, label: string}|null
     */
    private function impactMetric( array $row ): ?array {
        if ( (string) ( $row['impact'] ?? '' ) !== 'estimated' ) {
            return null;
        }

        return [
            'value' => null,
            'kind'  => 'ESTIMATED',
            'label' => __( 'Estimated', 'querynova' ),
        ];
    }

    /**
     * @param array<string, mixed>|null $metric
     * @return array<string, mixed>
     */
    private function item( string $id, string $title, string $summary, ?array $metric ): array {
        return [
            'id'      => $id,
            'title'   => $title,
            'summary' => $summary,
            'metric'  => $metric,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function identity( array $row, string $fallback ): string {
        if ( isset( $row['id'] ) && ( is_int( $row['id'] ) || ( is_string( $row['id'] ) && $row['id'] !== '' ) ) ) {
            return (string) $row['id'];
        }

        return hash( 'sha256', $fallback );
    }
}
