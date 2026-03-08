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
use Gobl\DBAL\Indexes\IndexType;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Tests\BaseTestCase;

/**
 * Class DiffSnapshotTest.
 *
 * Cross-driver migration SQL (up + down) snapshot tests produced by the Diff engine.
 * Each test runs for every supported RDBMS so that dialect-specific ALTER / DROP / ADD
 * SQL is captured and guarded by the snapshot.
 *
 * Snapshots are stored under tests/assets/snapshots/{driver}/diff/{scenario}.txt
 *
 * To regenerate a snapshot, delete the corresponding .txt file and re-run the suite once.
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

	/**
	 * Identical DBs produce an empty diff and no migration SQL.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testIdenticalDbs(string $driver): void
	{
		$db = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->id();
				$t->string('name');
			});
		});

		$this->assertDiffSnapshot($driver, 'identical_dbs', $db, $db);
	}

	/**
	 * A brand-new table in $to => up() creates it, down() drops it.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTableAdded(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			// no tables
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('products', static function (TableBuilder $t) {
				$t->id();
				$t->string('name')->min(1)->max(120);
				$t->decimal('price')->unsigned();
			});
		});

		$this->assertDiffSnapshot($driver, 'table_added', $from, $to);
	}

	/**
	 * A table present in $from but absent in $to => up() drops it, down() recreates it.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTableDeleted(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('temporary', static function (TableBuilder $t) {
				$t->id();
				$t->string('label');
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			// table removed
		});

		$this->assertDiffSnapshot($driver, 'table_deleted', $from, $to);
	}

	/**
	 * A new column appears in $to => up() ALTERs to add it, down() drops it.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testColumnAdded(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('posts', static function (TableBuilder $t) {
				$t->id();
				$t->string('title');
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('posts', static function (TableBuilder $t) {
				$t->id();
				$t->string('title');
				$t->string('body')->nullable();   // new column
			});
		});

		$this->assertDiffSnapshot($driver, 'column_added', $from, $to);
	}

	/**
	 * A column present in $from is absent in $to => up() drops it, down() re-adds it.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testColumnDeleted(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('posts', static function (TableBuilder $t) {
				$t->id();
				$t->string('title');
				$t->string('subtitle')->nullable();
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('posts', static function (TableBuilder $t) {
				$t->id();
				$t->string('title');
				// subtitle removed
			});
		});

		$this->assertDiffSnapshot($driver, 'column_deleted', $from, $to);
	}

	/**
	 * A column's type changes (int => bigint) => up() ALTERs type, down() reverts.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testColumnTypeChanged(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('items', static function (TableBuilder $t) {
				$t->columnPrefix('item');
				$t->id();
				$t->int('counter')->unsigned();   // int
				$t->string('code')->min(1)->max(32);
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('items', static function (TableBuilder $t) {
				$t->columnPrefix('item');
				$t->id();
				$t->bigint('counter')->unsigned();   // int => bigint
				$t->string('code')->min(1)->max(32);
			});
		});

		$this->assertDiffSnapshot($driver, 'column_type_changed', $from, $to);
	}

	/**
	 * A column gains a default value it did not have before.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testColumnDefaultAdded(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('items', static function (TableBuilder $t) {
				$t->id();
				$t->string('status')->min(1)->max(20);
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('items', static function (TableBuilder $t) {
				$t->id();
				$t->string('status')->min(1)->max(20)->default('active');
			});
		});

		$this->assertDiffSnapshot($driver, 'column_default_added', $from, $to);
	}

	/**
	 * A non-nullable column becomes nullable.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testColumnNullableChanged(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('profiles', static function (TableBuilder $t) {
				$t->id();
				$t->string('bio')->max(500);
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('profiles', static function (TableBuilder $t) {
				$t->id();
				$t->string('bio')->max(500)->nullable();
			});
		});

		$this->assertDiffSnapshot($driver, 'column_nullable_changed', $from, $to);
	}

	/**
	 * A FK-less schema gains a foreign key constraint.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testForeignKeyAdded(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
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

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
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

		$this->assertDiffSnapshot($driver, 'foreign_key_added', $from, $to);
	}

	/**
	 * An existing FK constraint is dropped.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testForeignKeyDeleted(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
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

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
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

		$this->assertDiffSnapshot($driver, 'foreign_key_deleted', $from, $to);
	}

	/**
	 * A unique key constraint is added to an existing column.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testUniqueKeyAdded(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->columnPrefix('user');
				$t->id();
				$t->string('email')->min(5)->max(200);
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->columnPrefix('user');
				$t->id();
				$t->string('email')->min(5)->max(200);
				$t->unique('email');   // unique key added
			});
		});

		$this->assertDiffSnapshot($driver, 'unique_key_added', $from, $to);
	}

	/**
	 * A plain index (no index type) is added to an existing column.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testIndexAdded(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->columnPrefix('user');
				$t->id();
				$t->string('email')->min(5)->max(200);
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->columnPrefix('user');
				$t->id();
				$t->string('email')->min(5)->max(200);
				$t->index(['email']);   // plain index added
			});
		});

		$this->assertDiffSnapshot($driver, 'index_added', $from, $to);
	}

	/**
	 * A plain index is removed from an existing column.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testIndexDeleted(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->columnPrefix('user');
				$t->id();
				$t->string('email')->min(5)->max(200);
				$t->index(['email']);   // plain index present
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('users', static function (TableBuilder $t) {
				$t->columnPrefix('user');
				$t->id();
				$t->string('email')->min(5)->max(200);
				// index removed
			});
		});

		$this->assertDiffSnapshot($driver, 'index_deleted', $from, $to);
	}

	/**
	 * Index with explicit type: shared BTREE, MySQL FULLTEXT, PostgreSQL GIN.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testIndexWithType(string $driver): void
	{
		$from = $this->buildDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('articles', static function (TableBuilder $t) {
				$t->columnPrefix('article');
				$t->id();
				$t->string('title')->min(1)->max(255);
				$t->string('tags')->min(0)->max(500)->nullable();
			});
		});

		$to = $this->buildDb($driver, static function (NamespaceBuilder $ns) use ($driver) {
			$ns->table('articles', static function (TableBuilder $t) use ($driver) {
				$t->columnPrefix('article');
				$t->id();
				$t->string('title')->min(1)->max(255);
				$t->string('tags')->min(0)->max(500)->nullable();
				// shared BTREE on title
				$t->index(['title'], IndexType::BTREE);

				// driver-specific typed index on tags
				if ('mysql' === $driver) {
					$t->index(['tags'], IndexType::MYSQL_FULLTEXT);
				} elseif ('pgsql' === $driver) {
					$t->index(['tags'], IndexType::PGSQL_GIN);
				}
			});
		});

		$this->assertDiffSnapshot($driver, 'index_with_type', $from, $to);
	}

	/**
	 * Full real-world diff: the production test schema (assets/schemas.php) migrated to
	 * the modified schema (assets/schema.with.changes.php). Heaviest integration scenario.
	 *
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testFullTablesDiff(string $driver): void
	{
		$from = self::getNewDbInstanceWithSchema($driver);

		$to = self::getNewDbInstance($driver);
		$to->ns('Test')
			->schema(self::getTablesDiffDefinitions());

		$to->lock();

		$this->assertDiffSnapshot($driver, 'full_tables_diff', $from, $to);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds and locks a DB of the given $driver with a single namespace configured by $setup.
	 *
	 * @param string                          $driver
	 * @param callable(NamespaceBuilder):void $setup
	 *
	 * @return RDBMSInterface
	 */
	private function buildDb(string $driver, callable $setup): RDBMSInterface
	{
		$db = self::getNewDbInstance($driver);
		$ns = $db->ns('test');
		$setup($ns);

		return $db->lock();
	}

	/**
	 * Generates migration SQL (up + down) from a Diff between $from and $to,
	 * and snapshots "UP:\n{sql}\n\nDOWN:\n{sql}" under {driver}/diff/{name}.
	 *
	 * The migration PHP file is written to tests/tmp/output/ using a name derived
	 * from $driver and $snapshotName to avoid opcode-cache collisions between tests.
	 *
	 * @param string         $driver
	 * @param string         $snapshotName
	 * @param RDBMSInterface $from
	 * @param RDBMSInterface $to
	 */
	private function assertDiffSnapshot(
		string $driver,
		string $snapshotName,
		RDBMSInterface $from,
		RDBMSInterface $to
	): void {
		$diff = new Diff($from, $to);

		if (!$diff->hasChanges()) {
			$this->assertMatchesContentSnapshot(
				$driver . '/diff/' . $snapshotName,
				'UP:' . \PHP_EOL . '(no changes)' . \PHP_EOL . \PHP_EOL . 'DOWN:' . \PHP_EOL . '(no changes)'
			);

			return;
		}

		// Use driver-prefixed filename to avoid opcode-cache collisions between tests and drivers
		$safe_name = \preg_replace('/[^a-zA-Z0-9_]/', '_', $driver . '_' . $snapshotName);
		$temp_file = GOBL_TEST_OUTPUT . '/migration_' . $safe_name . '.php';
		\file_put_contents($temp_file, (string) $diff->generateMigrationFile(1));

		/** @var MigrationInterface $migration */
		$migration = require $temp_file;

		$content = 'UP:' . \PHP_EOL . $migration->up()
			. \PHP_EOL . \PHP_EOL . 'DOWN:' . \PHP_EOL . $migration->down();

		$this->assertMatchesContentSnapshot($driver . '/diff/' . $snapshotName, $content);
	}
}
