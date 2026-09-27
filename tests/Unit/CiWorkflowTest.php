<?php
/**
 * The CI workflow contains the checks the specification requires.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CiWorkflowTest extends TestCase {

    public function testWorkflowListsTheRequiredChecks(): void {
        $workflow = (string) file_get_contents( QUERYNOVA_PATH . '.github/workflows/ci.yml' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local workflow file, not a remote request.

        foreach (
            [
                'composer validate --strict',
                'tools: composer',
                'php -l',
                'composer lint',
                'composer analyse',
                'composer test',
                "php: ['8.1', '8.3']",
                'npm run lint',
                'npm run typecheck',
                'npm run test',
                'npm run build',
            ] as $command
        ) {
            self::assertStringContainsString( $command, $workflow );
        }
    }
}
