<?php
/**
 * Keyword research. A request stores or reads keywords. It does not scrape search results.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Keywords;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Keywords\Application\KeywordIntelligence;
use QueryNova\Modules\Keywords\Domain\KeywordRecord;
use QueryNova\Modules\Keywords\Infrastructure\KeywordRepository;
use QueryNova\Modules\Keywords\Infrastructure\NullAdsProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class KeywordModule extends AbstractModule {

    private ?KeywordRepository $keywords = null;

    private KeywordIntelligence $intelligence;

    public function __construct() {
        $this->intelligence = new KeywordIntelligence( new NullAdsProvider() );
    }

    public function getName(): string {
        return 'keywords';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.keywords',
                    'Keyword intelligence',
                    'keywords',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::MANAGE_SEO ],
                )
            );
        }
        $database       = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->keywords = new KeywordRepository( $database );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'GET', '/keywords', [ $this, 'index' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/keywords', [ $this, 'create' ], Capability::MANAGE_SEO );
    }

    /**
     * @return array<string, mixed>
     */
    public function index( \WP_REST_Request $request ): array {
        unset( $request );
        $rows = [];
        foreach ( $this->repository()->all() as $record ) {
            $rows[] = $this->intelligence->explore( $record );
        }

        return [ 'keywords' => $rows ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function create( \WP_REST_Request $request ): array|\WP_Error {
        $params  = $request->get_json_params();
        $keyword = trim( (string) ( $params['keyword'] ?? '' ) );
        if ( $keyword === '' ) {
            return new \WP_Error( 'querynova_invalid_keyword', 'A keyword is required.', [ 'status' => 400 ] );
        }
        $record = new KeywordRecord( 0, $keyword, (string) ( $params['country'] ?? '' ), (string) ( $params['language'] ?? '' ), null, null, null, null, null, null, '', 'manual', '', null );
        $id     = $this->repository()->save( $record );

        return [
            'id'     => $id,
            'volume' => null,
        ];
    }

    private function repository(): KeywordRepository {
        return $this->keywords instanceof KeywordRepository ? $this->keywords : new KeywordRepository( new ArrayDatabase() );
    }
}
