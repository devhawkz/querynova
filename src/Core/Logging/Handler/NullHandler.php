<?php
/**
 * Discards log records. Used when a channel is muted.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging\Handler;

use QueryNova\Core\Logging\LogRecord;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullHandler implements LogHandlerInterface {

    public function handle( LogRecord $record ): void {
    }
}
