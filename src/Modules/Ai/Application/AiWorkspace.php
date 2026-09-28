<?php
/**
 * AI Visibility screens. Missing citations and mention share stay empty.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Ai\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AiWorkspace {

    /**
     * @param list<array<string, mixed>>                                          $prompts
     * @param list<array{brand: bool, product: bool, cited: bool, commercial: bool, competitors: list<string>}>|null $runs
     * @param list<array{sessions: int|null, orders: int|null, revenue: float|null}>|null $referrals
     * @return array<string, mixed>
     */
    public static function present( string $providerId, array $prompts, ?array $runs, ?array $referrals ): array {
        $visibility = new AiVisibility();
        $connected  = trim( $providerId ) !== '';
        $commerce   = $visibility->commerce( $runs );
        $traffic    = $visibility->referrals( $referrals );
        $index      = $visibility->index( [] );

        return [
            'provider'    => [
                'status'   => $connected ? 'connected' : 'not_connected',
                'provider' => $connected ? trim( $providerId ) : '',
                'called'   => false,
                'note'     => $connected
                    ? 'A provider id is configured. This request did not call the provider.'
                    : 'No AI provider is connected. Citations and mention share stay empty. QueryNova did not call a model.',
            ],
            'overview'    => [
                'status'     => $connected ? 'connected' : 'not_connected',
                'index'      => $index['value'],
                'prompts'    => count( $prompts ),
                'called'     => false,
                'disclaimer' => AiVisibility::DISCLAIMER,
                'note'       => 'Overview uses stored prompts only. The visibility index stays empty until every input is supplied.',
            ],
            'prompts'     => $prompts,
            'mentions'    => self::metric( $runs, $commerce['brand_share'] ?? null, 'Mention share was not measured.' ),
            'citations'   => self::metric( $runs, $commerce['product_citations'] ?? null, 'Citations were not measured.' ),
            'competitors' => self::metric( $runs, $commerce['competitor_presence'] ?? null, 'Competitor presence was not measured.' ),
            'brands'      => self::metric( $runs, $commerce['brand_share'] ?? null, 'Brand share was not measured.' ),
            'products'    => self::metric( $runs, $commerce['product_mentions'] ?? null, 'Product mentions were not measured.' ),
            'sentiment'   => [
                'status' => 'UNAVAILABLE',
                'value'  => null,
                'note'   => 'Sentiment was not measured.',
            ],
            'traffic'     => [
                'status'   => $traffic['status'],
                'sessions' => $traffic['sessions'],
                'note'     => $traffic['note'],
            ],
            'revenue'     => [
                'status'  => $traffic['status'],
                'revenue' => $traffic['revenue'],
                'note'    => $referrals === null ? 'AI revenue was not measured.' : $traffic['note'],
            ],
            'disclaimer'  => AiVisibility::DISCLAIMER,
            'cloud'       => false,
        ];
    }

    /**
     * @param list<array{brand: bool, product: bool, cited: bool, commercial: bool, competitors: list<string>}>|null $runs
     * @return array{status: string, value: mixed, note: string}
     */
    private static function metric( ?array $runs, mixed $value, string $emptyNote ): array {
        if ( $runs === null ) {
            return [
                'status' => 'UNAVAILABLE',
                'value'  => null,
                'note'   => $emptyNote,
            ];
        }

        return [
            'status' => 'MEASURED',
            'value'  => $value,
            'note'   => 'Counted from stored observations. This is not a provider ranking.',
        ];
    }
}
