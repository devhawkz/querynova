<?php
/**
 * PSR-3 level ranking.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

use Psr\Log\LogLevel;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LogLevelRank {

    /**
     * @return array<string, int>
     */
    public static function ranks(): array {
        return [
            LogLevel::DEBUG     => 0,
            LogLevel::INFO      => 1,
            LogLevel::NOTICE    => 2,
            LogLevel::WARNING   => 3,
            LogLevel::ERROR     => 4,
            LogLevel::CRITICAL  => 5,
            LogLevel::ALERT     => 6,
            LogLevel::EMERGENCY => 7,
        ];
    }

    public static function meets( string $level, string $minimum ): bool {
        $ranks = self::ranks();

        return ( $ranks[ $level ] ?? 0 ) >= ( $ranks[ $minimum ] ?? 0 );
    }
}
