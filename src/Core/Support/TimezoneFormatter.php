<?php
/**
 * Formats stored UTC timestamps in the WordPress timezone.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Support;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class TimezoneFormatter {

    public function display( \DateTimeImmutable $utc, string $format = 'Y-m-d H:i' ): string {
        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );

        return $utc->setTimezone( $timezone )->format( $format );
    }
}
