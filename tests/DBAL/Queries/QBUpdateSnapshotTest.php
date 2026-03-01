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
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBUpdateSnapshotTest.
 *
 * Snapshot tests for MySQL QBUpdate SQL generation.
 *
 * @covers \Gobl\DBAL\Queries\QBUpdate
 *
 * @internal
 */
final class QBUpdateSnapshotTest extends BaseTestCase
{
	/** UPDATE with simple equality WHERE. */
	public function testUpdateSimple(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBUpdate($db);
		$qb->update('clients')
			->set([
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
			])
			->where($qb->filters()->eq('client_id', 1));

		$this->assertMatchesSnapshot('mysql/qb_update_simple', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** UPDATE with a complex WHERE (AND + OR). */
	public function testUpdateComplexWhere(): void
	{
		$db = self::getDb(MySQL::NAME);
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

		$this->assertMatchesSnapshot('mysql/qb_update_complex_where', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** UPDATE a single boolean/flag column on all rows matching a range. */
	public function testUpdateWithComparison(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBUpdate($db);
		$qb->update('accounts')
			->set(['valid' => false])
			->where(
				$qb->filters()
					->lt('account_balance', 0)
			);

		$this->assertMatchesSnapshot('mysql/qb_update_comparison', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** UPDATE using a raw SQL expression as a value (e.g. increment a column). */
	public function testUpdateWithExpression(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBUpdate($db);
		$qb->update('accounts')
			->set([
				'balance' => new QBExpression('account_balance + 100.00'),
			])
			->where($qb->filters()->eq('account_id', 7));

		$this->assertMatchesSnapshot('mysql/qb_update_expression', $qb->getSqlQuery(), $qb->getBoundValues());
	}

	/** UPDATE multiple columns, no WHERE (affects all rows). */
	public function testUpdateAllRows(): void
	{
		$db = self::getDb(MySQL::NAME);
		$qb = new QBUpdate($db);
		$qb->update('clients')
			->set(['valid' => true, 'given_name' => 'N/A']);

		$this->assertMatchesSnapshot('mysql/qb_update_all_rows', $qb->getSqlQuery(), $qb->getBoundValues());
	}
}
