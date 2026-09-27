<?php
/**
 * Collects health checks. One failing optional check does not fail the rest.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Health;

use QueryNova\Core\Contracts\HealthCheckInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HealthRegistry {

    /** @var array<string, HealthCheckInterface> */
    private array $checks = [];

    public function add( HealthCheckInterface $check ): void {
        $this->checks[ $check->name() ] = $check;
    }

    /**
     * @return list<HealthReport>
     */
    public function run(): array {
        $reports = [];
        foreach ( $this->checks as $check ) {
            try {
                $reports[] = $check->check();
            } catch ( \Throwable $exception ) {
                $reports[] = new HealthReport(
                    $check->name(),
                    HealthStatus::Unhealthy,
                    'Health check failed.',
                    [ 'exception' => $exception::class ],
                );
            }
        }

        return $reports;
    }

    public function overall(): HealthStatus {
        $rank  = [
            HealthStatus::Healthy->value   => 0,
            HealthStatus::Disabled->value  => 1,
            HealthStatus::Degraded->value  => 2,
            HealthStatus::Unhealthy->value => 3,
        ];
        $worst = HealthStatus::Healthy;
        foreach ( $this->run() as $report ) {
            if ( $rank[ $report->status()->value ] > $rank[ $worst->value ] ) {
                $worst = $report->status();
            }
        }

        return $worst;
    }
}
