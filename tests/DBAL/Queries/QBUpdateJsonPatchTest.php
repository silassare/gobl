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
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQLQueryGenerator;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Drivers\SQLite\SQLiteQueryGenerator;
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Queries\NamedToPositionalParams;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\DBAL\Queries\Traits\QBSetColumnsValuesTrait;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\Tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\CoversTrait;

/**
 * What an UPDATE sends for a JSON column given a patch: the paths, for the database to apply to the
 * row it holds, so that two transactions patching different paths keep each other's writes.
 *
 * @internal
 */
#[CoversClass(PostgreSQLQueryGenerator::class)]
#[CoversClass(SQLiteQueryGenerator::class)]
#[CoversMethod(SQLQueryGeneratorBase::class, 'getJsonPatchUpdateExpression')]
#[CoversTrait(QBSetColumnsValuesTrait::class)]
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
				. "json(CASE WHEN json_type(client_data, '$.oz') = 'object' "
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

		// PostgreSQL addresses a path with a bound `text[]`, so a segment is a value here where
		// MySQL and SQLite take a literal path string.
		self::assertSame(
			'UPDATE "gObL_clients" SET client_data = jsonb_set(jsonb_set('
				. "COALESCE((client_data)::jsonb, ('{}')::jsonb), ARRAY[?]::text[], "
				. "(CASE WHEN jsonb_typeof(((client_data)::jsonb #> ARRAY[?]::text[])) = 'object' "
				. "THEN ((client_data)::jsonb #> ARRAY[?]::text[]) ELSE '{}' END)::jsonb, true), "
				. 'ARRAY[?, ?]::text[], (?)::jsonb, true) WHERE ((id = ?))',
			$sql
		);
		self::assertSame(['oz', 'oz', 'oz', 'oz', 'lang', '"fr"', 1], $params);
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
		self::assertSame(['a' => ['b' => 9, 'c' => 3]], \json_decode((string) $params[0], true));
	}

	/**
	 * A digit segment where nothing is yet: an entry, since that is what PHP makes of it too, so the
	 * path expression and {@see JsonPatch::toArray()} agree.
	 */
	public function testADigitSegmentOnNothingAddressesAnEntry(): void
	{
		[$sql, $params] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->set('tags.0', 'first');
		});

		self::assertSame(
			'UPDATE `gObL_clients` SET client_data = JSON_SET(JSON_SET('
				. "COALESCE(client_data, CAST('{}' AS JSON)), '$.tags', "
				. "CAST(CASE WHEN JSON_TYPE(JSON_EXTRACT(client_data, '$.tags')) = 'ARRAY' "
				. "THEN JSON_EXTRACT(client_data, '$.tags') ELSE '[]' END AS JSON)), "
				. "'$.tags[0]', CAST(? AS JSON)) WHERE ((id = ?))",
			$sql
		);
		self::assertSame(['"first"', 1], $params);
	}

	/**
	 * A digit segment where a map already is: a member, whose name is quoted so the engine does not
	 * read it as a position. This is the shape a store keeping entries under numeric ids writes, and
	 * the one that used to send the whole document and undo every concurrent write with it.
	 */
	public function testADigitSegmentOnAMapAddressesAMember(): void
	{
		[$sql, $params] = $this->patchQuery(
			MySQL::NAME,
			static function (JsonPatch $patch): void {
				$patch->set('blocks.12.title', 'Hi');
			},
			['blocks' => ['12' => ['title' => 'Ho']]]
		);

		self::assertStringContainsString('\'$.blocks."12".title\'', $sql);
		self::assertStringContainsString('\'$.blocks."12"\', CAST(CASE WHEN JSON_TYPE', $sql);
		self::assertStringNotContainsString('[12]', $sql);
		self::assertSame(['"Hi"', 1], $params);
	}

	/**
	 * A caller that does not want the document asked: an int segment is a position, a string
	 * segment is a member, whatever the document holds.
	 */
	public function testASegmentSaysWhichItIsWhenTheCallerGivesIt(): void
	{
		[$by_index] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->setAt(['tags', 0], 'first');
		});
		[$by_member] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->setAt(['tags', '0'], 'first');
		});

		self::assertStringContainsString("'\$.tags[0]'", $by_index);
		self::assertStringContainsString('\'$.tags."0"\'', $by_member);
	}

	/**
	 * One path written twice is applied in the order it was asked for: the expression nests the
	 * operations, so the last one answers for the path. Two-factor verification does exactly this --
	 * it sets a flag and clears it in the same request -- and refusing it sent the whole session
	 * document, which is the lost update the paths exist to prevent.
	 */
	public function testOnePathWrittenTwiceIsStillSentAsPaths(): void
	{
		[$sql, $params] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->set('oz.2fa.verified', true)
				->remove('oz.2fa_pending_user')
				->remove('oz.2fa.verified');
		});

		self::assertStringContainsString('JSON_SET', $sql);
		self::assertStringContainsString('JSON_REMOVE', $sql);
		self::assertSame(['true', 1], $params);
	}

	/**
	 * A later path inside an earlier one cannot be sent: every container is made from the row before
	 * any operation runs, so the later write would go into what the row held, or into nothing.
	 */
	public function testALaterPathInsideAnEarlierOneSendsTheDocument(): void
	{
		[$sql, $params] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->remove('a')
				->set('a.b', 1);
		});

		self::assertSame('UPDATE `gObL_clients` SET client_data = ? WHERE ((id = ?))', $sql);
		self::assertSame(['a' => ['b' => 1]], \json_decode((string) $params[0], true));
	}

	/**
	 * A container cannot be made an object and an array at once, and an engine asked for the wrong
	 * one writes nothing without saying so: the document is written instead, with the reason.
	 */
	public function testAContainerAddressedBothWaysSendsTheDocument(): void
	{
		[$sql, $params] = $this->patchQuery(MySQL::NAME, static function (JsonPatch $patch): void {
			$patch->setAt(['a', 0], 'entry')
				->setAt(['a', 'b'], 'member');
		});

		self::assertSame('UPDATE `gObL_clients` SET client_data = ? WHERE ((id = ?))', $sql);
		self::assertSame(['a' => ['0' => 'entry', 'b' => 'member']], \json_decode((string) $params[0], true));
	}

	/**
	 * SQLite addresses a member and a position the same way MySQL does.
	 */
	public function testSQLiteTellsAMemberFromAPosition(): void
	{
		[$by_index] = $this->patchQuery(SQLite::NAME, static function (JsonPatch $patch): void {
			$patch->setAt(['tags', 0], 'first');
		});
		[$by_member] = $this->patchQuery(SQLite::NAME, static function (JsonPatch $patch): void {
			$patch->setAt(['tags', '0'], 'first');
		});

		self::assertStringContainsString("'\$.tags[0]'", $by_index);
		self::assertStringContainsString("json_type(client_data, '\$.tags') = 'array'", $by_index);
		self::assertStringContainsString('\'$.tags."0"\'', $by_member);
		self::assertStringContainsString("json_type(client_data, '\$.tags') = 'object'", $by_member);
	}

	/**
	 * PostgreSQL reads one path form as a position or as a member from the value it finds, so a
	 * segment binds as text either way; what the kind decides is the container made for it.
	 */
	public function testPostgreSQLBindsBothKindsAsTextAndMakesTheContainer(): void
	{
		[$by_index, $index_params] = $this->patchQuery(PostgreSQL::NAME, static function (JsonPatch $patch): void {
			$patch->setAt(['tags', 0], 'first');
		});
		[$by_member, $member_params] = $this->patchQuery(PostgreSQL::NAME, static function (JsonPatch $patch): void {
			$patch->setAt(['tags', '0'], 'first');
		});

		self::assertStringContainsString("jsonb_typeof(((client_data)::jsonb #> ARRAY[?]::text[])) = 'array'", $by_index);
		self::assertStringContainsString("jsonb_typeof(((client_data)::jsonb #> ARRAY[?]::text[])) = 'object'", $by_member);
		self::assertSame(['tags', 'tags', 'tags', 'tags', '0', '"first"', 1], $index_params);
		self::assertSame(['tags', 'tags', 'tags', 'tags', '0', '"first"', 1], $member_params);
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

		$data = [];

		foreach (\array_values($qb->getBoundValues()) as $value) {
			if (\is_string($value) && \str_contains($value, 'lang')) {
				$data = (array) \json_decode($value, true);
			}
		}

		self::assertSame(['oz' => ['lang' => 'fr']], $data);
	}

	/**
	 * Builds the UPDATE a patch of the `data` column produces.
	 *
	 * @param array $initial what the row holds, which decides what a digit segment addresses
	 *
	 * @return array{0: string, 1: array}
	 */
	private function patchQuery(string $driver, callable $operations, array $initial = []): array
	{
		$db = self::getNewDbInstanceWithSchema($driver);

		$patch = new JsonPatch($initial);

		$operations($patch);

		$qb = new QBUpdate($db);
		$qb->update('clients')
			->set(['data' => $patch])
			->where($qb->filters()->eq('id', 1));

		$n = new NamedToPositionalParams($qb->getSqlQuery(), $qb->getBoundValues(), $qb->getBoundValuesTypes());

		return [$n->getNewQuery(), $n->getNewParams()];
	}
}
