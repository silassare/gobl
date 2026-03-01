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
 * Snapshot tests for MySQL QBDelete SQL generation.
 *
 * @covers \Gobl\DBAL\Queries\QBDelete
 *
 * @internal
 */
final class QBDeleteSnapshotTest extends BaseTestCase
{
	/** DELETE with simple equality WHERE. */
	public function testDeleteSimple(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBDelete($db);
		$qb->from('clients')
			->where($qb->filters()->eq('client_id', 1));

		$this->assertMatchesSnapshot('mysql/qb_delete_simple', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** DELETE with an IN list WHERE. */
	public function testDeleteWhereIn(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBDelete($db);
		$qb->from('clients')
			->where($qb->filters()->in('client_id', [1, 2, 3]));

		$this->assertMatchesSnapshot('mysql/qb_delete_where_in', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** DELETE with a complex WHERE (AND + comparison). */
	public function testDeleteComplexWhere(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBDelete($db);
		$qb->from('clients')
			->where(
				$qb->filters()
					->eq('client_valid', false)
					->and($qb->filters()->lt('client_id', 100))
			);

		$this->assertMatchesSnapshot('mysql/qb_delete_complex_where', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** DELETE with a nested OR condition. */
	public function testDeleteNestedOr(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBDelete($db);
		$qb->from('transactions')
			->where(
				$qb->filters()->subGroup()
					->eq('transaction_type', 'out')
					->or($qb->filters()->eq('transaction_state', 'in_error'))
			);

		$this->assertMatchesSnapshot('mysql/qb_delete_nested_or', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** DELETE all rows (no WHERE clause). */
	public function testDeleteAll(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBDelete($db);
		$qb->from('clients');

		$this->assertMatchesSnapshot('mysql/qb_delete_all', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** DELETE with LIMIT (MySQL-specific). */
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
