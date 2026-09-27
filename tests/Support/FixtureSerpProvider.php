<?php
/**
 * Canned SERP results for tests. This provider does not perform HTTP.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Support;

use QueryNova\Modules\Serp\Domain\ProviderCapabilities;
use QueryNova\Modules\Serp\Domain\SerpHit;
use QueryNova\Modules\Serp\Domain\SerpProvider;
use QueryNova\Modules\Serp\Domain\SerpQuery;
use QueryNova\Modules\Serp\Domain\SerpSnapshot;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FixtureSerpProvider implements SerpProvider {

    public int $calls = 0;

    /**
     * @param list<SerpHit> $hits
     * @param list<string>|null $features
     */
    public function __construct(
        private readonly array $hits,
        private readonly ?array $features = null,
    ) {
    }

    public function id(): string {
        return 'fixture';
    }

    public function capabilities(): ProviderCapabilities {
        return new ProviderCapabilities( true, [ 10, 20, 100 ], true, true, true );
    }

    public function snapshot( SerpQuery $query ): ?SerpSnapshot {
        unset( $query );
        ++$this->calls;

        return new SerpSnapshot( $this->hits, $this->features, '2026-09-27 12:00:00' );
    }
}
