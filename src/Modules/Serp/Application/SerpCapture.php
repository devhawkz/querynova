<?php
/**
 * Stores one provider snapshot. The provider is called here, never on a public page view.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Serp\Application;

use QueryNova\Modules\Serp\Domain\SerpHit;
use QueryNova\Modules\Serp\Domain\SerpProvider;
use QueryNova\Modules\Serp\Domain\SerpQuery;
use QueryNova\Modules\Serp\Infrastructure\SerpRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SerpCapture {

    public function __construct(
        private readonly SerpRepository $snapshots,
        private readonly PageTypeClassifier $types = new PageTypeClassifier(),
        private readonly SerpStability $stability = new SerpStability(),
        private readonly CompetitorMatrix $matrix = new CompetitorMatrix(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function capture( SerpQuery $query, SerpProvider $provider ): array {
        $payload = $provider->snapshot( $query );
        if ( $payload === null ) {
            $id = $this->snapshots->saveSnapshot( $query, $provider->id(), 'unavailable', null, null, [] );

            return [
                'snapshot_id' => $id,
                'status'      => 'unavailable',
                'results'     => null,
                'stability'   => null,
                'rank'        => null,
            ];
        }
        $hits = [];
        $kept = 0;
        foreach ( $payload->hits as $hit ) {
            if ( $kept >= $query->depth ) {
                break;
            }
            $type   = $hit->pageType !== '' ? $hit->pageType : $this->types->classify( $hit->url, $hit->title );
            $hits[] = new SerpHit( $hit->position, $hit->url, $hit->domain, $hit->title, $hit->snippet, $type, $hit->features );
            ++$kept;
        }
        $previous  = $this->snapshots->previousUrls( $query );
        $stability = $this->stability->compare( $previous, $this->urls( $hits ) );
        $id        = $this->snapshots->saveSnapshot( $query, $provider->id(), 'ok', $payload->features, $stability, $hits );
        $rank      = $this->matrix->oursPosition( $hits, $query->domain );
        $this->snapshots->saveRank( $query, $provider->id(), $rank['position'], $rank['url'] );
        $this->snapshots->saveCompetitors( $query, $provider->id(), $this->matrix->competitors( $hits, $query->domain ) );

        return [
            'snapshot_id' => $id,
            'status'      => 'ok',
            'results'     => count( $hits ),
            'stability'   => $stability,
            'rank'        => $rank['position'],
            'matrix'      => $this->matrix->compare( $hits, $query->domain ),
        ];
    }

    /**
     * @param list<SerpHit> $hits
     * @return list<string>
     */
    private function urls( array $hits ): array {
        $urls = [];
        foreach ( $hits as $hit ) {
            $urls[] = $hit->url;
        }

        return $urls;
    }
}
