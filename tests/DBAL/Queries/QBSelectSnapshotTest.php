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

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;
use SQLite3;

/**
 * Class QBSelectSnapshotTest.
 *
 * Cross-driver snapshot tests for QBSelect SQL generation.
 * Each test runs for MySQL, PostgreSQL, and SQLite via the allDrivers data provider.
 * On the first run each fixture file is auto-generated; delete a fixture and
 * re-run to regenerate it.
 *
 * Snapshots are stored under tests/assets/snapshots/{driver}/qb_select_{scenario}.txt
 *
 * @covers \Gobl\DBAL\Queries\QBSelect
 *
 * @internal
 */
final class QBSelectSnapshotTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// Basic SELECT forms
	// -------------------------------------------------------------------------

	/**
	 * SELECT * FROM table (no alias).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectAll(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients');

		$this->assertMatchesSnapshot($driver . '/qb_select_all', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * SELECT specific columns using table alias.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectSpecificColumns(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name', 'last_name']);

		$this->assertMatchesSnapshot($driver . '/qb_select_columns', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * SELECT columns with custom aliases (AS).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectAliasedColumns(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id' => 'uid', 'first_name' => 'fname']);

		$this->assertMatchesSnapshot($driver . '/qb_select_aliased_columns', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// WHERE clause variations
	// -------------------------------------------------------------------------

	/**
	 * WHERE single equality condition.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectWhereEq(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->where($qb->filters()->eq('client_id', 42));

		$this->assertMatchesSnapshot($driver . '/qb_select_where_eq', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * WHERE with AND/OR combination and nested group.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectWhereComplex(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')->select('c');

		$f = $qb->filters()
			->eq('client_gender', 'male')
			->and(
				$qb->filters()->subGroup()
					->like('client_first_name', 'Jo%')
					->or($qb->filters()->eq('client_valid', true))
			);

		$qb->where($f);

		$this->assertMatchesSnapshot($driver . '/qb_select_where_complex', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * WHERE ... IN (...) list.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectWhereIn(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->where($qb->filters()->in('client_gender', ['male', 'female']));

		$this->assertMatchesSnapshot($driver . '/qb_select_where_in', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * WHERE IS NULL / IS NOT NULL.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectWhereNull(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->where(
				$qb->filters()
					->isNull('client_given_name')
					->and($qb->filters()->isNotNull('client_first_name'))
			);

		$this->assertMatchesSnapshot($driver . '/qb_select_where_null', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * WHERE with comparison operators: gt, gte, lt, lte, neq.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectWhereComparisons(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('accounts', 'a')
			->select('a')
			->where(
				$qb->filters()
					->gt('account_balance', 0)
					->and($qb->filters()->lte('account_balance', 10000))
					->and($qb->filters()->neq('account_valid', false))
			);

		$this->assertMatchesSnapshot($driver . '/qb_select_where_comparisons', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// LIMIT / OFFSET
	// -------------------------------------------------------------------------

	/**
	 * LIMIT without offset.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectLimit(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->limit(10);

		$this->assertMatchesSnapshot($driver . '/qb_select_limit', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * LIMIT with OFFSET.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectLimitOffset(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->limit(10, 20);

		$this->assertMatchesSnapshot($driver . '/qb_select_limit_offset', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// ORDER BY
	// -------------------------------------------------------------------------

	/**
	 * ORDER BY single column ASC.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectOrderByAsc(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->orderBy(['client_last_name' => 'ASC']);

		$this->assertMatchesSnapshot($driver . '/qb_select_order_by_asc', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * ORDER BY single column DESC.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectOrderByDesc(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->orderBy(['client_last_name' => 'DESC']);

		$this->assertMatchesSnapshot($driver . '/qb_select_order_by_desc', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * ORDER BY multiple columns with mixed directions.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectOrderByMulti(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->orderBy(['client_last_name' => 'ASC', 'client_first_name' => 'DESC']);

		$this->assertMatchesSnapshot($driver . '/qb_select_order_by_multi', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// GROUP BY / HAVING
	// -------------------------------------------------------------------------

	/**
	 * GROUP BY with raw HAVING string.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectGroupByHaving(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('transactions', 't')
			->select(null, [
				'transaction_account_id',
				'COUNT(*) as total',
				'SUM(transaction_amount) as sum',
			])
			->groupBy(['transaction_account_id'])
			->having('COUNT(*) > 1');

		$this->assertMatchesSnapshot($driver . '/qb_select_group_by_having', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// JOINs
	// -------------------------------------------------------------------------

	/**
	 * INNER JOIN two tables on a foreign key.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectInnerJoin(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name', 'last_name']);

		$qb->innerJoin('c')
			->to('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', $qb->expr('c.client_id')));

		$qb->select('a', ['id', 'label', 'balance']);

		$this->assertMatchesSnapshot($driver . '/qb_select_inner_join', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * LEFT JOIN clients with their linked accounts, including clients with no accounts.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectLeftJoin(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name']);

		$qb->leftJoin('c')
			->to('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', $qb->expr('c.client_id')));

		$qb->select('a', ['id', 'balance']);

		$this->assertMatchesSnapshot($driver . '/qb_select_left_join', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * RIGHT JOIN — all three drivers.
	 *
	 * MySQL and PostgreSQL emit native RIGHT JOIN syntax.
	 * SQLite emulates it transparently as LEFT JOIN with swapped tables:
	 *   FROM host AS h RIGHT JOIN target AS t ON cond
	 * becomes:
	 *   FROM target AS t LEFT JOIN host AS h ON cond
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectRightJoin(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name']);

		$qb->rightJoin('c')
			->to('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', $qb->expr('c.client_id')));

		$qb->select('a', ['id', 'balance']);

		$this->assertMatchesSnapshot($driver . '/qb_select_right_join', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/**
	 * RIGHT JOIN at sub-join level: throws on SQLite < 3.39.0 (emulation does not cover it),
	 * succeeds on SQLite >= 3.39.0 (native RIGHT JOIN support).
	 */
	public function testSelectRightJoinSubLevelSQLiteThrows(): void
	{
		$db = self::getNewDbInstanceWithSchema('sqlite');
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name']);

		// INNER JOIN anchored at 'c' — sub-join of 'a' is the right join
		$qb->innerJoin('c')
			->to('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', $qb->expr('c.client_id')));

		// RIGHT JOIN anchored at 'a' — sub-level right join
		$qb->rightJoin('a')
			->to('transactions', 't')
			->on($qb->filters()->eq('t.transaction_account_id', $qb->expr('a.account_id')));

		$qb->select('a', ['id', 'balance']);

		$sqliteVersion = SQLite3::version()['versionString'];

		if (\version_compare($sqliteVersion, '3.39.0', '<')) {
			// Older SQLite: emulation layer does not cover sub-level RIGHT JOINs.
			$this->expectException(DBALRuntimeException::class);
		}

		// On SQLite >= 3.39.0 this must not throw — native RIGHT JOIN handles it.
		$sql = $qb->getSqlQuery();

		if (\version_compare($sqliteVersion, '3.39.0', '>=')) {
			self::assertStringContainsString('RIGHT JOIN', $sql);
		}
	}

	/**
	 * Chained INNER JOINs across three tables: clients -> accounts -> transactions.
	 *
	 * Each join is anchored to the PREVIOUS table's alias (the left / host side).
	 * The generator recurses through joined aliases, so the chain can be arbitrarily deep:
	 *   FROM clients AS c
	 *     INNER JOIN accounts AS a ON ...   <= anchored at 'c'
	 *       INNER JOIN transactions AS t ON ...  <= anchored at 'a'
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectMultiJoin(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name']);

		// First join: c (host) -> accounts as a
		$qb->innerJoin('c')
			->to('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', $qb->expr('c.client_id')));

		// Second join: a (host, already joined) -> transactions as t
		$qb->innerJoin('a')
			->to('transactions', 't')
			->on($qb->filters()->eq('t.transaction_account_id', $qb->expr('a.account_id')));

		$qb->select('t', ['id', 'reference', 'amount']);

		$this->assertMatchesSnapshot($driver . '/qb_select_multi_join', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// Subquery as FROM
	// -------------------------------------------------------------------------

	/**
	 * SELECT from an inline sub-query.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectSubquery(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);

		$sub = new QBSelect($db);
		$sub->from('clients', 'c')
			->select('c', ['id', 'first_name', 'last_name'])
			->where($sub->filters()->eq('client_valid', true));

		$qb = new QBSelect($db);
		$qb->from($sub, 'sub')
			->select(null, ['sub.client_id', 'sub.client_first_name'])
			->orderBy(['sub.client_last_name' => 'ASC'])
			->limit(5);

		$this->assertMatchesSnapshot($driver . '/qb_select_subquery', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// Combined: WHERE + ORDER BY + LIMIT
	// -------------------------------------------------------------------------

	/**
	 * WHERE + ORDER BY + LIMIT + OFFSET combined.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSelectCombined(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->where(
				$qb->filters()
					->eq('client_valid', true)
					->and($qb->filters()->gt('client_id', 0))
			)
			->orderBy(['client_last_name' => 'ASC', 'client_id' => 'DESC'])
			->limit(25, 50);

		$this->assertMatchesSnapshot($driver . '/qb_select_combined', $qb->getSqlQuery(), $qb->getBoundValues());
	}
}
