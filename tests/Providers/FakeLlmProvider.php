<?php
/**
 * Model fixture. It does not call a language-model API.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Providers;

use QueryNova\Modules\Ai\Domain\AiObservation;
use QueryNova\Modules\Ai\Domain\LlmProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FakeLlmProvider implements LlmProvider {

    public function __construct( private readonly ?AiObservation $observation ) {
    }

    public function id(): string {
        return 'fake-llm';
    }

    public function model(): string {
        return 'fake-model';
    }

    public function observe( string $prompt, string $locale ): ?AiObservation {
        unset( $prompt, $locale );

        return $this->observation;
    }
}
