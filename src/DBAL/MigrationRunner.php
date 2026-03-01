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

namespace Gobl\DBAL;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;

/**
 * Class MigrationRunner.
 *
 * Tracks and applies {@see MigrationInterface} files against a live database,
 * persisting state in a `_gobl_migrations` bookkeeping table.
 */
class MigrationRunner
{
	/** The name of the internal bookkeeping table. */
	public const MIGRATIONS_TABLE = '_gobl_migrations';

	/**
	 * Registered migrations, keyed and sorted by version number.
	 *
	 * @var array<int, MigrationInterface>
	 */
	private array $migrations = [];

	/**
	 * MigrationRunner constructor.
	 *
	 * @param RDBMSInterface $db The database to run migrations against
	 */
	public function __construct(private readonly RDBMSInterface $db) {}

	/**
	 * Registers one or more migration instances with this runner.
	 *
	 * @param MigrationInterface ...$migrations
	 *
	 * @return $this
	 */
	public function add(MigrationInterface ...$migrations): static
	{
		foreach ($migrations as $m) {
			$this->migrations[$m->getVersion()] = $m;
		}

		\ksort($this->migrations);

		return $this;
	}

	/**
	 * Loads migration instances from PHP files and registers them.
	 *
	 * Each file is expected to return a {@see MigrationInterface} instance when included.
	 *
	 * @param string ...$paths Absolute paths to migration PHP files
	 *
	 * @return $this
	 */
	public function addFromFile(string ...$paths): static
	{
		foreach ($paths as $path) {
			$m = include $path;

			if ($m instanceof MigrationInterface) {
				$this->add($m);
			}
		}

		return $this;
	}

	/**
	 * Returns all registered migrations, sorted ascending by version.
	 *
	 * @return array<int, MigrationInterface>
	 */
	public function getMigrations(): array
	{
		return $this->migrations;
	}

	/**
	 * Applies all pending migrations, or only those up to and including
	 * the given target version.
	 *
	 * @param null|int $target_version Stop after this version (inclusive). null = apply all.
	 *
	 * @return int[] Versions that were applied during this call
	 */
	public function migrate(?int $target_version = null): array
	{
		$this->ensureMigrationsTable();

		$applied = \array_flip($this->getAppliedVersions());
		$result  = [];

		foreach ($this->migrations as $version => $m) {
			if (null !== $target_version && $version > $target_version) {
				break;
			}

			if (!isset($applied[$version])) {
				$this->runMigration($m, MigrationMode::UP);
				$result[] = $version;
			}
		}

		return $result;
	}

	/**
	 * Rolls back the last `$steps` applied migrations, in reverse order.
	 *
	 * @param int $steps Number of migrations to roll back (default 1)
	 *
	 * @return int[] Versions that were rolled back during this call
	 */
	public function rollback(int $steps = 1): array
	{
		$this->ensureMigrationsTable();

		$applied     = \array_reverse($this->getAppliedVersions());
		$to_rollback = \array_slice($applied, 0, $steps);
		$result      = [];

		foreach ($to_rollback as $version) {
			if (isset($this->migrations[$version])) {
				$this->runMigration($this->migrations[$version], MigrationMode::DOWN);
				$result[] = $version;
			}
		}

		return $result;
	}

	/**
	 * Returns the status of every registered migration.
	 *
	 * @return array<int, array{version: int, label: string, applied: bool, applied_at: null|int}> Ordered ascending by version
	 */
	public function status(): array
	{
		$this->ensureMigrationsTable();

		$applied_map = $this->getAppliedMap();
		$list        = [];

		foreach ($this->migrations as $version => $m) {
			$list[] = [
				'version'    => $version,
				'label'      => $m->getLabel(),
				'applied'    => isset($applied_map[$version]),
				'applied_at' => $applied_map[$version] ?? null,
			];
		}

		return $list;
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * Creates the `_gobl_migrations` bookkeeping table if it does not exist.
	 */
	private function ensureMigrationsTable(): void
	{
		$table = self::MIGRATIONS_TABLE;
		$sql   = <<<SQL
			CREATE TABLE IF NOT EXISTS {$table} (
				version    INTEGER NOT NULL,
				label      TEXT    NOT NULL DEFAULT '',
				applied_at INTEGER NOT NULL,
				PRIMARY KEY (version)
			)
			SQL;

		$this->db->execute(\trim(\preg_replace('/\s+/', ' ', $sql)));
	}

	/**
	 * Returns the sorted list of applied version numbers (ascending).
	 *
	 * @return int[]
	 */
	private function getAppliedVersions(): array
	{
		$table = self::MIGRATIONS_TABLE;
		$stmt  = $this->db->select("SELECT version FROM {$table} ORDER BY version ASC");

		return \array_map(static fn(array $row) => (int) $row['version'], $stmt->fetchAll());
	}

	/**
	 * Returns a map of applied version => applied_at timestamp.
	 *
	 * @return array<int, int>
	 */
	private function getAppliedMap(): array
	{
		$table = self::MIGRATIONS_TABLE;
		$stmt  = $this->db->select("SELECT version, applied_at FROM {$table}");
		$map   = [];

		foreach ($stmt->fetchAll() as $row) {
			$map[(int) $row['version']] = (int) $row['applied_at'];
		}

		return $map;
	}

	/**
	 * Executes a single migration in the requested direction.
	 *
	 * @param MigrationInterface $m
	 * @param MigrationMode      $mode
	 *
	 * @throws DBALException
	 */
	private function runMigration(MigrationInterface $m, MigrationMode $mode): void
	{
		$query  = match ($mode) {
			MigrationMode::UP   => $m->up(),
			MigrationMode::DOWN => $m->down(),
			MigrationMode::FULL => $this->db->loadSchema($m->getSchema())->getGenerator()->buildDatabase(),
		};

		$result = $m->beforeRun($mode, $query);

		if (false === $result) {
			// Migration chose to skip itself
			return;
		}

		if (\is_string($result)) {
			$query = $result;
		}

		// Execute the migration SQL (DDL-safe via executeMulti)
		if ('' !== \trim($query)) {
			$this->db->executeMulti($query);
		}

		// Track the state change
		if (MigrationMode::UP === $mode) {
			$this->markApplied($m);
		} else {
			$this->markRolledBack($m);
		}

		$m->afterRun($mode);
	}

	/**
	 * Records a migration as applied in the bookkeeping table.
	 */
	private function markApplied(MigrationInterface $m): void
	{
		$table = self::MIGRATIONS_TABLE;

		$this->db->execute(
			"INSERT INTO {$table} (version, label, applied_at) VALUES (?, ?, ?)",
			[$m->getVersion(), $m->getLabel(), \time()]
		);
	}

	/**
	 * Removes a migration from the bookkeeping table (rolled back).
	 */
	private function markRolledBack(MigrationInterface $m): void
	{
		$table = self::MIGRATIONS_TABLE;

		$this->db->execute(
			"DELETE FROM {$table} WHERE version = ?",
			[$m->getVersion()]
		);
	}
}
