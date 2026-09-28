<?php
/**
 * One link or keyword rule. The default is Suggest Only, and nothing is inserted.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Modules\Content\Application;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ContentAutomation {

    public const OPTION = 'querynova_content_link_rule';

    /**
     * @var list<string>
     */
    public const RULES = [ 'internal_links', 'keyword_links' ];

    /**
     * @return array{rules: list<array{rule: string, mode: string, applied: bool, inserted: bool, page_changed: bool}>, note: string}
     */
    public static function present(): array {
        $choice = self::choice();
        $rules  = [];
        foreach ( self::RULES as $name ) {
            $rules[] = self::row( $name, $choice['enabled'] && $choice['rule'] === $name );
        }

        return [
            'rules' => $rules,
            'note'  => $choice['enabled']
                ? 'Automation is on for one rule. No link was inserted and the live page was not changed.'
                : 'Suggestions stay Suggest Only. Nothing is inserted.',
        ];
    }

    /**
     * @return array{rules: list<array{rule: string, mode: string, applied: bool, inserted: bool, page_changed: bool}>, note: string, stored: bool}
     */
    public static function apply( string $rule, bool $enabled ): array {
        $rule = in_array( $rule, self::RULES, true ) ? $rule : '';
        if ( ! $enabled || $rule === '' ) {
            update_option(
                self::OPTION,
                [
                    'rule'    => '',
                    'enabled' => false,
                ],
                false
            );
        } else {
            update_option(
                self::OPTION,
                [
                    'rule'    => $rule,
                    'enabled' => true,
                ],
                false
            );
        }
        $board           = self::present();
        $board['stored'] = true;

        return $board;
    }

    /**
     * @return array{rule: string, enabled: bool}
     */
    private static function choice(): array {
        $stored = get_option( self::OPTION, [] );
        if ( ! is_array( $stored ) ) {
            return [
                'rule'    => '',
                'enabled' => false,
            ];
        }
        $rule = is_string( $stored['rule'] ?? null ) ? $stored['rule'] : '';
        if ( ! in_array( $rule, self::RULES, true ) || ( $stored['enabled'] ?? false ) !== true ) {
            return [
                'rule'    => '',
                'enabled' => false,
            ];
        }

        return [
            'rule'    => $rule,
            'enabled' => true,
        ];
    }

    /**
     * @return array{rule: string, mode: string, applied: bool, inserted: bool, page_changed: bool}
     */
    private static function row( string $rule, bool $automated ): array {
        return [
            'rule'         => $rule,
            'mode'         => $automated ? 'Automated' : 'Suggest Only',
            'applied'      => false,
            'inserted'     => false,
            'page_changed' => false,
        ];
    }
}
