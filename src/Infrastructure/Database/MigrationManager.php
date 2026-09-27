<?php
/**
 * Applies pending migrations in version order and records each one.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Infrastructure\Database;

use QueryNova\Core\Contracts\ClockInterface;
use QueryNova\Core\Exceptions\MigrationException;
use QueryNova\Core\Logging\LogChannel;
use QueryNova\Core\Logging\Logger;
use QueryNova\Infrastructure\WordPress\OptionStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class MigrationManager {

    public const OPTION = 'querynova_db_version';

    public function __construct(
        private readonly DatabaseConnection $db,
        private readonly MigrationRegistrar $registrar,
        private readonly OptionStore $options,
        private readonly ClockInterface $clock,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * @return list<string> Applied versions.
     */
    public function migrate(): array {
        $current = (string) $this->options->get( self::OPTION, '0' );
        $applied = [];
        foreach ( $this->registrar->all() as $migration ) {
            if ( strcmp( $migration->version(), $current ) <= 0 ) {
                continue;
            }
            try {
                $migration->up( $this->db );
                $this->db->insert(
                    $this->db->prefix() . 'qn_migrations',
                    [
						'version'     => $migration->version(),
						'description' => $migration->description(),
						'executed_at' => $this->clock->now()->format( 'Y-m-d H:i:s' ),
						'status'      => 'applied',
					]
                );
                $this->options->set( self::OPTION, $migration->version(), false );
                $current   = $migration->version();
                $applied[] = $migration->version();
                $this->logger?->info(
                    'Migration applied.',
                    [
						'channel' => LogChannel::MIGRATIONS,
						'version' => $migration->version(),
					]
                );
            } catch ( \Throwable $exception ) {
                $this->logger?->error(
                    'Migration failed.',
                    [
						'channel'   => LogChannel::MIGRATIONS,
						'version'   => $migration->version(),
						'exception' => $exception,
					]
                );
                throw new MigrationException(
                    sprintf( 'Migration %s failed.', $migration->version() ),
                    0,
                    $exception
                );
            }
        }

        return $applied;
    }

    public function currentVersion(): string {
        return (string) $this->options->get( self::OPTION, '0' );
    }
}
