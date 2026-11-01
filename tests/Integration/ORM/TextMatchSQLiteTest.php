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

use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Drivers\SQLite\SQLiteQueryGenerator;
use Gobl\DBAL\Filters\TextMatch;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Runs the text search tests against a real SQLite database.
 *
 * SQLite folds ASCII case and nothing else: both its `lower()` and its `LIKE` are ASCII-only
 * without the ICU extension, so an accented letter is matched only as itself.
 *
 * @internal
 */
#[CoversMethod(SQLiteQueryGenerator::class, 'getTextMatchExpression')]
#[CoversClass(TextMatch::class)]
final class TextMatchSQLiteTest extends TextMatchTestCase
{
	#[Override]
	protected static function getDriverName(): string
	{
		return SQLite::NAME;
	}

	#[Override]
	protected static function foldsAccents(): bool
	{
		return false;
	}
}
