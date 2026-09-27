<?php
/**
 * Persists log records for the admin log viewer.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging\Handler;

use QueryNova\Core\Logging\LogRecord;
use QueryNova\Infrastructure\Database\LogRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class DatabaseLogHandler implements LogHandlerInterface {

    public function __construct( private readonly LogRepository $logs ) {
    }

    public function handle( LogRecord $record ): void {
        $this->logs->insert( $record );
    }
}
