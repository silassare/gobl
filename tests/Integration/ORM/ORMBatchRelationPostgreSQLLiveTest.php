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

/**
 * Class ORMBatchRelationPostgreSQLLiveTest.
 *
 * Runs the batch-relation integration tests against a real PostgreSQL database.
 *
 * @internal
 *
 * @covers \Gobl\DBAL\Relations\LinkColumns
 * @covers \Gobl\DBAL\Relations\LinkJoin
 * @covers \Gobl\DBAL\Relations\LinkMorph
 * @covers \Gobl\DBAL\Relations\LinkThrough
 * @covers \Gobl\ORM\ORMController::countRelativesBatch
 * @covers \Gobl\ORM\ORMController::getAllRelativesBatch
 * @covers \Gobl\ORM\ORMController::getRelativeBatch
 */
final class ORMBatchRelationPostgreSQLLiveTest extends ORMLiveBatchRelationTestCase
{
	/**
	 * {@inheritDoc}
	 */
	protected static function getDriverName(): string
	{
		return PostgreSQL::NAME;
	}
}
