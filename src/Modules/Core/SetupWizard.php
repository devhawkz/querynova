<?php
/**
 * Setup answers. Nothing here connects a provider or starts a crawl.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Infrastructure\WordPress\OptionStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SetupWizard {

    public const OPTION = 'querynova_setup';

    /**
     * @var list<string>
     */
    private const SITE_TYPES = [ 'store', 'publisher', 'business', 'other' ];

    public function __construct( private readonly OptionStore $options ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function read( bool $wooCommerceActive ): array {
        $stored = $this->options->get( self::OPTION, [] );
        $stored = is_array( $stored ) ? $stored : [];

        return $this->present( $stored, $wooCommerceActive );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save( array $input, bool $wooCommerceActive ): array {
        $current = $this->options->get( self::OPTION, [] );
        $current = is_array( $current ) ? $current : [];
        $next    = $current;
        if ( array_key_exists( 'site_type', $input ) ) {
            $site              = is_string( $input['site_type'] ) ? trim( $input['site_type'] ) : '';
            $next['site_type'] = in_array( $site, self::SITE_TYPES, true ) ? $site : null;
        }
        if ( array_key_exists( 'business_type', $input ) ) {
            $next['business_type'] = $this->shortText( $input['business_type'] );
        }
        if ( array_key_exists( 'organization', $input ) && is_array( $input['organization'] ) ) {
            $next['organization'] = [
                'name' => $this->shortText( $input['organization']['name'] ?? null ),
                'url'  => $this->httpUrl( $input['organization']['url'] ?? null ),
                'logo' => $this->httpUrl( $input['organization']['logo'] ?? null ),
            ];
        }
        if ( array_key_exists( 'search_console', $input ) ) {
            $next['search_console'] = $this->property( $input['search_console'] );
        }
        if ( array_key_exists( 'ga4', $input ) ) {
            $next['ga4'] = $this->property( $input['ga4'] );
        }
        if ( array_key_exists( 'title_separator', $input ) ) {
            $separator               = is_string( $input['title_separator'] ) ? trim( $input['title_separator'] ) : '';
            $next['title_separator'] = in_array( $separator, [ '|', '-', '–' ], true ) ? $separator : null;
        }
        foreach ( [ 'schema_enabled', 'sitemap_enabled', 'crawler_enabled' ] as $flag ) {
            if ( array_key_exists( $flag, $input ) ) {
                $next[ $flag ] = is_bool( $input[ $flag ] ) ? $input[ $flag ] : null;
            }
        }
        if ( array_key_exists( 'crawler_origin', $input ) ) {
            $next['crawler_origin'] = $this->httpUrl( $input['crawler_origin'] );
        }
        unset( $next['api_key'], $next['token'], $next['secret'] );
        $this->options->set( self::OPTION, $next, false );

        return $this->present( $next, $wooCommerceActive );
    }

    /**
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private function present( array $stored, bool $wooCommerceActive ): array {
        $organization = is_array( $stored['organization'] ?? null ) ? $stored['organization'] : [];
        $search       = is_array( $stored['search_console'] ?? null ) ? $stored['search_console'] : [];
        $ga4          = is_array( $stored['ga4'] ?? null ) ? $stored['ga4'] : [];
        $site         = $stored['site_type'] ?? null;

        return [
            'site_type'       => is_string( $site ) && in_array( $site, self::SITE_TYPES, true ) ? $site : null,
            'business_type'   => $this->shortText( $stored['business_type'] ?? null ),
            'woocommerce'     => $wooCommerceActive,
            'organization'    => [
                'name' => $this->shortText( $organization['name'] ?? null ),
                'url'  => $this->httpUrl( $organization['url'] ?? null ),
                'logo' => $this->httpUrl( $organization['logo'] ?? null ),
            ],
            'search_console'  => [
                'property' => $this->shortText( $search['property'] ?? null ),
                'state'    => 'not_configured',
            ],
            'ga4'             => [
                'property' => $this->shortText( $ga4['property'] ?? null ),
                'state'    => 'not_configured',
            ],
            'title_separator' => is_string( $stored['title_separator'] ?? null ) ? $stored['title_separator'] : null,
            'schema_enabled'  => is_bool( $stored['schema_enabled'] ?? null ) ? $stored['schema_enabled'] : null,
            'sitemap_enabled' => is_bool( $stored['sitemap_enabled'] ?? null ) ? $stored['sitemap_enabled'] : null,
            'crawler_enabled' => is_bool( $stored['crawler_enabled'] ?? null ) ? $stored['crawler_enabled'] : null,
            'crawler_origin'  => $this->httpUrl( $stored['crawler_origin'] ?? null ),
            'providers'       => 'not_configured',
            'completed'       => is_string( $site ) && in_array( $site, self::SITE_TYPES, true ),
            'connected'       => false,
            'crawl_started'   => false,
        ];
    }

    private function shortText( mixed $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }
        $trimmed = trim( $value );
        if ( $trimmed === '' ) {
            return null;
        }

        return function_exists( 'mb_substr' ) ? mb_substr( $trimmed, 0, 80 ) : substr( $trimmed, 0, 80 );
    }

    private function httpUrl( mixed $value ): ?string {
        $url = $this->shortText( $value );
        if ( $url === null ) {
            return null;
        }
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return null;
        }
        $scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || ( $parts['host'] ?? '' ) === '' ) {
            return null;
        }

        return $url;
    }

    /**
     * @return array{property: string|null}
     */
    private function property( mixed $value ): array {
        $property = null;
        if ( is_string( $value ) ) {
            $property = $this->shortText( $value );
        } elseif ( is_array( $value ) ) {
            $property = $this->shortText( $value['property'] ?? null );
        }

        return [ 'property' => $property ];
    }
}
