<?php
/**
 * Publishes QueryNova health checks on the WordPress Site Health screen.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Health;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SiteHealthTests {

    public function __construct( private readonly HealthRegistry $health ) {
    }

    public function register(): void {
        add_filter( 'site_status_tests', [ $this, 'registerTest' ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress Site Health hook.
    }

    /**
     * @param array<string, mixed> $tests
     * @return array<string, mixed>
     */
    public function registerTest( array $tests ): array {
        if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
            $tests['direct'] = [];
        }
        $tests['direct']['querynova'] = [
            'label' => __( 'QueryNova health', 'querynova' ),
            'test'  => [ $this, 'run' ],
        ];

        return $tests;
    }

    /**
     * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
     */
    public function run(): array {
        $reports = $this->health->run();
        $status  = 'good';
        $lines   = [];
        if ( $reports === [] ) {
            $status  = 'recommended';
            $lines[] = __( 'Nothing recorded.', 'querynova' );
        }
        foreach ( $reports as $report ) {
            $mapped = $this->map( $report->status() );
            if ( $this->rank( $mapped ) > $this->rank( $status ) ) {
                $status = $mapped;
            }
            $lines[] = $report->name() . ': ' . $report->summary();
        }

        return [
            'label'       => __( 'QueryNova health', 'querynova' ),
            'status'      => $status,
            'badge'       => [
                'label' => 'QueryNova',
                'color' => 'blue',
            ],
            'description' => '<p>' . esc_html( implode( ' ', $lines ) ) . '</p>',
            'actions'     => '',
            'test'        => 'querynova',
        ];
    }

    private function map( HealthStatus $status ): string {
        return match ( $status ) {
            HealthStatus::Healthy => 'good',
            HealthStatus::Degraded, HealthStatus::Disabled => 'recommended',
            HealthStatus::Unhealthy => 'critical',
        };
    }

    private function rank( string $status ): int {
        return match ( $status ) {
            'critical' => 3,
            'recommended' => 2,
            default => 1,
        };
    }
}
