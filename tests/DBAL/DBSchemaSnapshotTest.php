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
 * Class DBSchemaSnapshotTest.
 *
 * Snapshot tests for MySQL CREATE TABLE and buildDatabase() output.
 * Covers every column type and its major options so that any change in SQL
 * generation is immediately visible as a snapshot diff.
 *
 * To regenerate a snapshot, delete the corresponding .txt file in
 * tests/assets/snapshots/mysql/ and re-run the suite once.
 *
 * @covers \Gobl\DBAL\Drivers\SQLQueryGeneratorBase
 *
 * @internal
 */
final class DBSchemaSnapshotTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// TypeInt
	// -------------------------------------------------------------------------

	/** INT column variants: plain, unsigned, with default, nullable, with min/max. */
	public function testIntVariants(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->int('plain');
			$t->int('unsigned')->unsigned();
			$t->int('with_default')->default(42);
			$t->int('nullable')->nullable();
			$t->int('unsigned_nullable')->unsigned()->nullable();
			$t->int('range')->min(-100)->max(100);
			$t->primary('plain');
		});

		$this->assertDbSchema('schema_int_variants', $db);
	}

	// -------------------------------------------------------------------------
	// TypeBigint
	// -------------------------------------------------------------------------

	/** BIGINT column variants: plain, unsigned, auto-increment PK, nullable. */
	public function testBigintVariants(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();                               // bigint unsigned auto_increment PK
			$t->bigint('plain_bigint');
			$t->bigint('unsigned_bigint')->unsigned();
			$t->bigint('nullable_bigint')->nullable();
		});

		$this->assertDbSchema('schema_bigint_variants', $db);
	}

	// -------------------------------------------------------------------------
	// TypeString
	// -------------------------------------------------------------------------

	/** VARCHAR column: fixed max length. */
	public function testStringVarchar(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->string('name')->min(1)->max(60);
			$t->string('code')->min(3)->max(3);        // CHAR(3)
			$t->string('title')->min(1)->max(255);
			$t->string('optional_note')->max(200)->nullable();
			$t->string('with_default')->max(50)->default('N/A');
			$t->string('status')->max(20)->oneOf(['active', 'inactive', 'pending']);
		});

		$this->assertDbSchema('schema_string_varchar', $db);
	}

	/** TEXT column variants: text, mediumtext, longtext. */
	public function testStringTextVariants(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->string('body');                         // no max = TEXT
			$t->string('medium_body')->medium();        // MEDIUMTEXT
			$t->string('long_body')->long();            // LONGTEXT
			$t->string('nullable_body')->nullable();    // TEXT NULL
		});

		$this->assertDbSchema('schema_string_text', $db);
	}

	// -------------------------------------------------------------------------
	// TypeBool
	// -------------------------------------------------------------------------

	/** BOOL/TINYINT column variants: default true, default false, nullable. */
	public function testBoolVariants(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->bool('active')->default(true);
			$t->bool('archived')->default(false);
			$t->bool('optional_flag')->nullable();
		});

		$this->assertDbSchema('schema_bool_variants', $db);
	}

	// -------------------------------------------------------------------------
	// TypeDecimal
	// -------------------------------------------------------------------------

	/** DECIMAL column variants: plain, unsigned, with precision, with default. */
	public function testDecimalVariants(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->decimal('amount');
			$t->decimal('unsigned_amount')->unsigned();
			$t->decimal('amount_default')->unsigned()->default('0.00');
			$t->decimal('nullable_amount')->nullable();
		});

		$this->assertDbSchema('schema_decimal_variants', $db);
	}

	// -------------------------------------------------------------------------
	// TypeFloat
	// -------------------------------------------------------------------------

	/** FLOAT column variants: plain, unsigned, with min/max, nullable. */
	public function testFloatVariants(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->float('score');
			$t->float('unsigned_score')->unsigned();
			$t->float('percent')->unsigned()->min(0)->max(100);
			$t->float('nullable_score')->nullable();
		});

		$this->assertDbSchema('schema_float_variants', $db);
	}

	// -------------------------------------------------------------------------
	// TypeDate
	// -------------------------------------------------------------------------

	/** Date as BIGINT (timestamp) and auto-timestamp. */
	public function testDateTimestampVariants(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->timestamp('created_at')->auto();       // auto bigint
			$t->timestamp('updated_at')->auto();       // auto bigint
			$t->timestamp('published_at');             // plain bigint, no auto
			$t->timestamp('expires_at')->nullable();   // nullable bigint
		});

		$this->assertDbSchema('schema_date_timestamp', $db);
	}

	/** Date with microsecond precision (DECIMAL(20,6)). */
	public function testDateMicroseconds(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->date('precise_time')->microseconds();
			$t->date('precise_nullable')->microseconds()->nullable();
		});

		$this->assertDbSchema('schema_date_microseconds', $db);
	}

	// -------------------------------------------------------------------------
	// TypeMap
	// -------------------------------------------------------------------------

	/** MAP column (stored as TEXT/JSON string). */
	public function testMapColumn(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->map('data')->default([]);
			$t->map('optional_extras')->nullable();
			$t->map('data_big')->big();
		});

		$this->assertDbSchema('schema_map_column', $db);
	}

	// -------------------------------------------------------------------------
	// TypeList
	// -------------------------------------------------------------------------

	/** LIST column (stored as TEXT/JSON array string). */
	public function testListColumn(): void
	{
		$db = $this->singleTableDb(static function (TableBuilder $t) {
			$t->columnPrefix('t');
			$t->id();
			$t->list('tags')->default([]);
			$t->list('optional_items')->nullable();
		});

		$this->assertDbSchema('schema_list_column', $db);
	}

	// -------------------------------------------------------------------------
	// Constraints
	// -------------------------------------------------------------------------

	/** Tables with Primary Key, Unique Key, Foreign Key constraints. */
	public function testConstraints(): void
	{
		$db = self::getEmptyDb(MySQL::NAME);
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

		$this->assertDbSchema('schema_constraints', $db);
	}

	// -------------------------------------------------------------------------
	// Full test schema
	// -------------------------------------------------------------------------

	/** buildDatabase() on the full production-like test schema (clients/accounts/transactions/currencies). */
	public function testFullTestSchema(): void
	{
		$db = self::getDb(MySQL::NAME);

		$this->assertDbSchema('schema_full_test', $db);
	}

	/** buildDatabase() on the TableBuilder-based sample DB (users/roles/tags/taggables/articles). */
	public function testSampleDbSchema(): void
	{
		$db = self::getSampleDB();

		$this->assertDbSchema('schema_sample_db', $db);
	}
	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds a locked MySQL DB instance with a single table defined by $builder.
	 *
	 * @param callable(TableBuilder):void $builder
	 *
	 * @return RDBMSInterface
	 */
	private function singleTableDb(callable $builder): RDBMSInterface
	{
		$db = self::getEmptyDb(MySQL::NAME);
		$db->ns('test')->table('t', $builder);

		return $db->lock();
	}

	/**
	 * Runs buildDatabase() on $db and snapshots its normalized output.
	 *
	 * @param string         $name
	 * @param RDBMSInterface $db
	 */
	private function assertDbSchema(string $name, RDBMSInterface $db): void
	{
		$this->assertMatchesContentSnapshot(
			'mysql/' . $name,
			$db->getGenerator()->buildDatabase()
		);
	}
}
