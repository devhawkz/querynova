<?php
/**
 * WordPress environment and the installed QueryNova build stay separate.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ReleaseProfile {

    public const STATUS_NORMAL = 'normal';

    public const STATUS_WARNING = 'warning';

    public const STATUS_INFO = 'info';

    public const STAGING_ON_PRODUCTION = 'Staging build is running on a production WordPress environment. This build includes additional diagnostic capabilities and is intended primarily for testing. For normal live-site operation, use the production QueryNova build.';

    public const PRODUCTION_ON_STAGING = 'A production QueryNova build is running on a staging WordPress environment. The plugin keeps working.';

    public const OTHER_MISMATCH = 'This QueryNova build does not match the WordPress environment. The plugin keeps working.';

    private function __construct(
        private readonly string $wordpressEnvironment,
        private readonly string $querynovaBuild,
        private readonly string $releaseChannel,
        private readonly string $status,
        private readonly ?string $notice,
    ) {
    }

    public static function assess( string $wordpressEnvironment, string $buildChannel ): self {
        $wordpress = self::normalizeWordPress( $wordpressEnvironment );
        $build     = self::normalizeBuild( $buildChannel );
        $release   = self::releaseChannelFor( $build );

        if ( $wordpress === 'production' && $build === 'production' ) {
            return new self( $wordpress, $build, $release, self::STATUS_NORMAL, null );
        }
        if ( $wordpress === 'staging' && $build === 'staging' ) {
            return new self( $wordpress, $build, $release, self::STATUS_NORMAL, null );
        }
        if ( in_array( $wordpress, [ 'local', 'development' ], true ) && $build === 'development' ) {
            return new self( $wordpress, $build, $release, self::STATUS_NORMAL, null );
        }
        if ( $wordpress === 'production' && $build === 'staging' ) {
            return new self( $wordpress, $build, $release, self::STATUS_WARNING, self::STAGING_ON_PRODUCTION );
        }
        if ( $wordpress === 'staging' && $build === 'production' ) {
            return new self( $wordpress, $build, $release, self::STATUS_INFO, self::PRODUCTION_ON_STAGING );
        }

        return new self( $wordpress, $build, $release, self::STATUS_INFO, self::OTHER_MISMATCH );
    }

    public static function releaseChannelFor( string $buildChannel ): string {
        return match ( self::normalizeBuild( $buildChannel ) ) {
            'production'  => 'stable',
            'staging'     => 'beta',
            'development' => 'development',
            default       => 'unknown',
        };
    }

    public function wordpressEnvironment(): string {
        return $this->wordpressEnvironment;
    }

    public function querynovaBuild(): string {
        return $this->querynovaBuild;
    }

    public function releaseChannel(): string {
        return $this->releaseChannel;
    }

    public function status(): string {
        return $this->status;
    }

    public function notice(): ?string {
        return $this->notice;
    }

    /**
     * @return array{wordpress_environment: string, querynova_build: string, release_channel: string, release_status: string, release_notice: string|null}
     */
    public function toArray(): array {
        return [
            'wordpress_environment' => $this->wordpressEnvironment,
            'querynova_build'       => $this->querynovaBuild,
            'release_channel'       => $this->releaseChannel,
            'release_status'        => $this->status,
            'release_notice'        => $this->notice,
        ];
    }

    private static function normalizeWordPress( string $name ): string {
        $allowed = [ 'local', 'development', 'staging', 'production' ];
        if ( ! in_array( $name, $allowed, true ) ) {
            return 'production';
        }

        return $name;
    }

    private static function normalizeBuild( string $name ): string {
        $allowed = [ 'production', 'staging', 'development' ];
        if ( ! in_array( $name, $allowed, true ) ) {
            return 'unknown';
        }

        return $name;
    }
}
