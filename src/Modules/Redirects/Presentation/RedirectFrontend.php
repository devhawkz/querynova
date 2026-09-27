<?php
/**
 * Applies stored redirects and records 404s.
 *
 * A 404 is never turned into a redirect by itself.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Presentation;

use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Modules\Redirects\Application\RedirectEngine;
use QueryNova\Modules\Redirects\Infrastructure\NotFoundRepository;
use QueryNova\Modules\Redirects\Infrastructure\RedirectRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RedirectFrontend implements HookSubscriberInterface {

    public function __construct(
        private readonly RedirectEngine $engine,
        private readonly RedirectRepository $redirects,
        private readonly NotFoundRepository $missing,
        private readonly FeatureRegistry $features,
        private readonly EnvironmentInterface $environment,
    ) {
    }

    public function hooks(): array {
        return [
            'template_redirect' => [ 'serve', 0, 0 ],
        ];
    }

    public function hookType( string $hook ): string {
        unset( $hook );

        return 'action';
    }

    public function serve(): void {
        if ( ! $this->features->isEnabled( 'querynova.redirects', $this->environment ) ) {
            return;
        }
        $rules    = $this->redirects->enabled();
        $path     = $this->requestPath();
        $decision = $this->engine->resolve( $path, $rules );
        if ( $decision->loop() || ! $decision->matched() ) {
            $this->recordMiss( $path, $rules );

            return;
        }
        $this->redirects->increment( $decision->ruleId() );
        if ( function_exists( 'status_header' ) ) {
            status_header( $decision->status() );
        }
        if ( $decision->location() !== '' && in_array( $decision->status(), [ 301, 302, 307 ], true ) ) {
            $this->send( $decision->location(), $decision->status() );
        }
        if ( $decision->status() === 410 ) {
            echo esc_html__( 'This page is gone.', 'querynova' );
        } elseif ( $decision->status() === 451 ) {
            echo esc_html__( 'This page is unavailable for legal reasons.', 'querynova' );
        }
        exit;
    }

    private function send( string $location, int $status ): void {
        if ( function_exists( 'wp_safe_redirect' ) ) {
            $host = wp_parse_url( $location, PHP_URL_HOST );
            if ( is_string( $host ) && $host !== '' ) {
                add_filter(
                    'allowed_redirect_hosts',
                    static function ( array $hosts ) use ( $host ): array {
                        $hosts[] = $host;

                        return $hosts;
                    }
                );
            }
            wp_safe_redirect( $location, $status );
            exit;
        }
        if ( ! headers_sent() ) {
            header( 'Location: ' . $location, true, $status );
        }
        exit;
    }

    /**
     * @param list<\QueryNova\Modules\Redirects\Domain\RedirectRule> $rules
     */
    private function recordMiss( string $path, array $rules ): void {
        if ( ! function_exists( 'is_404' ) || ! is_404() || $path === '' ) {
            return;
        }
        $referrer = function_exists( 'wp_get_referer' ) ? (string) wp_get_referer() : '';
        $agent    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $this->missing->record( $path, $referrer, $agent, $this->engine->suggest( $path, $rules ) );
    }

    private function requestPath(): string {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
        $uri = strtok( $uri, '#' );
        if ( ! is_string( $uri ) ) {
            return '';
        }
        $parts = wp_parse_url( $uri );
        if ( ! is_array( $parts ) ) {
            return '';
        }
        $path  = (string) ( $parts['path'] ?? '' );
        $query = isset( $parts['query'] ) ? '?' . (string) $parts['query'] : '';
        if ( $path === '' || ! str_starts_with( $path, '/' ) || str_starts_with( $path, '//' ) ) {
            return '';
        }

        return $path . $query;
    }
}
