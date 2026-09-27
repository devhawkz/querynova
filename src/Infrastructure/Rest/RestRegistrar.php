<?php
/**
 * REST route collector. Controllers stay thin and permission checks are capabilities.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Rest;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RestRegistrar {

    /** @var list<array{method: string, route: string, callback: callable, capability: string}> */
    private array $routes = [];

    /**
     * @param callable(\WP_REST_Request): mixed $callback
     */
    public function route( string $method, string $route, callable $callback, string $capability ): void {
        $this->routes[] = [
            'method'     => $method,
            'route'      => $route,
            'callback'   => $callback,
            'capability' => $capability,
        ];
    }

    /**
     * @return list<array{method: string, route: string, callback: callable, capability: string}>
     */
    public function routes(): array {
        return $this->routes;
    }

    public function register(): void {
        add_action(
            'rest_api_init',
            function (): void {
				foreach ( $this->routes as $route ) {
					register_rest_route(
                        'querynova/v1',
                        $route['route'],
                        [
							'methods'             => $route['method'],
							'callback'            => $route['callback'],
							'permission_callback' => static function () use ( $route ): bool {
								return current_user_can( $route['capability'] );
							},
                        ]
					);
				}
			}
        );
    }
}
