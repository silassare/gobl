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

/**
 * Runs the JSON patch tests against a real MySQL database.
 *
 * @internal
 *
 * @covers \Gobl\DBAL\Drivers\SQLQueryGeneratorBase::getJsonPatchUpdateExpression
 * @covers \Gobl\DBAL\Types\Utils\JsonPatch
 * @covers \Gobl\ORM\ORMEntity
 */
final class JsonPatchUpdateMySQLTest extends JsonPatchUpdateTestCase
{
	/**
	 * {@inheritDoc}
	 */
	protected static function getDriverName(): string
	{
		return MySQL::NAME;
	}
}
