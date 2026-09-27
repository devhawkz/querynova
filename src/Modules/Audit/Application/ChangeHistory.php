<?php
/**
 * QueryNova change records. Rollback is limited to title and description.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Audit\Application;

use QueryNova\Modules\Seo\Domain\MetaStoreInterface;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ChangeHistory {

    /**
     * @var list<string>
     */
    public const ACTIONS = [
        'seo_changed',
        'canonical_changed',
        'redirect_created',
        'integration_changed',
        'debug_enabled',
        'bulk_action',
        'feature_flag_changed',
    ];

    /**
     * @var list<string>
     */
    private const SAFE_FIELDS = [ 'title', 'description' ];

    /**
     * @var list<string>
     */
    private const DANGEROUS_FIELDS = [ 'canonical', 'robots', 'slug', 'url', 'indexability', 'redirect', 'deletion' ];

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function allows( string $action, ?array $before, ?array $after ): bool {
        unset( $after );
        if ( ! in_array( $action, self::ACTIONS, true ) ) {
            return false;
        }
        if ( $action !== 'seo_changed' || $before === null || $before === [] ) {
            return false;
        }
        foreach ( array_keys( $before ) as $field ) {
            if ( ! is_string( $field ) || ! in_array( $field, self::SAFE_FIELDS, true ) ) {
                return false;
            }
        }
        foreach ( self::DANGEROUS_FIELDS as $field ) {
            if ( array_key_exists( $field, $before ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $before
     * @return array{applied: bool, fields: list<string>, note: string}
     */
    public function rollback( string $objectType, int $objectId, array $before, MetaStoreInterface $meta ): array {
        if ( ! $this->allows( 'seo_changed', $before, null ) || $objectId < 1 || $objectType === '' ) {
            return [
                'applied' => false,
                'fields'  => [],
                'note'    => 'This change was not rolled back. Canonical, robots, slugs, URLs, redirects, and indexability stay as they are.',
            ];
        }
        $fields = [];
        foreach ( self::SAFE_FIELDS as $field ) {
            if ( ! array_key_exists( $field, $before ) || ! is_string( $before[ $field ] ) ) {
                continue;
            }
            $meta->set( $objectType, $objectId, $field, $before[ $field ] );
            $fields[] = $field;
        }
        if ( $fields === [] ) {
            return [
                'applied' => false,
                'fields'  => [],
                'note'    => 'No title or description value was available to restore.',
            ];
        }

        return [
            'applied' => true,
            'fields'  => $fields,
            'note'    => 'The previous title or description was restored.',
        ];
    }
}
