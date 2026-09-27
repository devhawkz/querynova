<?php
/**
 * Where a metric came from.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core\Domain;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum DataSource: string {

    case WordPress          = 'WordPress';
    case WooCommerce        = 'WOOCOMMERCE';
    case SearchConsole      = 'SEARCH_CONSOLE';
    case GoogleAnalytics    = 'GOOGLE_ANALYTICS';
    case GoogleAds          = 'GOOGLE_ADS';
    case GooglePageSpeed    = 'GOOGLE_PAGESPEED';
    case SerpProvider       = 'SERP_PROVIDER';
    case KeywordProvider    = 'KEYWORD_PROVIDER';
    case BacklinkProvider   = 'BACKLINK_PROVIDER';
    case LlmProvider        = 'LLM_PROVIDER';
    case QueryNovaCrawler   = 'QUERYNOVA_CRAWLER';
    case QueryNovaHeuristic = 'QUERYNOVA_HEURISTIC';
    case QueryNovaCloud     = 'QUERYNOVA_CLOUD';
    case UserInput          = 'USER_INPUT';
}
