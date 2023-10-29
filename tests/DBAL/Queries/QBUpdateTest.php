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
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBUpdateTest.
 *
 * @covers \Gobl\DBAL\Queries\QBUpdate
 *
 * @internal
 */
final class QBUpdateTest extends BaseTestCase
{
	public function testUpdate(): void
	{
		$db = self::getDb();

		$qb = new QBUpdate($db);

		$qb->update('clients');

		$qb->set([
			'first_name' => 'John',
			'last_name'  => 'Adams',
		]);
		$filter = $qb->filters()->eq('id', 1);
		$qb->where($filter);

		$sql          = $qb->getSqlQuery();
		$bound_values = $qb->getBoundValues();
		$n            = new NamedToPositionalParams($sql, $bound_values, $qb->getBoundValuesTypes());
		self::assertSame('UPDATE gObL_clients SET client_first_name = ?, client_last_name = ? WHERE ((id = ?))', $n->getNewQuery());
		self::assertSame(['John', 'Adams', 1], $n->getNewParams());
	}
}
