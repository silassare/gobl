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

namespace Gobl\Tests\Integration\DBAL\Types;

use Gobl\DBAL\Diff\Diff;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\MigrationRunner;
use Gobl\DBAL\Types\TypeJson;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class NativeJsonMigrationMySQLTest.
 *
 * Runs the native JSON migration test suite against a real MySQL database.
 *
 * @internal
 */
#[CoversClass(Diff::class)]
#[CoversClass(MigrationRunner::class)]
#[CoversClass(TypeJson::class)]
final class NativeJsonMigrationMySQLTest extends NativeJsonMigrationTestCase
{
	protected static function getDriverName(): string
	{
		return MySQL::NAME;
	}
}
