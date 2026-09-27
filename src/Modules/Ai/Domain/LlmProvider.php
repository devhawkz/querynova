<?php
/**
 * Model observation. A null return means the provider did not run.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface LlmProvider {

    public function id(): string;

    public function model(): string;

    public function observe( string $prompt, string $locale ): ?AiObservation;
}
