<?php
/**
 * Diagnostics snapshot. Missing versions stay null, and log text is scrubbed.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Cli;

use QueryNova\Core\Logging\LogSanitizer;

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
        $cron = $input['cron_scheduled'] ?? null;

        return [
            'environment'         => trim( (string) ( $input['environment'] ?? '' ) ),
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

    private static function nullableString( mixed $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }
        $trimmed = trim( $value );

        return $trimmed === '' ? null : $trimmed;
    }
}
