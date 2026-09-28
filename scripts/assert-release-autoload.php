<?php
/**
 * A release ZIP must be able to load Composer without requiring a file that was left out.
 *
 * @package QueryNova
 */

declare(strict_types=1);

if ( ! function_exists( 'querynova_release_missing_autoload_files' ) ) {
	/**
	 * Paths inside the ZIP that Composer's file autoload requires and that are absent.
	 *
	 * @return list<string>
	 */
	function querynova_release_missing_autoload_files( string $zipPath ): array {
		$zip = new ZipArchive();
		if ( $zip->open( $zipPath ) !== true ) {
			return [ $zipPath . ' could not be opened' ];
		}

		$static = $zip->getFromName( 'querynova/vendor/composer/autoload_static.php' );
		if ( ! is_string( $static ) ) {
			$zip->close();

			return [ 'querynova/vendor/composer/autoload_static.php' ];
		}

		if ( ! preg_match( '/public static \$files = array \((.*?)\);/s', $static, $block ) ) {
			$zip->close();

			return [];
		}

		preg_match_all( "/__DIR__\\s*\\.\\s*'\\/\\.\\.'\\s*\\.\\s*'([^']+)'/", $block[1], $paths );
		$missing = [];
		foreach ( $paths[1] as $relative ) {
			$entry = 'querynova/vendor' . $relative;
			if ( $zip->locateName( $entry ) === false ) {
				$missing[] = $entry;
			}
		}
		$zip->close();

		return $missing;
	}
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( (string) $argv[0] ) === realpath( __FILE__ ) ) {
	querynova_release_autoload_cli( $argv );
}

/**
 * @param list<string> $args
 */
function querynova_release_autoload_cli( array $args ): void {
	$querynovaZip = $args[1] ?? '';
	if ( $querynovaZip === '' || ! is_file( $querynovaZip ) ) {
		fwrite( STDERR, "Usage: php scripts/assert-release-autoload.php <zip>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI script, not a WordPress request.
		exit( 1 );
	}
	$querynovaMissing = querynova_release_missing_autoload_files( $querynovaZip );
	if ( $querynovaMissing !== [] ) {
		fwrite( STDERR, "Release ZIP autoload requires missing files:\n" . implode( "\n", $querynovaMissing ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI script, not a WordPress request.
		exit( 1 );
	}
}
