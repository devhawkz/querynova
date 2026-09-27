<?php
/**
 * Log channels.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Logging;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class LogChannel {

    public const CORE            = 'core';
    public const DATABASE        = 'database';
    public const MIGRATIONS      = 'migrations';
    public const REST            = 'rest';
    public const JOBS            = 'jobs';
    public const CRON            = 'cron';
    public const PROVIDERS       = 'providers';
    public const SEARCH_CONSOLE  = 'search_console';
    public const ANALYTICS       = 'analytics';
    public const WOOCOMMERCE     = 'woocommerce';
    public const COMMERCE        = 'commerce';
    public const KEYWORDS        = 'keywords';
    public const SERP            = 'serp';
    public const RANKINGS        = 'rankings';
    public const BACKLINKS       = 'backlinks';
    public const CONTENT         = 'content';
    public const AI              = 'ai';
    public const RECOMMENDATIONS = 'recommendations';
    public const SECURITY        = 'security';
    public const PERFORMANCE     = 'performance';

    /**
     * @return list<string>
     */
    public static function all(): array {
        return [
            self::CORE,
            self::DATABASE,
            self::MIGRATIONS,
            self::REST,
            self::JOBS,
            self::CRON,
            self::PROVIDERS,
            self::SEARCH_CONSOLE,
            self::ANALYTICS,
            self::WOOCOMMERCE,
            self::COMMERCE,
            self::KEYWORDS,
            self::SERP,
            self::RANKINGS,
            self::BACKLINKS,
            self::CONTENT,
            self::AI,
            self::RECOMMENDATIONS,
            self::SECURITY,
            self::PERFORMANCE,
        ];
    }
}
