<?php
/**
 * Provenance tests. Estimates must not be presented as measurements.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Domain\ConfidenceBand;
use QueryNova\Core\Domain\DataSource;
use QueryNova\Core\Domain\MetricValue;
use QueryNova\Core\Domain\ProvenanceKind;

final class MetricValueTest extends TestCase {

    public function testEstimatedValueIsLabeledEstimated(): void {
        $metric = new MetricValue(
            120.5,
            ProvenanceKind::Estimated,
            DataSource::QueryNovaHeuristic,
            null,
            'RevenueAttribution',
            'v1',
            ConfidenceBand::Low,
            new \DateTimeImmutable( '2026-09-27 00:00:00', new \DateTimeZone( 'UTC' ) ),
        );

        self::assertSame( 'Estimated', $metric->displayLabel() );
        self::assertFalse( $metric->isMeasured() );
        self::assertSame( 'ESTIMATED', $metric->toArray()['kind'] );
    }

    public function testUnavailableRejectsANumericValue(): void {
        $this->expectException( \InvalidArgumentException::class );
        new MetricValue(
            0,
            ProvenanceKind::Unavailable,
            DataSource::BacklinkProvider,
            null,
            null,
            null,
            ConfidenceBand::Unknown,
            new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ),
        );
    }
}
