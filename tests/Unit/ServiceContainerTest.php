<?php
/**
 * Container tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Container\NotFoundException;
use QueryNova\Core\Container\ServiceContainer;

final class ServiceContainerTest extends TestCase {

    public function testSingletonIsCreatedOnce(): void {
        $container = new ServiceContainer();
        $container->singleton( 'clock', static fn (): \stdClass => new \stdClass() );

        self::assertSame( $container->get( 'clock' ), $container->get( 'clock' ) );
    }

    public function testMissingServiceThrows(): void {
        $this->expectException( NotFoundException::class );
        ( new ServiceContainer() )->get( 'missing' );
    }

    public function testCircularServiceDependencyIsRejected(): void {
        $container = new ServiceContainer();
        $container->singleton( 'a', static fn ( ServiceContainer $c ) => $c->get( 'b' ) );
        $container->singleton( 'b', static fn ( ServiceContainer $c ) => $c->get( 'a' ) );

        $this->expectException( \QueryNova\Core\Container\ContainerException::class );
        $container->get( 'a' );
    }
}
