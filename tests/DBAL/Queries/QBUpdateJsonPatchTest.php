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

namespace Gobl\Tests\DBAL\Queries;

use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\Tests\BaseTestCase;
use PHPUtils\Store\Map;

/**
 * What an UPDATE sends for a JSON column given a patch: the paths, for the database to apply to the
 * row it holds, so that two transactions patching different paths keep each other's writes.
 *
 * @covers \Gobl\DBAL\Drivers\PostgreSQL\PostgreSQLQueryGenerator
 * @covers \Gobl\DBAL\Drivers\SQLite\SQLiteQueryGenerator
 * @covers \Gobl\DBAL\Drivers\SQLQueryGeneratorBase::getJsonPatchUpdateExpression
 * @covers \Gobl\DBAL\Queries\Traits\QBSetColumnsValuesTrait
 *
 * @internal
 */
final class QBUpdateJsonPatchTest extends BaseTestCase
{
	public function testMySQLSendsThePaths(): void
	{
		[$sql, $params] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->set('oz.lang', 'fr')
				->remove('uid');
		});

		self::assertSame(
			'UPDATE `gObL_clients` SET client_data = JSON_REMOVE(JSON_SET(JSON_SET('
				. "COALESCE(client_data, CAST('{}' AS JSON)), '$.oz', "
				. "CAST(CASE WHEN JSON_TYPE(JSON_EXTRACT(client_data, '$.oz')) = 'OBJECT' "
				. "THEN JSON_EXTRACT(client_data, '$.oz') ELSE '{}' END AS JSON)), "
				. "'$.oz.lang', CAST(? AS JSON)), '$.uid') WHERE ((id = ?))",
			$sql
		);
		self::assertSame(['"fr"', 1], $params);
	}

	public function testSQLiteSendsThePaths(): void
	{
		[$sql, $params] = $this->patchQuery(SQLite::NAME, static function (JsonPatch $patch): void {
			$patch->set('oz.lang', 'fr');
		});

		self::assertSame(
			'UPDATE "gObL_clients" SET client_data = json_set(json_set('
				. "COALESCE(client_data, json('{}')), '$.oz', "
				. "json(CASE WHEN json_type(json_extract(client_data, '$.oz')) = 'object' "
				. "THEN json_extract(client_data, '$.oz') ELSE '{}' END)), "
				. "'$.oz.lang', json(?)) WHERE ((id = ?))",
			$sql
		);
		self::assertSame(['"fr"', 1], $params);
	}

	public function testPostgreSQLSendsThePaths(): void
	{
		[$sql, $params] = $this->patchQuery(PostgreSQL::NAME, static function (JsonPatch $patch): void {
			$patch->set('oz.lang', 'fr');
		});

		// PostgreSQL addresses a path with a bound `text[]`, so the segments are values and the
		// query is read for its shape: the object above the path made from the column, then the
		// path written.
		self::assertStringContainsString("COALESCE((client_data)::jsonb, ('{}')::jsonb)", $sql);
		self::assertStringContainsString("jsonb_typeof((client_data #> ARRAY[?]::text[])) = 'object'", $sql);
		self::assertStringContainsString('ARRAY[?, ?]::text[], (?)::jsonb, true)', $sql);
		self::assertSame(2, \substr_count($sql, 'jsonb_set('));
		self::assertSame(['oz', 'oz', '"fr"', 'oz', 'lang', 1], $params);
	}

	/**
	 * Paths that are inside one another: their result depends on the order they are applied in,
	 * which an expression of paths does not keep, so the document is written instead.
	 */
	public function testPathsInsideOneAnotherSendTheDocument(): void
	{
		[$sql, $params] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->set('a', ['b' => 9])
				->set('a.c', 3);
		});

		self::assertSame('UPDATE `gObL_clients` SET client_data = ? WHERE ((id = ?))', $sql);
		self::assertSame(['a' => ['b' => 9, 'c' => 3]], $params[0]->getData());
	}

	/**
	 * A segment that reads as an array index addresses an entry in PHP and a member named `0` in a
	 * JSON path expression: the document is written instead of guessing which was meant.
	 */
	public function testAnIndexSegmentSendsTheDocument(): void
	{
		[$sql, $params] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->set('tags.0', 'first');
		});

		self::assertSame('UPDATE `gObL_clients` SET client_data = ? WHERE ((id = ?))', $sql);
		self::assertSame(['tags' => ['first']], $params[0]->getData());
	}

	/**
	 * There is no row for the database to apply paths to in an INSERT.
	 */
	public function testAnInsertSendsTheDocument(): void
	{
		$db = self::getNewDbInstanceWithSchema(MySQL::NAME);

		$patch = new JsonPatch();
		$patch->set('oz.lang', 'fr');

		$qb = new QBInsert($db);
		$qb->into('clients')
			->values(['data' => $patch]);

		self::assertStringNotContainsString('JSON_SET', $qb->getSqlQuery());

		$bound = \array_values($qb->getBoundValues());
		$data  = [];

		foreach ($bound as $value) {
			if ($value instanceof Map) {
				$data = $value->getData();
			}
		}

		self::assertSame(['oz' => ['lang' => 'fr']], $data);
	}

	/**
	 * Builds the UPDATE a patch of the `data` column produces.
	 *
	 * @return array{0: string, 1: array}
	 */
	private function patchQuery(string $driver, callable $operations): array
	{
		$db = self::getNewDbInstanceWithSchema($driver);

		$patch = new JsonPatch();

		$operations($patch);

		$qb = new QBUpdate($db);
		$qb->update('clients')
			->set(['data' => $patch])
			->where($qb->filters()->eq('id', 1));

		// The parameter names are generated: the query is read with them replaced by `?`, in the
		// order they appear, which is the order they were bound in.
		$sql = (string) \preg_replace('~:_val_\w+~', '?', $qb->getSqlQuery());

		return [$sql, \array_values($qb->getBoundValues())];
	}
}
