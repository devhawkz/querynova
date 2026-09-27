<?php
/**
 * Reads another plugin's post or term meta keys. It does not load that plugin.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Infrastructure;

use QueryNova\Modules\Seo\Application\SeoImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WordPressForeignMetaReader {

	/**
	 * @return array<string, mixed>
	 */
	public function read( string $objectType, int $objectId, string $plugin ): array {
		$keys = SeoImporter::metaKeys( $plugin );
		$meta = [];
		foreach ( $keys as $key ) {
			$value = $this->raw( $objectType, $objectId, $key );
			if ( $value === null || $value === '' || $value === false ) {
				continue;
			}
			$meta[ $key ] = $value;
		}

		return $meta;
	}

	private function raw( string $objectType, int $objectId, string $key ): mixed {
		if ( $objectType === 'term' && function_exists( 'get_term_meta' ) ) {
			return get_term_meta( $objectId, $key, true );
		}
		if ( $objectType === 'post' && function_exists( 'get_post_meta' ) ) {
			return get_post_meta( $objectId, $key, true );
		}

		return null;
	}
}
