<?php
/**
 * Opportunity detail. Actions record a status and do not change the live page.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Opportunities\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OpportunityDrawer {

    public const OPTION = 'querynova_opportunity_drawer';

    /**
     * @var list<string>
     */
    public const ACTIONS = [ 'accept', 'dismiss', 'applied', 'experiment' ];

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function present( array $item ): array {
        return [
            'why'          => self::text( $item['why'] ?? $item['rationale'] ?? null ),
            'evidence'     => self::text( $item['evidence'] ?? null ),
            'sources'      => self::sources( $item['sources'] ?? $item['provenance'] ?? null ),
            'action'       => self::text( $item['action'] ?? $item['title'] ?? null ),
            'kpi'          => self::text( $item['kpi'] ?? $item['impact'] ?? null ),
            'confidence'   => self::text( $item['confidence'] ?? null ),
            'risks'        => self::text( $item['risks'] ?? null ),
            'url'          => self::text( $item['url'] ?? null ),
            'page_changed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function act( int $id, string $action, bool $confirmed ): array {
        $known = in_array( $action, self::ACTIONS, true );
        if ( ! $confirmed || ! $known || $id < 1 ) {
            return [
                'id'           => $id,
                'action'       => $known ? $action : '',
                'stored'       => false,
                'page_changed' => false,
                'published'    => false,
                'note'         => 'The live page stays unchanged until you confirm Accept, Dismiss, Mark Applied, or Create Experiment.',
            ];
        }
        update_option(
            self::OPTION,
            [
                'id'           => $id,
                'action'       => $action,
                'page_changed' => false,
            ],
            false
        );

        return [
            'id'           => $id,
            'action'       => $action,
            'stored'       => true,
            'page_changed' => false,
            'published'    => false,
            'note'         => 'The recommendation status was recorded. The live page was not changed.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function sources( mixed $value ): array {
        if ( is_string( $value ) ) {
            $text = self::text( $value );

            return $text === null ? [] : [ $text ];
        }
        if ( ! is_array( $value ) ) {
            return [];
        }
        $sources = [];
        foreach ( $value as $source ) {
            $text = self::text( $source );
            if ( $text !== null ) {
                $sources[] = $text;
            }
        }

        return $sources;
    }

    private static function text( mixed $value ): ?string {
        $text = trim( wp_strip_all_tags( is_string( $value ) ? $value : '' ) );

        return $text === '' ? null : $text;
    }
}
