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

use Gobl\DBAL\Queries\NamedToPositionalParams;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBInsertTest.
 *
 * @covers \Gobl\DBAL\Queries\QBInsert
 *
 * @internal
 */
final class QBInsertTest extends BaseTestCase
{
	public function testInsert(): void
	{
		$db = self::getDb();

		$qb = new QBInsert($db);

		$qb->into('clients')->values([
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'gender'     => 'Male',
		]);

		$sql          = $qb->getSqlQuery();
		$bound_values = $qb->getBoundValues();
		$n            = new NamedToPositionalParams($sql, $bound_values, $qb->getBoundValuesTypes());
		self::assertSame('INSERT INTO gObL_clients (client_first_name, client_last_name, client_gender) VALUES (?, ?, ?)', $n->getNewQuery());
		self::assertSame(['John', 'Doe', 'Male'], $n->getNewParams());
	}

	public function testInsertMultiple(): void
	{
		$db = self::getDb();

		$qb = new QBInsert($db);

		$qb->into('clients')->values([
			[
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'gender'     => 'Male',
			], [
				'first_name' => 'Alicia',
				'last_name'  => 'Doe',
				'gender'     => 'Female',
			],
		]);

		$sql          = $qb->getSqlQuery();
		$bound_values = $qb->getBoundValues();
		$n            = new NamedToPositionalParams($sql, $bound_values, $qb->getBoundValuesTypes());
		self::assertSame('INSERT INTO gObL_clients (client_first_name, client_last_name, client_gender) VALUES (?, ?, ?), (?, ?, ?)', $n->getNewQuery());
		self::assertSame(['John', 'Doe', 'Male', 'Alicia', 'Doe', 'Female'], $n->getNewParams());
	}
}
