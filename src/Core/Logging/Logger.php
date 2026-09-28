<?php
/**
 * PSR-3 logger with channels, correlation, and redaction.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use QueryNova\Core\Contracts\ClockInterface;
use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Logging\Handler\LogHandlerInterface;
use QueryNova\Core\ReleaseProfile;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Logger implements LoggerInterface {

    use LoggerTrait;

    /**
     * @param list<LogHandlerInterface> $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly LogSanitizer $sanitizer,
        private readonly EnvironmentInterface $environment,
        private readonly ClockInterface $clock,
        private readonly CorrelationContext $correlation,
        private string $minimumLevel,
        private readonly ReleaseProfile $release,
    ) {
    }

    public function setMinimumLevel( string $minimumLevel ): void {
        $this->minimumLevel = $minimumLevel;
    }

    public function minimumLevel(): string {
        return $this->minimumLevel;
    }

    /**
     * @param mixed $level
     * @param mixed $message
     * @param array<string, mixed> $context
     */
    public function log( $level, $message, array $context = [] ): void {
        $level = strtolower( (string) $level );
        if ( ! LogLevelRank::meets( $level, $this->minimumLevel ) ) {
            return;
        }

        $context['wordpress_environment'] = $this->release->wordpressEnvironment();
        $context['querynova_build']       = $this->release->querynovaBuild();
        $context['release_channel']       = $this->release->releaseChannel();
        $context['release_status']        = $this->release->status();
        if ( $this->release->notice() !== null ) {
            $context['release_notice'] = $this->release->notice();
        }
        $context        = $this->sanitizer->sanitize( $context );
        $exceptionClass = '';
        $exceptionCode  = '';
        if ( isset( $context['exception_class'] ) && is_string( $context['exception_class'] ) ) {
            $exceptionClass = $context['exception_class'];
            $exceptionCode  = isset( $context['exception_code'] ) ? (string) $context['exception_code'] : '';
        }

        $reference = isset( $context['error_reference'] ) && is_string( $context['error_reference'] )
            ? $context['error_reference']
            : ( ( $level === 'error' || LogLevelRank::meets( $level, 'error' ) ) ? ErrorReference::generate() : '' );

        $record = new LogRecord(
            $this->clock->now(),
            $level,
            $this->stringFrom( $context, 'channel', LogChannel::CORE ),
            $this->sanitizer->scrubString( $this->interpolate( (string) $message, $context ) ),
            $this->environment->getName(),
            QUERYNOVA_VERSION,
            $this->stringFrom( $context, 'wordpress_version', $this->wordpressVersion() ),
            $this->stringFrom( $context, 'woocommerce_version', $this->woocommerceVersion() ),
            $this->correlation->requestId(),
            $this->correlation->correlationId(),
            $this->correlation->jobId(),
            $this->stringFrom( $context, 'module', '' ),
            $this->stringFrom( $context, 'provider', '' ),
            $context,
            $exceptionClass,
            $exceptionCode,
            $reference,
        );

        foreach ( $this->handlers as $handler ) {
            try {
                $handler->handle( $record );
            } catch ( \Throwable $handler_error ) {
                unset( $handler_error );
            }
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolate( string $message, array $context ): string {
        $replace = [];
        foreach ( $context as $key => $value ) {
            if ( is_scalar( $value ) || $value === null ) {
                $replace[ '{' . $key . '}' ] = (string) $value;
            }
        }

        return strtr( $message, $replace );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function stringFrom( array $context, string $key, string $fallback ): string {
        $value = $context[ $key ] ?? $fallback;

        return is_scalar( $value ) ? (string) $value : $fallback;
    }

    private function wordpressVersion(): string {
        global $wp_version;

        return is_string( $wp_version ?? null ) ? $wp_version : '';
    }

    private function woocommerceVersion(): string {
        if ( defined( 'WC_VERSION' ) ) {
            return (string) WC_VERSION;
        }

        return '';
    }
}
