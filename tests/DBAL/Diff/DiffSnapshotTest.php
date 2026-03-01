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

namespace Gobl\Tests\DBAL\Diff;

use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Diff\Diff;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Tests\BaseTestCase;

/**
 * Class DiffSnapshotTest.
 *
 * Snapshot tests for MySQL migration SQL (up + down) produced by the Diff engine.
 * Each test defines a "before" and "after" schema state, computes the diff, generates
 * the migration, and snapshots the resulting up() / down() SQL strings.
 *
 * To regenerate a snapshot, delete the corresponding .txt file in
 * tests/assets/snapshots/mysql/diff/ and re-run the suite once.
 *
 * @covers \Gobl\DBAL\Diff\Diff
 *
 * @internal
 */
final class DiffSnapshotTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/** Identical DBs produce an empty diff and no migration SQL. */
	public function testIdenticalDbs(): void
	{
		$db = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->id();
				$t->string('name');
				$t->primary('id');
			});
		});

		$this->assertDiffSnapshot('identical_dbs', $db, $db);
	}

	/** A brand-new table in $to → up() creates it, down() drops it. */
	public function testTableAdded(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			// no tables
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('products', static function (TableBuilder $t) {
				$t->id();
				$t->string('name')->min(1)->max(120);
				$t->decimal('price')->unsigned();
				$t->primary('id');
			});
		});

		$this->assertDiffSnapshot('table_added', $from, $to);
	}

	/** A table present in $from but absent in $to → up() drops it, down() recreates it. */
	public function testTableDeleted(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('temporary', static function (TableBuilder $t) {
				$t->id();
				$t->string('label');
				$t->primary('id');
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			// table removed
		});

		$this->assertDiffSnapshot('table_deleted', $from, $to);
	}

	/** A new column appears in $to → up() ALTERs to add it, down() drops it. */
	public function testColumnAdded(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('posts', static function (TableBuilder $t) {
				$t->id();
				$t->string('title');
				$t->primary('id');
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('posts', static function (TableBuilder $t) {
				$t->id();
				$t->string('title');
				$t->string('body')->nullable();   // new column
				$t->primary('id');
			});
		});

		$this->assertDiffSnapshot('column_added', $from, $to);
	}

	/** A column present in $from is absent in $to → up() drops it, down() re-adds it. */
	public function testColumnDeleted(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('posts', static function (TableBuilder $t) {
				$t->id();
				$t->string('title');
				$t->string('subtitle')->nullable();
				$t->primary('id');
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('posts', static function (TableBuilder $t) {
				$t->id();
				$t->string('title');
				// subtitle removed
				$t->primary('id');
			});
		});

		$this->assertDiffSnapshot('column_deleted', $from, $to);
	}

	/** A column's type changes (int → bigint) → up() ALTERs type, down() reverts. */
	public function testColumnTypeChanged(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('items', static function (TableBuilder $t) {
				$t->columnPrefix('item');
				$t->id();
				$t->int('counter')->unsigned();   // int
				$t->string('code')->min(1)->max(32);
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('items', static function (TableBuilder $t) {
				$t->columnPrefix('item');
				$t->id();
				$t->bigint('counter')->unsigned();   // int → bigint
				$t->string('code')->min(1)->max(32);
			});
		});

		$this->assertDiffSnapshot('column_type_changed', $from, $to);
	}

	/** A column gains a default value it did not have before. */
	public function testColumnDefaultAdded(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('items', static function (TableBuilder $t) {
				$t->id();
				$t->string('status')->min(1)->max(20);
				$t->primary('id');
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('items', static function (TableBuilder $t) {
				$t->id();
				$t->string('status')->min(1)->max(20)->default('active');
				$t->primary('id');
			});
		});

		$this->assertDiffSnapshot('column_default_added', $from, $to);
	}

	/** A non-nullable column becomes nullable. */
	public function testColumnNullableChanged(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('profiles', static function (TableBuilder $t) {
				$t->id();
				$t->string('bio')->max(500);
				$t->primary('id');
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('profiles', static function (TableBuilder $t) {
				$t->id();
				$t->string('bio')->max(500)->nullable();
				$t->primary('id');
			});
		});

		$this->assertDiffSnapshot('column_nullable_changed', $from, $to);
	}

	/** A FK-less schema gains a foreign key constraint. */
	public function testForeignKeyAdded(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('categories', static function (TableBuilder $t) {
				$t->columnPrefix('category');
				$t->id();
				$t->string('name');
			});

			$ns->table('products', static function (TableBuilder $t) {
				$t->columnPrefix('product');
				$t->id();
				$t->string('name');
				$t->bigint('category_id')->unsigned();   // no FK yet
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('categories', static function (TableBuilder $t) {
				$t->columnPrefix('category');
				$t->id();
				$t->string('name');
			});

			$ns->table('products', static function (TableBuilder $t) {
				$t->columnPrefix('product');
				$t->id();
				$t->string('name');
				$t->foreign('category_id', 'categories', 'id');   // FK added
			});
		});

		$this->assertDiffSnapshot('foreign_key_added', $from, $to);
	}

	/** An existing FK constraint is dropped. */
	public function testForeignKeyDeleted(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('categories', static function (TableBuilder $t) {
				$t->columnPrefix('category');
				$t->id();
				$t->string('name');
			});

			$ns->table('products', static function (TableBuilder $t) {
				$t->columnPrefix('product');
				$t->id();
				$t->string('name');
				$t->foreign('category_id', 'categories', 'id');   // FK present
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('categories', static function (TableBuilder $t) {
				$t->columnPrefix('category');
				$t->id();
				$t->string('name');
			});

			$ns->table('products', static function (TableBuilder $t) {
				$t->columnPrefix('product');
				$t->id();
				$t->string('name');
				$t->bigint('category_id')->unsigned();   // FK removed
			});
		});

		$this->assertDiffSnapshot('foreign_key_deleted', $from, $to);
	}

	/** A unique key constraint is added to an existing column. */
	public function testUniqueKeyAdded(): void
	{
		$from = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->columnPrefix('user');
				$t->id();
				$t->string('email')->min(5)->max(200);
			});
		});

		$to = $this->buildDb(static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->columnPrefix('user');
				$t->id();
				$t->string('email')->min(5)->max(200);
				$t->unique('email');   // unique key added
			});
		});

		$this->assertDiffSnapshot('unique_key_added', $from, $to);
	}

	/**
	 * Full real-world diff: the production test schema (tables.php) migrated to
	 * the modified schema (tables.diff.php). This is the heaviest integration
	 * scenario, any regression in diff/SQL generation will be caught here.
	 */
	public function testFullTablesDiff(): void
	{
		$from = self::getDb(MySQL::NAME);

		$to = self::getEmptyDb(MySQL::NAME);
		$to->ns('Test')
			->schema(self::getTablesDiffDefinitions());

		$to->lock();

		$this->assertDiffSnapshot('full_tables_diff', $from, $to);
	}
	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds and locks a MySQL DB with a single namespace configured by $setup.
	 *
	 * @param callable(NamespaceBuilder):void $setup
	 *
	 * @return RDBMSInterface
	 */
	private function buildDb(callable $setup): RDBMSInterface
	{
		$db = self::getEmptyDb(MySQL::NAME);
		$ns = $db->ns('test');
		$setup($ns);

		return $db->lock();
	}

	/**
	 * Generates migration SQL (up + down) from a Diff and snapshots
	 * UP:\n{sql}\n\nDOWN:\n{sql} under mysql/diff/{name}.
	 *
	 * The migration PHP file is written to tests/tmp/output/ using a name derived
	 * from $snapshotName to avoid opcode-cache collisions between tests.
	 *
	 * @param string         $snapshotName
	 * @param RDBMSInterface $from
	 * @param RDBMSInterface $to
	 */
	private function assertDiffSnapshot(string $snapshotName, RDBMSInterface $from, RDBMSInterface $to): void
	{
		$diff = new Diff($from, $to);

		if (!$diff->hasChanges()) {
			$this->assertMatchesContentSnapshot(
				'mysql/diff/' . $snapshotName,
				'UP:' . \PHP_EOL . '(no changes)' . \PHP_EOL . \PHP_EOL . 'DOWN:' . \PHP_EOL . '(no changes)'
			);

			return;
		}

		// Write to a unique filename so PHP's require cache never returns stale data
		$safe_name = \preg_replace('/[^a-zA-Z0-9_]/', '_', $snapshotName);
		$temp_file = GOBL_TEST_OUTPUT . '/migration_' . $safe_name . '.php';
		\file_put_contents($temp_file, (string) $diff->generateMigrationFile(1));

		/** @var MigrationInterface $migration */
		$migration = require $temp_file;

		$content = 'UP:' . \PHP_EOL . $migration->up()
			. \PHP_EOL . \PHP_EOL . 'DOWN:' . \PHP_EOL . $migration->down();

		$this->assertMatchesContentSnapshot('mysql/diff/' . $snapshotName, $content);
	}
}
