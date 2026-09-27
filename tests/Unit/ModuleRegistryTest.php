<?php
/**
 * Module registry tests.
 *
 * @package QueryNova
 */

declare(strict_types=1);

namespace QueryNova\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QueryNova\Core\Modules\AbstractModule;
use QueryNova\Core\Modules\CircularDependencyException;
use QueryNova\Core\Modules\ModuleRegistry;

final class ModuleRegistryTest extends TestCase {

    public function testBootOrderRespectsDependencies(): void {
        $registry = new ModuleRegistry();
        $registry->add(
            new class() extends AbstractModule {
				public function getName(): string {
					return 'seo';
				}
			}
        );
        $registry->add(
            new class() extends AbstractModule {
				public function getName(): string {
					return 'core';
				}

				public function getDependencies(): array {
					return [];
				}
			}
        );

        $names = array_map( static fn ( $module ) => $module->getName(), $registry->bootOrder() );
        self::assertSame( [ 'core', 'seo' ], $names );
    }

    public function testCircularDependencyIsRejected(): void {
        $registry = new ModuleRegistry();
        $registry->add(
            new class() extends AbstractModule {
				public function getName(): string {
					return 'a';
				}

				public function getDependencies(): array {
					return [ 'b' ];
				}
			}
        );
        $registry->add(
            new class() extends AbstractModule {
				public function getName(): string {
					return 'b';
				}

				public function getDependencies(): array {
					return [ 'a' ];
				}
			}
        );

        $this->expectException( CircularDependencyException::class );
        $registry->bootOrder();
    }
}
