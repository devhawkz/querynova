<?php
/**
 * Declared support versus combinations that have actually run.
 *
 * PHP observed is only the interpreter running this process.
 * WordPress 7.1.2, WooCommerce 11.1.2 on that WordPress, and the admin
 * browser pass were observed on commit dbe8ec5edaed5782802597d17b3a1394c0fff0a5
 * in GitHub Actions run 36388914008.
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
     * PHP versions named in CI. Observed PHP stays the running interpreter.
     *
     * @var list<string>
     */
    public const CI_PHP = [ '8.1', '8.3' ];

    public const OBSERVED_WORDPRESS = '7.1.2';

    public const OBSERVED_WOOCOMMERCE = '11.1.2';

    public const OBSERVED_COMMIT = 'dbe8ec5edaed5782802597d17b3a1394c0fff0a5';

    public const OBSERVED_ACTIONS_RUN = '36388914008';

    /**
     * @return array{
     *     php: array{minimum: string, ci: list<string>, observed: list<string>},
     *     wp: array{minimum: string, observed: list<string>, commit: string, actions_run: string},
     *     wc: array{required: false, hpos: string, observed: list<string>, wp_release: string, commit: string, actions_run: string},
     *     admin_browser: array{observed: true, wp_release: string, commit: string, actions_run: string}
     * }
     */
    public function combinations(): array {
        return [
            'php'           => [
                'minimum'  => self::MIN_PHP,
                'ci'       => self::CI_PHP,
                'observed' => [ PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ],
            ],
            'wp'            => [
                'minimum'     => self::MIN_WORDPRESS,
                'observed'    => [ self::OBSERVED_WORDPRESS ],
                'commit'      => self::OBSERVED_COMMIT,
                'actions_run' => self::OBSERVED_ACTIONS_RUN,
            ],
            'wc'            => [
                'required'    => false,
                'hpos'        => 'api',
                'observed'    => [ self::OBSERVED_WOOCOMMERCE ],
                'wp_release'  => self::OBSERVED_WORDPRESS,
                'commit'      => self::OBSERVED_COMMIT,
                'actions_run' => self::OBSERVED_ACTIONS_RUN,
            ],
            'admin_browser' => [
                'observed'    => true,
                'wp_release'  => self::OBSERVED_WORDPRESS,
                'commit'      => self::OBSERVED_COMMIT,
                'actions_run' => self::OBSERVED_ACTIONS_RUN,
            ],
        ];
    }
}
