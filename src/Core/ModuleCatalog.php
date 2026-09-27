<?php
/**
 * Modules loaded for this request. Safe mode drops optional modules.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

use QueryNova\Core\Contracts\ModuleInterface;
use QueryNova\Modules\Core\CoreModule;
use QueryNova\Modules\Seo\SeoModule;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ModuleCatalog {

    /**
     * @return list<ModuleInterface>
     */
    public static function modules( bool $safeMode ): array {
        $modules = [ new CoreModule() ];
        if ( $safeMode ) {
            return $modules;
        }

        $modules[] = new SeoModule();

        return $modules;
    }
}
