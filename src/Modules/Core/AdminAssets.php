<?php
/**
 * Enqueues the admin application only on QueryNova screens.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Core;

use QueryNova\Core\BuildChannel;
use QueryNova\Core\Container\ServiceContainer;
use QueryNova\Core\Contracts\HookSubscriberInterface;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\ModuleCatalog;
use QueryNova\Core\Plugin;
use QueryNova\Core\ReleaseProfile;
use QueryNova\Core\SafeMode\SafeMode;
use QueryNova\Core\Support\SystemClock;
use QueryNova\Infrastructure\Cli\DiagnosticsReport;
use QueryNova\Infrastructure\Database\LogRepository;
use QueryNova\Infrastructure\Database\MigrationManager;
use QueryNova\Infrastructure\Database\MigrationRegistrar;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Queue\JobRepository;
use QueryNova\Infrastructure\WordPress\OptionStore;
use QueryNova\Modules\Ai\AiModule;
use QueryNova\Modules\Analytics\AnalyticsModule;
use QueryNova\Modules\Content\ContentModule;
use QueryNova\Modules\Local\LocalGate;
use QueryNova\Modules\Reports\ReportModule;
use QueryNova\Modules\Schema\SchemaModule;
use QueryNova\Modules\Seo\Application\MetaDefaults;
use QueryNova\Modules\Seo\Application\SeoConflictDetector;
use QueryNova\Modules\Seo\Presentation\OnPageController;
use QueryNova\Modules\Seo\Presentation\SiteToolsController;
use QueryNova\Modules\Serp\Application\RankTracker;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AdminAssets implements HookSubscriberInterface {

    public function hooks(): array {
        return [
            'admin_enqueue_scripts' => 'enqueue',
        ];
    }

    public function hookType( string $hook ): string {
        unset( $hook );

        return 'action';
    }

    public function enqueue( string $page ): void {
        if ( ! str_contains( $page, 'querynova' ) ) {
            return;
        }

        $path = QUERYNOVA_PATH . 'build/admin.js';
        if ( ! is_file( $path ) ) {
            add_action(
                'admin_notices',
                static function (): void {
                    echo '<div class="notice notice-warning"><p>' . esc_html__( 'QueryNova admin assets are missing. Run npm run build inside the plugin directory.', 'querynova' ) . '</p></div>';
                }
            );

            return;
        }

        $briefing = $this->briefing();
        $style    = QUERYNOVA_PATH . 'build/admin.css';
        if ( is_file( $style ) ) {
            wp_enqueue_style( 'querynova-admin', QUERYNOVA_URL . 'build/admin.css', [], QUERYNOVA_VERSION );
        }
        wp_enqueue_script( 'querynova-admin', QUERYNOVA_URL . 'build/admin.js', [ 'wp-i18n' ], QUERYNOVA_VERSION, true );
        wp_set_script_translations( 'querynova-admin', 'querynova', QUERYNOVA_PATH . 'languages' );
        wp_add_inline_script(
            'querynova-admin',
            'window.querynovaAdmin = ' . wp_json_encode(
                [
                    'restUrl'           => esc_url_raw( rest_url( 'querynova/v1' ) ),
                    'nonce'             => wp_create_nonce( 'wp_rest' ),
                    'version'           => QUERYNOVA_VERSION,
                    'environment'       => ( new WordPressEnvironment() )->getName(),
                    'wooCommerceActive' => class_exists( 'WooCommerce' ),
                    'actions'           => $briefing['actions'],
                    'sections'          => $briefing['sections'],
                    'advanced'          => $this->advanced(),
                    'product'           => $this->product(),
                    'category'          => $this->category(),
                    'diagnostics'       => $this->diagnostics(),
                    'documents'         => QuickSearchIndex::catalog(),
                    'setup'             => ( new SetupWizard( new OptionStore() ) )->read( class_exists( 'WooCommerce' ) ),
                    'schemaRules'       => SchemaModule::storedRules(),
                    'settings'          => $this->settingsSnapshot(),
                    'metaDefaults'      => MetaDefaults::read(),
                    'seoAudit'          => $this->seoAudit(),
                    'analytics'         => $this->analytics(),
                    'rankTracker'       => RankTracker::catalog(),
                    'siteTools'         => SiteToolsController::snapshot(),
                    'contentWorkspace'  => ContentModule::workspaceSnapshot(),
                    'aiWorkspace'       => AiModule::workspaceSnapshot(),
                    'reportsWorkspace'  => ReportModule::workspaceSnapshot(),
                    'localWorkspace'    => LocalGate::present(),
                    'notifications'     => $this->notifications(),
                    'sparklines'        => $this->sparklines(),
                ]
            ) . ';',
            'before'
        );
    }

    /**
     * Stored rows only. A read failure leaves every section empty.
     *
     * @return array{actions: list<array<string, mixed>>, sections: array<string, list<array<string, mixed>>>}
     */
    private function briefing(): array {
        $empty = [
            'actions'  => [],
            'sections' => DashboardBriefing::emptySections(),
        ];
        if ( ! isset( $GLOBALS['wpdb'] ) ) {
            return $empty;
        }
        try {
            return ( new DashboardBriefing() )->fromDatabase( new \QueryNova\Infrastructure\Database\WpdbConnection() );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return $empty;
        }
    }

    /**
     * Detail rows only. A read failure leaves the advanced view empty.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function advanced(): array {
        if ( ! isset( $GLOBALS['wpdb'] ) ) {
            return AdvancedBriefing::emptySections();
        }
        try {
            return ( new AdvancedBriefing() )->fromDatabase( new \QueryNova\Infrastructure\Database\WpdbConnection() );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return AdvancedBriefing::emptySections();
        }
    }

    /**
     * The latest stored product. A read failure leaves the screen empty.
     *
     * @return array{title: string|null, tabs: array<string, list<array<string, mixed>>>, workspace: array<string, mixed>}
     */
    private function product(): array {
        if ( ! isset( $GLOBALS['wpdb'] ) ) {
            return ProductScreen::emptyScreen();
        }
        try {
            return ( new ProductScreen() )->fromDatabase( new \QueryNova\Infrastructure\Database\WpdbConnection() );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return ProductScreen::emptyScreen();
        }
    }

    /**
     * The latest stored category. A read failure leaves the screen empty.
     *
     * @return array{title: string|null, tabs: array<string, list<array<string, mixed>>>, workspace: array<string, mixed>}
     */
    private function category(): array {
        if ( ! isset( $GLOBALS['wpdb'] ) ) {
            return CategoryScreen::emptyScreen();
        }
        try {
            return ( new CategoryScreen() )->fromDatabase( new \QueryNova\Infrastructure\Database\WpdbConnection() );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return CategoryScreen::emptyScreen();
        }
    }

    /**
     * Disconnected screen model. Opening the admin page does not call a provider.
     *
     * @return array<string, mixed>
     */
    private function analytics(): array {
        return AnalyticsModule::screenModel( '', '', '', '' );
    }

    /**
     * Stored audit report only. A missing report stays unavailable.
     *
     * @return array<string, mixed>
     */
    private function seoAudit(): array {
        $stored = get_option( OnPageController::AUDIT_OPTION, null );
        if ( ! is_array( $stored ) ) {
            return [
                'name'     => 'SEO Analyzer',
                'note'     => 'These findings do not estimate ranking impact.',
                'status'   => 'unavailable',
                'findings' => [],
            ];
        }

        return $stored;
    }

    /**
     * Registries only. A boot failure leaves the module grid empty.
     *
     * @return array<string, mixed>
     */
    private function settingsSnapshot(): array {
        $environment = new WordPressEnvironment();
        $build       = BuildChannel::installedChannel();
        try {
            return SettingsCatalog::fromContainer( Plugin::container(), $environment, $build );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return SettingsCatalog::fromContainer( new ServiceContainer(), $environment, $build );
        }
    }

    /**
     * The same snapshot as wp querynova diagnostics. A read failure stays empty.
     *
     * @return array<string, mixed>
     */
    private function diagnostics(): array {
        if ( ! isset( $GLOBALS['wpdb'] ) ) {
            return DiagnosticsReport::build( [] );
        }
        try {
            $database  = new WpdbConnection();
            $options   = new OptionStore();
            $registrar = new MigrationRegistrar();
            ( new CoreModule() )->registerMigrations( $registrar );
            $manager = new MigrationManager( $database, $registrar, $options, new SystemClock() );
            $names   = [];
            foreach ( ModuleCatalog::modules( ( new SafeMode( $options ) )->isEnabled() ) as $module ) {
                $names[] = $module->getName();
            }
            $scheduled = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( 'querynova_process_jobs' ) !== false : null;

            return DiagnosticsReport::build(
                [
                    'environment'         => ( new WordPressEnvironment() )->getName(),
                    'querynova_build'     => BuildChannel::installedChannel(),
                    'querynova_version'   => QUERYNOVA_VERSION,
                    'wp_version'          => isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : null,
                    'php_version'         => PHP_VERSION,
                    'woocommerce_version' => defined( 'WC_VERSION' ) ? (string) WC_VERSION : null,
                    'schema_version'      => $manager->currentVersion(),
                    'modules'             => $names,
                    'queue'               => ( new JobRepository( $database ) )->statusCounts(),
                    'cron_scheduled'      => $scheduled,
                    'cache_adapter'       => 'object-cache',
                    'pending_migrations'  => $manager->pendingVersions(),
                    'errors'              => ( new LogRepository( $database ) )->search( [ 'level' => 'error' ], 10, 0 ),
                ]
            );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return DiagnosticsReport::build( [] );
        }
    }

    /**
     * Stored points only. A read failure leaves every series empty.
     *
     * @return array<string, mixed>
     */
    private function sparklines(): array {
        if ( ! isset( $GLOBALS['wpdb'] ) ) {
            return ChartSeries::present( [], [], [] );
        }
        try {
            return ChartSeries::fromDatabase( new \QueryNova\Infrastructure\Database\WpdbConnection() );
        } catch ( \Throwable $exception ) {
            unset( $exception );

            return ChartSeries::present( [], [], [] );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function notifications(): array {
        $environment = ( new WordPressEnvironment() )->getName();
        $staging     = ( new StagingDataWarning() )->detect(
            $environment,
            ( new SetupWizard( new OptionStore() ) )->read( class_exists( 'WooCommerce' ) )
        );
        $profile     = ReleaseProfile::assess( $environment, BuildChannel::installedChannel() );
        $detector    = new SeoConflictDetector();
        $conflict    = $detector->detect( $detector->activePlugins() );

        return NotificationCenter::collect(
            $staging['warnings'],
            $profile->notice(),
            $profile->status(),
            $conflict['plugins']
        );
    }
}
