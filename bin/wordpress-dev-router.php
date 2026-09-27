<?php
/**
 * Router for PHP's built-in server during the admin browser job.
 *
 * Existing files are served by the built-in server. Other requests enter WordPress.
 *
 * @package QueryNova
 */

declare(strict_types=1);

$root = getenv( 'WP_CORE_DIR' );
if ( ! is_string( $root ) || $root === '' ) {
	http_response_code( 500 );
	echo 'WP_CORE_DIR is required.';
	return;
}

$root = rtrim( $root, '/' );
$path = parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
$path = is_string( $path ) ? $path : '/';
$file = $root . $path;
if ( $path !== '/' && is_file( $file ) ) {
	return false;
}

require $root . '/index.php';
