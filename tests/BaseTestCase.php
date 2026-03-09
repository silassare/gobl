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

namespace Gobl\Tests;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\MySQL\MySQLQueryGenerator;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQLQueryGenerator;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Drivers\SQLite\SQLiteQueryGenerator;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Relations\LinkType;
use Gobl\Exceptions\GoblRuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Class BaseTestCase.
 *
 * @internal
 */
abstract class BaseTestCase extends TestCase
{
	public const DEFAULT_RDBMS     = MySQL::NAME;
	public const TEST_DB_NAMESPACE = 'Gobl\Tests\Db';

	/** @var RDBMSInterface[] */
	private static array $rdbms = [];

	/**
	 * Loads variables from .env.test into $_ENV if the file exists.
	 * Called once before the test suite via setUpBeforeClass().
	 */
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::loadDotEnvTest();
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		QBUtils::resetIdentifierCounter();
		self::$rdbms = [];
	}

	/**
	 * Returns an empty db instance.
	 *
	 * @param string $driver
	 *
	 * @return RDBMSInterface
	 */
	protected static function getNewDbInstance(string $driver = self::DEFAULT_RDBMS): RDBMSInterface
	{
		return Db::newInstanceOf($driver, self::getDbConfig($driver));
	}

	/**
	 * @param string $type
	 *
	 * @return RDBMSInterface
	 */
	protected static function getNewDbInstanceWithSchema(string $type = self::DEFAULT_RDBMS): RDBMSInterface
	{
		if (!isset(self::$rdbms[$type])) {
			try {
				$db = self::$rdbms[$type] = self::getNewDbInstance($type);

				$db->ns(self::TEST_DB_NAMESPACE)
					->schema(self::getTablesDefinitions());
			} catch (Throwable $t) {
				gobl_test_log($t);

				throw new GoblRuntimeException('db init failed.', null, $t);
			}
		}

		return self::$rdbms[$type];
	}

	/**
	 * Returns a DbConfig instance for the given driver type, populated with connection
	 * parameters from environment variables (or .env.test).
	 *
	 * @param string $type Driver type (MySQL, PostgreSQL, SQLite)
	 *
	 * @return DbConfig
	 */
	protected static function getDbConfig(string $type): DbConfig
	{
		$config = match ($type) {
			MySQL::NAME   => [
				'db_table_prefix' => 'gObL',
				'db_name'         => self::env('GOBL_TEST_MYSQL_DB', 'gobl_test'),
				'db_host'         => self::env('GOBL_TEST_MYSQL_HOST', '127.0.0.1'),
				'db_port'         => (int) self::env('GOBL_TEST_MYSQL_PORT', '3306'),
				'db_user'         => self::env('GOBL_TEST_MYSQL_USER', 'unknown'),
				'db_pass'         => self::env('GOBL_TEST_MYSQL_PASSWORD', ''),
				'db_charset'      => 'utf8mb4',
				'db_collate'      => 'utf8mb4_unicode_ci',
			],
			PostgreSQL::NAME => [
				'db_table_prefix' => 'gObL',

				'db_name'     => self::env('GOBL_TEST_POSTGRESQL_DB', 'gobl_test'),
				'db_host'     => self::env('GOBL_TEST_POSTGRESQL_HOST', '127.0.0.1'),
				'db_port'     => (int) self::env('GOBL_TEST_POSTGRESQL_PORT', '5432'),
				'db_user'     => self::env('GOBL_TEST_POSTGRESQL_USER', 'unknown'),
				'db_pass'     => self::env('GOBL_TEST_POSTGRESQL_PASSWORD', ''),

				'db_charset'      => 'utf8',
				'db_collate'      => 'utf8',
			],
			SQLite::NAME => [
				'db_table_prefix' => 'gObL',
				// SQLite::connect() uses db_host as the file path passed to `sqlite:<path>`.
				// Use ":memory:" for an in-process in-memory DB (default),
				// or an absolute/relative file path to persist the database to disk.
				'db_host'         => self::env('GOBL_TEST_SQLITE_FILE', ':memory:'),
				'db_port'         => '',
				'db_name'         => '',
				'db_user'         => '',
				'db_pass'         => '',
				'db_charset'      => 'utf8',
				'db_collate'      => 'utf8',
			],
			default => throw new InvalidArgumentException("Unsupported driver type: {$type}"),
		};

		return new DbConfig($config);
	}

	protected static function getTablesDefinitions(): array
	{
		return require GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'schemas.php';
	}

	/**
	 * Returns a sample db instance with some tables.
	 */
	protected static function getSampleDB(string $type = self::DEFAULT_RDBMS): RDBMSInterface
	{
		$db = self::getNewDbInstance($type);
		$ns = $db->ns('test');

		$users = $ns->table('users', static function (TableBuilder $t) {
			$t->id();
			$t->string('name');
			$t->softDeletable();
		});

		$roles = $ns->table('roles', static function (TableBuilder $t) {
			$t->id();
			$t->string('title');
			$t->foreign('user_id', 'users', 'id');

			$t->belongsTo('user')
				->from('users');
		});

		$ns->table('tags', static function (TableBuilder $t) {
			$t->id();
			$t->string('label');
		});

		$ns->table('taggables', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('tag_id', 'tags', 'id');
			$t->timestamps();
			$t->morph('taggable');

			$t->belongsTo('tag')
				->from('tags');
		});

		$ns->table('articles', static function (TableBuilder $t) {
			$t->id();
			$t->string('title');
			$t->foreign('user_id', 'users', 'id');
			$t->softDeletable();

			$t->belongsTo('user')
				->from('users');

			$t->hasMany('tags')
				->from('tags')
				->through('taggables', [
					'type'   => LinkType::MORPH,
					'prefix' => 'taggable',
				]);
			$t->hasMany('recently_added_tags')
				->from('tags')
				->through('taggables', [
					'type'    => LinkType::MORPH,
					'prefix'  => 'taggable',
					'filters' => ['created_at', 'gt', '2020-01-01'],
				]);
		});

		$roles->factory(static function (TableBuilder $t) {
			$t->hasMany('users')
				->from('users');
		});

		$users->factory(static function (TableBuilder $t) {
			$t->hasMany('roles')
				->from('roles');
		});

		return $db->lock();
	}

	protected static function getTablesDiffDefinitions(): array
	{
		return require GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'schema.with.changes.php';
	}

	/**
	 * Returns all registered RDBMS drivers as a PHPUnit data-provider array.
	 *
	 * Usage: @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 *
	 * @return array<string, array{0: string}>
	 */
	protected static function allDrivers(): array
	{
		return [
			MySQL::NAME      => [MySQL::NAME, MySQL::class],
			PostgreSQL::NAME => [PostgreSQL::NAME, PostgreSQL::class],
			SQLite::NAME     => [SQLite::NAME, SQLite::class],
		];
	}

	/**
	 * Returns MySQL and PostgreSQL drivers only, excluding SQLite.
	 *
	 * Use this provider for features that SQLite does not support
	 * (e.g. RIGHT JOIN, RETURNING clause, certain JSON operators).
	 *
	 * Usage: @dataProvider Gobl\Tests\BaseTestCase::mysqlAndPostgresDrivers
	 *
	 * @return array<string, array{0: string}>
	 */
	protected static function mysqlAndPostgresDrivers(): array
	{
		$all = self::allDrivers();

		return [
			MySQL::NAME      => $all[MySQL::NAME],
			PostgreSQL::NAME => $all[PostgreSQL::NAME],
		];
	}

	/**
	 * @return string[][]
	 */
	protected static function getTestRDBMSList(): array
	{
		return [
			MySQL::NAME => [
				'rdbms'     => MySQL::class,
				'generator' => MySQLQueryGenerator::class,
			],
			PostgreSQL::NAME => [
				'rdbms'     => PostgreSQL::class,
				'generator' => PostgreSQLQueryGenerator::class,
			],
			SQLite::NAME => [
				'rdbms'     => SQLite::class,
				'generator' => SQLiteQueryGenerator::class,
			],
		];
	}

	/**
	 * Normalizes dynamic content (generated timestamps, version strings) in SQL/file output
	 * so that snapshots remain stable across runs.
	 *
	 * - Replaces ISO-8601 timestamps (e.g. inside `-- Time: ...` comments) with a fixed placeholder.
	 * - Replaces Gobl version comments (e.g. `gobl v2.0.0`) with a fixed placeholder.
	 *
	 * @param string $content Raw output to normalize
	 *
	 * @return string
	 */
	protected static function normalizeGeneratedContent(string $content): string
	{
		// Replace ISO-8601 datetime stamps produced by date(DATE_ATOM)
		$normalized = \preg_replace(
			'/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}/',
			'[GENERATED_AT]',
			$content
		);

		// remove gobl version comments like `gobl v2.0.0`
		return \preg_replace(
			'/gobl v\d+\.\d+\.\d+/',
			'gobl [GOBL_VERSION]',
			$normalized
		);
	}

	/**
	 * Asserts that the given raw (non-query-builder) content matches a stored snapshot.
	 * Dynamic timestamps in the content are normalized before comparison.
	 *
	 * @param string $snapshotName Slash-separated key, e.g. "mysql/db_schema_full"
	 * @param string $content      The raw content to snapshot
	 * @param string $extension    File extension for the snapshot file (default: 'txt')
	 */
	protected function assertMatchesContentSnapshot(string $snapshotName, string $content, string $extension = 'txt'): void
	{
		$normalized = self::normalizeGeneratedContent($content);

		$expected_dir  = GOBL_TEST_SNAPSHOTS . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, \dirname($snapshotName));
		$expected_file = GOBL_TEST_SNAPSHOTS . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, $snapshotName) . '.' . $extension;

		$actual_dir  = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, \dirname($snapshotName));
		$actual_file = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, $snapshotName) . '.' . $extension;

		if (!\is_dir($actual_dir)) {
			\mkdir($actual_dir, 0755, true);
		}

		\file_put_contents($actual_file, $normalized);

		if (!\file_exists($expected_file)) {
			if (!\is_dir($expected_dir)) {
				\mkdir($expected_dir, 0755, true);
			}

			\file_put_contents($expected_file, $normalized);
		}

		// rtrim so that editors adding a trailing newline don't break the comparison
		$expected_content = \rtrim((string) \file_get_contents($expected_file), \PHP_EOL);

		self::assertSame(
			$expected_content,
			// rtrim this too because its may come from a file template
			\rtrim($normalized, \PHP_EOL),
			\sprintf('Snapshot mismatch for "%s". To regenerate, delete: %s', $snapshotName, $expected_file)
		);
	}

	/**
	 * Asserts that the given SQL and bound values match a stored snapshot fixture.
	 *
	 * On the first run (when the fixture file does not exist) the snapshot is
	 * written automatically so subsequent runs can compare against it.
	 *
	 * Snapshot files live under:
	 *   tests/assets/snapshots/{snapshotName}.txt
	 *
	 * To regenerate a snapshot, simply delete its fixture file and re-run the
	 * tests once.
	 *
	 * @param string $snapshotName Slash-separated key, e.g. "mysql/qb_select_all"
	 * @param string $sql          The SQL string produced by the query builder
	 * @param array  $boundValues  The bound parameter values
	 */
	protected function assertMatchesSnapshot(string $snapshotName, string $sql, array $boundValues = []): void
	{
		$content = 'SQL:' . \PHP_EOL . $sql . \PHP_EOL
			. 'BOUND:' . \PHP_EOL . \json_encode($boundValues, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);

		$expected_dir  = GOBL_TEST_SNAPSHOTS . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, \dirname($snapshotName));
		$expected_file = GOBL_TEST_SNAPSHOTS . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, $snapshotName) . '.txt';

		$actual_dir  = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, \dirname($snapshotName));
		$actual_file = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, $snapshotName) . '.txt';

		if (!\is_dir($actual_dir)) {
			\mkdir($actual_dir, 0755, true);
		}

		\file_put_contents($actual_file, $content);

		if (!\file_exists($expected_file)) {
			if (!\is_dir($expected_dir)) {
				\mkdir($expected_dir, 0755, true);
			}

			\file_put_contents($expected_file, $content);
		}

		// rtrim so that editors adding a trailing newline don't break the comparison
		$expected_content = \rtrim((string) \file_get_contents($expected_file), \PHP_EOL);

		self::assertSame(
			$expected_content,
			// rtrim this too because its may come from a file template
			\rtrim($content, \PHP_EOL),
			\sprintf('Snapshot mismatch for "%s". To regenerate, delete: %s', $snapshotName, $expected_file)
		);
	}

	protected static function rmDirRecursive(string $dir): void
	{
		if (!\is_dir($dir)) {
			return;
		}

		foreach (\scandir($dir) as $item) {
			if ('.' === $item || '..' === $item) {
				continue;
			}

			$path = $dir . \DIRECTORY_SEPARATOR . $item;

			if (\is_dir($path)) {
				self::rmDirRecursive($path);
			} else {
				\unlink($path);
			}
		}

		\rmdir($dir);
	}

	/** Recursively lists all file absolute paths under $dir, sorted. */
	protected static function scanFiles(string $dir): array
	{
		$result = [];

		foreach (\scandir($dir) as $item) {
			if ('.' === $item || '..' === $item) {
				continue;
			}

			$path = $dir . \DIRECTORY_SEPARATOR . $item;

			if (\is_dir($path)) {
				foreach (self::scanFiles($path) as $sub) {
					$result[] = $sub;
				}
			} else {
				$result[] = $path;
			}
		}

		\sort($result);

		return $result;
	}

	protected static function loadDotEnvTest(): void
	{
		$envFile = \dirname(__DIR__) . \DIRECTORY_SEPARATOR . '.env.test';

		if (!\file_exists($envFile)) {
			return;
		}

		$lines = \file($envFile, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			$line = \trim($line);

			// Skip comments
			if ('' === $line || \str_starts_with($line, '#')) {
				continue;
			}

			if (\str_contains($line, '=')) {
				[$key, $value]   = \explode('=', $line, 2);
				$key             = \trim($key);
				$value           = \trim($value, " \t\"'");
				$_ENV[$key]      = $value;
				$_SERVER[$key]   = $value;
			}
		}
	}

	protected static function env(string $key, string $default = ''): string
	{
		return (string) ($_ENV[$key] ?? \getenv($key) ?: $default);
	}
}
