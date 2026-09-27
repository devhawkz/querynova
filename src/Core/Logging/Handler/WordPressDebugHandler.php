<?php
/**
 * Writes PHP error_log only outside production, and never with secrets.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging\Handler;

use QueryNova\Core\Contracts\EnvironmentInterface;
use QueryNova\Core\Logging\LogRecord;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WordPressDebugHandler implements LogHandlerInterface {

    public function __construct( private readonly EnvironmentInterface $environment ) {
    }

    public function handle( LogRecord $record ): void {
        if ( $this->environment->isProduction() ) {
            return;
        }
        if ( ! function_exists( 'error_log' ) ) {
            return;
        }
        // This handler is not attached in production. It is the debug sink.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( '[QueryNova] ' . $record->level . ' ' . $record->channel . ' ' . $record->message . ' ' . $record->errorReference );
    }
}
