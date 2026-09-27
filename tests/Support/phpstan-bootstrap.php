<?php
/**
 * PHPStan bootstrap. Defines plugin constants before analysis.
 *
 * @package QueryNova
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/wordpress/' );
}

if ( ! defined( 'QUERYNOVA_VERSION' ) ) {
    define( 'QUERYNOVA_VERSION', '0.1.0' );
}

if ( ! defined( 'QUERYNOVA_FILE' ) ) {
    define( 'QUERYNOVA_FILE', dirname( __DIR__, 2 ) . '/querynova.php' );
}

if ( ! defined( 'QUERYNOVA_PATH' ) ) {
    define( 'QUERYNOVA_PATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'QUERYNOVA_URL' ) ) {
    define( 'QUERYNOVA_URL', 'http://example.test/wp-content/plugins/querynova/' );
}

if ( ! defined( 'QUERYNOVA_DB_VERSION' ) ) {
    define( 'QUERYNOVA_DB_VERSION', '1.0.0' );
}

$querynovaStubs = dirname( __DIR__, 2 ) . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
if ( is_file( $querynovaStubs ) ) {
    require_once $querynovaStubs;
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
    /**
     * Action Scheduler function provided by woocommerce/action-scheduler at runtime.
     *
     * @param array<string, mixed> $args
     */
    function as_enqueue_async_action( string $hook, array $args = [], string $group = '' ): int {
        unset( $hook, $args, $group );

        return 0;
    }
}
