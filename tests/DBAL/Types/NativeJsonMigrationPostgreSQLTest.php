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

namespace Gobl\Tests\DBAL\Types;

use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;

/**
 * Class NativeJsonMigrationPostgreSQLTest.
 *
 * Runs the native JSON migration test suite against a real PostgreSQL database.
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
 * @covers \Gobl\DBAL\Diff\Diff
 * @covers \Gobl\DBAL\MigrationRunner
 * @covers \Gobl\DBAL\Types\TypeJson
 */
final class NativeJsonMigrationPostgreSQLTest extends NativeJsonMigrationTestCase
{
	/**
	 * {@inheritDoc}
	 */
	protected static function getDriverName(): string
	{
		return PostgreSQL::NAME;
	}
}
