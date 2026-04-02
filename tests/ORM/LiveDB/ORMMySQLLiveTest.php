<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\Tests\ORM\LiveDB;

use Gobl\DBAL\Drivers\MySQL\MySQL;

/**
 * Class ORMMySQLLiveTest.
 *
 * Runs the full ORM live-DB test suite against a real MySQL database.
 *
 * @internal
 *
 * @covers \Gobl\ORM\ORM
 * @covers \Gobl\ORM\ORMController
 * @covers \Gobl\ORM\ORMEntity
 * @covers \Gobl\ORM\ORMResults
 * @covers \Gobl\ORM\ORMTableQuery
 */
final class ORMMySQLLiveTest extends ORMLiveTestCase
{
	/**
	 * {@inheritDoc}
	 */
	protected static function getDriverName(): string
	{
		return MySQL::NAME;
	}
}
