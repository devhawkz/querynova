<?php
/**
 * Search Console and GA4 connection state.
 *
 * A provider with an empty id stays Not connected. This class does not call Google.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Analytics\Application;

use QueryNova\Modules\Analytics\Domain\AnalyticsProvider;
use QueryNova\Modules\Analytics\Domain\AnalyticsRow;
use QueryNova\Modules\Analytics\Domain\SearchConsoleProvider;
use QueryNova\Modules\Analytics\Domain\SearchConsoleRow;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProviderConnections {

    public const OPTION = 'querynova_provider_connections';

    public const CHANNELS = [ 'search_console', 'ga4' ];

    /**
     * @return array{search_console: array{property: string, connected: bool}, ga4: array{property: string, connected: bool}}
     */
    public static function read(): array {
        $stored = get_option( self::OPTION, null );
        $rows   = is_array( $stored ) ? $stored : [];
        $clean  = [];
        foreach ( self::CHANNELS as $channel ) {
            $row               = is_array( $rows[ $channel ] ?? null ) ? $rows[ $channel ] : [];
            $property          = is_string( $row['property'] ?? null ) ? trim( wp_strip_all_tags( $row['property'] ) ) : '';
            $clean[ $channel ] = [
                'property'  => $property,
                'connected' => ( $row['connected'] ?? false ) === true && $property !== '',
            ];
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    public static function connect( string $channel, string $property, string $providerId ): array {
        if ( ! in_array( $channel, self::CHANNELS, true ) ) {
            return self::invalid();
        }
        $property           = trim( wp_strip_all_tags( $property ) );
        $stored             = self::read();
        $stored[ $channel ] = [
            'property'  => $property,
            'connected' => $providerId !== '' && $property !== '',
        ];
        update_option( self::OPTION, $stored, false );

        return self::card( $channel, $providerId );
    }

    /**
     * @return array<string, mixed>
     */
    public static function disconnect( string $channel ): array {
        if ( ! in_array( $channel, self::CHANNELS, true ) ) {
            return self::invalid();
        }
        $stored             = self::read();
        $stored[ $channel ] = [
            'property'  => '',
            'connected' => false,
        ];
        update_option( self::OPTION, $stored, false );

        return self::card( $channel, '' );
    }

    /**
     * @return array<string, mixed>
     */
    public static function card( string $channel, string $providerId ): array {
        $stored    = self::read();
        $row       = $stored[ $channel ] ?? [
            'property'  => '',
            'connected' => false,
        ];
        $connected = $providerId !== '' && $row['connected'] === true && $row['property'] !== '';

        return [
            'channel'     => $channel,
            'label'       => $channel === 'ga4' ? 'GA4' : 'Search Console',
            'status'      => $connected ? 'connected' : 'not_connected',
            'state'       => $connected ? 'Connected' : 'Not connected',
            'property'    => $row['property'],
            'clicks'      => null,
            'impressions' => null,
            'sessions'    => null,
            'revenue'     => null,
            'note'        => $connected
                ? 'Connected. Metrics stay empty until the provider returns rows.'
                : 'Not connected. Clicks, impressions, sessions, and revenue are not invented.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function probeSearch( SearchConsoleProvider $provider, string $property, string $start, string $end ): array {
        if ( $provider->id() === '' ) {
            return self::emptyTest();
        }

        return self::searchTest( $provider->id(), $provider->rows( $property, $start, $end ) );
    }

    /**
     * @return array<string, mixed>
     */
    public static function probeAnalytics( AnalyticsProvider $provider, string $property, string $start, string $end ): array {
        if ( $provider->id() === '' ) {
            return self::emptyTest();
        }

        return self::analyticsTest( $provider->id(), $provider->rows( $property, $start, $end ) );
    }

    /**
     * @param list<SearchConsoleRow>|null $rows
     * @return array<string, mixed>
     */
    public static function searchTest( string $providerId, ?array $rows ): array {
        if ( $providerId === '' || $rows === null ) {
            return self::emptyTest();
        }
        $summary = ( new AnalyticsWorkspace() )->search( $rows );

        return [
            'status'      => 'connected',
            'state'       => 'Connected',
            'clicks'      => $summary['clicks'],
            'impressions' => $summary['impressions'],
            'sessions'    => null,
            'revenue'     => null,
            'note'        => 'Search Console rows came from the provider. Sessions and revenue were not invented.',
        ];
    }

    /**
     * @param list<AnalyticsRow>|null $rows
     * @return array<string, mixed>
     */
    public static function analyticsTest( string $providerId, ?array $rows ): array {
        if ( $providerId === '' || $rows === null ) {
            return self::emptyTest();
        }
        $sessions = 0;
        $revenue  = 0.0;
        $known    = true;
        foreach ( $rows as $row ) {
            if ( ! $row instanceof AnalyticsRow || $row->sessions === null ) {
                return self::emptyTest();
            }
            $sessions += $row->sessions;
            if ( $row->revenue === null ) {
                $known = false;
                continue;
            }
            $revenue += $row->revenue;
        }

        return [
            'status'      => 'connected',
            'state'       => 'Connected',
            'clicks'      => null,
            'impressions' => null,
            'sessions'    => $sessions,
            'revenue'     => $known ? $revenue : null,
            'note'        => 'GA4 rows came from the provider. Clicks and impressions were not invented.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resyncPlan( string $channel, string $providerId, string $start, string $end ): array {
        $card = self::card( $channel, $providerId );
        if ( $card['status'] !== 'connected' || $card['property'] === '' ) {
            return [
                'status' => 'not_connected',
                'state'  => 'Not connected',
                'queued' => false,
                'note'   => 'Resync did not run because the provider is not connected.',
            ];
        }
        $start = trim( $start );
        $end   = trim( $end );
        if ( $start === '' || $end === '' ) {
            return [
                'status' => 'not_connected',
                'state'  => 'Not connected',
                'queued' => false,
                'note'   => 'A start and end date are required. Resync did not run.',
            ];
        }

        return [
            'status'  => 'queued',
            'state'   => 'Connected',
            'queued'  => true,
            'type'    => 'querynova.analytics.sync',
            'payload' => [
                'property' => $card['property'],
                'start'    => $start,
                'end'      => $end,
            ],
            'note'    => 'Resync is queued. This request does not call the provider.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyTest(): array {
        return [
            'status'      => 'not_connected',
            'state'       => 'Not connected',
            'clicks'      => null,
            'impressions' => null,
            'sessions'    => null,
            'revenue'     => null,
            'note'        => 'The connection test did not invent clicks, impressions, sessions, or revenue.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function invalid(): array {
        return [
            'status'      => 'not_connected',
            'state'       => 'Not connected',
            'property'    => '',
            'clicks'      => null,
            'impressions' => null,
            'sessions'    => null,
            'revenue'     => null,
            'note'        => 'Choose Search Console or GA4. Metrics were not invented.',
        ];
    }
}
