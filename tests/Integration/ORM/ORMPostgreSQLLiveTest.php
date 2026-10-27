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
use Gobl\ORM\ORM;
use Gobl\ORM\ORMController;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMResults;
use Gobl\ORM\ORMTableQuery;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class ORMPostgreSQLLiveTest.
 *
 * Runs the full ORM live-DB test suite against a real PostgreSQL database.
 *
 * @internal
 */
#[CoversClass(ORM::class)]
#[CoversClass(ORMController::class)]
#[CoversClass(ORMEntity::class)]
#[CoversClass(ORMResults::class)]
#[CoversClass(ORMTableQuery::class)]
final class ORMPostgreSQLLiveTest extends ORMLiveTestCase
{
	protected static function getDriverName(): string
	{
		return PostgreSQL::NAME;
	}
}
