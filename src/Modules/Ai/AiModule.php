<?php
/**
 * AI visibility. A public request does not call a model.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai;

use QueryNova\Core\Container\ContainerInterface;
use QueryNova\Core\Exceptions\JobException;
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
use QueryNova\Infrastructure\Lock\LockInterface;
use QueryNova\Infrastructure\Lock\TransientLock;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Ai\Application\AiCrawlers;
use QueryNova\Modules\Ai\Application\AiVisibility;
use QueryNova\Modules\Ai\Application\LlmsDocument;
use QueryNova\Modules\Ai\Domain\AiObservation;
use QueryNova\Modules\Ai\Domain\LlmProvider;
use QueryNova\Modules\Ai\Infrastructure\AiRepository;
use QueryNova\Modules\Ai\Infrastructure\NullLlmProvider;
use QueryNova\Modules\Ai\Presentation\LlmsFrontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AiModule extends AbstractModule {

    private ?AiRepository $store = null;

    private ?JobRunner $runner = null;

    private ?LockInterface $lock = null;

    private LlmProvider $provider;

    private AiVisibility $visibility;

    private AiCrawlers $crawlers;

    private LlmsDocument $llms;

    private LlmsFrontend $frontend;

    public function __construct( ?LlmProvider $provider = null ) {
        $this->provider   = $provider ?? new NullLlmProvider();
        $this->visibility = new AiVisibility();
        $this->crawlers   = new AiCrawlers();
        $this->llms       = new LlmsDocument();
        $this->frontend   = new LlmsFrontend();
    }

    public function getName(): string {
        return 'ai';
    }

    public function register( ContainerInterface $container ): void {
        $features = $container->get( FeatureRegistry::class );
        if ( $features instanceof FeatureRegistry ) {
            $features->register(
                new Feature(
                    'querynova.ai',
                    'AI visibility',
                    'ai',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::On,
                    [ Capability::RUN_ANALYSIS ],
                )
            );
            $features->register(
                new Feature(
                    'querynova.llms',
                    'llms.txt',
                    'ai',
                    QUERYNOVA_VERSION,
                    [],
                    LicenseTier::Free,
                    [],
                    FeatureFlagState::Experimental,
                    [ Capability::MANAGE_SEO ],
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
        $this->store = new AiRepository( $database );
    }

    public function registerHooks( HookRegistrar $hooks ): void {
        $hooks->add( $this->frontend );
    }

    public function registerJobs( JobRegistrar $jobs ): void {
        $jobs->register(
            'querynova.ai.observe',
            function ( array $payload, array $job ): void {
                unset( $job );
                $this->run( (int) ( $payload['prompt_id'] ?? 0 ), (string) ( $payload['prompt'] ?? '' ), (string) ( $payload['locale'] ?? '' ) );
            }
        );
    }

    public function registerRoutes( RestRegistrar $rest ): void {
        $rest->route( 'POST', '/ai/prompts', [ $this, 'enqueue' ], Capability::RUN_ANALYSIS );
        $rest->route( 'GET', '/ai', [ $this, 'show' ], Capability::VIEW_ANALYTICS );
    }

    /**
     * @return array<string, mixed>|\WP_Error
     */
    public function enqueue( \WP_REST_Request $request ): array|\WP_Error {
        if ( ! $this->runner instanceof JobRunner ) {
            return new \WP_Error( 'querynova_ai_unavailable', 'AI observation is unavailable.', [ 'status' => 500 ] );
        }
        $params = $request->get_json_params();
        $prompt = trim( (string) ( $params['prompt'] ?? '' ) );
        $locale = trim( (string) ( $params['locale'] ?? '' ) );
        if ( $prompt === '' ) {
            return new \WP_Error( 'querynova_invalid_prompt', 'A prompt is required.', [ 'status' => 400 ] );
        }
        $promptId = $this->repository()->savePrompt( $prompt, $locale, $this->provider->id(), $this->provider->model() );
        $jobId    = $this->runner->enqueue(
            'querynova.ai.observe',
            [
                'prompt_id' => $promptId,
                'prompt'    => $prompt,
                'locale'    => $locale,
            ],
            'ai-' . hash( 'sha256', $prompt . $locale . microtime( true ) )
        );

        return [
            'job_id'    => $jobId,
            'prompt_id' => $promptId,
            'status'    => 'queued',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show( \WP_REST_Request $request ): array {
        unset( $request );
        if ( ! $this->repository()->hasRuns() ) {
            return $this->visibility->commerce( null );
        }

        return [
            'status'     => 'stored',
            'disclaimer' => AiVisibility::DISCLAIMER,
            'note'       => 'Stored observations. This response did not call a model.',
        ];
    }

    /**
     * @param array<string, float|null> $indexInputs
     * @param list<array{title: string, url: string}> $pages
     * @return array<string, mixed>
     */
    public function inspect( string $prompt, string $locale, ?AiObservation $observation, array $indexInputs, string $robots, array $pages, bool $llmsEnabled ): array {
        $stored = 0;
        if ( $observation instanceof AiObservation ) {
            $promptId = $this->repository()->savePrompt( $prompt, $locale, 'fixture', 'fixture' );
            $this->repository()->saveRun( $promptId, 'fixture', 'fixture', $observation );
            $stored = 1;
        }

        return [
            'called_provider_on_frontend' => false,
            'stored_runs'                 => $stored,
            'index'                       => $this->visibility->index( $indexInputs ),
            'crawlers'                    => $this->crawlers->registry( $robots ),
            'robots_changed'              => false,
            'llms'                        => $this->llms->body( $pages, $llmsEnabled ),
            'disclaimer'                  => AiVisibility::DISCLAIMER,
        ];
    }

    private function run( int $promptId, string $prompt, string $locale ): void {
        if ( $promptId < 1 || $prompt === '' ) {
            throw new ValidationException( 'A stored prompt is required.' );
        }
        $lock = $this->lock instanceof LockInterface ? $this->lock : new TransientLock();
        if ( ! $lock->acquire( 'ai-batch', 120 ) ) {
            throw new JobException( 'An AI observation is already running.' );
        }
        try {
            $observation = $this->provider->observe( $prompt, $locale );
            if ( $observation instanceof AiObservation ) {
                $this->repository()->saveRun( $promptId, $this->provider->id(), $this->provider->model(), $observation );
            }
        } finally {
            $lock->release( 'ai-batch' );
        }
    }

    private function repository(): AiRepository {
        return $this->store instanceof AiRepository ? $this->store : new AiRepository( new ArrayDatabase() );
    }
}
