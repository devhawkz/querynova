<?php
/**
 * Backlink fixture. It does not call a backlink vendor.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Providers;

use QueryNova\Modules\Backlinks\Domain\Backlink;
use QueryNova\Modules\Backlinks\Domain\BacklinkProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FakeBacklinkProvider implements BacklinkProvider {

    /**
     * @param list<Backlink>|null $links
     */
    public function __construct( private readonly ?array $links ) {
    }

    public function id(): string {
        return 'fake-backlink';
    }

    public function authorityMetric(): string {
        return '';
    }

    public function links( string $targetUrl ): ?array {
        unset( $targetUrl );

        return $this->links;
    }
}
