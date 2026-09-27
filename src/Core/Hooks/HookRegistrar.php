<?php
/**
 * Registers WordPress hooks outside constructors.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Hooks;

use QueryNova\Core\Contracts\HookSubscriberInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class HookRegistrar {

    /** @var list<HookSubscriberInterface> */
    private array $subscribers = [];

    private bool $registered = false;

    public function add( HookSubscriberInterface $subscriber ): void {
        $this->subscribers[] = $subscriber;
        if ( $this->registered ) {
            $this->registerSubscriber( $subscriber );
        }
    }

    public function register(): void {
        if ( $this->registered ) {
            return;
        }
        $this->registered = true;
        foreach ( $this->subscribers as $subscriber ) {
            $this->registerSubscriber( $subscriber );
        }
    }

    private function registerSubscriber( HookSubscriberInterface $subscriber ): void {
        foreach ( $subscriber->hooks() as $hook => $callback ) {
            $method   = is_array( $callback ) ? $callback[0] : $callback;
            $priority = is_array( $callback ) ? $callback[1] : 10;
            $args     = is_array( $callback ) ? $callback[2] : 1;
            $callable = [ $subscriber, $method ];
            if ( $subscriber->hookType( $hook ) === 'filter' ) {
                add_filter( $hook, $callable, $priority, $args );
            } else {
                add_action( $hook, $callable, $priority, $args );
            }
        }
    }
}
