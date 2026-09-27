<?php
/**
 * Job idempotency and retry tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Exceptions\ValidationException;
use QueryNova\Core\Logging\CorrelationContext;
use QueryNova\Core\Support\FrozenClock;
use QueryNova\Infrastructure\Database\ArrayDatabase;
use QueryNova\Infrastructure\Queue\JobRegistrar;
use QueryNova\Infrastructure\Queue\JobRepository;
use QueryNova\Infrastructure\Queue\JobRunner;
use QueryNova\Infrastructure\Queue\JobStatus;
use QueryNova\Infrastructure\Queue\RetryPolicy;

final class JobRunnerTest extends TestCase {

    public function testDuplicateIdempotencyKeyDoesNotCreateASecondJob(): void {
        $runner = $this->runner( new JobRegistrar() );
        $first  = $runner->enqueue( 'demo', [ 'n' => 1 ], 'same-key' );
        $second = $runner->enqueue( 'demo', [ 'n' => 2 ], 'same-key' );

        self::assertSame( $first, $second );
    }

    public function testValidationFailureIsNotRetried(): void {
        $registrar = new JobRegistrar();
        $registrar->register(
            'demo',
            static function (): void {
				throw new ValidationException( 'Invalid payload.' );
			}
        );
        $db     = new ArrayDatabase();
        $runner = $this->runner( $registrar, $db );
        $id     = $runner->enqueue( 'demo', [], 'once' );
        $runner->work( $id );

        $job = ( new JobRepository( $db ) )->find( $id );
        self::assertSame( JobStatus::Dead->value, $job['status'] ?? null );
    }

    public function testSuccessfulJobCompletes(): void {
        $registrar = new JobRegistrar();
        $registrar->register(
            'demo',
            static function (): void {
			}
        );
        $db     = new ArrayDatabase();
        $runner = $this->runner( $registrar, $db );
        $id     = $runner->enqueue( 'demo', [], 'ok' );
        $runner->work( $id );

        $job = ( new JobRepository( $db ) )->find( $id );
        self::assertSame( JobStatus::Completed->value, $job['status'] ?? null );
    }

    private function runner( JobRegistrar $registrar, ?ArrayDatabase $db = null ): JobRunner {
        $db = $db ?? new ArrayDatabase();

        return new JobRunner(
            new JobRepository( $db ),
            $registrar,
            new RetryPolicy(),
            new FrozenClock( new \DateTimeImmutable( '2026-09-27 12:00:00', new \DateTimeZone( 'UTC' ) ) ),
            CorrelationContext::fresh(),
        );
    }
}
