<?php
/**
 * Core SEO module.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Seo;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Hooks\HookRegistrar;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Redirects\Application\RedirectEngine;
use QueryNova\Modules\Redirects\Infrastructure\RedirectRepository;
use QueryNova\Modules\Local\LocalGate;
use QueryNova\Modules\Podcast\PodcastGate;
use QueryNova\Modules\Seo\Application\HeadlessDocument;
use QueryNova\Modules\Seo\Application\ImportPreview;
use QueryNova\Modules\Seo\Application\SeoConflictDetector;
use QueryNova\Modules\Seo\Application\SeoImporter;
use QueryNova\Modules\Seo\Application\SeoMetaService;
use QueryNova\Modules\Seo\Domain\TemplateRenderer;
use QueryNova\Modules\Seo\Infrastructure\WordPressForeignMetaReader;
use QueryNova\Modules\Seo\Infrastructure\WordPressMetaStore;
use QueryNova\Modules\Seo\Presentation\EditorPanel;
use QueryNova\Modules\Seo\Presentation\FrontendSeoSubscriber;
use QueryNova\Modules\Seo\Presentation\OnPageController;
use QueryNova\Modules\Seo\Presentation\PostListColumnsSubscriber;
use QueryNova\Modules\Seo\Presentation\SeoConflictNotice;
use QueryNova\Modules\Seo\Presentation\SiteHead;
use QueryNova\Modules\Seo\Presentation\SiteToolsController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SeoModule extends AbstractModule {

    private ?OnPageController $onPage = null;

    public function getName(): string {
        return 'seo';
    }

    public function register( ContainerInterface $container ): void {
        $container->set( SeoMetaService::class, new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() ) );
        $runner       = $container->has( JobRunner::class ) ? $container->get( JobRunner::class ) : null;
        $this->onPage = new OnPageController( $runner instanceof JobRunner ? $runner : null );
        $features     = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.seo',
                    'Core SEO',
                    'seo',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::MANAGE_SEO ],
                )
            );
        }
    }

    public function registerHooks( HookRegistrar $hooks ): void {
        $hooks->add( new FrontendSeoSubscriber( new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() ) ) );
        $hooks->add( new SeoConflictNotice( new SeoConflictDetector() ) );
        $hooks->add( new SiteHead() );
        $hooks->add( new PostListColumnsSubscriber() );
        $hooks->add( $this->editorPanel() );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $onPage = $this->onPage();
        $rest->route( 'GET', '/seo/meta', [ $this, 'show' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/headless/seo', [ $this, 'headless' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/seo/meta', [ $this, 'update' ], Capability::MANAGE_SEO );
        $rest->route( 'PUT', '/seo/editor', [ $this->editorPanel(), 'update' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/seo/checklist', [ $onPage, 'checklist' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/seo/templates', [ $onPage, 'templates' ], Capability::MANAGE_SEO );
        $rest->route( 'PUT', '/seo/templates', [ $onPage, 'saveTemplates' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/seo/audit', [ $onPage, 'startAudit' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/seo/audit', [ $onPage, 'showAudit' ], Capability::RUN_ANALYSIS );
        $rest->route( 'POST', '/seo/import', [ $this, 'import' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/seo/import/preview', [ $this, 'importPreview' ], Capability::MANAGE_SEO );
        $tools = new SiteToolsController();
        $rest->route( 'GET', '/seo/robots', [ $tools, 'robots' ], Capability::MANAGE_SEO );
        $rest->route( 'PUT', '/seo/robots', [ $tools, 'saveRobots' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/seo/htaccess', [ $tools, 'htaccess' ], Capability::MANAGE_SEO );
        $rest->route( 'PUT', '/seo/htaccess', [ $tools, 'saveHtaccess' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/seo/webmaster', [ $tools, 'webmaster' ], Capability::MANAGE_SEO );
        $rest->route( 'PUT', '/seo/webmaster', [ $tools, 'saveWebmaster' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/seo/rss', [ $tools, 'rss' ], Capability::MANAGE_SEO );
        $rest->route( 'PUT', '/seo/rss', [ $tools, 'saveRss' ], Capability::MANAGE_SEO );
        $rest->route( 'GET', '/seo/breadcrumbs', [ $tools, 'breadcrumbs' ], Capability::MANAGE_SEO );
        $rest->route( 'PUT', '/seo/breadcrumbs', [ $tools, 'saveBreadcrumbs' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/seo/breadcrumbs/preview', [ $tools, 'breadcrumbPreview' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/seo/image-alt', [ $tools, 'imageAlt' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/seo/indexnow', [ $tools, 'indexNow' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/podcast', [ $this, 'podcast' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/local', [ $this, 'localSeo' ], Capability::MANAGE_SEO );
        $rest->route( 'POST', '/local/locations', [ $this, 'localLocation' ], Capability::MANAGE_SEO );
    }

    public function registerJobs( JobRegistrar $jobs ): void {
        $onPage = $this->onPage();
        $jobs->register(
            'querynova.seo.audit',
            static function ( array $payload, array $job ) use ( $onPage ): void {
                unset( $job );
                $onPage->runAudit( $payload );
            }
        );
    }

    private function onPage(): OnPageController {
        if ( ! $this->onPage instanceof OnPageController ) {
            $this->onPage = new OnPageController();
        }

        return $this->onPage;
    }

    private function editorPanel(): EditorPanel {
        return new EditorPanel( new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() ) );
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        $objectId = (int) $request->get_param( 'object_id' );
        $service  = new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() );
        $document = $service->resolve(
            'post',
            $objectId,
            [
				'title'    => '',
				'sep'      => '-',
				'sitename' => '',
				'excerpt'  => '',
			],
			[]
        );

        return $document->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function headless( \WP_REST_Request $request ): array {
        return HeadlessDocument::present( $this->show( $request ) );
    }

    /**
     * @return array<string, string>
     */
    public function update( \WP_REST_Request $request ): array {
        $objectId = (int) $request->get_param( 'object_id' );
        $fields   = $request->get_json_params();
        if ( ! is_array( $fields ) ) {
            $fields = [];
        }
        $clean = [];
        foreach ( $fields as $key => $value ) {
            if ( is_string( $key ) && is_string( $value ) ) {
                $clean[ $key ] = $value;
            }
        }
        $service = new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() );
        $service->save( 'post', $objectId, $clean );

        return [ 'status' => 'saved' ];
    }

    /**
     * @return array<string, mixed>
     */
    public function import( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = [];
        }
        $plugin  = is_string( $params['plugin'] ?? null ) ? $params['plugin'] : '';
        $replace = ( $params['replace'] ?? false ) === true;
        $objects = $this->importObjects( $params, $plugin );
        $rows    = [];
        if ( isset( $params['redirects'] ) && is_array( $params['redirects'] ) ) {
            foreach ( $params['redirects'] as $row ) {
                if ( is_array( $row ) ) {
                    $rows[] = $row;
                }
            }
        }
        $importer = $this->importer();

        return [
            'meta'      => $importer->importMeta( $plugin, $objects, $replace ),
            'redirects' => $importer->importRedirects( $rows ),
        ];
    }

    /**
     * Preview only. This does not call the importer and does not read live meta.
     *
     * @return array<string, mixed>
     */
    public function importPreview( \WP_REST_Request $request ): array {
        $params  = $request->get_json_params();
        $params  = is_array( $params ) ? $params : [];
        $plugin  = is_string( $params['plugin'] ?? null ) ? $params['plugin'] : '';
        $objects = [];
        if ( isset( $params['objects'] ) && is_array( $params['objects'] ) ) {
            foreach ( $params['objects'] as $object ) {
                if ( is_array( $object ) ) {
                    $objects[] = $object;
                }
            }
        }

        return ImportPreview::plan( $plugin, $objects );
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array{object_type: string, object_id: int, meta: array<string, mixed>}>
     */
    private function importObjects( array $params, string $plugin ): array {
        $objects = [];
        if ( isset( $params['objects'] ) && is_array( $params['objects'] ) ) {
            foreach ( $params['objects'] as $object ) {
                if ( ! is_array( $object ) ) {
                    continue;
                }
                $meta      = isset( $object['meta'] ) && is_array( $object['meta'] ) ? $object['meta'] : [];
                $objects[] = [
                    'object_type' => is_string( $object['object_type'] ?? null ) ? $object['object_type'] : 'post',
                    'object_id'   => (int) ( $object['object_id'] ?? 0 ),
                    'meta'        => $meta,
                ];
            }

            return $objects;
        }
        if ( ! isset( $params['object_ids'] ) || ! is_array( $params['object_ids'] ) ) {
            return [];
        }
        $reader = new WordPressForeignMetaReader();
        $type   = is_string( $params['object_type'] ?? null ) ? $params['object_type'] : 'post';
        foreach ( $params['object_ids'] as $id ) {
            $objectId  = (int) $id;
            $objects[] = [
                'object_type' => $type,
                'object_id'   => $objectId,
                'meta'        => $reader->read( $type, $objectId, $plugin ),
            ];
        }

        return $objects;
    }

    /**
     * @return array<string, mixed>
     */
    public function podcast( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];

        return PodcastGate::save(
            ( $params['enabled'] ?? false ) === true,
            ( $params['confirmed'] ?? false ) === true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function localSeo( \WP_REST_Request $request ): array {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : [];

        return LocalGate::save(
            ( $params['enabled'] ?? false ) === true,
            ( $params['confirmed'] ?? false ) === true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function localLocation( \WP_REST_Request $request ): array {
        $params  = $request->get_json_params();
        $params  = is_array( $params ) ? $params : [];
        $name    = is_string( $params['name'] ?? null ) ? $params['name'] : '';
        $address = is_string( $params['address'] ?? null ) ? $params['address'] : '';

        return LocalGate::saveLocation( $name, $address, ( $params['confirmed'] ?? false ) === true );
    }

    private function importer(): SeoImporter {
        $database = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();

        return new SeoImporter(
            new SeoMetaService( new WordPressMetaStore(), new TemplateRenderer() ),
            new RedirectEngine(),
            new RedirectRepository( $database )
        );
    }
}
