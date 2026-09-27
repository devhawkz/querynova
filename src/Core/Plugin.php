<?php
/**
 * Composition root. The main plugin file only boots this class.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

use QueryNova\Core\Container\ServiceContainer;
use QueryNova\Core\Contracts\ModuleInterface;
use QueryNova\Core\Environment\WordPressEnvironment;
use QueryNova\Core\Events\EventDispatcher;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Health\HealthRegistry;
use QueryNova\Core\Hooks\HookRegistrar;
use QueryNova\Core\Logging\CorrelationContext;
use QueryNova\Core\Logging\DebugMode;
use QueryNova\Core\Logging\Handler\DatabaseLogHandler;
use QueryNova\Core\Logging\Handler\FileLogHandler;
use QueryNova\Core\Logging\Handler\NullHandler;
use QueryNova\Core\Logging\Handler\WordPressDebugHandler;
use QueryNova\Core\Logging\Logger;
use QueryNova\Core\Logging\LogSanitizer;
use QueryNova\Core\Modules\ModuleRegistry;
use QueryNova\Core\SafeMode\SafeMode;
use QueryNova\Core\Security\RoleMap;
use QueryNova\Core\Support\SystemClock;
use QueryNova\Infrastructure\Cache\CacheInvalidator;
use QueryNova\Infrastructure\Cli\CliCommands;
use QueryNova\Infrastructure\Cli\WpCliRegistrar;
use QueryNova\Infrastructure\Cache\WordPressObjectCache;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\LogRepository;
use QueryNova\Infrastructure\Database\MigrationManager;
use QueryNova\Infrastructure\Database\MigrationRegistrar;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Http\WordPressHttpClient;
use QueryNova\Infrastructure\Lock\TransientLock;
use QueryNova\Infrastructure\Queue\ActionSchedulerAdapter;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRepository;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Queue\RetryPolicy;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Infrastructure\WordPress\AdminPageRegistrar;
use QueryNova\Infrastructure\WordPress\CapabilityRegistrar;
use QueryNova\Infrastructure\WordPress\OptionStore;
use QueryNova\Infrastructure\WordPress\SecretsStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {

    private static ?ServiceContainer $container = null;

    public static function container(): ServiceContainer {
        if ( self::$container === null ) {
            self::boot();
        }

        return self::$container;
    }

    public static function boot(): ServiceContainer {
        if ( self::$container !== null ) {
            return self::$container;
        }

        $container       = new ServiceContainer();
        self::$container = $container;

        $requirements = new Requirements();
        if ( $requirements->failures() !== [] ) {
            return $container;
        }

        self::loadActionScheduler();

        $environment = new WordPressEnvironment();
        $clock       = new SystemClock();
        $options     = new OptionStore();
        $correlation = CorrelationContext::fresh();
        $safeMode    = new SafeMode( $options );
        $debug       = new DebugMode( $options, $clock );
        $db          = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $logs        = new LogRepository( $db );
        $logger      = new Logger(
            [
                new FileLogHandler( self::logPath() ),
                new DatabaseLogHandler( $logs ),
                $environment->isProduction() ? new NullHandler() : new WordPressDebugHandler( $environment ),
            ],
            new LogSanitizer(),
            $environment,
            $clock,
            $correlation,
            $debug->minimumLevel( $environment ),
        );

        $container->set( WordPressEnvironment::class, $environment );
        $container->set( SystemClock::class, $clock );
        $container->set( OptionStore::class, $options );
        $container->set( SecretsStore::class, new SecretsStore( $options ) );
        $container->set( CorrelationContext::class, $correlation );
        $container->set( Logger::class, $logger );
        $container->set( SafeMode::class, $safeMode );
        $container->set( FeatureRegistry::class, new FeatureRegistry() );
        $container->set( EventDispatcher::class, new EventDispatcher( $logger ) );
        $container->set( HealthRegistry::class, new HealthRegistry() );
        $hooks        = new HookRegistrar();
        $rest         = new RestRegistrar();
        $jobs         = new JobRegistrar();
        $capabilities = new CapabilityRegistrar( new RoleMap() );
        $migrations   = new MigrationRegistrar();
        $admin        = new AdminPageRegistrar();
        $container->set( HookRegistrar::class, $hooks );
        $container->set( RestRegistrar::class, $rest );
        $container->set( JobRegistrar::class, $jobs );
        $container->set( MigrationRegistrar::class, $migrations );
        $container->set( AdminPageRegistrar::class, $admin );
        $container->set( LogRepository::class, $logs );
        $cache = new WordPressObjectCache();
        $container->set( WordPressObjectCache::class, $cache );
        $container->set( CacheInvalidator::class, new CacheInvalidator( $cache ) );
        $container->set( WordPressHttpClient::class, new WordPressHttpClient( $correlation, $logger ) );
        $container->set( TransientLock::class, new TransientLock() );

        $jobRepository = new JobRepository( $db );
        $runner        = new JobRunner(
            $jobRepository,
            $jobs,
            new RetryPolicy(),
            $clock,
            $correlation,
            $logger,
            new ActionSchedulerAdapter(),
        );
        $container->set( JobRepository::class, $jobRepository );
        $container->set( JobRunner::class, $runner );
        $container->set( MigrationManager::class, new MigrationManager( $db, $migrations, $options, $clock, $logger ) );

        $registry = new ModuleRegistry();
        foreach ( ModuleCatalog::modules( $safeMode->isEnabled() ) as $module ) {
            $registry->add( $module );
        }
        $container->set( ModuleRegistry::class, $registry );

        foreach ( $registry->bootOrder() as $module ) {
            self::bootModule( $module, $container, $registry, $hooks, $rest, $jobs, $capabilities, $migrations, $admin, $logger );
        }

        $hooks->register();
        $rest->register();
        $admin->register();
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $migrationManager = $container->get( MigrationManager::class );
            $healthRegistry   = $container->get( HealthRegistry::class );
            if ( $migrationManager instanceof MigrationManager && $healthRegistry instanceof HealthRegistry ) {
                $cronScheduled = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( 'querynova_process_jobs' ) !== false : null;
                WpCliRegistrar::register(
                    new CliCommands(
                        QUERYNOVA_VERSION,
                        $environment->getName(),
                        isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : null,
                        defined( 'WC_VERSION' ) ? (string) WC_VERSION : null,
                        $migrationManager,
                        $healthRegistry,
                        $registry,
                        $jobRepository,
                        $runner,
                        $cache,
                        $logs,
                        $cronScheduled,
                    )
                );
            }
        }
        add_action(
            'init',
            static function () use ( $capabilities ): void {
				$capabilities->register();
			}
        );
        add_action(
            'querynova_run_job',
            static function ( array $args ) use ( $runner ): void {
				$runner->work( (int) ( $args['job_id'] ?? 0 ) );
			}
        );
        add_action(
            'querynova_process_jobs',
            static function () use ( $runner ): void {
				$runner->workDue( 20 );
			}
        );

        return $container;
    }

    public static function activate( bool $networkWide = false ): void {
        if ( ! ( new MultisitePolicy() )->activatesCurrentSite( $networkWide ) ) {
            return;
        }
        $failures = ( new Requirements() )->failures();
        if ( $failures !== [] ) {
            if ( function_exists( 'deactivate_plugins' ) && defined( 'QUERYNOVA_BASENAME' ) ) {
                deactivate_plugins( QUERYNOVA_BASENAME );
            }
            wp_die( esc_html( implode( ' ', $failures ) ) );
        }
        $container = self::boot();
        $container->get( MigrationManager::class )->migrate();
        $container->get( CapabilityRegistrar::class )->register();
        ( new Lifecycle() )->ensureDefaults();
        if ( ! wp_next_scheduled( 'querynova_process_jobs' ) ) {
            wp_schedule_event( time() + 60, 'querynova_quarter_hour', 'querynova_process_jobs' );
        }
        do_action( 'querynova_register_rewrites' );
        if ( function_exists( 'flush_rewrite_rules' ) ) {
            flush_rewrite_rules( false );
        }
    }

    public static function deactivate(): void {
        ( new Lifecycle() )->releaseRuntime();
        if ( function_exists( 'flush_rewrite_rules' ) ) {
            flush_rewrite_rules( false );
        }
    }

    private static function bootModule(
        ModuleInterface $module,
        ServiceContainer $container,
        ModuleRegistry $registry,
        HookRegistrar $hooks,
        RestRegistrar $rest,
        JobRegistrar $jobs,
        CapabilityRegistrar $capabilities,
        MigrationRegistrar $migrations,
        AdminPageRegistrar $admin,
        Logger $logger,
    ): void {
        try {
            $module->register( $container );
            $module->registerHooks( $hooks );
            $module->registerRoutes( $rest );
            $module->registerJobs( $jobs );
            $module->registerCapabilities( $capabilities );
            $module->registerMigrations( $migrations );
            $module->registerAdminPages( $admin );
            $module->boot( $container );
            $container->get( HealthRegistry::class )->add( new \QueryNova\Core\Health\ModuleHealthCheck( $module ) );
        } catch ( \Throwable $exception ) {
            if ( ! $module->isOptional() ) {
                throw $exception;
            }
            $registry->recordFailure( $module->getName(), $exception );
            $logger->error(
                'Optional module failed to boot.',
                [
					'channel'   => 'core',
					'module'    => $module->getName(),
					'exception' => $exception,
				]
            );
        }
    }

    private static function logPath(): string {
        $uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : [ 'basedir' => sys_get_temp_dir() ];
        $base    = is_array( $uploads ) ? (string) ( $uploads['basedir'] ?? sys_get_temp_dir() ) : sys_get_temp_dir();

        return rtrim( $base, '/\\' ) . '/querynova/logs/querynova.log';
    }

    private static function loadActionScheduler(): void {
        if ( ! defined( 'WPINC' ) || function_exists( 'as_enqueue_async_action' ) ) {
            return;
        }
        $path = QUERYNOVA_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
        if ( is_file( $path ) ) {
            require_once $path;
        }
    }
}
