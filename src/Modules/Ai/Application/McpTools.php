<?php
/**
 * Read-only assistant tools. A write does nothing until permission is explicit.
 *
 * Responses omit secret-like fields.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class McpTools {

    /**
     * @var list<string>
     */
    public const READS = [
        'site_status',
        'issues',
        'keywords',
        'rankings',
        'not_found',
        'redirects',
        'sitemap_status',
        'product_seo',
        'opportunities',
        'ai_visibility',
    ];

    /**
     * @var list<string>
     */
    public const WRITES = [ 'store_note' ];

    /**
     * @return array{reads: list<string>, writes: list<string>, note: string}
     */
    public static function catalog(): array {
        return [
            'reads'  => self::READS,
            'writes' => self::WRITES,
            'note'   => 'Reads return stored snapshots. Writes need an explicit permission and do not change live content.',
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function read( string $tool, array $context ): array {
        $context = self::redact( $context );
        if ( ! in_array( $tool, self::READS, true ) ) {
            return [
                'tool'   => $tool,
                'status' => 'unavailable',
                'value'  => null,
                'note'   => 'That read tool is not available.',
            ];
        }
        if ( $tool === 'ai_visibility' ) {
            $workspace = AiWorkspace::present( '', [], null, null );

            return [
                'tool'      => $tool,
                'status'    => 'not_connected',
                'value'     => [
                    'citations' => $workspace['citations']['value'],
                    'mentions'  => $workspace['mentions']['value'],
                ],
                'note'      => 'No AI provider is connected. Citations and mention share stay empty.',
                'connected' => false,
            ];
        }
        if ( $tool === 'site_status' ) {
            return [
                'tool'        => $tool,
                'status'      => 'stored',
                'environment' => is_string( $context['environment'] ?? null ) ? $context['environment'] : null,
                'version'     => is_string( $context['version'] ?? null ) ? $context['version'] : null,
                'note'        => 'Site status from the supplied snapshot. Secrets are omitted.',
            ];
        }
        $value = $context[ $tool ] ?? null;
        if ( $value === null ) {
            return [
                'tool'   => $tool,
                'status' => 'unavailable',
                'value'  => null,
                'note'   => 'Nothing stored was supplied for this tool. A missing value is not zero.',
            ];
        }

        return [
            'tool'   => $tool,
            'status' => 'stored',
            'value'  => $value,
            'note'   => 'Stored snapshot. Secrets are omitted.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function write( string $tool, array $payload, bool $permitted ): array {
        unset( $payload );
        if ( ! $permitted ) {
            return [
                'tool'      => $tool,
                'accepted'  => false,
                'changed'   => false,
                'published' => false,
                'note'      => 'Writes need an explicit permission. Nothing was changed.',
            ];
        }
        if ( ! in_array( $tool, self::WRITES, true ) ) {
            return [
                'tool'      => $tool,
                'accepted'  => false,
                'changed'   => false,
                'published' => false,
                'note'      => 'That write is not available. Nothing was changed.',
            ];
        }

        return [
            'tool'      => $tool,
            'accepted'  => true,
            'changed'   => false,
            'published' => false,
            'note'      => 'Permission was recorded. Live content was not changed.',
        ];
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private static function redact( array $value ): array {
        $clean = [];
        foreach ( $value as $key => $item ) {
            if ( is_string( $key ) && self::secret( $key ) ) {
                continue;
            }
            if ( is_array( $item ) ) {
                $clean[ $key ] = self::redact( $item );
                continue;
            }
            $clean[ $key ] = $item;
        }

        return $clean;
    }

    private static function secret( string $key ): bool {
        $key = strtolower( $key );
        foreach ( [ 'secret', 'token', 'password', 'nonce', 'api_key', 'authorization', 'credential' ] as $needle ) {
            if ( str_contains( $key, $needle ) ) {
                return true;
            }
        }

        return false;
    }
}
