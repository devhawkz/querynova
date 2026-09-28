<?php
/**
 * Stored crawler allow and block preferences. robots.txt is not rewritten.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CrawlerAccess {

    public const OPTION = 'querynova_ai_crawler_access';

    /**
     * @return array<string, mixed>
     */
    public static function plan( string $agent, string $decision, bool $confirmed ): array {
        $agent    = trim( wp_strip_all_tags( $agent ) );
        $decision = strtolower( trim( $decision ) );
        $result   = [
            'agent'          => $agent,
            'decision'       => $decision === 'block' ? 'block' : 'allow',
            'stored'         => false,
            'robots_changed' => false,
            'note'           => 'Crawler access stays unchanged until you confirm.',
        ];
        if ( ! $confirmed || $agent === '' || ! in_array( $decision, [ 'allow', 'block' ], true ) ) {
            if ( $confirmed && ( $agent === '' || ! in_array( $decision, [ 'allow', 'block' ], true ) ) ) {
                $result['note'] = 'A crawler name and an allow or block choice are required. robots.txt was not changed.';
            }

            return $result;
        }
        $stored           = self::preferences();
        $stored[ $agent ] = $decision;
        update_option( self::OPTION, $stored, false );
        $result['stored']   = true;
        $result['decision'] = $decision;
        $result['note']     = 'The preference is stored. robots.txt was not changed.';

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public static function preferences(): array {
        $stored = get_option( self::OPTION, [] );
        if ( ! is_array( $stored ) ) {
            return [];
        }
        $rows = [];
        foreach ( $stored as $agent => $decision ) {
            if ( ! is_string( $agent ) || $agent === '' ) {
                continue;
            }
            if ( $decision !== 'allow' && $decision !== 'block' ) {
                continue;
            }
            $rows[ $agent ] = $decision;
        }

        return $rows;
    }
}
