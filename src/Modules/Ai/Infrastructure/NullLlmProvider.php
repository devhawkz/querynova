<?php
/**
 * Disconnected model adapter. It does not call a provider.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Infrastructure;

use QueryNova\Modules\Ai\Domain\LlmProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullLlmProvider implements LlmProvider {

    public function id(): string {
        return '';
    }

    public function model(): string {
        return '';
    }

    public function observe( string $prompt, string $locale ): ?\QueryNova\Modules\Ai\Domain\AiObservation {
        unset( $prompt, $locale );

        return null;
    }
}
