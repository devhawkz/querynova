<?php
/**
 * Uses Action Scheduler when another plugin or this package has loaded it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Queue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ActionSchedulerAdapter {

    public function isAvailable(): bool {
        return function_exists( 'as_enqueue_async_action' );
    }

    public function enqueue( int $jobId ): void {
        if ( ! $this->isAvailable() ) {
            return;
        }
        as_enqueue_async_action( 'querynova_run_job', [ 'job_id' => $jobId ], 'querynova' );
    }
}
