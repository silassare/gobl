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

namespace Gobl\Tests\Integration\DBAL;

use Gobl\DBAL\Drivers\SQLite\SQLite;
use Override;

/**
 * {@see DBALUniqueViolationTestCase} on SQLite.
 *
 * @internal
 *
 * @coversNothing
 *
 * @group sqlite
 */
final class DBALUniqueViolationSQLiteTest extends DBALUniqueViolationTestCase
{
	#[Override]
	protected static function getDriverName(): string
	{
		return SQLite::NAME;
	}
}
