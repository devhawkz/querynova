<?php
/**
 * Temporary production debug window. It always expires.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

use Psr\Log\LogLevel;
use QueryNova\Core\Contracts\ClockInterface;
use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Infrastructure\WordPress\OptionStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DebugMode {

    public const OPTION = 'querynova_debug_until';

    /** @var list<int> */
    public const ALLOWED_SECONDS = [ 900, 3600, 21600, 86400 ];

    public function __construct(
        private readonly OptionStore $options,
        private readonly ClockInterface $clock,
    ) {
    }

    public function enable( int $seconds ): \DateTimeImmutable {
        if ( ! in_array( $seconds, self::ALLOWED_SECONDS, true ) ) {
            throw new ValidationException( 'Debug mode must be 15 minutes, 1 hour, 6 hours, or 24 hours.' );
        }
        $until = $this->clock->now()->modify( '+' . $seconds . ' seconds' );
        $this->options->set( self::OPTION, $until->format( 'c' ) );

        return $until;
    }

    public function disable(): void {
        $this->options->delete( self::OPTION );
    }

    public function isActive(): bool {
        $raw = $this->options->get( self::OPTION, '' );
        if ( ! is_string( $raw ) || $raw === '' ) {
            return false;
        }
        try {
            $until = new \DateTimeImmutable( $raw );
        } catch ( \Exception ) {
            return false;
        }
        if ( $until <= $this->clock->now() ) {
            $this->disable();

            return false;
        }

        return true;
    }

    public function minimumLevel( EnvironmentInterface $environment ): string {
        if ( $this->isActive() ) {
            return LogLevel::DEBUG;
        }
        if ( $environment->isProduction() ) {
            return LogLevel::INFO;
        }

        return LogLevel::DEBUG;
    }
}
