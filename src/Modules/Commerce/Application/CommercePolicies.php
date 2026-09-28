<?php
/**
 * Commerce decisions that are not applied automatically.
 *
 * Classifications are QueryNova labels. They are not Google or OpenAI scores.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Commerce\Application;

use QueryNova\Core\Domain\ConfidenceBand;
use QueryNova\Modules\Commerce\Domain\CatalogProduct;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CommercePolicies {

    /**
     * @return array{label: string, confidence: string, note: string}
     */
    public function intent( CatalogProduct $product ): array {
        $name  = strtolower( $product->name );
        $label = 'exact_product';
        if ( str_contains( $name, ' vs ' ) || str_contains( $name, 'comparison' ) ) {
            $label = 'comparison';
        } elseif ( str_contains( $name, 'spare' ) ) {
            $label = 'spare_part';
        } elseif ( str_contains( $name, 'accessor' ) ) {
            $label = 'accessory';
        } elseif ( str_contains( $name, 'replacement' ) ) {
            $label = 'replacement';
        } elseif ( $product->brand !== '' && preg_match( '/\d/', $name ) === 1 ) {
            $label = 'brand_model';
        } elseif ( $product->variationIds !== [] ) {
            $label = 'product_family';
        }
        $confidence = $product->brand !== '' && $product->sku !== '' ? ConfidenceBand::Medium : ConfidenceBand::Low;

        return [
            'label'      => $label,
            'confidence' => $confidence->value,
            'note'       => 'QueryNova classification from the product name. Not a Google intent label.',
        ];
    }

    /**
     * @return array{index_separately: bool, landing: bool, reason: string}
     */
    public function variation( CatalogProduct $product, ?int $searchDemand ): array {
        if ( $product->parentId === 0 && $product->variationIds === [] ) {
            return [
                'index_separately' => false,
                'landing'          => false,
                'reason'           => 'This is not a variation.',
            ];
        }
        if ( $searchDemand === null ) {
            return [
                'index_separately' => false,
                'landing'          => false,
                'reason'           => 'Search demand is unavailable, so the variation stays out of the index.',
            ];
        }

        return [
            'index_separately' => $searchDemand >= 10,
            'landing'          => $searchDemand >= 10,
            'reason'           => 'Measured search demand is ' . $searchDemand . '.',
        ];
    }

    /**
     * @return array{auto_noindex: bool, action: string, return_probability: null, traffic: null, backlinks: null, revenue: null, note: string}
     */
    public function outOfStock( CatalogProduct $product ): array {
        $out = $product->availability === 'outofstock';

        return [
            'auto_noindex'       => false,
            'action'             => $out ? 'review' : 'not_applicable',
            'return_probability' => null,
            'traffic'            => null,
            'backlinks'          => null,
            'revenue'            => null,
            'note'               => $out ? 'Out-of-stock pages stay indexable until measured demand says otherwise.' : 'The product is not out of stock.',
        ];
    }

    /**
     * @return array{suggested: string, confidence: string, options: list<string>, applied: bool, reason: string}
     */
    public function discontinued( CatalogProduct $product, ?int $demand ): array {
        $options = [ 'keep', 'replacement', '301', '410' ];
        if ( ! $product->discontinued ) {
            return [
                'suggested'  => 'keep',
                'confidence' => ConfidenceBand::Unknown->value,
                'options'    => $options,
                'applied'    => false,
                'reason'     => 'The product is not marked discontinued.',
            ];
        }
        if ( $demand === null ) {
            return [
                'suggested'  => 'keep',
                'confidence' => ConfidenceBand::Low->value,
                'options'    => $options,
                'applied'    => false,
                'reason'     => 'Traffic and backlinks are unavailable, so QueryNova does not remove the URL.',
            ];
        }
        if ( $demand === 0 ) {
            return [
                'suggested'  => '410',
                'confidence' => ConfidenceBand::Low->value,
                'options'    => $options,
                'applied'    => false,
                'reason'     => 'Measured demand is zero. This is a suggestion, not a redirect or removal.',
            ];
        }

        return [
            'suggested'  => 'keep',
            'confidence' => ConfidenceBand::Medium->value,
            'options'    => $options,
            'applied'    => false,
            'reason'     => 'Measured demand is ' . $demand . '.',
        ];
    }

    /**
     * @return array{choice: string, reasons: list<string>}
     */
    public function facet( string $attribute, int $productCount, ?int $searchDemand, bool $distinctIntent, bool $hasContent, ?bool $inventoryStable ): array {
        $reasons = [];
        if ( $searchDemand === null || $searchDemand <= 0 ) {
            $reasons[] = $searchDemand === null ? 'Search demand is unavailable.' : 'Search demand is zero.';
        }
        if ( $productCount < 3 ) {
            $reasons[] = 'Fewer than 3 products.';
        }
        if ( ! $distinctIntent ) {
            $reasons[] = 'Distinct intent was not established.';
        }
        if ( ! $hasContent ) {
            $reasons[] = 'The facet has no useful content.';
        }
        if ( $attribute === '' ) {
            $reasons[] = 'The attribute name is empty.';
        }
        if ( $inventoryStable !== true ) {
            $reasons[] = $inventoryStable === null ? 'Inventory stability is unavailable.' : 'Inventory is not stable.';
        }

        return [
            'choice'  => $reasons === [] ? 'indexable_landing' : 'noindex',
            'reasons' => $reasons,
        ];
    }

    /**
     * Facet combinations that can trap a crawler. This does not crawl or rewrite URLs.
     *
     * @return array{status: string, indexable: int|null, applied: bool, note: string}
     */
    public function crawlTrap( int $facetCount, ?int $combinationCount, ?int $indexableCount ): array {
        if ( $combinationCount === null ) {
            return [
                'status'    => 'unavailable',
                'indexable' => $indexableCount,
                'applied'   => false,
                'note'      => 'Combination count was not measured. QueryNova did not crawl facet URLs.',
            ];
        }
        $trap = $combinationCount >= 50 || ( $facetCount > 0 && $combinationCount > $facetCount * 8 );

        return [
            'status'    => $trap ? 'crawl_trap' : 'clear',
            'indexable' => $indexableCount,
            'applied'   => false,
            'note'      => $trap
                ? 'These facet combinations can trap a crawler. Nothing was noindexed and no URL was rewritten.'
                : 'The supplied facet counts do not show a crawl trap. Nothing was changed.',
        ];
    }

    /**
     * @return array{path: string, title: string, description: string, h1: string, status: string}|null
     */
    public function landing( string $basePath, string $value, bool $indexable ): ?array {
        if ( ! $indexable ) {
            return null;
        }
        $slug = strtolower( trim( $value ) );
        $slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
        $slug = is_string( $slug ) ? trim( $slug, '-' ) : '';
        if ( $slug === '' ) {
            return null;
        }

        return [
            'path'        => rtrim( $basePath, '/' ) . '/' . $slug . '/',
            'title'       => $value,
            'description' => 'Draft description for ' . $value . '.',
            'h1'          => $value,
            'status'      => 'suggestion',
        ];
    }

    /**
     * @return array{code: string, confidence: string, note: string}|null
     */
    public function pageTypeMismatch( string $dominantSerp, string $pageKind ): ?array {
        if ( $dominantSerp === '' || $pageKind === '' ) {
            return null;
        }
        if ( $dominantSerp === 'category' && $pageKind === 'article' ) {
            return [
                'code'       => 'page_type_mismatch',
                'confidence' => ConfidenceBand::Medium->value,
                'note'       => 'The supplied dominant result type is a category page and this URL is an article. The SERP type was not measured by QueryNova.',
            ];
        }

        return null;
    }

    /**
     * @param list<string> $keywords
     * @param list<string> $brands
     * @return array<string, list<string>>
     */
    public function clusters( array $keywords, array $brands ): array {
        $groups = [
            'primary'       => [],
            'commercial'    => [],
            'transactional' => [],
            'brand'         => [],
            'attribute'     => [],
            'use_case'      => [],
        ];
        foreach ( $keywords as $keyword ) {
            $text    = strtolower( $keyword );
            $matched = false;
            if ( $this->containsAny( $text, [ 'best', 'review', 'comparison', ' vs ' ] ) ) {
                $groups['commercial'][] = $keyword;
                $matched                = true;
            }
            if ( $this->containsAny( $text, [ 'buy', 'price', 'cheap', 'deal', 'order' ] ) ) {
                $groups['transactional'][] = $keyword;
                $matched                   = true;
            }
            if ( $this->containsBrand( $text, $brands ) ) {
                $groups['brand'][] = $keyword;
                $matched           = true;
            }
            if ( $this->containsAny( $text, [ 'color', 'size', 'power', 'amperage', 'material' ] ) ) {
                $groups['attribute'][] = $keyword;
                $matched               = true;
            }
            if ( $this->containsAny( $text, [ ' for ', ' with ', 'how to' ] ) ) {
                $groups['use_case'][] = $keyword;
                $matched              = true;
            }
            if ( ! $matched ) {
                $groups['primary'][] = $keyword;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, int|float|null> $metrics
     * @return array{status: string, missing: list<string>, note: string}
     */
    public function opportunity( array $metrics ): array {
        $missing = [];
        foreach ( $metrics as $key => $value ) {
            if ( $value === null ) {
                $missing[] = $key;
            }
        }
        if ( $missing !== [] ) {
            return [
                'status'  => 'unavailable',
                'missing' => $missing,
                'note'    => 'Opportunity is unavailable until every input is measured. Missing values are not treated as zero.',
            ];
        }
        $impressions = $metrics['impressions'] ?? null;
        $revenue     = $metrics['revenue'] ?? null;
        $note        = 'All supplied inputs are present. QueryNova does not turn them into a ranking score.';
        if ( is_numeric( $impressions ) && (float) $impressions > 0 && is_numeric( $revenue ) && (float) $revenue === 0.0 ) {
            $note = 'Measured impressions with no measured revenue. This is not a ranking score.';
        }

        return [
            'status'  => 'measured',
            'missing' => [],
            'note'    => $note,
        ];
    }

    /**
     * @param list<string> $bodies
     * @return array{status: string, questions: list<string>|null, repeated: list<string>|null}
     */
    public function reviews( array $bodies ): array {
        if ( $bodies === [] ) {
            return [
                'status'    => 'unavailable',
                'questions' => null,
                'repeated'  => null,
            ];
        }
        $questions = [];
        $counts    = [];
        foreach ( $bodies as $body ) {
            if ( str_contains( $body, '?' ) ) {
                $questions[] = $body;
            }
            $words = preg_split( '/[^a-z0-9]+/i', strtolower( $body ) );
            if ( ! is_array( $words ) ) {
                continue;
            }
            foreach ( $words as $word ) {
                if ( strlen( $word ) < 5 ) {
                    continue;
                }
                $counts[ $word ] = ( $counts[ $word ] ?? 0 ) + 1;
            }
        }
        $repeated = [];
        foreach ( $counts as $word => $count ) {
            if ( $count >= 2 ) {
                $repeated[] = (string) $word;
            }
        }

        return [
            'status'    => 'measured',
            'questions' => $questions,
            'repeated'  => $repeated,
        ];
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny( string $haystack, array $needles ): bool {
        foreach ( $needles as $needle ) {
            if ( str_contains( $haystack, $needle ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $brands
     */
    private function containsBrand( string $keyword, array $brands ): bool {
        foreach ( $brands as $brand ) {
            $brand = strtolower( trim( $brand ) );
            if ( $brand !== '' && str_contains( $keyword, $brand ) ) {
                return true;
            }
        }

        return false;
    }
}
