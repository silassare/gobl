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
use Gobl\DBAL\Search\Searchable;
use Override;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Runs the searchable-column tests against a real SQLite database.
 *
 * SQLite has no substring index, so what the folded column buys here is the folding and not the
 * speed: the same answer as the other two engines, read by scanning one short text column.
 *
 * @internal
 */
#[CoversMethod(SQLiteQueryGenerator::class, 'getSearchIndexExpression')]
#[CoversClass(Searchable::class)]
final class SearchableTextSQLiteTest extends SearchableTextTestCase
{
	#[Override]
	protected static function getDriverName(): string
	{
		return SQLite::NAME;
	}

	#[Override]
	protected function indexedColumnsOnServer(): array
	{
		$table = self::db()->getTableOrFail('articles')->getFullName();
		$names = self::db()->execute(
			'SELECT name FROM sqlite_master WHERE type = \'index\' AND tbl_name = :table;',
			['table' => $table]
		)->fetchAll(PDO::FETCH_ASSOC);

		$columns = [];

		foreach ($names as $row) {
			$info = self::db()->execute('PRAGMA index_info(' . self::db()->getGenerator()
				->quoteIdentifier((string) $row['name']) . ');', [])->fetchAll(PDO::FETCH_ASSOC);

			foreach ($info as $entry) {
				$columns[] = (string) $entry['name'];
			}
		}

		return $columns;
	}
}
