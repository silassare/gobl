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
use Gobl\DBAL\Search\Searchable;
use Override;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Runs the searchable-column tests against a real PostgreSQL database.
 *
 * @internal
 */
#[CoversMethod(PostgreSQLQueryGenerator::class, 'getSearchIndexExpression')]
#[CoversClass(Searchable::class)]
final class SearchableTextPostgreSQLTest extends SearchableTextTestCase
{
	/**
	 * The extension the substring index needs is on the server, created by the schema itself.
	 */
	public function testTheTrigramExtensionIsInstalled(): void
	{
		$row = self::db()->execute(
			'SELECT COUNT(*) AS found FROM pg_extension WHERE extname = :name;',
			['name' => PostgreSQLQueryGenerator::TEXT_SEARCH_EXTENSION]
		)->fetch(PDO::FETCH_ASSOC);

		self::assertSame(1, (int) ($row['found'] ?? 0));
	}

	#[Override]
	protected static function getDriverName(): string
	{
		return PostgreSQL::NAME;
	}

	#[Override]
	protected function indexedColumnsOnServer(): array
	{
		$sql = 'SELECT a.attname AS col
			FROM pg_index i
			JOIN pg_class t ON t.oid = i.indrelid
			JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY (i.indkey)
			WHERE t.relname = :table;';

		$rows = self::db()->execute($sql, ['table' => self::db()->getTableOrFail('articles')->getFullName()])
			->fetchAll(PDO::FETCH_ASSOC);

		return \array_map(static fn (array $row): string => (string) $row['col'], $rows);
	}
}
