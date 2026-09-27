<?php
/**
 * Declared support versus combinations this process has actually run.
 *
 * CI configuration is not the same as an observed run.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SupportMatrix {

    public const MIN_PHP = '8.1';

    public const MIN_WORDPRESS = '6.4';

    /**
     * PHP versions named in CI. This process has not necessarily executed them.
     *
     * @var list<string>
     */
    public const CI_PHP = [ '8.1', '8.3' ];

    /**
     * @return array{
     *     php: array{minimum: string, ci: list<string>, observed: list<string>},
     *     wp: array{minimum: string, observed: list<string>},
     *     wc: array{required: false, hpos: string, observed: list<string>}
     * }
     */
    public function combinations(): array {
        return [
            'php' => [
                'minimum'  => self::MIN_PHP,
                'ci'       => self::CI_PHP,
                'observed' => [ PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ],
            ],
            'wp'  => [
                'minimum'  => self::MIN_WORDPRESS,
                'observed' => [],
            ],
            'wc'  => [
                'required' => false,
                'hpos'     => 'api',
                'observed' => [],
            ],
        ];
    }
}
