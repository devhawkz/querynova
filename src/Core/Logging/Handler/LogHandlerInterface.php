<?php
/**
 * Log handler contract.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging\Handler;

use QueryNova\Core\Logging\LogRecord;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface LogHandlerInterface {

    public function handle( LogRecord $record ): void;
}
