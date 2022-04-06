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

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\MySQL\MySQLQueryGenerator;
use Gobl\DBAL\Drivers\SQLLite\SQLLite;
use Gobl\DBAL\Drivers\SQLLite\SQLLiteQueryGenerator;
use Gobl\DBAL\Interfaces\RDBMSInterface;
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
			$config = self::getDbConfig($type);

			try {
				$db = self::$rdbms[$type] = Db::createInstanceWithName($type, $config);
				$db->addTablesToNamespace(
					self::TEST_DB_NAMESPACE,
					self::getTablesDefinitions()
				);
			} catch (Throwable $t) {
				gobl_test_log($t);

				throw new GoblRuntimeException('db init failed.', 0, $t);
			}
		}

		return self::$rdbms[$type];
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
