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

use Gobl\DBAL\Drivers\SQLite\SQLite;

/**
 * Class ORMSQLiteLiveTest.
 *
 * Runs the full ORM live-DB test suite against a real SQLite database.
 * Set the following env var (or .env.test) to enable this test class:
 *
 *   GOBL_TEST_SQLITE_FILE=:memory:   (recommended no file created on disk)
 *   GOBL_TEST_SQLITE_FILE=/path/to/test.db
 *
 * @internal
 *
 * @covers \Gobl\ORM\ORM
 * @covers \Gobl\ORM\ORMController
 * @covers \Gobl\ORM\ORMEntity
 * @covers \Gobl\ORM\ORMResults
 * @covers \Gobl\ORM\ORMTableQuery
 */
final class ORMSQLiteLiveTest extends ORMLiveTestCase
{
	/**
	 * {@inheritDoc}
	 */
	protected static function getDriverName(): string
	{
		return SQLite::NAME;
	}
}
