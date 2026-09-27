<?php
/**
 * Safe mode keeps core, settings, diagnostics, and logs.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\SafeMode;

use QueryNova\Infrastructure\WordPress\OptionStore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SafeMode {

    public const OPTION = 'querynova_safe_mode';

    public function __construct( private readonly OptionStore $options ) {
    }

    public function isEnabled(): bool {
        if ( defined( 'QUERYNOVA_SAFE_MODE' ) && QUERYNOVA_SAFE_MODE ) {
            return true;
        }

        return $this->options->get( self::OPTION, 'no' ) === 'yes';
    }

    public function enable(): void {
        $this->options->set( self::OPTION, 'yes', true );
    }

    public function disable(): void {
        $this->options->set( self::OPTION, 'no', true );
    }
}
