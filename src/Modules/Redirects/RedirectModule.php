<?php
/**
 * Redirects and the 404 monitor.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Redirects;

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
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Redirects\Application\PermalinkRedirect;
use QueryNova\Modules\Redirects\Application\RedirectCsv;
use QueryNova\Modules\Redirects\Application\RedirectEngine;
use QueryNova\Modules\Redirects\Application\RedirectList;
use QueryNova\Modules\Redirects\Domain\RedirectRule;
use QueryNova\Modules\Redirects\Infrastructure\NotFoundRepository;
use QueryNova\Modules\Redirects\Infrastructure\RedirectRepository;
use QueryNova\Modules\Redirects\Presentation\RedirectFrontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class RedirectModule extends AbstractModule {

    private ?RedirectEngine $engine = null;

    private ?RedirectRepository $redirects = null;

    private ?NotFoundRepository $missing = null;

    private ?RedirectFrontend $frontend = null;

    public function getName(): string {
        return 'redirects';
    }

    public function register( ContainerInterface $container ): void {
        $database        = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->engine    = new RedirectEngine();
        $this->redirects = new RedirectRepository( $database );
        $this->missing   = new NotFoundRepository( $database );
        $features        = $container->get( FeatureRegistry::class );
        $environment     = $container->get( WordPressEnvironment::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.redirects',
                    'Redirects',
                    'redirects',
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
        $this->frontend = new RedirectFrontend( $this->engine, $this->redirects, $this->missing, $features, $environment );
    }

    public function registerHooks( HookRegistrar $hooks ): void {
        if ( $this->frontend instanceof RedirectFrontend ) {
            $hooks->add( $this->frontend );
        }
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'GET', '/redirects', [ $this, 'index' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/redirects', [ $this, 'create' ], Capability::MANAGE_SEO );
        $rest->route( 'DELETE', '/redirects', [ $this, 'destroy' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/redirects/import', [ $this, 'import' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/redirects/export', [ $this, 'export' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/not-found', [ $this, 'notFound' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/redirects/list', [ $this, 'list' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/not-found/list', [ $this, 'notFoundList' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/redirects/permalink', [ $this, 'permalink' ], Capability::MANAGE_SEO );
    }

    /**
     * @return array<string, mixed>
     */
    public function index( \WP_REST_Request $request ): array {
        unset( $request );

        return [ 'redirects' => $this->exportRows( $this->rules() ) ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function create( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) || ! $this->engine instanceof RedirectEngine || ! $this->redirects instanceof RedirectRepository ) {
            return new \WP_Error( 'querynova_redirects_unavailable', 'Redirects are unavailable.', [ 'status' => 500 ] );
        }
        try {
            $regex  = (bool) ( $params['regex'] ?? false );
            $status = (int) ( $params['status'] ?? 0 );
            $rule   = new RedirectRule(
                0,
                $this->engine->normalizeSource( (string) ( $params['source'] ?? '' ), $regex ),
                $this->engine->normalizeTarget( (string) ( $params['target'] ?? '' ), $status ),
                $status,
                $regex
            );
            $this->engine->assertSafe( $this->redirects->all(), $rule );
            $id = $this->redirects->save( $rule );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_redirect', $exception->getMessage(), [ 'status' => 400 ] );
        }

        return [ 'id' => $id ];
    }

    /**
     * @return array<string, string>|\WP_Error
     */
    public function destroy( \WP_REST_Request $request ): array|\WP_Error {
        if ( ! $this->redirects instanceof RedirectRepository ) {
            return new \WP_Error( 'querynova_redirects_unavailable', 'Redirects are unavailable.', [ 'status' => 500 ] );
        }
        $this->redirects->delete( (int) $request->get_param( 'id' ) );

        return [ 'status' => 'deleted' ];
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function import( \WP_REST_Request $request ): array|\WP_Error {
        $params = $request->get_json_params();
        $csv    = is_array( $params ) ? (string) ( $params['csv'] ?? '' ) : '';
        if ( ! $this->engine instanceof RedirectEngine || ! $this->redirects instanceof RedirectRepository ) {
            return new \WP_Error( 'querynova_redirects_unavailable', 'Redirects are unavailable.', [ 'status' => 500 ] );
        }
        try {
            $rules = ( new RedirectCsv( $this->engine ) )->import( $csv, $this->redirects->all() );
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_redirect', $exception->getMessage(), [ 'status' => 400 ] );
        }
        $imported = 0;
        foreach ( $rules as $rule ) {
            if ( $rule->id() !== 0 ) {
                continue;
            }
            $this->redirects->save( $rule );
            ++$imported;
        }

        return [ 'imported' => $imported ];
    }

    /**
     * @return array<string, string>
     */
    public function export( \WP_REST_Request $request ): array {
        unset( $request );

        return [ 'csv' => ( new RedirectCsv( new RedirectEngine() ) )->export( $this->rules() ) ];
    }

    /**
     * @return array<string, mixed>
     */
    public function notFound( \WP_REST_Request $request ): array {
        unset( $request );
        if ( ! $this->missing instanceof NotFoundRepository ) {
            return [ 'not_found' => [] ];
        }

        return [ 'not_found' => $this->missing->recent() ];
    }

    /**
     * @return array<string, mixed>
     */
    public function list( \WP_REST_Request $request ): array {
        return RedirectList::slice(
            $this->exportRows( $this->rules() ),
            $this->textParam( $request, 'search' ),
            $this->textParam( $request, 'status' ),
            (int) $request->get_param( 'page' ),
            (int) $request->get_param( 'per_page' )
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function notFoundList( \WP_REST_Request $request ): array {
        $rows = $this->missing instanceof NotFoundRepository ? $this->missing->recent() : [];

        return RedirectList::slice(
            $rows,
            $this->textParam( $request, 'search' ),
            $this->textParam( $request, 'status' ),
            (int) $request->get_param( 'page' ),
            (int) $request->get_param( 'per_page' )
        );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function permalink( \WP_REST_Request $request ): array|\WP_Error {
        $params    = $request->get_json_params();
        $params    = is_array( $params ) ? $params : [];
        $from      = is_string( $params['from'] ?? null ) ? $params['from'] : '';
        $to        = is_string( $params['to'] ?? null ) ? $params['to'] : '';
        $confirmed = ( $params['confirmed'] ?? false ) === true;
        $plan      = PermalinkRedirect::plan( $from, $to, $confirmed );
        if ( $plan['created'] !== true ) {
            $plan['stored'] = false;

            return $plan;
        }
        if ( ! $this->engine instanceof RedirectEngine || ! $this->redirects instanceof RedirectRepository ) {
            return new \WP_Error( 'querynova_redirects_unavailable', 'Redirects are unavailable.', [ 'status' => 500 ] );
        }
        try {
            $rule = new RedirectRule(
                0,
                $this->engine->normalizeSource( $from, false ),
                $this->engine->normalizeTarget( $to, 301 ),
                301,
                false
            );
            $this->engine->assertSafe( $this->redirects->all(), $rule );
            $plan['id']     = $this->redirects->save( $rule );
            $plan['stored'] = true;
        } catch ( ValidationException $exception ) {
            return new \WP_Error( 'querynova_invalid_redirect', $exception->getMessage(), [ 'status' => 400 ] );
        }

        return $plan;
    }

    private function textParam( \WP_REST_Request $request, string $key ): string {
        $value = $request->get_param( $key );

        return is_string( $value ) ? $value : '';
    }

    /**
     * @return list<RedirectRule>
     */
    private function rules(): array {
        return $this->redirects instanceof RedirectRepository ? $this->redirects->all() : [];
    }

    /**
     * @param list<RedirectRule> $rules
     * @return list<array<string, mixed>>
     */
    private function exportRows( array $rules ): array {
        $rows = [];
        foreach ( $rules as $rule ) {
            $rows[] = [
                'id'      => $rule->id(),
                'source'  => $rule->source(),
                'target'  => $rule->target(),
                'status'  => $rule->status(),
                'regex'   => $rule->regex(),
                'hits'    => $rule->hits(),
                'enabled' => $rule->enabled(),
            ];
        }

        return $rows;
    }
}
