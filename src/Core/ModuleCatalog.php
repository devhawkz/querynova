<?php
/**
 * Modules loaded for this request. Safe mode drops optional modules.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Core;

use QueryNova\Core\Contracts\ModuleInterface;
use QueryNova\Modules\Ai\AiModule;
use QueryNova\Modules\Alerts\AlertModule;
use QueryNova\Modules\Analytics\AnalyticsModule;
use QueryNova\Modules\Audit\AuditModule;
use QueryNova\Modules\Backlinks\BacklinkModule;
use QueryNova\Modules\Commerce\CommerceModule;
use QueryNova\Modules\Content\ContentModule;
use QueryNova\Modules\Edd\EddModule;
use QueryNova\Modules\Experience\ExperienceModule;
use QueryNova\Modules\Experiments\ExperimentModule;
use QueryNova\Modules\Core\CoreModule;
use QueryNova\Modules\Keywords\KeywordModule;
use QueryNova\Modules\Opportunities\OpportunityModule;
use QueryNova\Modules\Podcast\PodcastGate;
use QueryNova\Modules\Podcast\PodcastModule;
use QueryNova\Modules\Opportunities\OutcomeModule;
use QueryNova\Modules\Crawler\CrawlerModule;
use QueryNova\Modules\Redirects\RedirectModule;
use QueryNova\Modules\Reports\ReportModule;
use QueryNova\Modules\Schema\SchemaModule;
use QueryNova\Modules\Seo\SeoModule;
use QueryNova\Modules\Serp\SerpModule;
use QueryNova\Modules\Sitemap\SitemapModule;

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
        $modules[] = new SitemapModule();
        $modules[] = new SchemaModule();
        $modules[] = new RedirectModule();
        $modules[] = new CrawlerModule();
        $modules[] = new CommerceModule();
        if ( EddModule::present() ) {
            $modules[] = new EddModule();
        }
        if ( PodcastGate::enabled() ) {
            $modules[] = new PodcastModule();
        }
        $modules[] = new KeywordModule();
        $modules[] = new SerpModule();
        $modules[] = new BacklinkModule();
        $modules[] = new ContentModule();
        $modules[] = new AnalyticsModule();
        $modules[] = new OpportunityModule();
        $modules[] = new OutcomeModule();
        $modules[] = new AiModule();
        $modules[] = new ExperienceModule();
        $modules[] = new ExperimentModule();
        $modules[] = new AuditModule();
        $modules[] = new AlertModule();
        $modules[] = new ReportModule();

        return $modules;
    }
}
