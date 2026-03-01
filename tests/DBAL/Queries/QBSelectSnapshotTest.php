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
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBSelectSnapshotTest.
 *
 * Snapshot tests for MySQL QBSelect SQL generation.
 * On the first run each fixture file is auto-generated; delete a fixture and
 * re-run to regenerate it.
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

	/** SELECT * FROM table (no alias). */
	public function testSelectAll(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients');

		$this->assertMatchesSnapshot('mysql/qb_select_all', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** SELECT specific columns using table alias. */
	public function testSelectSpecificColumns(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name', 'last_name']);

		$this->assertMatchesSnapshot('mysql/qb_select_columns', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** SELECT columns with custom aliases (AS). */
	public function testSelectAliasedColumns(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id' => 'uid', 'first_name' => 'fname']);

		$this->assertMatchesSnapshot('mysql/qb_select_aliased_columns', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// WHERE clause variations
	// -------------------------------------------------------------------------

	/** WHERE single equality condition. */
	public function testSelectWhereEq(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->where($qb->filters()->eq('client_id', 42));

		$this->assertMatchesSnapshot('mysql/qb_select_where_eq', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** WHERE with AND/OR combination and nested group. */
	public function testSelectWhereComplex(): void
	{
		$db = self::getDb(MySQL::NAME);
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

		$this->assertMatchesSnapshot('mysql/qb_select_where_complex', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** WHERE ... IN (...) list. */
	public function testSelectWhereIn(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->where($qb->filters()->in('client_gender', ['male', 'female']));

		$this->assertMatchesSnapshot('mysql/qb_select_where_in', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** WHERE IS NULL / IS NOT NULL. */
	public function testSelectWhereNull(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->where(
				$qb->filters()
					->isNull('client_given_name')
					->and($qb->filters()->isNotNull('client_first_name'))
			);

		$this->assertMatchesSnapshot('mysql/qb_select_where_null', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** WHERE with comparison operators: gt, gte, lt, lte, neq. */
	public function testSelectWhereComparisons(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('accounts', 'a')
			->select('a')
			->where(
				$qb->filters()
					->gt('account_balance', 0)
					->and($qb->filters()->lte('account_balance', 10000))
					->and($qb->filters()->neq('account_valid', false))
			);

		$this->assertMatchesSnapshot('mysql/qb_select_where_comparisons', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// LIMIT / OFFSET
	// -------------------------------------------------------------------------

	/** LIMIT without offset. */
	public function testSelectLimit(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->limit(10);

		$this->assertMatchesSnapshot('mysql/qb_select_limit', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** LIMIT with OFFSET. */
	public function testSelectLimitOffset(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->limit(10, 20);

		$this->assertMatchesSnapshot('mysql/qb_select_limit_offset', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// ORDER BY
	// -------------------------------------------------------------------------

	/** ORDER BY single column ASC. */
	public function testSelectOrderByAsc(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->orderBy(['client_last_name' => 'ASC']);

		$this->assertMatchesSnapshot('mysql/qb_select_order_by_asc', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** ORDER BY multiple columns with mixed directions. */
	public function testSelectOrderByMulti(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c')
			->orderBy(['client_last_name' => 'ASC', 'client_first_name' => 'DESC']);

		$this->assertMatchesSnapshot('mysql/qb_select_order_by_multi', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// GROUP BY / HAVING
	// -------------------------------------------------------------------------

	/** GROUP BY with raw HAVING string. */
	public function testSelectGroupByHaving(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('transactions', 't')
			->select(null, [
				'transaction_account_id',
				'COUNT(*) as total',
				'SUM(transaction_amount) as sum',
			])
			->groupBy(['transaction_account_id'])
			->having('COUNT(*) > 1');

		$this->assertMatchesSnapshot('mysql/qb_select_group_by_having', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// JOINs
	// -------------------------------------------------------------------------

	/** INNER JOIN two tables on a foreign key. */
	public function testSelectInnerJoin(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name', 'last_name']);

		$qb->innerJoin('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', new QBExpression('c.client_id')));

		$qb->select('a', ['id', 'label', 'balance']);

		$this->assertMatchesSnapshot('mysql/qb_select_inner_join', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** LEFT JOIN — clients with their linked accounts, including clients with no accounts. */
	public function testSelectLeftJoin(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name']);

		$qb->leftJoin('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', new QBExpression('c.client_id')));

		$qb->select('a', ['id', 'balance']);

		$this->assertMatchesSnapshot('mysql/qb_select_left_join', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** RIGHT JOIN. */
	public function testSelectRightJoin(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name']);

		$qb->rightJoin('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', new QBExpression('c.client_id')));

		$qb->select('a', ['id', 'balance']);

		$this->assertMatchesSnapshot('mysql/qb_select_right_join', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** Chained INNER JOINs across three tables. */
	public function testSelectMultiJoin(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBSelect($db);
		$qb->from('clients', 'c')
			->select('c', ['id', 'first_name']);

		$qb->innerJoin('accounts', 'a')
			->on($qb->filters()->eq('a.account_client_id', new QBExpression('c.client_id')));

		$qb->innerJoin('transactions', 't')
			->on($qb->filters()->eq('t.transaction_account_id', new QBExpression('a.account_id')));

		$qb->select('t', ['id', 'reference', 'amount']);

		$this->assertMatchesSnapshot('mysql/qb_select_multi_join', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// Subquery as FROM
	// -------------------------------------------------------------------------

	/** SELECT from an inline sub-query. */
	public function testSelectSubquery(): void
	{
		$db = self::getDb(MySQL::NAME);

		$sub = new QBSelect($db);
		$sub->from('clients', 'c')
			->select('c', ['id', 'first_name', 'last_name'])
			->where($sub->filters()->eq('client_valid', true));

		$qb = new QBSelect($db);
		$qb->from($sub, 'sub')
			->select(null, ['sub.client_id', 'sub.client_first_name'])
			->orderBy(['sub.client_last_name' => 'ASC'])
			->limit(5);

		$this->assertMatchesSnapshot('mysql/qb_select_subquery', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	// -------------------------------------------------------------------------
	// Combined: WHERE + ORDER BY + LIMIT
	// -------------------------------------------------------------------------

	/** WHERE + ORDER BY + LIMIT + OFFSET combined. */
	public function testSelectCombined(): void
	{
		$db = self::getDb(MySQL::NAME);
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

		$this->assertMatchesSnapshot('mysql/qb_select_combined', $qb->getSqlQuery(), $qb->getBoundValues());
	}
}
