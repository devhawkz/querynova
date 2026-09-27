<?php
/**
 * Admin page registration. Rendering is delegated to a callback.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminPageRegistrar {

    /** @var list<array{slug: string, title: string, capability: string, callback: callable}> */
    private array $pages = [];

    /**
     * @param callable(): void $callback
     */
    public function add( string $slug, string $title, string $capability, callable $callback ): void {
        $this->pages[] = [
            'slug'       => $slug,
            'title'      => $title,
            'capability' => $capability,
            'callback'   => $callback,
        ];
    }

    public function register(): void {
        add_action(
            'admin_menu',
            function (): void {
				$first = true;
				foreach ( $this->pages as $page ) {
					if ( $first ) {
						add_menu_page(
                            'QueryNova',
                            'QueryNova',
                            $page['capability'],
                            'querynova',
                            $page['callback'],
                            'dashicons-chart-line',
                            58
						);
						$first = false;
					}
					add_submenu_page( 'querynova', $page['title'], $page['title'], $page['capability'], $page['slug'], $page['callback'] );
				}
			}
        );
    }
}
