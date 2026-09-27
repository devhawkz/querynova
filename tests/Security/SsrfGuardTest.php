<?php
/**
 * SSRF guard tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Security;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Security\SsrfGuard;

final class SsrfGuardTest extends TestCase {

    public function testPrivateAndMetadataAddressesAreBlocked(): void {
        $guard = new SsrfGuard(
            static fn ( string $host ): array => match ( $host ) {
            'public.example' => [ '1.1.1.1' ],
            'internal.example' => [ '10.1.1.5' ],
            'metadata.example' => [ '169.254.169.254' ],
            default => [ $host ],
            }
        );

        $guard->assertSafe( 'https://public.example/page' );

        $this->expectException( ValidationException::class );
        $guard->assertSafe( 'http://169.254.169.254/latest/meta-data' );
    }

    public function testLocalhostAndUnsafeSchemesAreBlocked(): void {
        $guard = new SsrfGuard( static fn (): array => [ '1.1.1.1' ] );

        try {
            $guard->assertSafe( 'http://localhost/admin' );
            self::fail( 'localhost was allowed' );
        } catch ( ValidationException ) {
            self::assertTrue( true );
        }

        $this->expectException( ValidationException::class );
        $guard->assertSafe( 'file:///etc/passwd' );
    }
}
