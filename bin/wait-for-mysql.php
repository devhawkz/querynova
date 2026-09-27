<?php
/**
 * Waits until the CI MySQL service accepts connections, then creates the database.
 *
 * @package QueryNova
 */

declare(strict_types=1);

$hostPort = getenv( 'QUERYNOVA_DB_HOST' );
$user     = getenv( 'QUERYNOVA_DB_USER' );
$password = getenv( 'QUERYNOVA_DB_PASSWORD' );
$name     = getenv( 'QUERYNOVA_DB_NAME' );

if ( ! is_string( $hostPort ) || $hostPort === '' || ! is_string( $user ) || ! is_string( $password ) || ! is_string( $name ) || $name === '' ) {
	fwrite( STDERR, "QUERYNOVA_DB_HOST, QUERYNOVA_DB_USER, QUERYNOVA_DB_PASSWORD, and QUERYNOVA_DB_NAME are required.\n" );
	exit( 1 );
}

$host = $hostPort;
$port = 3306;
if ( str_contains( $hostPort, ':' ) ) {
	$parts = explode( ':', $hostPort, 2 );
	$host  = $parts[0];
	$port  = (int) $parts[1];
}

mysqli_report( MYSQLI_REPORT_OFF );
$connection = null;
for ( $attempt = 0; $attempt < 30; $attempt++ ) {
	$connection = mysqli_init();
	if ( $connection instanceof mysqli && @$connection->real_connect( $host, $user, $password, '', $port ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- CI wait loop; a refused connection is expected until MySQL is ready.
		break;
	}
	$connection = null;
	sleep( 2 );
}

if ( ! $connection instanceof mysqli ) {
	fwrite( STDERR, "MySQL did not accept connections on {$hostPort}.\n" );
	exit( 1 );
}

$database = '`' . str_replace( '`', '``', $name ) . '`';
if ( ! $connection->query( 'CREATE DATABASE IF NOT EXISTS ' . $database . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' ) ) {
	fwrite( STDERR, "MySQL refused to create {$name}: {$connection->error}\n" );
	exit( 1 );
}

fwrite( STDOUT, "MySQL database {$name} is ready on {$hostPort}.\n" );
