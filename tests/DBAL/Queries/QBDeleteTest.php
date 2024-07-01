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
use Gobl\DBAL\Queries\QBDelete;
use Gobl\Tests\BaseTestCase;

/**
 * Class QBDeleteTest.
 *
 * @covers \Gobl\DBAL\Queries\QBDelete
 *
 * @internal
 */
final class QBDeleteTest extends BaseTestCase
{
	public function testDelete(): void
	{
		$db = self::getDb();

		$qb = new QBDelete($db);

		$qb->from('clients');
		$qb->where($qb->filters()->eq('id', 1));

		$sql          = $qb->getSqlQuery();
		$bound_values = $qb->getBoundValues();
		$n            = new NamedToPositionalParams($sql, $bound_values, $qb->getBoundValuesTypes());
		self::assertSame('DELETE _a_ FROM gObL_clients AS _a_ WHERE ((id = ?))', $n->getNewQuery());
		self::assertSame([1], $n->getNewParams());
	}
}
