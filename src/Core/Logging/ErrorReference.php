<?php
/**
 * Error reference shown to people instead of a raw exception.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ErrorReference {

    public static function generate(): string {
        return 'QN-' . strtoupper( bin2hex( random_bytes( 4 ) ) );
    }

    public static function isValid( string $reference ): bool {
        return preg_match( '/^QN-[A-F0-9]{8}$/', $reference ) === 1;
    }
}
