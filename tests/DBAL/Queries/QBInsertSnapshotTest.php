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

namespace Gobl\Tests\DBAL\Queries;

use Gobl\DBAL\Queries\QBInsert;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBInsertSnapshotTest.
 *
 * Cross-driver snapshot tests for QBInsert SQL generation.
 * Each test runs for MySQL, PostgreSQL, and SQLite via the allDrivers data provider.
 *
 * Snapshots are stored under tests/assets/snapshots/{driver}/qb_insert_{scenario}.txt
 *
 * @covers \Gobl\DBAL\Queries\QBInsert
 *
 * @internal
 */
final class QBInsertSnapshotTest extends BaseTestCase
{
	/**
	 * INSERT single row with all columns.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testInsertSingleRow(string $driver): void
	{
		$db = self::getDb($driver);
		$qb = new QBInsert($db);
		$qb->into('clients')->values([
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'given_name' => 'Johnny',
			'gender'     => 'male',
			'valid'      => true,
		]);

		$this->assertMatchesSnapshot($driver . '/qb_insert_single', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * INSERT multiple rows in one statement.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testInsertMultipleRows(string $driver): void
	{
		$db = self::getDb($driver);
		$qb = new QBInsert($db);
		$qb->into('clients')->values([
			[
				'first_name' => 'Alice',
				'last_name'  => 'Smith',
				'gender'     => 'female',
			],
			[
				'first_name' => 'Bob',
				'last_name'  => 'Jones',
				'gender'     => 'male',
			],
			[
				'first_name' => 'Carol',
				'last_name'  => 'White',
				'gender'     => 'female',
			],
		]);

		$this->assertMatchesSnapshot($driver . '/qb_insert_multiple', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * INSERT with only a subset of columns (omitting optional fields).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testInsertPartialColumns(string $driver): void
	{
		$db = self::getDb($driver);
		$qb = new QBInsert($db);
		$qb->into('currencies')->values([
			'code'   => 'USD',
			'name'   => 'US Dollar',
			'symbol' => '$',
		]);

		$this->assertMatchesSnapshot($driver . '/qb_insert_partial', $qb->getSqlQuery(), $qb->getBoundValues());
	}
}
