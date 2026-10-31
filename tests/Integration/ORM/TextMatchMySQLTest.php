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
use Gobl\DBAL\Filters\TextMatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Runs the text search tests against a real MySQL database.
 *
 * MySQL folds both case and accents, because the expression is given the table's character set and
 * then its collation: this is the only engine of the three that needs nothing added for accents.
 *
 * @internal
 */
#[CoversMethod(SQLQueryGeneratorBase::class, 'getTextMatchExpression')]
#[CoversClass(TextMatch::class)]
final class TextMatchMySQLTest extends TextMatchTestCase
{
	protected static function getDriverName(): string
	{
		return MySQL::NAME;
	}

	protected static function foldsAccents(): bool
	{
		return true;
	}
}
