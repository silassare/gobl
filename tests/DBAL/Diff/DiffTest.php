<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\Tests\DBAL\Diff;

use Gobl\DBAL\Db;
use Gobl\DBAL\Diff\Diff;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\Tests\BaseTestCase;

/**
 * Class DiffTest.
 *
 * @covers \Gobl\DBAL\Diff\Diff
 *
 * @internal
 */
final class DiffTest extends BaseTestCase
{
	public function testDiff(): void
	{
		$db_a = self::getDb();

		$diff = new Diff($db_a, $db_a);

		static::assertSame([], $diff->getDiff());

		$db_b = Db::createInstanceOf($db_a->getType(), $db_a->getConfig());

		$db_b->ns('Test')
			->addTables(self::getTablesDiffDefinitions());

		$diff = new Diff($db_a, $db_b);

		\file_put_contents(GOBL_TEST_OUTPUT . '/diff.out.php', (string) $diff->generateMigrationFile(1));

		/** @var MigrationInterface $expected */
		/** @var MigrationInterface $actual */
		$expected = require GOBL_TEST_ASSETS . '/diff.out.php';
		$actual   = require GOBL_TEST_OUTPUT . '/diff.out.php';

		static::assertInstanceOf(MigrationInterface::class, $actual);

		static::assertSame($expected->getVersion(), $actual->getVersion());
		static::assertSame($expected->up(), $actual->up());
		static::assertSame($expected->down(), $actual->down());
	}
}
