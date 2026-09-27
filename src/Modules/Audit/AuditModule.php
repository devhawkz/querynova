<?php
/**
 * Audit log and change history. Operational logs stay in their own table.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Audit;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Audit\Application\ChangeHistory;
use QueryNova\Modules\Audit\Infrastructure\AuditRepository;
use QueryNova\Modules\Seo\Domain\MetaStoreInterface;
use QueryNova\Modules\Seo\Infrastructure\ArrayMetaStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AuditModule extends AbstractModule {

    private ChangeHistory $history;

    private ?AuditRepository $store = null;

    private MetaStoreInterface $meta;

    public function __construct( ?AuditRepository $store = null, ?MetaStoreInterface $meta = null ) {
        $this->history = new ChangeHistory();
        $this->store   = $store;
        $this->meta    = $meta ?? new ArrayMetaStore();
    }

    public function getName(): string {
        return 'audit';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.audit',
                    'Audit log',
                    'audit',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::VIEW_LOGS ],
                )
            );
        }
        if ( ! $this->store instanceof AuditRepository ) {
            $database    = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
            $this->store = new AuditRepository( $database );
        }
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/audit', [ $this, 'create' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/audit/rollback', [ $this, 'rollbackRoute' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/audit', [ $this, 'show' ], Capability::VIEW_LOGS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function create( \WP_REST_Request $request ): array|\WP_Error {
        try {
            return $this->record( $request->get_json_params() );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_audit', $exception->getMessage(), [ 'status' => 400 ] );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rollbackRoute( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();

        return $this->rollback( (int) ( $params['id'] ?? 0 ) );
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        $type = trim( (string) $request->get_param( 'object_type' ) );
        $id   = (int) $request->get_param( 'object_id' );
        $rows = $type === '' ? [] : $this->repository()->history( $type, $id );
        if ( $rows === [] ) {
            return [
                'status'  => 'unavailable',
                'changes' => null,
            ];
        }

        return [
            'status'  => 'stored',
            'changes' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function record( array $input ): array {
        $action = trim( (string) ( $input['action'] ?? '' ) );
        if ( ! in_array( $action, ChangeHistory::ACTIONS, true ) ) {
            throw new ValidationException( 'Unknown audit action.' );
        }
        $before = $this->payload( $input['before'] ?? null );
        $after  = $this->payload( $input['after'] ?? null );
        $id     = $this->repository()->record(
            (int) ( $input['user_id'] ?? 0 ),
            $action,
            trim( (string) ( $input['object_type'] ?? '' ) ),
            (int) ( $input['object_id'] ?? 0 ),
            $before,
            $after,
            trim( (string) ( $input['ip'] ?? '' ) ),
            trim( (string) ( $input['environment'] ?? '' ) )
        );

        return [
            'id'      => $id,
            'action'  => $action,
            'ip_hash' => trim( (string) ( $input['ip'] ?? '' ) ) === '' ? '' : hash( 'sha256', (string) $input['ip'] ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback( int $id ): array {
        $row = $this->repository()->find( $id );
        if ( $row === null ) {
            return [
                'applied' => false,
                'note'    => 'No audit row was found.',
            ];
        }
        $decoded = json_decode( (string) $row['before_json'], true );
        $before  = is_array( $decoded ) ? $decoded : null;
        $result  = $this->history->rollback( (string) $row['object_type'], (int) $row['object_id'], is_array( $before ) ? $before : [], $this->meta );
        if ( $result['applied'] === true ) {
            $this->repository()->record(
                (int) $row['user_id'],
                'seo_changed',
                (string) $row['object_type'],
                (int) $row['object_id'],
                $this->payload( $row['after_json'] ),
                $before,
                '',
                (string) $row['environment']
            );
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload( mixed $value ): ?array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );

            return is_array( $decoded ) ? $decoded : null;
        }
        if ( ! is_array( $value ) ) {
            return null;
        }

        return $value;
    }

    private function repository(): AuditRepository {
        if ( ! $this->store instanceof AuditRepository ) {
            $this->store = new AuditRepository( new ArrayDatabase() );
        }

        return $this->store;
    }
}
