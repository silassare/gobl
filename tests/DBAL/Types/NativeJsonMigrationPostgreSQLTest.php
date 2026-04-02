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
