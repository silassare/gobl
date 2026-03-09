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

use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;

/**
 * Class ORMPostgreSQLLiveTest.
 *
 * Runs the full ORM live-DB test suite against a real PostgreSQL database.
 * Set the following env vars (or .env.test) to enable this test class:
 *
 *   GOBL_TEST_POSTGRESQL_USER=<username>
 *   GOBL_TEST_POSTGRESQL_PASSWORD=<password>
 *   GOBL_TEST_POSTGRESQL_HOST=127.0.0.1   (default)
 *   GOBL_TEST_POSTGRESQL_PORT=5432        (default)
 *   GOBL_TEST_POSTGRESQL_DB=gobl_test     (default)
 *
 * @internal
 *
 * @covers \Gobl\ORM\ORM
 * @covers \Gobl\ORM\ORMController
 * @covers \Gobl\ORM\ORMEntity
 * @covers \Gobl\ORM\ORMResults
 * @covers \Gobl\ORM\ORMTableQuery
 */
final class ORMPostgreSQLLiveTest extends ORMLiveTestCase
{
	/**
	 * {@inheritDoc}
	 */
	protected static function getDriverName(): string
	{
		return PostgreSQL::NAME;
	}
}
