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
use Gobl\DBAL\Relations\LinkColumns;
use Gobl\DBAL\Relations\LinkJoin;
use Gobl\DBAL\Relations\LinkMorph;
use Gobl\DBAL\Relations\LinkThrough;
use Gobl\ORM\ORMController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Class ORMBatchRelationMySQLLiveTest.
 *
 * Runs the batch-relation integration tests against a real MySQL database.
 *
 * @internal
 */
#[CoversClass(LinkColumns::class)]
#[CoversClass(LinkJoin::class)]
#[CoversClass(LinkMorph::class)]
#[CoversClass(LinkThrough::class)]
#[CoversMethod(ORMController::class, 'getAllRelativesBatch')]
#[CoversMethod(ORMController::class, 'getRelativeBatch')]
final class ORMBatchRelationMySQLLiveTest extends ORMLiveBatchRelationTestCase
{
	protected static function getDriverName(): string
	{
		return MySQL::NAME;
	}
}
