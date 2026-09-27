<?php
/**
 * Copies stored SEO fields from another plugin's post meta.
 *
 * This does not load that plugin, evaluate its templates, or disable it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Redirects\Application\RedirectEngine;
use QueryNova\Modules\Redirects\Domain\RedirectRule;
use QueryNova\Modules\Redirects\Infrastructure\RedirectRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SeoImporter {

	public const MAX_OBJECTS   = 50;
	public const MAX_REDIRECTS = 200;

	/**
	 * @var array<string, array<string, string>>
	 */
	private const KEYS = [
		'yoast'    => [
			'title'       => '_yoast_wpseo_title',
			'description' => '_yoast_wpseo_metadesc',
			'canonical'   => '_yoast_wpseo_canonical',
			'noindex'     => '_yoast_wpseo_meta-robots-noindex',
			'nofollow'    => '_yoast_wpseo_meta-robots-nofollow',
			'focus'       => '_yoast_wpseo_focuskw',
		],
		'rankmath' => [
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
			'canonical'   => 'rank_math_canonical_url',
			'robots'      => 'rank_math_robots',
			'focus'       => 'rank_math_focus_keyword',
		],
		'aioseo'   => [
			'title'       => '_aioseo_title',
			'description' => '_aioseo_description',
			'canonical'   => '_aioseo_canonical_url',
			'noindex'     => '_aioseo_robots_noindex',
			'nofollow'    => '_aioseo_robots_nofollow',
			'focus'       => '_aioseo_keywords',
		],
	];

	public function __construct(
		private readonly SeoMetaService $seo,
		private readonly RedirectEngine $redirects,
		private readonly RedirectRepository $repository,
	) {
	}

	/**
	 * @return list<string>
	 */
	public static function metaKeys( string $plugin ): array {
		return array_values( self::KEYS[ $plugin ] ?? [] );
	}

	/**
	 * @param list<array{object_type: string, object_id: int, meta: array<string, mixed>}> $objects
	 * @return array<string, mixed>
	 */
	public function importMeta( string $plugin, array $objects, bool $replace ): array {
		if ( ! isset( self::KEYS[ $plugin ] ) ) {
			throw new ValidationException( 'SEO import supports Yoast, Rank Math, and AIOSEO post meta.' );
		}
		if ( count( $objects ) > self::MAX_OBJECTS ) {
			throw new ValidationException( 'SEO import accepts at most 50 objects in one request.' );
		}
		$written  = 0;
		$skipped  = 0;
		$rejected = 0;
		foreach ( $objects as $object ) {
			$type = $object['object_type'];
			if ( ! in_array( $type, [ 'post', 'term' ], true ) || $object['object_id'] < 1 ) {
				++$rejected;
				continue;
			}
			$fields = $this->fields( $plugin, $object['meta'] );
			foreach ( $fields as $key => $value ) {
				if ( ! $replace && $this->seo->stored( $type, $object['object_id'], $key ) !== '' ) {
					++$skipped;
					continue;
				}
				try {
					$this->seo->save( $type, $object['object_id'], [ $key => $value ] );
					++$written;
				} catch ( ValidationException ) {
					++$rejected;
				}
			}
		}

		return [
			'plugin'                => $plugin,
			'written'               => $written,
			'skipped'               => $skipped,
			'rejected'              => $rejected,
			'disabled_other_plugin' => false,
		];
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return array<string, mixed>
	 */
	public function importRedirects( array $rows ): array {
		if ( count( $rows ) > self::MAX_REDIRECTS ) {
			throw new ValidationException( 'SEO import accepts at most 200 redirects in one request.' );
		}
		$existing = $this->repository->all();
		$imported = 0;
		$rejected = 0;
		foreach ( $rows as $row ) {
			if ( ( $row['regex'] ?? false ) === true ) {
				++$rejected;
				continue;
			}
			$status = $row['status'] ?? null;
			if ( ! is_int( $status ) && ! ( is_string( $status ) && ctype_digit( $status ) ) ) {
				++$rejected;
				continue;
			}
			try {
				$rule = new RedirectRule(
					0,
					$this->redirects->normalizeSource( is_string( $row['source'] ?? null ) ? $row['source'] : '', false ),
					$this->redirects->normalizeTarget( is_string( $row['target'] ?? null ) ? $row['target'] : '', (int) $status ),
					(int) $status,
					false
				);
				$this->redirects->assertSafe( $existing, $rule );
				$id         = $this->repository->save( $rule );
				$existing[] = new RedirectRule( $id, $rule->source(), $rule->target(), $rule->status(), false );
				++$imported;
			} catch ( ValidationException ) {
				++$rejected;
			}
		}

		return [
			'imported'              => $imported,
			'rejected'              => $rejected,
			'disabled_other_plugin' => false,
		];
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, string>
	 */
	private function fields( string $plugin, array $meta ): array {
		$keys   = self::KEYS[ $plugin ];
		$fields = [];
		$title  = $this->plainText( $meta[ $keys['title'] ] ?? null );
		if ( $title !== null ) {
			$fields[ SeoMetaService::TITLE ] = $title;
		}
		$description = $this->plainText( $meta[ $keys['description'] ] ?? null );
		if ( $description !== null ) {
			$fields[ SeoMetaService::DESCRIPTION ] = $description;
		}
		$canonical = $this->plainText( $meta[ $keys['canonical'] ] ?? null );
		if ( $canonical !== null ) {
			$fields[ SeoMetaService::CANONICAL ] = $canonical;
		}
		$focus = $this->focus( $meta[ $keys['focus'] ] ?? null );
		if ( $focus !== null ) {
			$fields[ SeoMetaService::FOCUS_KEYWORD ] = $focus;
		}
		$robots = $this->robots( $plugin, $meta );
		foreach ( $robots as $key => $value ) {
			$fields[ $key ] = $value;
		}

		return $fields;
	}

	/**
	 * @param array<string, mixed> $meta
	 * @return array<string, string>
	 */
	private function robots( string $plugin, array $meta ): array {
		$keys = self::KEYS[ $plugin ];
		if ( $plugin === 'rankmath' ) {
			return $this->rankMathRobots( $meta[ $keys['robots'] ] ?? null );
		}
		$fields = [];
		$index  = $this->flag( $meta[ $keys['noindex'] ] ?? null, $plugin === 'yoast' );
		$follow = $this->followFlag( $meta[ $keys['nofollow'] ] ?? null );
		if ( $index !== null ) {
			$fields[ SeoMetaService::ROBOTS_INDEX ] = $index;
		}
		if ( $follow !== null ) {
			$fields[ SeoMetaService::ROBOTS_FOLLOW ] = $follow;
		}

		return $fields;
	}

	/**
	 * @return array<string, string>
	 */
	private function rankMathRobots( mixed $value ): array {
		if ( is_string( $value ) ) {
			if ( str_starts_with( $value, 'a:' ) || str_starts_with( $value, 'O:' ) ) {
				return [];
			}
			$value = array_map( 'trim', explode( ',', $value ) );
		}
		if ( ! is_array( $value ) ) {
			return [];
		}
		$items = [];
		foreach ( $value as $item ) {
			if ( is_string( $item ) && $item !== '' ) {
				$items[] = strtolower( $item );
			}
		}
		$fields = [];
		$index  = $this->oneOf( $items, 'noindex', 'index' );
		$follow = $this->oneOf( $items, 'nofollow', 'follow' );
		if ( $index !== null ) {
			$fields[ SeoMetaService::ROBOTS_INDEX ] = $index;
		}
		if ( $follow !== null ) {
			$fields[ SeoMetaService::ROBOTS_FOLLOW ] = $follow;
		}

		return $fields;
	}

	/**
	 * @param list<string> $items
	 */
	private function oneOf( array $items, string $left, string $right ): ?string {
		$hasLeft  = in_array( $left, $items, true );
		$hasRight = in_array( $right, $items, true );
		if ( $hasLeft === $hasRight ) {
			return null;
		}

		return $hasLeft ? $left : $right;
	}

	private function flag( mixed $value, bool $yoast ): ?string {
		if ( $yoast ) {
			if ( $value === 1 || $value === '1' ) {
				return 'noindex';
			}
			if ( $value === 2 || $value === '2' ) {
				return 'index';
			}

			return null;
		}
		if ( $value === true || $value === 1 || $value === '1' || $value === 'on' ) {
			return 'noindex';
		}
		if ( $value === 'off' ) {
			return 'index';
		}

		return null;
	}

	private function followFlag( mixed $value ): ?string {
		if ( $value === true || $value === 1 || $value === '1' || $value === 'on' ) {
			return 'nofollow';
		}

		return null;
	}

	private function plainText( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$clean = trim( wp_strip_all_tags( $value ) );
		if ( $clean === '' || $this->isForeignTemplate( $clean ) ) {
			return null;
		}

		return $clean;
	}

	private function focus( mixed $value ): ?string {
		$text = $this->plainText( $value );
		if ( $text === null ) {
			return null;
		}
		$parts = preg_split( '/\s*,\s*/', $text );
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$kept = [];
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( $part === '' || $this->isForeignTemplate( $part ) ) {
				continue;
			}
			$kept[] = function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 80 ) : substr( $part, 0, 80 );
			if ( count( $kept ) === 5 ) {
				break;
			}
		}
		if ( $kept === [] ) {
			return null;
		}

		return implode( ', ', $kept );
	}

	private function isForeignTemplate( string $value ): bool {
		return str_contains( $value, '%%' )
			|| str_contains( $value, '%title%' )
			|| str_contains( $value, '%sep%' )
			|| str_contains( $value, '%sitename%' )
			|| str_contains( $value, '#post_' )
			|| str_contains( $value, '#site_' );
	}
}
