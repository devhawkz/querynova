<?php
/**
 * A permalink redirect is created only after the user confirms it.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PermalinkRedirect {

    /**
     * @return array<string, mixed>
     */
    public static function plan( string $from, string $to, bool $confirmed ): array {
        $from = trim( $from );
        $to   = trim( $to );
        if ( ! $confirmed || $from === '' || $to === '' || $from === $to ) {
            return [
                'created'   => false,
                'confirmed' => $confirmed,
                'source'    => $from,
                'target'    => $to,
                'note'      => 'A redirect is not created until you confirm it.',
            ];
        }

        return [
            'created'   => true,
            'confirmed' => true,
            'source'    => $from,
            'target'    => $to,
            'status'    => 301,
            'note'      => 'The confirmed permalink change can be stored as one redirect.',
        ];
    }
}
