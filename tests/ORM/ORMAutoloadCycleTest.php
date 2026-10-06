<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\Tests\ORM;

use Gobl\Tests\BaseTestCase;

/**
 * A framework may declare an ORM namespace lazily, when its first class is asked for, and attach
 * the CRUD listeners of its tables then: while that first class is still loading. A generated class
 * that loaded another to be constructed (a constructor reading the entity's table name) closed a
 * cycle back to the class being loaded (the entity's `qb()` returns the query class), which PHP
 * reports as a compile error no handler can catch. Each case runs in a process of its own.
 *
 * @covers \Gobl\ORM\Generators\CSGeneratorORM
 *
 * @internal
 */
final class ORMAutoloadCycleTest extends BaseTestCase
{
	private static string $dir;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$dir = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'autoload-cycle';

		self::rmDirRecursive(self::$dir);
		\mkdir(self::$dir . \DIRECTORY_SEPARATOR . 'Base', 0o755, true);

		[$code, $output] = self::runFixture('generate');

		self::assertSame(0, $code, $output);
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provideFirstClassCases(): iterable
	{
		foreach (['query', 'entity', 'crud', 'controller', 'results'] as $first) {
			yield $first . ', listening by table' => [$first, 'producer'];
		}

		// The generated CRUD class loads no other: only itself cannot be the one loading.
		foreach (['query', 'entity', 'controller', 'results'] as $first) {
			yield $first . ', listening through the CRUD class' => [$first, 'generated'];
		}
	}

	/**
	 * @dataProvider provideFirstClassCases
	 */
	public function testAnyGeneratedClassMayBeTheFirstOneLoaded(string $first, string $listen): void
	{
		[$code, $output] = self::runFixture('use', $first, $listen);

		self::assertSame([0, 'ok'], [$code, $output]);
	}

	public function testTheGeneratedCrudClassCannotListenWhileItIsTheOneLoading(): void
	{
		[$code, $output] = self::runFixture('use', 'crud', 'generated');

		self::assertSame(255, $code);
		self::assertStringContainsString('Class "Gobl\\Tests\\Cycle\\Db\\CycleEventsCrud" not found', $output);
	}

	/**
	 * @return array{int, string}
	 */
	private static function runFixture(string $mode, string $first = '', string $listen = 'producer'): array
	{
		$script  = \dirname(__DIR__) . '/Fixtures/orm_autoload_cycle.php';
		$command = \sprintf(
			'%s %s %s %s %s %s 2>&1',
			\escapeshellarg(\PHP_BINARY),
			\escapeshellarg($script),
			$mode,
			\escapeshellarg(self::$dir),
			'' === $first ? '' : \escapeshellarg($first),
			'' === $first ? '' : \escapeshellarg($listen)
		);

		\exec($command, $lines, $code);

		return [$code, \trim(\implode("\n", $lines))];
	}
}
