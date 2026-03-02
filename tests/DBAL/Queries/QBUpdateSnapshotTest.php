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

use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBUpdateSnapshotTest.
 *
 * Cross-driver snapshot tests for QBUpdate SQL generation.
 * Each test runs for MySQL, PostgreSQL, and SQLite via the allDrivers data provider.
 *
 * Snapshots are stored under tests/assets/snapshots/{driver}/qb_update_{scenario}.txt
 *
 * @covers \Gobl\DBAL\Queries\QBUpdate
 *
 * @internal
 */
final class QBUpdateSnapshotTest extends BaseTestCase
{
	/**
	 * UPDATE with simple equality WHERE.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testUpdateSimple(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBUpdate($db);
		$qb->update('clients')
			->set([
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
			])
			->where($qb->filters()->eq('client_id', 1));

		$this->assertMatchesSnapshot($driver . '/qb_update_simple', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * UPDATE with a complex WHERE (AND + OR).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testUpdateComplexWhere(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBUpdate($db);
		$qb->update('clients', 'c')
			->set(['valid' => false])
			->where(
				$qb->filters()
					->eq('client_gender', 'unknown')
					->and(
						$qb->filters()->subGroup()
							->isNull('client_given_name')
							->or($qb->filters()->eq('client_valid', false))
					)
			);

		$this->assertMatchesSnapshot($driver . '/qb_update_complex_where', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * UPDATE a single boolean/flag column on all rows matching a range.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testUpdateWithComparison(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBUpdate($db);
		$qb->update('accounts')
			->set(['valid' => false])
			->where(
				$qb->filters()
					->lt('account_balance', 0)
			);

		$this->assertMatchesSnapshot($driver . '/qb_update_comparison', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * UPDATE using a raw SQL expression as a value (e.g. increment a column).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testUpdateWithExpression(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBUpdate($db);
		$qb->update('accounts')
			->set([
				'balance' => new QBExpression('account_balance + 100.00'),
			])
			->where($qb->filters()->eq('account_id', 7));

		$this->assertMatchesSnapshot($driver . '/qb_update_expression', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * UPDATE multiple columns, no WHERE (affects all rows).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testUpdateAllRows(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBUpdate($db);
		$qb->update('clients')
			->set(['valid' => true, 'given_name' => 'N/A']);

		$this->assertMatchesSnapshot($driver . '/qb_update_all_rows', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * UPDATE with a LIMIT clause.
	 *
	 * - MySQL     : appends `LIMIT n` directly.
	 * - SQLite    : rewrites WHERE as `rowid IN (SELECT rowid ... LIMIT n)`.
	 * - PostgreSQL: rewrites WHERE as `ctid IN (SELECT ctid ... LIMIT n)`.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testUpdateWithLimit(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBUpdate($db);
		$qb->update('clients')
			->set(['valid' => false])
			->where($qb->filters()->eq('client_gender', 'unknown'))
			->limit(25);

		$this->assertMatchesSnapshot($driver . '/qb_update_with_limit', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * UPDATE with LIMIT and ORDER BY.
	 *
	 * All three drivers support ORDER BY + LIMIT together:
	 * - MySQL     : appends `ORDER BY ... LIMIT n` directly.
	 * - SQLite    : both go into the rowid subquery `SELECT rowid ... ORDER BY ... LIMIT n`.
	 * - PostgreSQL: both go into the ctid subquery `SELECT ctid ... ORDER BY ... LIMIT n`.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testUpdateWithLimitAndOrderBy(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBUpdate($db);
		$qb->update('clients')
			->set(['valid' => false])
			->where($qb->filters()->eq('client_gender', 'unknown'))
			->orderBy(['client_id ASC'])
			->limit(5);

		$this->assertMatchesSnapshot($driver . '/qb_update_with_limit_order_by', $qb->getSqlQuery(), $qb->getBoundValues());
	}
}
