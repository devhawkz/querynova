<?php
/**
 * Keyword research that keeps measured, estimated, and missing values apart.
 *
 * Organic difficulty is QueryNova's average of supplied inputs. It is not a Google score
 * and it is never copied from paid competition.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Keywords\Application;

use QueryNova\Core\Domain\ConfidenceBand;
use QueryNova\Core\Domain\ProvenanceKind;
use QueryNova\Modules\Keywords\Domain\AdsKeywordProvider;
use QueryNova\Modules\Keywords\Domain\KeywordRecord;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class KeywordIntelligence {

    public const DIFFICULTY_METHOD = 'querynova.organic_difficulty';

    public const DIFFICULTY_VERSION = '1';

    /**
     * Equal weight across the nine published inputs. Each input is 0–100.
     * A missing input makes the result unavailable instead of treating it as zero.
     */
    private const DIFFICULTY_INPUTS = [
        'top10_authority',
        'page_strength',
        'referring_domains',
        'serp_stability',
        'brand_dominance',
        'page_type_consistency',
        'content_strength',
        'topical_authority',
        'serp_features',
    ];

    public function __construct( private readonly AdsKeywordProvider $ads ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function explore( KeywordRecord $record ): array {
        $ads = $this->ads->metrics( $record->keyword, $record->country, $record->language );

        return [
            'keyword'            => $record->keyword,
            'volume'             => $this->metric( $ads['volume'] ?? $record->volume, $ads === null ? ProvenanceKind::Unavailable : ProvenanceKind::Measured ),
            'trend'              => $this->metric( $ads['trend'] ?? $record->trend, $ads === null && $record->trend === null ? ProvenanceKind::Unavailable : ProvenanceKind::Measured ),
            'cpc'                => $this->metric( $ads['cpc'] ?? $record->cpc, $ads === null && $record->cpc === null ? ProvenanceKind::Unavailable : ProvenanceKind::Measured ),
            'paid_competition'   => $this->metric( $ads['paid_competition'] ?? $record->paidCompetition, ProvenanceKind::Measured ),
            'organic_difficulty' => $this->metric(
                $record->organicDifficulty,
                $record->organicDifficulty === null ? ProvenanceKind::Unavailable : ProvenanceKind::Estimated
            ),
            'intent'             => $this->intent( $record->keyword ),
            'traffic_potential'  => $this->traffic( [ $record->volume ] ),
            'rank'               => $this->metric( $record->rank, $record->rank === null ? ProvenanceKind::Unavailable : ProvenanceKind::Measured ),
            'ranking_url'        => $record->rankingUrl,
            'business_value'     => null,
            'commerce_potential' => null,
            'opportunity'        => [
                'status' => ProvenanceKind::Unavailable->value,
                'note'   => 'Opportunity needs measured demand, revenue, and difficulty. Missing inputs are not zero.',
            ],
        ];
    }

    /**
     * @param array<string, float|null> $inputs
     * @return array{value: int|null, provenance: string, methodology: string, version: string, missing: list<string>}
     */
    public function difficulty( array $inputs ): array {
        $missing = [];
        $total   = 0.0;
        foreach ( self::DIFFICULTY_INPUTS as $key ) {
            $value = $inputs[ $key ] ?? null;
            if ( $value === null ) {
                $missing[] = $key;
                continue;
            }
            $total += max( 0.0, min( 100.0, $value ) );
        }
        if ( $missing !== [] ) {
            return [
                'value'       => null,
                'provenance'  => ProvenanceKind::Unavailable->value,
                'methodology' => self::DIFFICULTY_METHOD,
                'version'     => self::DIFFICULTY_VERSION,
                'missing'     => $missing,
            ];
        }

        return [
            'value'       => (int) round( $total / count( self::DIFFICULTY_INPUTS ) ),
            'provenance'  => ProvenanceKind::Estimated->value,
            'methodology' => self::DIFFICULTY_METHOD,
            'version'     => self::DIFFICULTY_VERSION,
            'missing'     => [],
        ];
    }

    /**
     * @param list<array{text: string, kind: string}> $seeds
     * @return array<string, list<array{text: string, suggested: bool}>>
     */
    public function discover( array $seeds ): array {
        $groups = [
            'products'       => [],
            'categories'     => [],
            'questions'      => [],
            'problems'       => [],
            'comparisons'    => [],
            'brands'         => [],
            'specifications' => [],
            'use_cases'      => [],
            'locations'      => [],
            'transactional'  => [],
            'commercial'     => [],
            'informational'  => [],
        ];
        foreach ( $seeds as $seed ) {
            $text = trim( $seed['text'] );
            if ( $text === '' ) {
                continue;
            }
            $kind   = $seed['kind'] !== '' ? $seed['kind'] : $this->kind( $text );
            $bucket = $groups[ $kind ] ?? null;
            if ( $bucket === null ) {
                $kind = 'informational';
            }
            $groups[ $kind ][] = [
                'text'      => $text,
                'suggested' => false,
            ];
            if ( $kind === 'products' ) {
                $groups['questions'][]     = [
                    'text'      => 'what is ' . $text,
                    'suggested' => true,
                ];
                $groups['transactional'][] = [
                    'text'      => $text . ' price',
                    'suggested' => true,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param list<array{text: string, intent: string}> $keywords
     * @return list<array{name: string, keywords: list<string>, page: string, confidence: string}>
     */
    public function cluster( array $keywords ): array {
        $groups = [];
        foreach ( $keywords as $keyword ) {
            $token            = $this->token( $keyword['text'] );
            $key              = $token === '' ? strtolower( $keyword['text'] ) : $token;
            $groups[ $key ][] = $keyword;
        }
        $clusters = [];
        foreach ( $groups as $name => $members ) {
            $intents = [];
            $texts   = [];
            foreach ( $members as $member ) {
                $texts[] = $member['text'];
                if ( $member['intent'] !== '' ) {
                    $intents[ $member['intent'] ] = true;
                }
            }
            $page = 'separate_page';
            $only = array_key_first( $intents );
            if ( count( $intents ) === 1 && is_string( $only ) ) {
                $page = match ( $only ) {
                    'transactional' => 'product',
                    'commercial' => 'category',
                    'comparison', 'informational' => 'article',
                    default => 'separate_page',
                };
            } elseif ( count( $members ) > 1 && $intents === [] ) {
                $page = 'same_page';
            }
            $clusters[] = [
                'name'       => (string) $name,
                'keywords'   => $texts,
                'page'       => count( $members ) > 1 && count( $intents ) > 1 ? 'separate_page' : $page,
                'confidence' => $intents === [] ? ConfidenceBand::Low->value : ConfidenceBand::Medium->value,
            ];
        }

        return $clusters;
    }

    /**
     * @param list<array{keyword: string, rank: int|null}> $ours
     * @param list<array{keyword: string, rank: int|null}> $theirs
     * @param list<string>                                 $commerceTerms
     * @param list<string>                                 $seeds
     * @return array<string, list<string>>
     */
    public function gap( array $ours, array $theirs, array $commerceTerms, array $seeds ): array {
        $groups   = [
            'missing'  => [],
            'weak'     => [],
            'strong'   => [],
            'shared'   => [],
            'untapped' => [],
            'commerce' => [],
        ];
        $ourMap   = $this->rankMap( $ours );
        $theirMap = $this->rankMap( $theirs );
        foreach ( $theirMap as $keyword => $theirRank ) {
            if ( ! array_key_exists( $keyword, $ourMap ) ) {
                $groups['missing'][] = $keyword;
                if ( $this->matchesTerm( $keyword, $commerceTerms ) ) {
                    $groups['commerce'][] = $keyword;
                }
                continue;
            }
            $ourRank = $ourMap[ $keyword ];
            if ( $ourRank === null || $theirRank === null || $ourRank === $theirRank ) {
                $groups['shared'][] = $keyword;
                continue;
            }
            $groups[ $ourRank < $theirRank ? 'strong' : 'weak' ][] = $keyword;
        }
        $known = array_fill_keys( array_merge( array_keys( $ourMap ), array_keys( $theirMap ) ), true );
        foreach ( $seeds as $seed ) {
            $key = strtolower( trim( $seed ) );
            if ( $key !== '' && ! isset( $known[ $key ] ) ) {
                $groups['untapped'][] = $key;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, int|float|null> $inputs
     * @return array{status: string, winnable: bool|null, missing: list<string>, note: string}
     */
    public function winnable( array $inputs ): array {
        $required = [ 'demand', 'competition', 'authority', 'position', 'inventory', 'revenue', 'margin', 'content' ];
        $missing  = [];
        foreach ( $required as $key ) {
            if ( ! array_key_exists( $key, $inputs ) || $inputs[ $key ] === null ) {
                $missing[] = $key;
            }
        }
        if ( $missing !== [] ) {
            return [
                'status'   => ProvenanceKind::Unavailable->value,
                'winnable' => null,
                'missing'  => $missing,
                'note'     => 'A keyword is not marked winnable while an input is missing.',
            ];
        }
        $winnable = (float) $inputs['demand'] > 0
            && (float) $inputs['competition'] <= 40
            && (float) $inputs['inventory'] > 0
            && (float) $inputs['content'] > 0;

        return [
            'status'   => ProvenanceKind::Estimated->value,
            'winnable' => $winnable,
            'missing'  => [],
            'note'     => 'QueryNova rule: measured demand, competition at or under 40, stock, and existing content. Not a Google score.',
        ];
    }

    /**
     * @param list<int|null> $volumes
     * @return array{volume: int|null, estimated_traffic: int|null, provenance: string, confidence: string, note: string}
     */
    public function traffic( array $volumes ): array {
        if ( $volumes === [] || in_array( null, $volumes, true ) ) {
            return [
                'volume'            => null,
                'estimated_traffic' => null,
                'provenance'        => ProvenanceKind::Unavailable->value,
                'confidence'        => ConfidenceBand::Unknown->value,
                'note'              => 'Traffic potential is unavailable until every keyword in the cluster has a measured volume.',
            ];
        }
        $volume = 0;
        foreach ( $volumes as $value ) {
            $volume += (int) $value;
        }

        return [
            'volume'            => $volume,
            'estimated_traffic' => (int) round( $volume * 0.25 ),
            'provenance'        => ProvenanceKind::Estimated->value,
            'confidence'        => ConfidenceBand::Medium->value,
            'note'              => 'Estimated with an assumed 25 percent cluster click share. This is not measured traffic.',
        ];
    }

    /**
     * @param list<array{type: string, name: string, url: string}> $content
     * @return array{type: string, url: string}
     */
    public function mapContent( string $keyword, array $content ): array {
        $needle = strtolower( trim( $keyword ) );
        $order  = [ 'product', 'category', 'brand', 'article', 'page' ];
        foreach ( $order as $type ) {
            foreach ( $content as $item ) {
                if ( $item['type'] === $type && $needle !== '' && str_contains( strtolower( $item['name'] ), $needle ) ) {
                    return [
                        'type' => $type,
                        'url'  => $item['url'],
                    ];
                }
            }
        }

        return [
            'type' => 'no_existing_content',
            'url'  => '',
        ];
    }

    /**
     * @param list<array{keyword: string, url: string, type: string}> $assignments
     * @return list<array{keyword: string, urls: list<string>, types: list<string>, confidence: string}>
     */
    public function cannibalization( array $assignments ): array {
        $byKeyword = [];
        foreach ( $assignments as $assignment ) {
            $key = strtolower( trim( $assignment['keyword'] ) );
            if ( $key === '' || $assignment['url'] === '' ) {
                continue;
            }
            $byKeyword[ $key ]['urls'][ $assignment['url'] ]   = $assignment['url'];
            $byKeyword[ $key ]['types'][ $assignment['type'] ] = $assignment['type'];
        }
        $conflicts = [];
        foreach ( $byKeyword as $keyword => $row ) {
            if ( count( $row['urls'] ) < 2 ) {
                continue;
            }
            $conflicts[] = [
                'keyword'    => $keyword,
                'urls'       => array_values( $row['urls'] ),
                'types'      => array_values( $row['types'] ),
                'confidence' => ConfidenceBand::Medium->value,
            ];
        }

        return $conflicts;
    }

    public function withAds( KeywordRecord $record ): KeywordRecord {
        $metrics = $this->ads->metrics( $record->keyword, $record->country, $record->language );
        if ( $metrics === null ) {
            return $record;
        }

        return new KeywordRecord(
            $record->id,
            $record->keyword,
            $record->country,
            $record->language,
            $metrics['volume'],
            $metrics['cpc'],
            $metrics['paid_competition'],
            $record->organicDifficulty,
            $metrics['trend'],
            $record->rank,
            $record->rankingUrl,
            'ads',
            $this->ads->id(),
            $record->methodology
        );
    }

    /**
     * @return array{value: mixed, provenance: string}
     */
    private function metric( mixed $value, ProvenanceKind $kind ): array {
        if ( $value === null ) {
            $kind = ProvenanceKind::Unavailable;
        }

        return [
            'value'      => $value,
            'provenance' => $kind->value,
        ];
    }

    /**
     * @return array{label: string, provenance: string}
     */
    private function intent( string $keyword ): array {
        return [
            'label'      => $this->kind( $keyword ),
            'provenance' => ProvenanceKind::Estimated->value,
        ];
    }

    private function kind( string $text ): string {
        $text = strtolower( $text );
        if ( str_contains( $text, '?' ) || str_starts_with( $text, 'what ' ) || str_starts_with( $text, 'how ' ) ) {
            return 'questions';
        }
        if ( str_contains( $text, ' vs ' ) || str_contains( $text, 'comparison' ) ) {
            return 'comparisons';
        }
        if ( str_contains( $text, 'price' ) || str_contains( $text, 'buy ' ) ) {
            return 'transactional';
        }
        if ( str_contains( $text, 'best ' ) || str_contains( $text, 'review' ) ) {
            return 'commercial';
        }
        if ( str_contains( $text, ' for ' ) ) {
            return 'use_cases';
        }

        return 'informational';
    }

    private function token( string $keyword ): string {
        $parts = preg_split( '/[^a-z0-9]+/i', strtolower( $keyword ) );
        if ( ! is_array( $parts ) ) {
            return '';
        }
        foreach ( $parts as $part ) {
            if ( strlen( $part ) >= 4 ) {
                return $part;
            }
        }

        return '';
    }

    /**
     * @param list<array{keyword: string, rank: int|null}> $rows
     * @return array<string, int|null>
     */
    private function rankMap( array $rows ): array {
        $map = [];
        foreach ( $rows as $row ) {
            $key = strtolower( trim( $row['keyword'] ) );
            if ( $key !== '' ) {
                $map[ $key ] = $row['rank'];
            }
        }

        return $map;
    }

    /**
     * @param list<string> $terms
     */
    private function matchesTerm( string $keyword, array $terms ): bool {
        foreach ( $terms as $term ) {
            $term = strtolower( trim( $term ) );
            if ( $term !== '' && str_contains( $keyword, $term ) ) {
                return true;
            }
        }

        return false;
    }
}
