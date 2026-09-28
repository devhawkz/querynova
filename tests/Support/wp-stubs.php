<?php
/**
 * Minimal WordPress stand-ins for unit tests. Not loaded in production.
 *
 * @package QueryNova
 */

declare(strict_types=1);

if ( ! isset( $GLOBALS['querynova_options'] ) ) {
    $GLOBALS['querynova_options'] = [];
}
if ( ! isset( $GLOBALS['qn_transients'] ) ) {
    $GLOBALS['qn_transients'] = [];
}
if ( ! isset( $GLOBALS['qn_cache'] ) ) {
    $GLOBALS['qn_cache'] = [];
}
if ( ! isset( $GLOBALS['qn_actions'] ) ) {
    $GLOBALS['qn_actions'] = [];
}
if ( ! isset( $GLOBALS['qn_roles'] ) ) {
    $GLOBALS['qn_roles'] = [];
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {

        public function __construct( public string $code = '', public string $message = '' ) {
        }
    }
}

if ( ! class_exists( 'WP_Role' ) ) {
    class WP_Role {

        /** @var array<string, bool> */
        public array $capabilities = [];

        public function add_cap( string $cap, bool $grant = true ): void {
            $this->capabilities[ $cap ] = $grant;
        }
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $option, mixed $default = false ): mixed {
        return $GLOBALS['querynova_options'][ $option ] ?? $default;
    }
}

if ( ! function_exists( 'add_option' ) ) {
    function add_option( string $option, mixed $value, string $deprecated = '', bool|string|null $autoload = true ): bool {
        if ( array_key_exists( $option, $GLOBALS['querynova_options'] ) ) {
            return false;
        }
        $GLOBALS['querynova_options'][ $option ] = $value;

        return true;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( string $option, mixed $value, mixed $autoload = null ): bool {
        $GLOBALS['querynova_options'][ $option ] = $value;

        return true;
    }
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( string $option ): bool {
        unset( $GLOBALS['querynova_options'][ $option ] );

        return true;
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( string $key ): mixed {
        $item = $GLOBALS['qn_transients'][ $key ] ?? null;
        if ( ! is_array( $item ) || $item['expires'] < time() ) {
            return false;
        }

        return $item['value'];
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( string $key, mixed $value, int $expiration ): bool {
        $GLOBALS['qn_transients'][ $key ] = [
			'value'   => $value,
			'expires' => time() + $expiration,
		];

        return true;
    }
}

if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( string $key ): bool {
        unset( $GLOBALS['qn_transients'][ $key ] );

        return true;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
        return json_encode( $value, $flags, $depth );
    }
}

if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( string $scheme = 'auth' ): string {
        return 'querynova-test-salt-' . $scheme;
    }
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
    function wp_get_environment_type(): string {
        return $GLOBALS['querynova_environment'] ?? 'production';
    }
}

if ( ! function_exists( 'wp_timezone' ) ) {
    function wp_timezone(): DateTimeZone {
        $name = $GLOBALS['qn_timezone'] ?? 'UTC'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores the timezone under this key.

        return new DateTimeZone( is_string( $name ) && $name !== '' ? $name : 'UTC' );
    }
}

if ( ! function_exists( 'is_network_admin' ) ) {
    function is_network_admin(): bool {
        return (bool) ( $GLOBALS['qn_network_admin'] ?? false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores network admin state under this key.
    }
}

if ( ! function_exists( 'add_menu_page' ) ) {
    /**
     * @param mixed ...$args
     */
    function add_menu_page( mixed ...$args ): string {
        $GLOBALS['qn_menus'][] = $args; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap records registered menus under this key.

        return 'querynova';
    }
}

if ( ! function_exists( 'add_submenu_page' ) ) {
    /**
     * @param mixed ...$args
     */
    function add_submenu_page( mixed ...$args ): string {
        $GLOBALS['qn_menus'][] = $args; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap records registered menus under this key.

        return 'querynova';
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
        $GLOBALS['qn_actions'][ $hook ][] = $callback;
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
        $GLOBALS['qn_actions'][ $hook ][] = $callback;
    }
}

if ( ! function_exists( 'do_action' ) ) {
    function do_action( string $hook, mixed ...$args ): void {
        foreach ( $GLOBALS['qn_actions'][ $hook ] ?? [] as $callback ) {
            $callback( ...$args );
        }
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
        foreach ( $GLOBALS['qn_actions'][ $hook ] ?? [] as $callback ) {
            $value = $callback( $value, ...$args );
        }

        return $value;
    }
}

if ( ! function_exists( 'wp_cache_get' ) ) {
    function wp_cache_get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
        $id = $group . ':' . $key;
        if ( ! array_key_exists( $id, $GLOBALS['qn_cache'] ) ) {
            $found = false;

            return false;
        }
        $found = true;

        return $GLOBALS['qn_cache'][ $id ];
    }
}

if ( ! function_exists( 'wp_cache_set' ) ) {
    function wp_cache_set( string $key, mixed $value, string $group = '', int $expire = 0 ): bool {
        $GLOBALS['qn_cache'][ $group . ':' . $key ] = $value;

        return true;
    }
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
    function wp_cache_delete( string $key, string $group = '' ): bool {
        unset( $GLOBALS['qn_cache'][ $group . ':' . $key ] );

        return true;
    }
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
    function wp_cache_flush(): bool {
        $GLOBALS['qn_cache'] = [];

        return true;
    }
}

if ( ! function_exists( 'get_role' ) ) {
    function get_role( string $role ): ?WP_Role {
        return $GLOBALS['qn_roles'][ $role ] ?? null;
    }
}

if ( ! function_exists( 'add_role' ) ) {
    function add_role( string $role, string $display, array $capabilities = [] ): ?WP_Role {
        $object                       = new WP_Role();
        $object->capabilities         = $capabilities;
        $GLOBALS['qn_roles'][ $role ] = $object;

        return $object;
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( string $capability, mixed ...$extra ): bool {
        unset( $extra );

        return in_array( $capability, $GLOBALS['qn_caps'] ?? [], true );
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( mixed $thing ): bool {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( string $text ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( '__' ) ) {
    function __( string $text, string $domain = 'default' ): string {
        return $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( string $text ): string {
        return trim( strip_tags( $text ) );
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( string $url, int $component = -1 ): mixed {
        $parts = parse_url( $url );
        if ( $component === -1 ) {
            return $parts;
        }
        if ( ! is_array( $parts ) ) {
            return null;
        }
        $map = [
            PHP_URL_SCHEME => 'scheme',
            PHP_URL_HOST   => 'host',
            PHP_URL_PORT   => 'port',
            PHP_URL_PATH   => 'path',
            PHP_URL_QUERY  => 'query',
        ];
        $key = $map[ $component ] ?? null;

        return $key === null ? null : ( $parts[ $key ] ?? null );
    }
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
    function wp_upload_dir(): array {
        return [
			'basedir' => sys_get_temp_dir(),
			'baseurl' => 'http://example.test/uploads',
		];
    }
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( string $hook ): int|false {
        return $GLOBALS['qn_cron'][ $hook ] ?? false;
    }
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = [] ): bool {
        $GLOBALS['qn_cron'][ $hook ] = $timestamp;

        return true;
    }
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
    function wp_clear_scheduled_hook( string $hook ): int {
        unset( $GLOBALS['qn_cron'][ $hook ] );

        return 1;
    }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( string $message = '' ): void {
        throw new RuntimeException( $message );
    }
}
