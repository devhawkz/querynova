<?php
/**
 * The release autoload must not require a dev file the ZIP omitted.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZipArchive;

require_once QUERYNOVA_PATH . 'scripts/assert-release-autoload.php';

final class ReleaseArchiveAutoloadTest extends TestCase {

	public function testAMissingComposerFileIsReported(): void {
		$zipPath = $this->zipWithFiles(
			[
				'querynova/vendor/composer/autoload_static.php' => $this->filesList(
					[ '/myclabs/deep-copy/src/DeepCopy/deep_copy.php' ]
				),
			]
		);

		self::assertSame(
			[ 'querynova/vendor/myclabs/deep-copy/src/DeepCopy/deep_copy.php' ],
			querynova_release_missing_autoload_files( $zipPath )
		);
	}

	public function testAPresentComposerFileIsAccepted(): void {
		$static  = $this->filesList( [ '/myclabs/deep-copy/src/DeepCopy/deep_copy.php' ] );
		$present = "<?php\n";
		$zipPath = $this->zipWithFiles(
			[
				'querynova/vendor/composer/autoload_static.php' => $static,
				'querynova/vendor/myclabs/deep-copy/src/DeepCopy/deep_copy.php' => $present,
			]
		);

		self::assertSame( [], querynova_release_missing_autoload_files( $zipPath ) );
	}

	public function testAnAutoloadWithoutFileEntriesIsAccepted(): void {
		$zipPath = $this->zipWithFiles(
			[
				'querynova/vendor/composer/autoload_static.php' => "<?php\nclass ComposerStaticInitFixture {\n    public static \$prefixDirsPsr4 = array ();\n}\n",
			]
		);

		self::assertSame( [], querynova_release_missing_autoload_files( $zipPath ) );
	}

	public function testThePackageScriptRefusesADevVendorCopy(): void {
		$script = (string) file_get_contents( QUERYNOVA_PATH . 'scripts/package-plugin.mjs' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local package script, not a remote request.

		self::assertStringNotContainsString( 'copyProductionVendor', $script );
		self::assertStringContainsString( '--no-dev', $script );
		self::assertStringContainsString( 'assert-release-autoload.php', $script );
	}

	/**
	 * @param array<string, string> $entries
	 */
	private function zipWithFiles( array $entries ): string {
		$path = tempnam( sys_get_temp_dir(), 'qnzip' );
		self::assertIsString( $path );
		$zip = new ZipArchive();
		$zip->open( $path, ZipArchive::OVERWRITE );
		foreach ( $entries as $name => $contents ) {
			$zip->addFromString( $name, $contents );
		}
		$zip->close();

		return $path;
	}

	/**
	 * @param list<string> $relative
	 */
	private function filesList( array $relative ): string {
		$lines = [];
		foreach ( $relative as $path ) {
			$lines[] = "        '0123456789abcdef0123456789abcdef' => __DIR__ . '/..' . '" . $path . "',";
		}

		return "<?php\nclass ComposerStaticInitFixture {\n    public static \$files = array (\n" . implode( "\n", $lines ) . "\n    );\n}\n";
	}
}
