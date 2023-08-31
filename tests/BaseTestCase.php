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
	public const TEST_DB_NAMESPACE = 'Gobl\\Tests\\Db';
	public const DEFAULT_RDBMS     = MySQL::NAME;

	/** @var RDBMSInterface[] */
	private static array $rdbms = [];

	protected function tearDown(): void
	{
		parent::tearDown();
		self::$rdbms = [];
	}

	/**
	 * @param string $type
	 *
	 * @return \Gobl\DBAL\Interfaces\RDBMSInterface
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
	 * @return \Gobl\DBAL\Interfaces\RDBMSInterface
	 */
	public static function getEmptyDb(string $type = self::DEFAULT_RDBMS): RDBMSInterface
	{
		$config = self::getDbConfig($type);

		return Db::createInstanceOf($type, $config);
	}

	/**
	 * Returns a sample db instance with some tables.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public static function getSampleDB(): RDBMSInterface
	{
		$db = self::getEmptyDb();
		$ns = $db->ns('test');

		$users = $ns->table('users', static function (TableBuilder $t) {
			$t->id();
			$t->string('name');
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
			$t->morph('taggable');

			$t->belongsTo('tag')
				->from('tags');
		});

		$articles = $ns->table('articles', static function (TableBuilder $t) {
			$t->id();
			$t->string('title');
			$t->foreign('user_id', 'users', 'id');

			$t->belongsTo('user')
				->from('users');

			$t->hasMany('tags')
				->from('tags')
				->through('taggables', [
					'type'   => LinkType::MORPH,
					'prefix' => 'taggable',
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

	/**
	 * @param string $type
	 *
	 * @return \Gobl\DBAL\DbConfig
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

	public static function getTablesDiffDefinitions(): array
	{
		return require GOBL_TEST_ROOT . \DIRECTORY_SEPARATOR . 'tables.diff.php';
	}

	/**
	 * @return \string[][]
	 */
	public static function getTestRDBMSList(): array
	{
		return [
			MySQL::NAME   => [
				'rdbms'     => MySQL::class,
				'generator' => MySQLQueryGenerator::class,
			],
			SQLLite::NAME => [
				'rdbms'     => SQLLite::class,
				'generator' => SQLLiteQueryGenerator::class,
			],
		];
	}
}
