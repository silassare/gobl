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

use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Queries\QBDelete;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBDeleteSnapshotTest.
 *
 * Cross-driver snapshot tests for QBDelete SQL generation.
 * Each test runs for MySQL, PostgreSQL, and SQLite via the allDrivers data provider.
 * Note: testDeleteWithLimit is MySQL-only (PostgreSQL does not support LIMIT in DELETE).
 *
 * Snapshots are stored under tests/assets/snapshots/{driver}/qb_delete_{scenario}.txt
 *
 * @covers \Gobl\DBAL\Queries\QBDelete
 *
 * @internal
 */
final class QBDeleteSnapshotTest extends BaseTestCase
{
	/**
	 * DELETE with simple equality WHERE.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDeleteSimple(string $driver): void
	{
		$db = self::getDb($driver);
		$qb = new QBDelete($db);
		$qb->from('clients')
			->where($qb->filters()->eq('client_id', 1));

		$this->assertMatchesSnapshot($driver . '/qb_delete_simple', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * DELETE with an IN list WHERE.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDeleteWhereIn(string $driver): void
	{
		$db = self::getDb($driver);
		$qb = new QBDelete($db);
		$qb->from('clients')
			->where($qb->filters()->in('client_id', [1, 2, 3]));

		$this->assertMatchesSnapshot($driver . '/qb_delete_where_in', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * DELETE with a complex WHERE (AND + comparison).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDeleteComplexWhere(string $driver): void
	{
		$db = self::getDb($driver);
		$qb = new QBDelete($db);
		$qb->from('clients')
			->where(
				$qb->filters()
					->eq('client_valid', false)
					->and($qb->filters()->lt('client_id', 100))
			);

		$this->assertMatchesSnapshot($driver . '/qb_delete_complex_where', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * DELETE with a nested OR condition.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDeleteNestedOr(string $driver): void
	{
		$db = self::getDb($driver);
		$qb = new QBDelete($db);
		$qb->from('transactions')
			->where(
				$qb->filters()->subGroup()
					->eq('transaction_type', 'out')
					->or($qb->filters()->eq('transaction_state', 'in_error'))
			);

		$this->assertMatchesSnapshot($driver . '/qb_delete_nested_or', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * DELETE all rows (no WHERE clause).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDeleteAll(string $driver): void
	{
		$db = self::getDb($driver);
		$qb = new QBDelete($db);
		$qb->from('clients');

		$this->assertMatchesSnapshot($driver . '/qb_delete_all', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** DELETE with LIMIT (MySQL-specific PostgreSQL does not support LIMIT in DELETE). */
	public function testDeleteWithLimit(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBDelete($db);
		$qb->from('clients')
			->where($qb->filters()->eq('client_valid', false))
			->limit(50);

		$this->assertMatchesSnapshot('mysql/qb_delete_with_limit', $qb->getSqlQuery(), $qb->getBoundValues());
	}
}
