<?php
/**
 * Connected schema graph and the schema builder.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Schema;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Hooks\HookRegistrar;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Schema\Application\ConnectedGraphFactory;
use QueryNova\Modules\Schema\Application\JsonLdStudio;
use QueryNova\Modules\Schema\Application\SchemaDocumentBuilder;
use QueryNova\Modules\Schema\Application\SchemaRuleCodec;
use QueryNova\Modules\Schema\Application\SchemaRuleCompiler;
use QueryNova\Modules\Schema\Domain\SchemaRuleStore;
use QueryNova\Modules\Schema\Infrastructure\NullCommerceReader;
use QueryNova\Modules\Schema\Infrastructure\OptionSchemaRuleStore;
use QueryNova\Modules\Schema\Infrastructure\WooCommerceReader;
use QueryNova\Modules\Schema\Infrastructure\WordPressContentSnapshot;
use QueryNova\Modules\Schema\Presentation\SchemaHead;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SchemaModule extends AbstractModule {

    private ?SchemaRuleStore $rules = null;

    private ?SchemaRuleCodec $codec = null;

    private ?SchemaHead $head = null;

    public function getName(): string {
        return 'schema';
    }

    public function getDependencies(): array {
        return [ 'core', 'seo' ];
    }

    public function register( ContainerInterface $container ): void {
        $codec       = new SchemaRuleCodec();
        $store       = new OptionSchemaRuleStore( $codec );
        $this->codec = $codec;
        $this->rules = $store;
        $container->set( SchemaRuleStore::class, $store );
        $features    = $container->get( FeatureRegistry::class );
        $environment = $container->get( WordPressEnvironment::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.schema',
                    'Schema graph',
                    'schema',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::MANAGE_SEO ],
                )
            );
        }
        if ( ! $features instanceof FeatureRegistry || ! $environment instanceof WordPressEnvironment ) {
            return;
        }
        try {
            $this->head = $this->headFor( $store, $features, $environment );
        } catch ( \Throwable $exception ) {
            $this->head = null;
            unset( $exception );
        }
    }

    private function headFor( SchemaRuleStore $store, FeatureRegistry $features, WordPressEnvironment $environment ): SchemaHead {
        $commerce = class_exists( 'WooCommerce' ) ? new WooCommerceReader() : new NullCommerceReader();

        return new SchemaHead(
            new SchemaDocumentBuilder( new ConnectedGraphFactory(), new SchemaRuleCompiler() ),
            $store,
            new WordPressContentSnapshot( $commerce ),
            $features,
            $environment
        );
    }

    public function registerHooks( HookRegistrar $hooks ): void {
        if ( $this->head instanceof SchemaHead ) {
            $hooks->add( $this->head );
        }
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'GET', '/schema/rules', [ $this, 'show' ], Capability::MANAGE_SEO, [ Capability::MANAGE_SETTINGS ] );
        $rest->route( 'PUT', '/schema/rules', [ $this, 'update' ], Capability::MANAGE_SEO, [ Capability::MANAGE_SETTINGS ] );
        $rest->route( 'GET', '/schema/templates', [ $this, 'templates' ], Capability::MANAGE_SEO, [ Capability::MANAGE_SETTINGS ] );
        $rest->route( 'POST', '/schema/jsonld', [ $this, 'jsonld' ], Capability::MANAGE_SEO, [ Capability::MANAGE_SETTINGS ] );
        $rest->route( 'POST', '/schema/preview', [ $this, 'preview' ], Capability::MANAGE_SEO, [ Capability::MANAGE_SETTINGS ] );
    }

    /**
     * @return array<string, mixed>
     */
    public function templates( \WP_REST_Request $request ): array {
        unset( $request );

        return [ 'templates' => JsonLdStudio::templates() ];
    }

    /**
     * Import previews a rule. It does not write the rule store.
     *
     * @return array<string, mixed>
     */
    public function jsonld( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $json   = is_array( $params ) && is_string( $params['json'] ?? null ) ? $params['json'] : '';

        return JsonLdStudio::import( $json );
    }

    /**
     * @return array<string, mixed>
     */
    public function preview( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $rule   = is_array( $params ) && is_array( $params['rule'] ?? null ) ? $params['rule'] : [];

        return JsonLdStudio::validate( $rule );
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        unset( $request );

        return [ 'rules' => $this->export() ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function update( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        $rows   = is_array( $params ) ? ( $params['rules'] ?? null ) : null;
        if ( ! $this->codec instanceof SchemaRuleCodec || ! $this->rules instanceof SchemaRuleStore ) {
            return new \WP_Error( 'querynova_schema_unavailable', 'Schema rules are unavailable.', [ 'status' => 500 ] );
        }
        try {
            $rules = $this->codec->import( $rows );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_schema', $exception->getMessage(), [ 'status' => 400 ] );
        }
        $this->rules->replace( $rules );

        return [ 'rules' => $this->codec->export( $rules ) ];
    }

    /**
     * Rules already stored for this site. An unreadable option stays an empty list.
     *
     * @return list<array<string, mixed>>
     */
    public static function storedRules(): array {
        $codec = new SchemaRuleCodec();

        return $codec->export( ( new OptionSchemaRuleStore( $codec ) )->all() );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function export(): array {
        if ( ! $this->codec instanceof SchemaRuleCodec || ! $this->rules instanceof SchemaRuleStore ) {
            return [];
        }

        return $this->codec->export( $this->rules->all() );
    }
}
