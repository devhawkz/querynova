<?php
/**
 * REST routes require a capability. A different capability does not grant access.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Security\Capability;
use QueryNova\Core\Security\RoleMap;
use QueryNova\Infrastructure\Rest\RestRegistrar;
use QueryNova\Modules\Ai\AiModule;
use QueryNova\Modules\Alerts\AlertModule;
use QueryNova\Modules\Analytics\AnalyticsModule;
use QueryNova\Modules\Audit\AuditModule;
use QueryNova\Modules\Backlinks\BacklinkModule;
use QueryNova\Modules\Commerce\CommerceModule;
use QueryNova\Modules\Content\ContentModule;
use QueryNova\Modules\Core\CoreModule;
use QueryNova\Modules\Crawler\CrawlerModule;
use QueryNova\Modules\Experiments\ExperimentModule;
use QueryNova\Modules\Experience\ExperienceModule;
use QueryNova\Modules\Keywords\KeywordModule;
use QueryNova\Modules\Opportunities\OpportunityModule;
use QueryNova\Modules\Opportunities\OutcomeModule;
use QueryNova\Modules\Redirects\RedirectModule;
use QueryNova\Modules\Reports\ReportModule;
use QueryNova\Modules\Schema\SchemaModule;
use QueryNova\Modules\Seo\SeoModule;
use QueryNova\Modules\Serp\SerpModule;

final class RestPermissionTest extends TestCase {

    public function testACapabilityDoesNotGrantAnother(): void {
        $rest               = new RestRegistrar();
        $GLOBALS['qn_caps'] = []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        self::assertFalse( $rest->allowed( '' ) );
        self::assertFalse( $rest->allowed( Capability::MANAGE_SETTINGS ) );

        $GLOBALS['qn_caps'] = [ Capability::MANAGE_SEO ]; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- test bootstrap stores capabilities under this key.
        self::assertTrue( $rest->allowed( Capability::MANAGE_SEO ) );
        self::assertFalse( $rest->allowed( Capability::MANAGE_DEBUG ) );
        self::assertFalse( $rest->allowed( Capability::MANAGE_SETTINGS ) );
    }

    public function testNarrowRolesDoNotReceiveBroaderCapabilities(): void {
        $grants = ( new RoleMap() )->grants();

        self::assertNotContains( Capability::MANAGE_SETTINGS, $grants['querynova_content_editor'] );
        self::assertNotContains( Capability::MANAGE_DEBUG, $grants['querynova_content_editor'] );
        self::assertNotContains( Capability::MANAGE_SEO, $grants['querynova_developer'] );
        self::assertNotContains( Capability::MANAGE_SETTINGS, $grants['querynova_commerce_manager'] );
    }

    public function testRegisteredRoutesNameARealCapability(): void {
        $rest    = new RestRegistrar();
        $modules = [
            new CoreModule(),
            new SeoModule(),
            new SchemaModule(),
            new RedirectModule(),
            new CrawlerModule(),
            new CommerceModule(),
            new AnalyticsModule(),
            new KeywordModule(),
            new SerpModule(),
            new BacklinkModule(),
            new ContentModule(),
            new OpportunityModule(),
            new OutcomeModule(),
            new AiModule(),
            new ExperienceModule(),
            new ExperimentModule(),
            new AuditModule(),
            new AlertModule(),
            new ReportModule(),
        ];
        foreach ( $modules as $module ) {
            $module->registerRoutes( $rest );
        }

        self::assertNotEmpty( $rest->routes() );
        foreach ( $rest->routes() as $route ) {
            self::assertContains( $route['capability'], Capability::all() );
            self::assertNotSame( '', $route['capability'] );
        }
    }
}
