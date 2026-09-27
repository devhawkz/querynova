<?php
/**
 * Runs queued work with idempotent creation and bounded retries.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Queue;

use QueryNova\Core\Contracts\ClockInterface;
use QueryNova\Core\Logging\CorrelationContext;
use QueryNova\Core\Logging\ErrorReference;
use QueryNova\Core\Logging\LogChannel;
use QueryNova\Core\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class JobRunner {

    public function __construct(
        private readonly JobRepository $jobs,
        private readonly JobRegistrar $registrar,
        private readonly RetryPolicy $retries,
        private readonly ClockInterface $clock,
        private readonly CorrelationContext $correlation,
        private readonly ?Logger $logger = null,
        private readonly ?ActionSchedulerAdapter $scheduler = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue( string $type, array $payload, string $idempotencyKey, int $priority = 10 ): int {
        $id = $this->jobs->create(
            $type,
            $payload,
            $idempotencyKey,
            $this->correlation->correlationId(),
            $this->clock->now(),
            $priority,
        );
        $this->scheduler?->enqueue( $id );

        return $id;
    }

    public function workDue( int $limit = 10 ): int {
        $processed = 0;
        foreach ( $this->jobs->due( $limit, $this->clock->now() ) as $job ) {
            $this->work( (int) $job['id'] );
            ++$processed;
        }

        return $processed;
    }

    public function work( int $id ): void {
        if ( ! $this->jobs->claim( $id, $this->clock->now() ) ) {
            return;
        }
        $job = $this->jobs->find( $id );
        if ( $job === null ) {
            return;
        }
        $this->correlation->setJobId( (string) $id );
        $payload = json_decode( (string) ( $job['payload'] ?? '{}' ), true );
        if ( ! is_array( $payload ) ) {
            $payload = [];
        }
        try {
            $this->registrar->handle( (string) $job['job_type'], $payload, $job );
            $this->jobs->complete( $id, $this->clock->now() );
        } catch ( \Throwable $exception ) {
            $reference = ErrorReference::generate();
            $attempt   = (int) $job['attempt'];
            $max       = (int) $job['max_attempts'];
            $this->logger?->error(
                'Job failed.',
                [
					'channel'         => LogChannel::JOBS,
					'job_type'        => (string) $job['job_type'],
					'error_reference' => $reference,
					'exception'       => $exception,
				]
            );
            if ( $this->retries->shouldRetry( $exception, $attempt, $max ) ) {
                $delay = $this->retries->delaySeconds( $exception, $attempt );
                $this->jobs->markRetry(
                    $id,
                    $this->clock->now()->modify( '+' . $delay . ' seconds' ),
                    $exception->getMessage(),
                    $reference,
                );
            } else {
                $this->jobs->markDead( $id, $exception->getMessage(), $reference, $this->clock->now() );
            }
        } finally {
            $this->correlation->setJobId( null );
        }
    }
}
