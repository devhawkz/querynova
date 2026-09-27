<?php
/**
 * In-process event dispatcher. Listeners cannot take the plugin down.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Events;

use QueryNova\Core\Logging\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class EventDispatcher {

    /** @var array<string, list<callable(EventInterface): void>> */
    private array $listeners = [];

    public function __construct( private readonly ?Logger $logger = null ) {
    }

    /**
     * @param callable(EventInterface): void $listener
     */
    public function listen( string $eventName, callable $listener ): void {
        $this->listeners[ $eventName ][] = $listener;
    }

    public function dispatch( EventInterface $event ): void {
        foreach ( $this->listeners[ $event->name() ] ?? [] as $listener ) {
            try {
                $listener( $event );
            } catch ( \Throwable $exception ) {
                $this->logger?->error(
                    'Event listener failed.',
                    [
                        'channel'   => 'core',
                        'event'     => $event->name(),
                        'exception' => $exception,
                    ]
                );
            }
        }

        if ( function_exists( 'do_action' ) ) {
            do_action( 'querynova_event_' . $event->name(), $event );
        }
    }
}
