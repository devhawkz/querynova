<?php
/**
 * Resolves SEO fields from explicit values, then templates.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Modules\Seo\Domain\MetaStoreInterface;
use QueryNova\Modules\Seo\Domain\RobotsDirective;
use QueryNova\Modules\Seo\Domain\SeoDocument;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SeoMetaService {

    public const TITLE          = 'title';
    public const DESCRIPTION    = 'description';
    public const CANONICAL      = 'canonical';
    public const ROBOTS_INDEX   = 'robots_index';
    public const ROBOTS_FOLLOW  = 'robots_follow';
    public const OG_TITLE       = 'og_title';
    public const OG_DESCRIPTION = 'og_description';
    public const OG_IMAGE       = 'og_image';
    public const TWITTER_CARD   = 'twitter_card';
    public const FOCUS_KEYWORD  = 'focus_keyword';

    public function __construct(
        private readonly MetaStoreInterface $meta,
        private readonly TemplateRenderer $templates,
    ) {
    }

    /**
     * @param array<string, string> $tokens
     * @param array<string, string> $templateSet
     */
    public function resolve( string $objectType, int $objectId, array $tokens, array $templateSet ): SeoDocument {
        $title = $this->meta->get( $objectType, $objectId, self::TITLE );
        if ( $title === '' ) {
            $title = $this->templates->render( $templateSet[ $objectType ] ?? '%%title%% %%sep%% %%sitename%%', $tokens );
        }

        $description = $this->meta->get( $objectType, $objectId, self::DESCRIPTION );
        if ( $description === '' ) {
            $description = $this->templates->render( (string) ( $templateSet[ $objectType . '_description' ] ?? '%%excerpt%%' ), $tokens );
        }

        $index  = $this->meta->get( $objectType, $objectId, self::ROBOTS_INDEX );
        $follow = $this->meta->get( $objectType, $objectId, self::ROBOTS_FOLLOW );
        $robots = RobotsDirective::fromStrings(
            $index !== '' ? $index : 'index',
            $follow !== '' ? $follow : 'follow',
        );

        $ogTitle       = $this->meta->get( $objectType, $objectId, self::OG_TITLE );
        $ogDescription = $this->meta->get( $objectType, $objectId, self::OG_DESCRIPTION );

        return new SeoDocument(
            $title,
            $description,
            $this->meta->get( $objectType, $objectId, self::CANONICAL ),
            $robots,
            $ogTitle !== '' ? $ogTitle : $title,
            $ogDescription !== '' ? $ogDescription : $description,
            $this->meta->get( $objectType, $objectId, self::OG_IMAGE ),
            $this->twitterCard( $objectType, $objectId ),
        );
    }

    public function stored( string $objectType, int $objectId, string $key ): string {
        return $this->meta->get( $objectType, $objectId, $key );
    }

    /**
     * @param array<string, string> $fields
     */
    public function save( string $objectType, int $objectId, array $fields ): void {
        $clean = [];
        foreach ( $fields as $key => $value ) {
            $clean[ $key ] = $this->clean( $key, $value );
        }
        foreach ( $clean as $key => $value ) {
            $this->meta->set( $objectType, $objectId, $key, $value );
        }
    }

    /**
     * @return list<string>
     */
    public static function keys(): array {
        return [
            self::TITLE,
            self::DESCRIPTION,
            self::CANONICAL,
            self::ROBOTS_INDEX,
            self::ROBOTS_FOLLOW,
            self::OG_TITLE,
            self::OG_DESCRIPTION,
            self::OG_IMAGE,
            self::TWITTER_CARD,
            self::FOCUS_KEYWORD,
        ];
    }

    private function clean( string $key, string $value ): string {
        if ( ! in_array( $key, self::keys(), true ) ) {
            throw new ValidationException( 'Unknown SEO field.' );
        }
        $clean = trim( wp_strip_all_tags( $value ) );
        if ( in_array( $key, [ self::CANONICAL, self::OG_IMAGE ], true ) && $clean !== '' && ! $this->isHttpUrl( $clean ) ) {
            throw new ValidationException( 'Canonical and image values must be http or https URLs.' );
        }
        if ( $key === self::ROBOTS_INDEX && ! in_array( $clean, [ '', 'index', 'noindex' ], true ) ) {
            throw new ValidationException( 'Index directive must be index or noindex.' );
        }
        if ( $key === self::ROBOTS_FOLLOW && ! in_array( $clean, [ '', 'follow', 'nofollow' ], true ) ) {
            throw new ValidationException( 'Follow directive must be follow or nofollow.' );
        }

        return $clean;
    }

    private function twitterCard( string $objectType, int $objectId ): string {
        $card = $this->meta->get( $objectType, $objectId, self::TWITTER_CARD );

        return $card !== '' ? $card : 'summary_large_image';
    }

    private function isHttpUrl( string $value ): bool {
        $parts = wp_parse_url( $value );

        return is_array( $parts ) && in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true );
    }
}
