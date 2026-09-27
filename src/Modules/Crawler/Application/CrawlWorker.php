<?php
/**
 * Runs one crawl batch under a lock, then queues the next batch.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Crawler\Application;

use QueryNova\Core\Exceptions\JobException;
use QueryNova\Infrastructure\Lock\LockInterface;
use QueryNova\Modules\Crawler\Infrastructure\CrawlPageStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CrawlWorker {

    public function __construct(
        private readonly CrawlBatch $batch,
        private readonly CrawlPageStore $store,
        private readonly LockInterface $lock,
    ) {
    }

    /**
     * @param array<string, mixed>     $payload
     * @param callable(CrawlRun): void $enqueueNext
     */
    public function handle( array $payload, callable $enqueueNext ): void {
        if ( ! $this->lock->acquire( 'crawl', 120 ) ) {
            throw new JobException( 'A crawl is already running.' );
        }
        try {
            $next = $this->batch->step( CrawlRun::fromPayload( $payload ) );
            $this->store->persist( $next );
            if ( ! $next->done() ) {
                $enqueueNext( $next );
            }
        } finally {
            $this->lock->release( 'crawl' );
        }
    }
}
