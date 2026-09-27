<?php
/**
 * Reserved cloud log destination. It records nothing until a cloud adapter is configured.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging\Handler;

use QueryNova\Core\Logging\LogRecord;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FutureCloudLogHandler implements LogHandlerInterface {

    private bool $enabled = false;

    public function enable(): void {
        $this->enabled = true;
    }

    public function handle( LogRecord $record ): void {
        if ( ! $this->enabled ) {
            return;
        }
    }
}
