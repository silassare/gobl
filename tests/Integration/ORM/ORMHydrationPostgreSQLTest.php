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

namespace Gobl\Tests\Integration\ORM;

use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * {@see ORMHydrationTestCase} on PostgreSQL.
 *
 * @internal
 */
#[CoversNothing]
#[Group('postgresql')]
final class ORMHydrationPostgreSQLTest extends ORMHydrationTestCase
{
	#[Override]
	protected static function getDriverName(): string
	{
		return PostgreSQL::NAME;
	}
}
