<?php
/**
 * Diagnostics snapshot. Missing versions stay null, and log text is scrubbed.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cli;

use QueryNova\Core\Logging\LogSanitizer;
use QueryNova\Core\ReleaseProfile;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DiagnosticsReport {

    /**
     * Connected adapters are null until a provider is configured.
     *
     * @var list<string>
     */
    private const PROVIDERS = [
        'search_console',
        'ga4',
        'commerce',
        'serp',
        'backlinks',
        'llm',
        'page_experience',
        'ads',
    ];

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function build( array $input ): array {
        $sanitizer = new LogSanitizer();
        $errors    = [];
        $rows      = is_array( $input['errors'] ?? null ) ? $input['errors'] : [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $message = $sanitizer->scrubString( (string) ( $row['message'] ?? '' ) );
            if ( $message === '' ) {
                continue;
            }
            $errors[] = [
                'message'   => $message,
                'reference' => $sanitizer->scrubString( (string) ( $row['error_reference'] ?? '' ) ),
            ];
            if ( count( $errors ) === 10 ) {
                break;
            }
        }
        $providers = [];
        foreach ( self::PROVIDERS as $name ) {
            $providers[] = [
                'name'  => $name,
                'state' => 'not_configured',
            ];
        }
        $queue  = [];
        $counts = is_array( $input['queue'] ?? null ) ? $input['queue'] : [];
        foreach ( $counts as $status => $count ) {
            if ( is_string( $status ) && is_int( $count ) ) {
                $queue[ $status ] = $count;
            }
        }
        $modules = [];
        foreach ( is_array( $input['modules'] ?? null ) ? $input['modules'] : [] as $name ) {
            if ( is_string( $name ) && $name !== '' ) {
                $modules[] = $name;
            }
        }
        $pending = [];
        foreach ( is_array( $input['pending_migrations'] ?? null ) ? $input['pending_migrations'] : [] as $version ) {
            if ( is_string( $version ) && $version !== '' ) {
                $pending[] = $version;
            }
        }
        $cron    = $input['cron_scheduled'] ?? null;
        $profile = self::profile( $input );

        return [
            'environment'         => $profile['environment'],
            'querynova_build'     => $profile['querynova_build'],
            'release_channel'     => $profile['release_channel'],
            'release_status'      => $profile['release_status'],
            'release_notice'      => $profile['release_notice'],
            'querynova_version'   => trim( (string) ( $input['querynova_version'] ?? '' ) ),
            'wp_version'          => self::nullableString( $input['wp_version'] ?? null ),
            'php_version'         => trim( (string) ( $input['php_version'] ?? PHP_VERSION ) ),
            'woocommerce_version' => self::nullableString( $input['woocommerce_version'] ?? null ),
            'db_version'          => null,
            'schema_version'      => trim( (string) ( $input['schema_version'] ?? '' ) ),
            'modules'             => $modules,
            'providers'           => $providers,
            'queue'               => $queue,
            'cron'                => [
                'scheduled' => is_bool( $cron ) ? $cron : null,
            ],
            'cache'               => [
                'adapter' => trim( (string) ( $input['cache_adapter'] ?? '' ) ),
                'hits'    => null,
            ],
            'migrations'          => [
                'current' => trim( (string) ( $input['schema_version'] ?? '' ) ),
                'pending' => $pending,
            ],
            'recent_errors'       => $errors,
        ];
    }

    /**
     * A missing snapshot stays empty. A supplied WordPress environment is normalized here.
     *
     * @param array<string, mixed> $input
     * @return array{environment: string, querynova_build: string, release_channel: string, release_status: string, release_notice: string|null}
     */
    private static function profile( array $input ): array {
        $environment = $input['environment'] ?? null;
        if ( ! is_string( $environment ) || trim( $environment ) === '' ) {
            return [
                'environment'     => '',
                'querynova_build' => '',
                'release_channel' => '',
                'release_status'  => '',
                'release_notice'  => null,
            ];
        }
        $build   = is_string( $input['querynova_build'] ?? null ) ? $input['querynova_build'] : 'unknown';
        $aligned = ReleaseProfile::assess( trim( $environment ), $build );

        return [
            'environment'     => $aligned->wordpressEnvironment(),
            'querynova_build' => $aligned->querynovaBuild(),
            'release_channel' => $aligned->releaseChannel(),
            'release_status'  => $aligned->status(),
            'release_notice'  => $aligned->notice(),
        ];
    }

    private static function nullableString( mixed $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }
        $trimmed = trim( $value );

        return $trimmed === '' ? null : $trimmed;
    }
}
