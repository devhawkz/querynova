<?php
/**
 * Disconnected page-experience adapter. It does not call PageSpeed or CrUX.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Experience\Infrastructure;

use QueryNova\Modules\Experience\Domain\ExperienceReport;
use QueryNova\Modules\Experience\Domain\PageExperienceProvider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class NullPageExperienceProvider implements PageExperienceProvider {

    public function id(): string {
        return '';
    }

    public function report( string $url, string $strategy ): ?ExperienceReport {
        unset( $url, $strategy );

        return null;
    }
}
