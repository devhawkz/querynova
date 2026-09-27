<?php
/**
 * SERP fixture. It does not fetch a search results page.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Providers;

use QueryNova\Modules\Serp\Domain\ProviderCapabilities;
use QueryNova\Modules\Serp\Domain\SerpProvider;
use QueryNova\Modules\Serp\Domain\SerpQuery;
use QueryNova\Modules\Serp\Domain\SerpSnapshot;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FakeSerpProvider implements SerpProvider {

    public function __construct( private readonly ?SerpSnapshot $snapshot ) {
    }

    public function id(): string {
        return 'fake-serp';
    }

    public function capabilities(): ProviderCapabilities {
        return new ProviderCapabilities( false, [ 10 ], true, true, false );
    }

    public function snapshot( SerpQuery $query ): ?SerpSnapshot {
        unset( $query );

        return $this->snapshot;
    }
}
