<?php
/**
 * No SERP provider is connected. Snapshots stay unavailable.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Infrastructure;

use QueryNova\Modules\Serp\Domain\ProviderCapabilities;
use QueryNova\Modules\Serp\Domain\SerpProvider;
use QueryNova\Modules\Serp\Domain\SerpQuery;
use QueryNova\Modules\Serp\Domain\SerpSnapshot;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullSerpProvider implements SerpProvider {

    public function id(): string {
        return '';
    }

    public function capabilities(): ProviderCapabilities {
        return ProviderCapabilities::none();
    }

    public function snapshot( SerpQuery $query ): ?SerpSnapshot {
        unset( $query );

        return null;
    }
}
