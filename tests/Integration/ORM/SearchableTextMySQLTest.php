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
use Gobl\DBAL\Search\Searchable;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * Runs the searchable-column tests against a real MySQL database.
 *
 * @internal
 */
#[CoversMethod(SQLQueryGeneratorBase::class, 'getSearchIndexExpression')]
#[CoversClass(Searchable::class)]
final class SearchableTextMySQLTest extends SearchableTextTestCase
{
	/**
	 * The server tokenises at the size the compiler assumes.
	 *
	 * `ngram_token_size` is server-wide and needs a restart and an index rebuild to change. A
	 * substring search is sent to the index only for a term at least this long, so a server
	 * configured higher would answer no rows for a term between the two sizes. The assumption is
	 * asserted here rather than left to be discovered on a deployment.
	 */
	public function testTheServerTokenizesAtTheAssumedSize(): void
	{
		$size = static::$db->execute('SELECT @@ngram_token_size AS size;', [])
			->fetch(PDO::FETCH_ASSOC);

		self::assertSame(
			SQLQueryGeneratorBase::NGRAM_TOKEN_SIZE,
			(int) ($size['size'] ?? 0),
			'this server tokenises at another size: a term shorter than it would find no rows'
		);
	}

	protected static function getDriverName(): string
	{
		return MySQL::NAME;
	}

	protected function indexedColumnsOnServer(): array
	{
		$sql = 'SELECT COLUMN_NAME AS col FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table;';

		$rows = static::$db->execute($sql, ['table' => static::$db->getTableOrFail('articles')->getFullName()])
			->fetchAll(PDO::FETCH_ASSOC);

		return \array_map(static fn (array $row): string => (string) $row['col'], $rows);
	}
}
