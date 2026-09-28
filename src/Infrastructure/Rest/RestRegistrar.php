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

    /** @var list<array{method: string, route: string, callback: callable, capability: string, capabilities: list<string>}> */
    private array $routes = [];

    /**
     * @param callable(\WP_REST_Request): mixed $callback
     * @param list<string>                      $also Capabilities that also grant this route. The screen capability is the usual extra.
     */
    public function route( string $method, string $route, callable $callback, string $capability, array $also = [] ): void {
        $capabilities = [];
        foreach ( array_merge( [ $capability ], $also ) as $name ) {
            if ( is_string( $name ) && $name !== '' && ! in_array( $name, $capabilities, true ) ) {
                $capabilities[] = $name;
            }
        }
        $this->routes[] = [
            'method'       => $method,
            'route'        => $route,
            'callback'     => $callback,
            'capability'   => $capability,
            'capabilities' => $capabilities,
        ];
    }

    /**
     * @return list<array{method: string, route: string, callback: callable, capability: string, capabilities: list<string>}>
     */
    public function routes(): array {
        return $this->routes;
    }

    public function allowed( string $capability ): bool {
        return $this->allowedAny( [ $capability ] );
    }

    /**
     * @param list<string> $capabilities
     */
    public function allowedAny( array $capabilities ): bool {
        foreach ( $capabilities as $capability ) {
            if ( $capability !== '' && current_user_can( $capability ) ) {
                return true;
            }
        }

        return false;
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
							'permission_callback' => function () use ( $route ): bool {
								return $this->allowedAny( $route['capabilities'] );
							},
                        ]
					);
				}
			}
        );
    }
}
