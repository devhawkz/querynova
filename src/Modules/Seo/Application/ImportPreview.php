<?php
/**
 * Import preview. It does not write meta and it does not disable another plugin.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ImportPreview {

    /**
     * @param list<array<string, mixed>> $objects
     * @return array<string, mixed>
     */
    public static function plan( string $plugin, array $objects ): array {
        $keys = SeoImporter::metaKeys( $plugin );
        if ( $keys === [] ) {
            return [
                'plugin'                => $plugin,
                'imported'              => 0,
                'skipped'               => 0,
                'failed'                => count( $objects ),
                'log'                   => [],
                'applied'               => false,
                'disabled_other_plugin' => false,
                'batched'               => false,
                'note'                  => 'SEO import supports Yoast, Rank Math, and AIOSEO. Nothing was written and the other plugin was not disabled.',
            ];
        }
        $batched = count( $objects ) > SeoImporter::MAX_OBJECTS;
        $slice   = array_slice( $objects, 0, SeoImporter::MAX_OBJECTS );
        $log     = [];
        $counts  = [
            'imported' => 0,
            'skipped'  => 0,
            'failed'   => 0,
        ];
        foreach ( $slice as $object ) {
            $id     = (int) ( $object['object_id'] ?? 0 );
            $type   = is_string( $object['object_type'] ?? null ) ? $object['object_type'] : '';
            $meta   = is_array( $object['meta'] ?? null ) ? $object['meta'] : [];
            $status = 'skipped';
            if ( ! in_array( $type, [ 'post', 'term' ], true ) || $id < 1 ) {
                $status = 'failed';
            } elseif ( self::hasMeta( $keys, $meta ) ) {
                $status = 'imported';
            }
            ++$counts[ $status ];
            $log[] = [
                'object_id' => $id,
                'status'    => $status,
            ];
        }

        return [
            'plugin'                => $plugin,
            'imported'              => $counts['imported'],
            'skipped'               => $counts['skipped'],
            'failed'                => $counts['failed'],
            'log'                   => $log,
            'applied'               => false,
            'disabled_other_plugin' => false,
            'batched'               => $batched,
            'note'                  => $batched
                ? 'Preview includes the first 50 objects. Nothing was written and the other plugin was not disabled.'
                : 'Preview only. Nothing was written and the other plugin was not disabled.',
        ];
    }

    /**
     * @param list<string>         $keys
     * @param array<string, mixed> $meta
     */
    private static function hasMeta( array $keys, array $meta ): bool {
        foreach ( $keys as $key ) {
            $value = $meta[ $key ] ?? null;
            if ( is_string( $value ) && trim( $value ) !== '' ) {
                return true;
            }
        }

        return false;
    }
}
