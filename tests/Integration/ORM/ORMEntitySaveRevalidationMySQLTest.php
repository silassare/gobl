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

use Gobl\DBAL\Drivers\MySQL\MySQL;
use Override;

/**
 * {@see ORMEntitySaveRevalidationTestCase} on MySQL.
 *
 * @internal
 *
 * @coversNothing
 *
 * @group mysql
 */
final class ORMEntitySaveRevalidationMySQLTest extends ORMEntitySaveRevalidationTestCase
{
	#[Override]
	protected static function getDriverName(): string
	{
		return MySQL::NAME;
	}
}
