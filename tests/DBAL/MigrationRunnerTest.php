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

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Diff\Diff;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\MigrationMode;
use Gobl\DBAL\MigrationRunner;
use Gobl\Tests\BaseTestCase;
use Throwable;

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
	/**
	 * Drop all tables that tests in this class may have created, so that each
	 * test starts with a clean state in live databases (MySQL, PostgreSQL, SQLite file).
	 */
	protected function tearDown(): void
	{
		$tables = [MigrationRunner::MIGRATIONS_TABLE, 't1', 't2', 't3', 'must_not_exist', 'intercepted_target', 'products'];

		if (\extension_loaded('pdo_mysql')) {
			try {
				$db = $this->getNewDbInstance(MySQL::NAME);

				foreach ($tables as $table) {
					try {
						$db->execute("DROP TABLE IF EXISTS `{$table}`");
					} catch (Throwable) {
					}
				}
			} catch (Throwable) {
			}
		}

		if (\extension_loaded('pdo_pgsql')) {
			try {
				$db = $this->getNewDbInstance(PostgreSQL::NAME);

				foreach ($tables as $table) {
					try {
						$db->execute("DROP TABLE IF EXISTS \"{$table}\"");
					} catch (Throwable) {
					}
				}
			} catch (Throwable) {
			}
		}

		if (\extension_loaded('pdo_sqlite')) {
			try {
				$db = $this->getNewDbInstance(SQLite::NAME);

				foreach ($tables as $table) {
					try {
						$db->execute("DROP TABLE IF EXISTS \"{$table}\"");
					} catch (Throwable) {
					}
				}
			} catch (Throwable) {
			}
		}

		parent::tearDown();
	}
	// ------------------------------------------------------------------
	// bookkeeping table
	// ------------------------------------------------------------------

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testMigrationsTableIsCreatedAutomatically(string $driver): void
	{
		$db     = $this->getNewDbInstance($driver);
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

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testMigrateReturnsPendingVersions(string $driver): void
	{
		$runner  = new MigrationRunner($this->getNewDbInstance($driver));
		$applied = $runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;'),
		)->migrate();

		self::assertSame([1, 2], $applied);
	}

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testMigrateIsIdempotent(string $driver): void
	{
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
		$runner->add($this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'));

		$runner->migrate();
		$second = $runner->migrate();

		self::assertSame([], $second, 'Second migrate() should apply nothing');
	}

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testMigrateWithTargetVersionOnlyAppliesUpToThat(string $driver): void
	{
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
		$runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;'),
			$this->makeMigration(3, 'CREATE TABLE t3 (id INTEGER PRIMARY KEY);', 'DROP TABLE t3;'),
		);

		$applied = $runner->migrate(2);

		self::assertSame([1, 2], $applied);

		// Version 3 should still be pending
		$pending = \array_filter($runner->status(), static fn (array $s) => !$s['applied']);
		self::assertCount(1, $pending);
		self::assertSame(3, \array_values($pending)[0]['version']);
	}

	// ------------------------------------------------------------------
	// rollback()
	// ------------------------------------------------------------------

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testRollbackRemovesLastApplied(string $driver): void
	{
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
		$runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;'),
		);

		$runner->migrate();
		$rolled = $runner->rollback();

		self::assertSame([2], $rolled);

		// Only version 1 should remain applied
		$applied = \array_filter($runner->status(), static fn (array $s) => $s['applied']);
		self::assertCount(1, $applied);
		self::assertSame(1, \array_values($applied)[0]['version']);
	}

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testRollbackMultipleSteps(string $driver): void
	{
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
		$runner->add(
			$this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'),
			$this->makeMigration(2, 'CREATE TABLE t2 (id INTEGER PRIMARY KEY);', 'DROP TABLE t2;'),
			$this->makeMigration(3, 'CREATE TABLE t3 (id INTEGER PRIMARY KEY);', 'DROP TABLE t3;'),
		);

		$runner->migrate();
		$rolled = $runner->rollback(2);

		self::assertSame([3, 2], $rolled);
	}

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testRollbackNothingWhenNoneApplied(string $driver): void
	{
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
		$runner->add($this->makeMigration(1, 'CREATE TABLE t1 (id INTEGER PRIMARY KEY);', 'DROP TABLE t1;'));

		$rolled = $runner->rollback();

		self::assertSame([], $rolled);
	}

	// ------------------------------------------------------------------
	// status()
	// ------------------------------------------------------------------

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testStatusReturnsPendingAndApplied(string $driver): void
	{
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
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

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testStatusLabelsArePersisted(string $driver): void
	{
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
		$runner->add($this->makeMigration(1, 'SELECT 1;', 'SELECT 1;', 'My Label'));

		$runner->migrate();

		self::assertSame('My Label', $runner->status()[0]['label']);
	}

	// ------------------------------------------------------------------
	// beforeRun() hooks
	// ------------------------------------------------------------------

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testBeforeRunFalseSkipsMigration(string $driver): void
	{
		$db     = $this->getNewDbInstance($driver);
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
		self::assertFalse($this->tableExists($db, 'must_not_exist'), 'Table must_not_exist should NOT have been created');
	}

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testBeforeRunStringReplacesQuery(string $driver): void
	{
		$db     = $this->getNewDbInstance($driver);
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

			/** Original query intentionally broken, beforeRun replaces it. */
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

		self::assertTrue($this->tableExists($db, 'intercepted_target'), 'Table intercepted_target should have been created');
	}

	// ------------------------------------------------------------------
	// add() / getMigrations()
	// ------------------------------------------------------------------

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testAddSortsByVersion(string $driver): void
	{
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
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

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testDiffBuildMigrationReturnsMigrationInterface(string $driver): void
	{
		$db_from = $this->runWithTestDb($driver, static function (NamespaceBuilder $ns) {});

		$db_to = $this->runWithTestDb($driver, static function (NamespaceBuilder $ns) {
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

	/**
	 * @dataProvider Gobl\Tests\DBAL\MigrationRunnerTest::availableDrivers
	 */
	public function testDiffMakeMigrationInstanceIsRunnable(string $driver): void
	{
		$db_from = $this->runWithTestDb($driver, static function (NamespaceBuilder $ns) {});

		$db_to = $this->runWithTestDb($driver, static function (NamespaceBuilder $ns) {
			$ns->table('products', static function (TableBuilder $t) {
				$t->id();
				$t->string('name')->min(1)->max(120);
			});
		});

		// db_to has more tables, migrate its schema onto a fresh SQLite db
		$runner = new MigrationRunner($this->getNewDbInstance($driver));
		$diff   = new Diff($db_from, $db_to);
		$runner->add($diff->makeMigrationInstance(1, 'Schema migration'));

		$applied = $runner->migrate();

		self::assertSame([1], $applied);
	}
	// ------------------------------------------------------------------
	// Helpers / infrastructure
	// ------------------------------------------------------------------

	/**
	 * Data provider that returns only drivers whose PDO extension is loaded.
	 * This prevents test errors when e.g. pdo_pgsql is not installed.
	 *
	 * @return array<string, array{0: string, 1: class-string}>
	 */
	public static function availableDrivers(): array
	{
		return \array_filter(parent::allDrivers(), static function (array $row): bool {
			return match ($row[0]) {
				PostgreSQL::NAME => \extension_loaded('pdo_pgsql'),
				MySQL::NAME      => \extension_loaded('pdo_mysql'),
				default          => true,
			};
		});
	}

	/**
	 * Returns true if the given table exists in the live database.
	 * Uses a driver-agnostic query (information_schema for MySQL/PostgreSQL,
	 * sqlite_master for SQLite).
	 */
	private function tableExists(RDBMSInterface $db, string $table): bool
	{
		try {
			if ($db instanceof SQLite) {
				$stmt = $db->select("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}';");
			} else {
				$stmt = $db->select("SELECT table_name FROM information_schema.tables WHERE table_name='{$table}';");
			}

			return \count($stmt->fetchAll()) > 0;
		} catch (Throwable) {
			return false;
		}
	}

	// ------------------------------------------------------------------
	// Fixtures
	// ------------------------------------------------------------------

	/**
	 * Builds a locked DB with tables configured by $setup.
	 *
	 * @param callable(NamespaceBuilder):void $setup
	 *
	 * @return RDBMSInterface
	 */
	private function runWithTestDb(string $type, callable $setup): RDBMSInterface
	{
		$db = $this->getNewDbInstance($type);

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
