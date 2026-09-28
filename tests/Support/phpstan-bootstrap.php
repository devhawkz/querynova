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
    define( 'QUERYNOVA_VERSION', '0.1.2' );
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

if ( ! class_exists( 'WP_CLI' ) ) {
    /**
     * Minimal WP-CLI stand-in for static analysis. Runtime registration still requires WP-CLI.
     */
    class WP_CLI {
        /**
         * @param array<int, mixed>    $args
         * @param array<string, mixed> $assoc
         */
        public static function add_command( string $name, callable $callable ): void {
            unset( $name, $callable );
        }

        public static function error( string $message ): void {
            unset( $message );
        }

        public static function log( string $message ): void {
            unset( $message );
        }
    }
}
