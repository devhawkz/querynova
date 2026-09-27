<?php
/**
 * Log redaction tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Logging\LogSanitizer;

final class LogSanitizerTest extends TestCase {

    public function testSecretsAndPersonalDataAreRedacted(): void {
        $clean = ( new LogSanitizer() )->sanitize(
            [
				'api_key'       => 'sk-live-secret',
				'authorization' => 'Bearer abc.def.ghi',
				'email'         => 'person@example.com',
				'filename'      => 'product-photo.webp',
				'nested'        => [
					'refresh_token' => 'token-value',
					'sku'           => 'MIG-200',
				],
				'note'          => 'Contact person@example.com or use api_key=abcd',
			]
        );

        self::assertSame( '[redacted]', $clean['api_key'] );
        self::assertSame( '[redacted]', $clean['authorization'] );
        self::assertSame( '[redacted]', $clean['email'] );
        self::assertSame( 'product-photo.webp', $clean['filename'] );
        self::assertSame( '[redacted]', $clean['nested']['refresh_token'] );
        self::assertSame( 'MIG-200', $clean['nested']['sku'] );
        self::assertStringNotContainsString( 'person@example.com', (string) $clean['note'] );
        self::assertStringNotContainsString( 'abcd', (string) $clean['note'] );
    }
}
