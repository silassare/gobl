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

use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Diff\Diff;
use Gobl\DBAL\Drivers\SQLLite\SQLLite;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\MigrationMode;
use Gobl\DBAL\MigrationRunner;
use Gobl\Tests\BaseTestCase;

/**
 * Class MigrationRunnerTest.
 *
 * Tests the MigrationRunner against an in-memory SQLite database.
 * No external server or environment variables are required.
 *
 * @covers \Gobl\DBAL\MigrationRunner
 *
 * @internal
 */
final class MigrationRunnerTest extends BaseTestCase
{
	// ------------------------------------------------------------------
	// bookkeeping table
	// ------------------------------------------------------------------

	public function testMigrationsTableIsCreatedAutomatically(): void
	{
		$db     = $this->newDb();
		$runner = new MigrationRunner($db);
		$runner->add($this->makeMigration(1, 'SELECT 1;', 'SELECT 1;'));

		$runner->migrate();

		// If the table didn't exist, the above would have thrown
		$stmt = $db->select('SELECT COUNT(*) AS cnt FROM ' . MigrationRunner::MIGRATIONS_TABLE);
		$row  = $stmt->fetch();
		self::assertSame(1, (int) $row['cnt']);
	}

	// ------------------------------------------------------------------
	// migrate()
	// ------------------------------------------------------------------

	public function testMigrateReturnsPendingVersions(): void
	{
		$runner  = new MigrationRunner($this->newDb());
		$applied = $runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;'),
		)->migrate();

		self::assertSame([1, 2], $applied);
	}

	public function testMigrateIsIdempotent(): void
	{
		$runner = new MigrationRunner($this->newDb());
		$runner->add($this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'));

		$runner->migrate();
		$second = $runner->migrate();

		self::assertSame([], $second, 'Second migrate() should apply nothing');
	}

	public function testMigrateWithTargetVersionOnlyAppliesUpToThat(): void
	{
		$runner = new MigrationRunner($this->newDb());
		$runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;'),
			$this->makeMigration(3, 'CREATE TABLE t3 (id INTEGER PRIMARY KEY);', 'DROP TABLE t3;'),
		);

		$applied = $runner->migrate(2);

		self::assertSame([1, 2], $applied);

		// Version 3 should still be pending
		$pending = \array_filter($runner->status(), static fn(array $s) => !$s['applied']);
		self::assertCount(1, $pending);
		self::assertSame(3, \array_values($pending)[0]['version']);
	}

	// ------------------------------------------------------------------
	// rollback()
	// ------------------------------------------------------------------

	public function testRollbackRemovesLastApplied(): void
	{
		$runner = new MigrationRunner($this->newDb());
		$runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;'),
		);

		$runner->migrate();
		$rolled = $runner->rollback();

		self::assertSame([2], $rolled);

		// Only version 1 should remain applied
		$applied = \array_filter($runner->status(), static fn(array $s) => $s['applied']);
		self::assertCount(1, $applied);
		self::assertSame(1, \array_values($applied)[0]['version']);
	}

	public function testRollbackMultipleSteps(): void
	{
		$runner = new MigrationRunner($this->newDb());
		$runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;'),
			$this->makeMigration(3, 'CREATE TABLE t3 (id INTEGER PRIMARY KEY);', 'DROP TABLE t3;'),
		);

		$runner->migrate();
		$rolled = $runner->rollback(2);

		self::assertSame([3, 2], $rolled);
	}

	public function testRollbackNothingWhenNoneApplied(): void
	{
		$runner = new MigrationRunner($this->newDb());
		$runner->add($this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'));

		$rolled = $runner->rollback();

		self::assertSame([], $rolled);
	}

	// ------------------------------------------------------------------
	// status()
	// ------------------------------------------------------------------

	public function testStatusReturnsPendingAndApplied(): void
	{
		$runner = new MigrationRunner($this->newDb());
		$runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;', 'Create t1'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;', 'Create t2'),
		);

		$runner->migrate(1);
		$status = $runner->status();

		self::assertCount(2, $status);
		self::assertSame(1, $status[0]['version']);
		self::assertTrue($status[0]['applied']);
		self::assertIsInt($status[0]['applied_at']);
		self::assertSame(2, $status[1]['version']);
		self::assertFalse($status[1]['applied']);
		self::assertNull($status[1]['applied_at']);
	}

	public function testStatusLabelsArePersisted(): void
	{
		$runner = new MigrationRunner($this->newDb());
		$runner->add($this->makeMigration(1, 'SELECT 1;', 'SELECT 1;', 'My Label'));

		$runner->migrate();

		self::assertSame('My Label', $runner->status()[0]['label']);
	}

	// ------------------------------------------------------------------
	// beforeRun() hooks
	// ------------------------------------------------------------------

	public function testBeforeRunFalseSkipsMigration(): void
	{
		$db     = $this->newDb();
		$runner = new MigrationRunner($db);

		$skippable = new class implements MigrationInterface {
			public function getVersion(): int
			{
				return 1;
			}

			public function getLabel(): string
			{
				return 'Skippable';
			}

			public function getTimestamp(): int
			{
				return 1;
			}

			public function getSchema(): array
			{
				return [];
			}

			public function getConfigs(): array
			{
				return [];
			}

			public function up(): string
			{
				return 'CREATE TABLE must_not_exist (id INTEGER PRIMARY KEY);';
			}

			public function down(): string
			{
				return '';
			}

			/** Always skip. */
			public function beforeRun(MigrationMode $mode, string $query): bool|string
			{
				return false;
			}

			public function afterRun(MigrationMode $mode): void {}
		};

		$runner->add($skippable)->migrate();

		// Table should NOT have been created because beforeRun returned false
		$stmt = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name='must_not_exist';");
		self::assertEmpty($stmt->fetchAll());
	}

	public function testBeforeRunStringReplacesQuery(): void
	{
		$db     = $this->newDb();
		$runner = new MigrationRunner($db);

		$intercepted = new class implements MigrationInterface {
			public function getVersion(): int
			{
				return 1;
			}

			public function getLabel(): string
			{
				return 'Intercepted';
			}

			public function getTimestamp(): int
			{
				return 1;
			}

			public function getSchema(): array
			{
				return [];
			}

			public function getConfigs(): array
			{
				return [];
			}

			/** Original query intentionally broken — beforeRun replaces it. */
			public function up(): string
			{
				return 'THIS IS INVALID SQL;';
			}

			public function down(): string
			{
				return 'DROP TABLE intercepted_target;';
			}

			/** Replace with a valid query. */
			public function beforeRun(MigrationMode $mode, string $query): bool|string
			{
				if (MigrationMode::UP === $mode) {
					return 'CREATE TABLE intercepted_target (id INTEGER PRIMARY KEY);';
				}

				return true;
			}

			public function afterRun(MigrationMode $mode): void {}
		};

		$runner->add($intercepted)->migrate();

		$stmt = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name='intercepted_target';");
		self::assertCount(1, $stmt->fetchAll());
	}

	// ------------------------------------------------------------------
	// add() / getMigrations()
	// ------------------------------------------------------------------

	public function testAddSortsByVersion(): void
	{
		$runner = new MigrationRunner($this->newDb());
		$runner->add(
			$this->makeMigration(3, '', ''),
			$this->makeMigration(1, '', ''),
			$this->makeMigration(2, '', ''),
		);

		self::assertSame([1, 2, 3], \array_keys($runner->getMigrations()));
	}

	// ------------------------------------------------------------------
	// Diff::buildMigration()
	// ------------------------------------------------------------------

	public function testDiffBuildMigrationReturnsMigrationInterface(): void
	{
		$db_from = $this->buildSQLiteDb(static function (NamespaceBuilder $ns) {});

		$db_to = $this->buildSQLiteDb(static function (NamespaceBuilder $ns) {
			$ns->table('products', static function (TableBuilder $t) {
				$t->id();
				$t->string('name')->min(1)->max(120);
			});
		});

		$diff      = new Diff($db_from, $db_to);
		$migration = $diff->makeMigrationInstance(42, 'Test build migration');

		self::assertInstanceOf(MigrationInterface::class, $migration);
		self::assertSame(42, $migration->getVersion());
		self::assertSame('Test build migration', $migration->getLabel());
		self::assertIsString($migration->up());
		self::assertIsString($migration->down());
		self::assertNotEmpty($migration->up(), 'UP SQL should not be empty for a schema diff');
	}

	public function testDiffMakeMigrationInstanceIsRunnable(): void
	{
		$db_from = $this->buildSQLiteDb(static function (NamespaceBuilder $ns) {});

		$db_to = $this->buildSQLiteDb(static function (NamespaceBuilder $ns) {
			$ns->table('products', static function (TableBuilder $t) {
				$t->id();
				$t->string('name')->min(1)->max(120);
			});
		});

		// db_to has more tables — migrate its schema onto a fresh SQLite db
		$runner = new MigrationRunner($this->newDb());
		$diff   = new Diff($db_from, $db_to);
		$runner->add($diff->makeMigrationInstance(1, 'Schema migration'));

		$applied = $runner->migrate();

		self::assertSame([1], $applied);
	}
	// ------------------------------------------------------------------
	// Fixtures
	// ------------------------------------------------------------------

	/** Returns a fresh in-memory SQLite RDBMS instance. */
	private function newDb(): SQLLite
	{
		return SQLLite::new(new DbConfig(['db_host' => ':memory:']));
	}

	/**
	 * Builds a locked SQLite DB with tables configured by $setup.
	 *
	 * @param callable(NamespaceBuilder):void $setup
	 *
	 * @return SQLLite
	 */
	private function buildSQLiteDb(callable $setup): SQLLite
	{
		$db = $this->newDb();
		$setup($db->ns('test'));

		return $db->lock();
	}

	/**
	 * Builds a simple anonymous MigrationInterface with the given version and SQL.
	 *
	 * @param int    $version
	 * @param string $up_sql
	 * @param string $down_sql
	 * @param string $label
	 *
	 * @return MigrationInterface
	 */
	private function makeMigration(
		int $version,
		string $up_sql,
		string $down_sql,
		string $label = ''
	): MigrationInterface {
		return new class($version, $up_sql, $down_sql, $label ?: "Migration {$version}") implements MigrationInterface {
			public function __construct(
				private int $version,
				private string $up_sql,
				private string $down_sql,
				private string $label,
			) {}

			public function getVersion(): int
			{
				return $this->version;
			}

			public function getLabel(): string
			{
				return $this->label;
			}

			public function getTimestamp(): int
			{
				return 1_000_000 + $this->version;
			}

			public function getSchema(): array
			{
				return [];
			}

			public function getConfigs(): array
			{
				return [];
			}

			public function up(): string
			{
				return $this->up_sql;
			}

			public function down(): string
			{
				return $this->down_sql;
			}

			public function beforeRun(MigrationMode $mode, string $query): bool|string
			{
				return true;
			}

			public function afterRun(MigrationMode $mode): void {}
		};
	}
}
