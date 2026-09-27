<?php
/**
 * The dashboard reads stored rows and does not invent amounts.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Modules\Alerts\Infrastructure\AlertRepository;
use QueryNova\Modules\Audit\Infrastructure\AuditRepository;
use QueryNova\Modules\Core\DashboardBriefing;
use QueryNova\Modules\Opportunities\Infrastructure\RecommendationRepository;

final class DashboardBriefingTest extends TestCase {

    public function testEmptyStoresStayEmpty(): void {
        $briefing = ( new DashboardBriefing() )->fromDatabase( new ArrayDatabase() );

        self::assertSame( [], $briefing['actions'] );
        foreach ( array_keys( DashboardBriefing::emptySections() ) as $section ) {
            self::assertSame( [], $briefing['sections'][ $section ], $section );
        }
    }

    public function testStoredRowsKeepEstimatesAndOmitAdvancedFields(): void {
        $database = new ArrayDatabase();
        ( new RecommendationRepository( $database ) )->save(
            [
                [
                    'title'        => 'Close the revenue gap',
                    'rationale'    => 'Business value was high.',
                    'impact'       => 'estimated',
                    'confidence'   => 'LOW',
                    'priority'     => 'high',
                    'data_sources' => [ 'commerce', 'search' ],
                    'evidence'     => [ 'secret-evidence-token' ],
                ],
                [
                    'title'        => 'Improve the ranking page',
                    'impact'       => 'measured',
                    'priority'     => 'low',
                    'data_sources' => [ 'search' ],
                ],
            ]
        );
        ( new AlertRepository( $database ) )->store(
            'home',
            [
                [
                    'type'     => 'schema_failure',
                    'severity' => 'medium',
                    'title'    => 'Schema failure',
                    'message'  => 'A schema failure was supplied.',
                ],
                [
                    'type'     => 'revenue_loss',
                    'severity' => 'high',
                    'title'    => 'Revenue loss',
                    'message'  => 'The supplied current revenue is lower.',
                ],
                [
                    'type'     => 'ai_visibility_decline',
                    'severity' => 'medium',
                    'title'    => 'AI visibility decline',
                    'message'  => 'This is not an official provider ranking.',
                ],
                [
                    'type'     => 'competitor_movement',
                    'severity' => 'low',
                    'title'    => 'Competitor movement',
                    'message'  => 'Competitor movement was supplied.',
                ],
            ]
        );
        ( new AuditRepository( $database ) )->record( 4, 'seo_changed', 'post', 12, [ 'title' => 'secret-before' ], [ 'title' => 'after' ], '127.0.0.1', 'production' );
        ( new AuditRepository( $database ) )->record( 4, 'dropped_table', 'post', 12, null, null, '', 'production' );

        $briefing = ( new DashboardBriefing() )->fromDatabase( $database );
        $encoded  = (string) wp_json_encode( $briefing );

        self::assertSame( 'Close the revenue gap', $briefing['sections']['revenue'][0]['title'] );
        self::assertSame( 'ESTIMATED', $briefing['sections']['revenue'][0]['metric']['kind'] );
        self::assertNull( $briefing['sections']['revenue'][0]['metric']['value'] );
        self::assertSame( 'Improve the ranking page', $briefing['sections']['search'][0]['title'] );
        self::assertNull( $briefing['sections']['search'][0]['metric'] );
        self::assertSame( 'Schema failure', $briefing['sections']['technical'][0]['title'] );
        self::assertSame( 'Revenue loss', $briefing['sections']['commerce'][0]['title'] );
        self::assertSame( 'AI visibility decline', $briefing['sections']['ai'][0]['title'] );
        self::assertSame( 'SEO change', $briefing['sections']['recent'][0]['title'] );
        self::assertSame( 'post 12', $briefing['sections']['recent'][0]['summary'] );
        self::assertSame( 'ESTIMATED', $briefing['actions'][0]['provenance'] );
        self::assertNotSame( 'MEASURED', $briefing['actions'][0]['provenance'] );
        self::assertStringNotContainsString( 'secret-evidence-token', $encoded );
        self::assertStringNotContainsString( 'secret-before', $encoded );
        self::assertStringNotContainsString( '127.0.0.1', $encoded );
        self::assertStringNotContainsString( 'Competitor movement', $encoded );
        self::assertStringNotContainsString( 'dropped_table', $encoded );
        self::assertStringNotContainsString( 'MEASURED', $encoded );
    }

    public function testSectionsStopAfterFiveItems(): void {
        $rows = [];
        for ( $index = 1; $index <= 6; $index++ ) {
            $rows[] = [
                'title'        => 'Revenue ' . $index,
                'data_sources' => [ 'commerce' ],
                'impact'       => 'unavailable',
            ];
        }
        $briefing = ( new DashboardBriefing() )->compose( $rows, [], [] );

        self::assertCount( 5, $briefing['sections']['revenue'] );
        self::assertSame( [], $briefing['sections']['technical'] );
    }
}
