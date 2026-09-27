<?php
/**
 * No backlink provider is connected.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Backlinks\Infrastructure;

use QueryNova\Modules\Backlinks\Domain\BacklinkProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullBacklinkProvider implements BacklinkProvider {

    public function id(): string {
        return '';
    }

    public function authorityMetric(): string {
        return '';
    }

    public function links( string $targetUrl ): ?array {
        unset( $targetUrl );

        return null;
    }
}
