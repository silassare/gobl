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

namespace Gobl\Tests;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\MySQL\MySQLQueryGenerator;
use Gobl\DBAL\Drivers\SQLLite\SQLLite;
use Gobl\DBAL\Drivers\SQLLite\SQLLiteQueryGenerator;
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
	 * @param string $type
	 *
	 * @return RDBMSInterface
	 */
	public static function getDb(string $type = self::DEFAULT_RDBMS): RDBMSInterface
	{
		if (!isset(self::$rdbms[$type])) {
			try {
				$db = self::$rdbms[$type] = self::getEmptyDb($type);
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
	 * Returns an empty db instance.
	 *
	 * @param string $type
	 *
	 * @return RDBMSInterface
	 */
	public static function getEmptyDb(string $type = self::DEFAULT_RDBMS): RDBMSInterface
	{
		$config = self::getDbConfig($type);

		return Db::newInstanceOf($type, $config);
	}

	/**
	 * @param string $type
	 *
	 * @return DbConfig
	 */
	public static function getDbConfig(string $type): DbConfig
	{
		$configs = require GOBL_TEST_ROOT . \DIRECTORY_SEPARATOR . 'db.configs.php';

		if (!isset($configs[$type])) {
			throw new InvalidArgumentException(\sprintf('"%s" db config not set.', $type));
		}

		return new DbConfig($configs[$type]);
	}

	public static function getTablesDefinitions(): array
	{
		return require GOBL_TEST_ROOT . \DIRECTORY_SEPARATOR . 'tables.php';
	}

	/**
	 * Returns a sample db instance with some tables.
	 */
	public static function getSampleDB(): RDBMSInterface
	{
		$db = self::getEmptyDb();
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

		$tags = $ns->table('tags', static function (TableBuilder $t) {
			$t->id();
			$t->string('label');
		});

		$taggables = $ns->table('taggables', static function (TableBuilder $t) {
			$t->id();
			$t->foreign('tag_id', 'tags', 'id');
			$t->timestamps();
			$t->morph('taggable');

			$t->belongsTo('tag')
				->from('tags');
		});

		$articles = $ns->table('articles', static function (TableBuilder $t) {
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

	public static function getTablesDiffDefinitions(): array
	{
		return require GOBL_TEST_ROOT . \DIRECTORY_SEPARATOR . 'tables.diff.php';
	}

	/**
	 * @return string[][]
	 */
	public static function getTestRDBMSList(): array
	{
		return [
			MySQL::NAME => [
				'rdbms'     => MySQL::class,
				'generator' => MySQLQueryGenerator::class,
			],
			SQLLite::NAME => [
				'rdbms'     => SQLLite::class,
				'generator' => SQLLiteQueryGenerator::class,
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
	public static function normalizeGeneratedContent(string $content): string
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
	 */
	protected function assertMatchesContentSnapshot(string $snapshotName, string $content): void
	{
		$normalized = self::normalizeGeneratedContent($content);

		$expected_dir  = GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, \dirname($snapshotName));
		$expected_file = GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, $snapshotName) . '.txt';

		$actual_dir  = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, \dirname($snapshotName));
		$actual_file = GOBL_TEST_OUTPUT . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, $snapshotName) . '.txt';

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

		$expected_dir  = GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
			. \str_replace('/', \DIRECTORY_SEPARATOR, \dirname($snapshotName));
		$expected_file = GOBL_TEST_ASSETS . \DIRECTORY_SEPARATOR . 'snapshots' . \DIRECTORY_SEPARATOR
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
		$envFile = \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . '.env.test';

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
