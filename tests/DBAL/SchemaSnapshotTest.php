<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Tests\BaseTestCase;

/**
 * Class SchemaSnapshotTest.
 *
 * Cross-driver CREATE TABLE / buildDatabase() snapshot tests.
 *
 * Every test marked with @dataProvider Gobl\Tests\BaseTestCase::allDrivers runs identically
 * for MySQL, PostgreSQL, and SQLite showing at a glance how each driver maps
 * the same schema definition to different DDL syntax.
 *
 * Snapshots are stored under tests/assets/snapshots/{driver}/{scenario}.txt
 *
 * To regenerate a snapshot, delete the corresponding .txt file and re-run the suite once.
 *
 * @covers \Gobl\DBAL\Drivers\MySQL\MySQLQueryGenerator
 * @covers \Gobl\DBAL\Drivers\PostgreSQL\PostgreSQLQueryGenerator
 * @covers \Gobl\DBAL\Drivers\SQLLite\SQLLiteQueryGenerator
 * @covers \Gobl\DBAL\Drivers\SQLQueryGeneratorBase
 *
 * @internal
 */
final class SchemaSnapshotTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// INT / BIGINT
	// -------------------------------------------------------------------------

	/**
	 * Integer and bigint column variants: auto-increment PK, plain, with default, nullable.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testIntAndBigintVariants(string $driver): void
	{
		$db = $this->singleTableDb($driver, static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();                                      // bigint unsigned auto_increment PK
			$t->int('plain_int');
			$t->int('signed_int')->default(42);
			$t->int('nullable_int')->nullable();
			$t->bigint('plain_bigint');
			$t->bigint('unsigned_bigint')->unsigned();
		});

		$this->assertDbSchema($driver . '/schema_int_bigint_variants', $db);
	}

	// -------------------------------------------------------------------------
	// TypeString
	// -------------------------------------------------------------------------

	/**
	 * String column variants: fixed-width, unbounded text, default values, nullable.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testStringVariants(string $driver): void
	{
		$db = $this->singleTableDb($driver, static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->string('name')->min(1)->max(60);
			$t->string('code')->min(3)->max(3);           // CHAR(3) on MySQL
			$t->string('title')->min(1)->max(255);
			$t->string('optional_note')->max(200)->nullable();
			$t->string('with_default')->max(50)->default('N/A');
			$t->string('body');                            // TEXT / unbounded
			$t->string('nullable_body')->nullable();
		});

		$this->assertDbSchema($driver . '/schema_string_variants', $db);
	}

	/**
	 * MySQL-specific text length variants: MEDIUMTEXT and LONGTEXT.
	 * Only meaningful on MySQL; not run for other drivers.
	 */
	public function testStringTextVariants(): void
	{
		$db = $this->singleTableDb(MySQL::NAME, static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->string('body');                           // TEXT
			$t->string('medium_body')->medium();          // MEDIUMTEXT
			$t->string('long_body')->long();              // LONGTEXT
			$t->string('nullable_body')->nullable();
		});

		$this->assertDbSchema('mysql/schema_string_text_variants', $db);
	}

	// -------------------------------------------------------------------------
	// BOOL / FLOAT / DECIMAL
	// -------------------------------------------------------------------------

	/**
	 * Bool, float, and decimal column variants in a single table.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testBoolFloatDecimalVariants(string $driver): void
	{
		$db = $this->singleTableDb($driver, static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->bool('active')->default(true);
			$t->bool('archived')->default(false);
			$t->bool('optional_flag')->nullable();
			$t->float('score');
			$t->float('unsigned_score')->unsigned();
			$t->float('nullable_score')->nullable();
			$t->decimal('amount');
			$t->decimal('unsigned_amount')->unsigned();
			$t->decimal('amount_default')->unsigned()->default('0.00');
			$t->decimal('nullable_amount')->nullable();
		});

		$this->assertDbSchema($driver . '/schema_bool_float_decimal_variants', $db);
	}

	// -------------------------------------------------------------------------
	// TypeDate
	// -------------------------------------------------------------------------

	/**
	 * Date / timestamp column variants: auto-timestamp, plain, nullable, microseconds.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDateVariants(string $driver): void
	{
		$db = $this->singleTableDb($driver, static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->timestamp('created_at')->auto();          // auto bigint
			$t->timestamp('updated_at')->auto();          // auto bigint
			$t->timestamp('published_at');                // plain bigint, no auto
			$t->timestamp('expires_at')->nullable();      // nullable bigint
			$t->date('precise_time')->microseconds();     // DECIMAL(20,6)
			$t->date('precise_nullable')->microseconds()->nullable();
		});

		$this->assertDbSchema($driver . '/schema_date_variants', $db);
	}

	// -------------------------------------------------------------------------
	// TypeMap / TypeList
	// -------------------------------------------------------------------------

	/**
	 * Map and list columns (stored as serialized text / JSON strings).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testMapAndListColumns(string $driver): void
	{
		$db = $this->singleTableDb($driver, static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->map('data')->default([]);
			$t->map('optional_extras')->nullable();
			$t->map('data_big')->big();
			$t->list('list_big')->big();
			$t->list('tags')->default([]);
			$t->list('optional_items')->nullable();
		});

		$this->assertDbSchema($driver . '/schema_map_list_columns', $db);
	}

	// -------------------------------------------------------------------------
	// Constraints (FK + unique)
	// -------------------------------------------------------------------------

	/**
	 * Tables with Primary Key, Unique Key, and Foreign Key constraints.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testConstraints(string $driver): void
	{
		$db = self::getNewDbInstance($driver);
		$ns = $db->ns('test');

		$ns->table('authors', static function (TableBuilder $t) {
			$t->columnPrefix('author');
			$t->id();
			$t->string('email')->max(255);
			$t->string('name')->max(100);
			$t->unique('email');
		});

		$ns->table('posts', static function (TableBuilder $t) {
			$t->columnPrefix('post');
			$t->id();
			$t->foreign('author_id', 'authors', 'id');
			$t->string('title')->min(1)->max(200);
			$t->string('slug')->max(200);
			$t->bool('published')->default(false);
			$t->unique('slug');
		});

		$db->lock();

		$this->assertDbSchema($driver . '/schema_constraints', $db);
	}

	// -------------------------------------------------------------------------
	// Full schemas
	// -------------------------------------------------------------------------

	/**
	 * buildDatabase() on the full production-like test schema (clients/accounts/transactions/currencies).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testFullTestSchema(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);

		$this->assertDbSchema($driver . '/schema_full_test', $db);
	}

	/**
	 * buildDatabase() on the TableBuilder-based sample DB (users/roles/tags/taggables/articles).
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testSampleDbSchema(string $driver): void
	{
		$db = self::getSampleDB($driver);

		$this->assertDbSchema($driver . '/schema_sample_db', $db);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds a locked DB instance of the given $driver with a single table defined by $builder.
	 *
	 * @param string                      $driver
	 * @param callable(TableBuilder):void $builder
	 *
	 * @return RDBMSInterface
	 */
	private function singleTableDb(string $driver, callable $builder): RDBMSInterface
	{
		$db = self::getNewDbInstance($driver);
		$db->ns('test')->table('t', $builder);

		return $db->lock();
	}

	/**
	 * Runs buildDatabase() on $db and snapshots its normalized output.
	 *
	 * @param string         $name Slash-separated snapshot key, e.g. "mysql/schema_int_bigint_variants"
	 * @param RDBMSInterface $db
	 */
	private function assertDbSchema(string $name, RDBMSInterface $db): void
	{
		$this->assertMatchesContentSnapshot(
			$name,
			$db->getGenerator()->buildDatabase()
		);
	}
}
