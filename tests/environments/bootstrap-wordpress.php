<?php
/**
 * Loads the WordPress test library for the release jobs.
 *
 * The default `composer test` suite does not include this file.
 *
 * @package QueryNova
 */

declare(strict_types=1);

$testsDir = getenv( 'WP_TESTS_DIR' );
if ( ! is_string( $testsDir ) || $testsDir === '' || ! is_file( $testsDir . '/includes/bootstrap.php' ) ) {
	fwrite( STDERR, "WP_TESTS_DIR must point at an installed WordPress test library.\n" );
	exit( 1 );
}

$polyfills = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( ! is_string( $polyfills ) || $polyfills === '' ) {
	fwrite( STDERR, "WP_TESTS_PHPUNIT_POLYFILLS_PATH is required by the WordPress test library.\n" );
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills );

require $testsDir . '/includes/bootstrap.php';
