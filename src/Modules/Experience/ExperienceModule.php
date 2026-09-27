<?php
/**
 * Page experience collection. A public request only enqueues.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experience;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Exceptions\JobException;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Features\Feature;
use QueryNova\Core\Features\FeatureFlagState;
use QueryNova\Core\Features\FeatureRegistry;
use QueryNova\Core\Features\LicenseTier;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Security\Capability;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Database\WpdbConnection;
use QueryNova\Infrastructure\Lock\LockInterface;
use QueryNova\Infrastructure\Lock\TransientLock;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Experience\Application\ExperienceSummary;
use QueryNova\Modules\Experience\Domain\ExperienceReport;
use QueryNova\Modules\Experience\Domain\PageExperienceProvider;
use QueryNova\Modules\Experience\Infrastructure\ExperienceRepository;
use QueryNova\Modules\Experience\Infrastructure\NullPageExperienceProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ExperienceModule extends AbstractModule {

    private ?ExperienceRepository $store = null;

    private ?JobRunner $runner = null;

    private ?LockInterface $lock = null;

    private PageExperienceProvider $provider;

    private ExperienceSummary $summary;

    public function __construct( ?PageExperienceProvider $provider = null ) {
        $this->provider = $provider ?? new NullPageExperienceProvider();
        $this->summary  = new ExperienceSummary();
    }

    public function getName(): string {
        return 'experience';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.experience',
                    'Page experience',
                    'experience',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::RUN_ANALYSIS ],
                )
            );
        }
        $runner = $container->get( JobRunner::class );
        $lock   = $container->get( TransientLock::class );
        if ( $runner instanceof JobRunner ) {
            $this->runner = $runner;
        }
        if ( $lock instanceof LockInterface ) {
            $this->lock = $lock;
        }
        $database    = isset( $GLOBALS['wpdb'] ) ? new WpdbConnection() : new ArrayDatabase();
        $this->store = new ExperienceRepository( $database );
    }

    public function registerJobs( JobRegistrar $jobs ): void {
        $jobs->register(
            'querynova.experience.collect',
            function ( array $payload, array $job ): void {
                unset( $job );
                $this->run( (string) ( $payload['url'] ?? '' ), (string) ( $payload['strategy'] ?? '' ) );
            }
        );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/experience', [ $this, 'enqueue' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/experience', [ $this, 'show' ], Capability::VIEW_ANALYTICS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function enqueue( \WP_REST_Request $request ): array|\WP_Error {
        if ( ! $this->runner instanceof JobRunner ) {
            return new \WP_Error( 'querynova_experience_unavailable', 'Page experience is unavailable.', [ 'status' => 500 ] );
        }
        $params   = $request->get_json_params();
        $url      = trim( (string) ( $params['url'] ?? '' ) );
        $strategy = trim( (string) ( $params['strategy'] ?? '' ) );
        if ( $url === '' || ! in_array( $strategy, [ 'desktop', 'mobile' ], true ) ) {
            return new \WP_Error( 'querynova_invalid_experience', 'A URL and a desktop or mobile strategy are required.', [ 'status' => 400 ] );
        }
        $jobId = $this->runner->enqueue(
            'querynova.experience.collect',
            [
                'url'      => $url,
                'strategy' => $strategy,
            ],
            'experience-' . hash( 'sha256', $url . $strategy . microtime( true ) )
        );

        return [
            'job_id' => $jobId,
            'status' => 'queued',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        $url      = trim( (string) $request->get_param( 'url' ) );
        $strategy = trim( (string) $request->get_param( 'strategy' ) );
        $row      = $url === '' ? null : $this->repository()->latest( $url, $strategy );
        if ( $row === null ) {
            return $this->summary->summarize( null, $strategy );
        }

        return $this->summary->summarize( new ExperienceReport( $this->floatOrNull( $row['lcp'] ), $this->floatOrNull( $row['inp'] ), $this->floatOrNull( $row['cls'] ), $this->floatOrNull( $row['ttfb'] ) ), $strategy );
    }

    /**
     * @return array<string, mixed>
     */
    public function collect( string $url, string $strategy ): array {
        $report = $this->provider->report( $url, $strategy );
        if ( $report instanceof ExperienceReport ) {
            $this->repository()->save( $url, $strategy, $this->provider->id(), $report );
        }

        return $this->summary->summarize( $report, $strategy );
    }

    private function run( string $url, string $strategy ): void {
        if ( $url === '' || ! in_array( $strategy, [ 'desktop', 'mobile' ], true ) ) {
            throw new ValidationException( 'A URL and a desktop or mobile strategy are required.' );
        }
        $lock = $this->lock instanceof LockInterface ? $this->lock : new TransientLock();
        if ( ! $lock->acquire( 'experience-batch', 120 ) ) {
            throw new JobException( 'A page experience collection is already running.' );
        }
        try {
            $this->collect( $url, $strategy );
        } finally {
            $lock->release( 'experience-batch' );
        }
    }

    private function floatOrNull( mixed $value ): ?float {
        if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && is_numeric( $value ) ) ) {
            return null;
        }

        return (float) $value;
    }

    private function repository(): ExperienceRepository {
        return $this->store instanceof ExperienceRepository ? $this->store : new ExperienceRepository( new ArrayDatabase() );
    }
}
