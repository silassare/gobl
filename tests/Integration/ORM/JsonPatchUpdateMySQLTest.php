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
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\ORM\ORMEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Runs the JSON patch tests against a real MySQL database.
 *
 * @internal
 */
#[CoversMethod(SQLQueryGeneratorBase::class, 'getJsonPatchUpdateExpression')]
#[CoversClass(JsonPatch::class)]
#[CoversClass(ORMEntity::class)]
final class JsonPatchUpdateMySQLTest extends JsonPatchUpdateTestCase
{
	protected static function getDriverName(): string
	{
		return MySQL::NAME;
	}
}
