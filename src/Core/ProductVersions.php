<?php
/**
 * Plugin, schema, API, and methodology versions stay separate.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

use QueryNova\Modules\Ai\Application\AiVisibility;
use QueryNova\Modules\Content\Infrastructure\ContentRepository;
use QueryNova\Modules\Keywords\Application\KeywordIntelligence;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ProductVersions {

    public const API = 'v1';

    /**
     * @return array{plugin: string, schema: string, api: string, channel: string, methodologies: array<string, string>}
     */
    public function describe(): array {
        return [
            'plugin'        => QUERYNOVA_VERSION,
            'schema'        => QUERYNOVA_DB_VERSION,
            'api'           => self::API,
            'channel'       => $this->channel(),
            'methodologies' => [
                KeywordIntelligence::DIFFICULTY_METHOD => KeywordIntelligence::DIFFICULTY_VERSION,
                ContentRepository::METHODOLOGY         => ContentRepository::METHODOLOGY_VERSION,
                AiVisibility::METHODOLOGY              => AiVisibility::VERSION,
            ],
        ];
    }

    public function channel(): string {
        $environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
        if ( in_array( $environment, [ 'local', 'development' ], true ) ) {
            return 'development';
        }
        if ( $environment === 'staging' ) {
            return 'beta';
        }

        return 'stable';
    }
}
