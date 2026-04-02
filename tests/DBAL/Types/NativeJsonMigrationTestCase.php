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

namespace Gobl\Tests\DBAL\Types;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Diff\Diff;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\MigrationRunner;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;
use PDOException;
use Throwable;

/**
 * Class NativeJsonMigrationTestCase.
 *
 * Abstract base for migration integration tests that verify data-integrity guarantees
 * when migrating json/map/list columns from TEXT storage (native_json=false) to the
 * native DB JSON type (native_json=true).
 *
 * MySQL:      TEXT -> JSON   (ALTER TABLE t CHANGE col col json ...)
 * PostgreSQL: TEXT -> JSONB  (ALTER TABLE t ALTER COLUMN col TYPE jsonb USING to_jsonb(col::text))
 *
 * Each concrete subclass targets one driver (MySQL, PostgreSQL) and provides
 * getDriverName().  Setup sequence per class:
 *   1. Establish a live DB connection via getNewDbInstance().
 *   2. Per-test: create the migration table, run migrations, assert, drop tables.
 *
 * @internal
 */
abstract class NativeJsonMigrationTestCase extends BaseTestCase
{
	/** Short table name; the DB prefix 'gObL' will be prepended -> gObL_json_mig */
	private const TABLE = 'json_mig';

	/** @var null|RDBMSInterface Shared live-DB connection for all tests in the class */
	protected static ?RDBMSInterface $db = null;

	/** @var bool true when setUpBeforeClass() could not establish a DB connection */
	protected static bool $setupFailed = false;

	// -------------------------------------------------------------------------
	// PHPUnit lifecycle
	// -------------------------------------------------------------------------

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		static::$setupFailed = false;

		try {
			static::$db = static::getNewDbInstance(static::getDriverName());
		} catch (Throwable $t) {
			static::$setupFailed = true;
			gobl_log(\sprintf('Error building live DB for %s: %s', static::getDriverName(), $t->getMessage()));
		}
	}

	public static function tearDownAfterClass(): void
	{
		static::$db = null;
		parent::tearDownAfterClass();
	}

	protected function setUp(): void
	{
		parent::setUp();

		if (static::$setupFailed || null === static::$db) {
			self::markTestSkipped(
				\sprintf('%s live database is not available (check env vars).', static::getDriverName())
			);
		}
	}

	protected function tearDown(): void
	{
		if (null !== static::$db) {
			$gen = static::$db->getGenerator();
			$t   = $gen->quoteIdentifier('gObL_' . self::TABLE);
			$mig = $gen->quoteIdentifier(MigrationRunner::MIGRATIONS_TABLE);

			try {
				static::$db->execute("DROP TABLE IF EXISTS {$t}");
			} catch (Throwable) {
			}

			try {
				static::$db->execute("DROP TABLE IF EXISTS {$mig}");
			} catch (Throwable) {
			}
		}

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * Valid JSON objects and arrays stored as TEXT are preserved byte-for-byte
	 * (semantically) after the column type is changed to native JSON/JSONB.
	 */
	public function testValidJsonDataSurvivesTextToNativeJsonMigration(): void
	{
		$db_text   = $this->buildSchema(false);
		$db_native = $this->buildSchema(true);
		$live_db   = static::$db;

		// Step 1: create the table with TEXT-backed json/map/list columns
		$runner = new MigrationRunner($live_db);
		$runner->add(
			(new Diff($this->emptyDb(), $db_text))->makeMigrationInstance(1, 'Create table')
		)->migrate();

		// Step 2: insert a row with valid JSON, bypassing Gobl ORM validation
		// so that the test directly controls the stored TEXT content.
		$qb_ins = new QBInsert($db_text);
		$qb_ins->into(self::TABLE)->values([
			'payload' => '{"key":"val","num":42}',
			'meta'    => '{"nested":true}',
			'tags'    => '[1,"two",null]',
		]);
		$live_db->insert($qb_ins->getSqlQuery(), $qb_ins->getBoundValues(), $qb_ins->getBoundValuesTypes());

		// Step 3: migrate TEXT columns to native JSON
		$runner->add(
			(new Diff($db_text, $db_native))->makeMigrationInstance(2, 'Text to native JSON')
		)->migrate();

		// Step 4: read back and verify the data survived unchanged (JSON-decoded to
		// normalise any whitespace differences between drivers).
		$qb_sel = new QBSelect($db_native);
		$qb_sel->from(self::TABLE, 't')->select('t', ['payload', 'meta', 'tags'])->limit(1);
		$row = $live_db->select($qb_sel->getSqlQuery(), $qb_sel->getBoundValues(), $qb_sel->getBoundValuesTypes())->fetch();

		self::assertSame(
			['key' => 'val', 'num' => 42],
			\json_decode((string) $row['payload'], true),
			'json column: object data must be preserved after TEXT -> native JSON migration'
		);
		self::assertSame(
			['nested' => true],
			\json_decode((string) $row['meta'], true),
			'map column: object data must be preserved after TEXT -> native JSON migration'
		);
		self::assertSame(
			[1, 'two', null],
			\json_decode((string) $row['tags'], true),
			'list column: array data must be preserved after TEXT -> native JSON migration'
		);
	}

	/**
	 * NULL values in nullable columns survive the TEXT -> native JSON migration
	 * as NULL (not as the string "null" or any other representation).
	 */
	public function testNullableColumnNullSurvivesTextToNativeJsonMigration(): void
	{
		$db_text   = $this->buildSchema(false, nullable: true);
		$db_native = $this->buildSchema(true, nullable: true);
		$live_db   = static::$db;

		$runner = new MigrationRunner($live_db);
		$runner->add(
			(new Diff($this->emptyDb(), $db_text))->makeMigrationInstance(1, 'Create table')
		)->migrate();

		$qb_ins = new QBInsert($db_text);
		$qb_ins->into(self::TABLE)->values(['payload' => null, 'meta' => null, 'tags' => null]);
		$live_db->insert($qb_ins->getSqlQuery(), $qb_ins->getBoundValues(), $qb_ins->getBoundValuesTypes());

		$runner->add(
			(new Diff($db_text, $db_native))->makeMigrationInstance(2, 'Text to native JSON')
		)->migrate();

		$qb_sel = new QBSelect($db_native);
		$qb_sel->from(self::TABLE, 't')->select('t', ['payload', 'meta', 'tags'])->limit(1);
		$row = $live_db->select($qb_sel->getSqlQuery(), $qb_sel->getBoundValues(), $qb_sel->getBoundValuesTypes())->fetch();

		self::assertNull(
			$row['payload'],
			'json column: NULL must remain NULL after TEXT -> native JSON migration'
		);
		self::assertNull(
			$row['meta'],
			'map column: NULL must remain NULL after TEXT -> native JSON migration'
		);
		self::assertNull(
			$row['tags'],
			'list column: NULL must remain NULL after TEXT -> native JSON migration'
		);
	}

	/**
	 * If any existing row contains a TEXT value that is not valid JSON,
	 * the migration to a native JSON column type must fail.
	 *
	 * This is the primary data-integrity risk when enabling native JSON on an
	 * existing table: the database engine validates every row on ALTER and
	 * rejects the operation if any value cannot be parsed as JSON.
	 */
	public function testInvalidJsonInExistingRowsCausesMigrationFailure(): void
	{
		$db_text   = $this->buildSchema(false, nullable: true);
		$db_native = $this->buildSchema(true, nullable: true);
		$live_db   = static::$db;

		$runner = new MigrationRunner($live_db);
		$runner->add(
			(new Diff($this->emptyDb(), $db_text))->makeMigrationInstance(1, 'Create table')
		)->migrate();

		// Insert a row whose `payload` column contains an invalid JSON string.
		// Because the column is TEXT at this point, the DB accepts any string.
		$qb_ins = new QBInsert($db_text);
		$qb_ins->into(self::TABLE)->values([
			'payload' => 'NOT VALID JSON',
			'meta'    => '{"ok":true}',
			'tags'    => '[]',
		]);
		$live_db->insert($qb_ins->getSqlQuery(), $qb_ins->getBoundValues(), $qb_ins->getBoundValuesTypes());

		// The migration to native JSON must fail because the engine validates
		// every row and rejects any that cannot be parsed as JSON.
		$this->expectException(PDOException::class);

		$runner->add(
			(new Diff($db_text, $db_native))->makeMigrationInstance(2, 'Text to native JSON')
		)->migrate();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Must return the driver name (e.g. MySQL::NAME, PostgreSQL::NAME).
	 */
	abstract protected static function getDriverName(): string;

	/**
	 * Returns a locked DB whose schema contains a single table with json/map/list
	 * columns using TEXT storage (native_json=false) or native JSON (native_json=true),
	 * optionally nullable.
	 *
	 * Uses a fresh DB instance for schema representation only (no SQL execution).
	 */
	private function buildSchema(bool $native, bool $nullable = false): RDBMSInterface
	{
		$db = static::getNewDbInstance(static::getDriverName());
		$db->ns('test')->table(self::TABLE, static function (TableBuilder $t) use ($native, $nullable) {
			$t->id();
			$json = $t->json('payload', $native);
			$map  = $t->map('meta', $native);
			$list = $t->list('tags', $native);

			if ($nullable) {
				$json->nullable();
				$map->nullable();
				$list->nullable();
			} else {
				$json->default('{}');
				$map->default([]);
				$list->default([]);
			}
		});

		return $db->lock();
	}

	/**
	 * Returns a locked empty DB (no tables) used as the "before" state when
	 * generating a CREATE TABLE migration.
	 *
	 * Uses a fresh DB instance for schema representation only (no SQL execution).
	 */
	private function emptyDb(): RDBMSInterface
	{
		return static::getNewDbInstance(static::getDriverName())->lock();
	}
}
