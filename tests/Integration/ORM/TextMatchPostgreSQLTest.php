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
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQLQueryGenerator;
use Gobl\DBAL\Filters\TextMatch;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Runs the text search tests against a real PostgreSQL database.
 *
 * PostgreSQL folds case and nothing else here: `LIKE` against a nondeterministic ICU collation,
 * the one way to have the engine fold accents, is refused before PostgreSQL 18. Folding accents
 * there needs the `unaccent` extension, which an indexed search needs anyway.
 *
 * @internal
 */
#[CoversMethod(PostgreSQLQueryGenerator::class, 'getTextMatchExpression')]
#[CoversClass(TextMatch::class)]
final class TextMatchPostgreSQLTest extends TextMatchTestCase
{
	#[Override]
	protected static function getDriverName(): string
	{
		return PostgreSQL::NAME;
	}

	#[Override]
	protected static function foldsAccents(): bool
	{
		return false;
	}
}
