<?php
/**
 * Breadcrumb settings, HTML, and BreadcrumbList data.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Breadcrumbs {

    public const OPTION = 'querynova_breadcrumbs';

    /**
     * @return array{separator: string, home: string}
     */
    public static function read(): array {
        $stored    = get_option( self::OPTION, null );
        $rows      = is_array( $stored ) ? $stored : [];
        $separator = is_string( $rows['separator'] ?? null ) ? trim( wp_strip_all_tags( $rows['separator'] ) ) : '/';
        $home      = is_string( $rows['home'] ?? null ) ? trim( wp_strip_all_tags( $rows['home'] ) ) : 'Home';

        return [
            'separator' => $separator !== '' ? $separator : '/',
            'home'      => $home !== '' ? $home : 'Home',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{separator: string, home: string}
     */
    public static function save( array $input ): array {
        $current = self::read();
        $next    = [
            'separator' => is_string( $input['separator'] ?? null ) && trim( wp_strip_all_tags( $input['separator'] ) ) !== ''
                ? trim( wp_strip_all_tags( $input['separator'] ) )
                : $current['separator'],
            'home'      => is_string( $input['home'] ?? null ) && trim( wp_strip_all_tags( $input['home'] ) ) !== ''
                ? trim( wp_strip_all_tags( $input['home'] ) )
                : $current['home'],
        ];
        update_option( self::OPTION, $next, false );

        return $next;
    }

    /**
     * @param list<array{label: string, url: string}>     $crumbs
     * @param array{separator: string, home: string}|null $settings
     */
    public static function html( array $crumbs, ?array $settings = null ): string {
        $settings = $settings ?? self::read();
        $parts    = [];
        foreach ( $crumbs as $crumb ) {
            $label = trim( wp_strip_all_tags( (string) ( $crumb['label'] ?? '' ) ) );
            $url   = trim( (string) ( $crumb['url'] ?? '' ) );
            if ( $label === '' ) {
                continue;
            }
            if ( $url !== '' && preg_match( '#^https?://#', $url ) === 1 ) {
                $safe    = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
                $parts[] = '<a href="' . $safe . '">' . esc_html( $label ) . '</a>';
                continue;
            }
            $parts[] = '<span>' . esc_html( $label ) . '</span>';
        }
        if ( $parts === [] ) {
            return '';
        }

        return '<nav class="querynova-breadcrumbs" aria-label="Breadcrumb">' . implode( ' ' . esc_html( (string) $settings['separator'] ) . ' ', $parts ) . '</nav>';
    }

    /**
     * @param list<array{label: string, url: string}> $crumbs
     * @return array<string, mixed>
     */
    public static function schema( array $crumbs ): array {
        $items    = [];
        $position = 1;
        foreach ( $crumbs as $crumb ) {
            $label = trim( (string) ( $crumb['label'] ?? '' ) );
            if ( $label === '' ) {
                continue;
            }
            $item = [
                '@type'    => 'ListItem',
                'position' => $position,
                'name'     => $label,
            ];
            $url  = trim( (string) ( $crumb['url'] ?? '' ) );
            if ( $url !== '' ) {
                $item['item'] = $url;
            }
            $items[] = $item;
            ++$position;
        }

        return [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
