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

namespace Gobl\Tests\DBAL\Drivers;

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Tests\BaseTestCase;

/**
 * Class MySQLDbTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class MySQLDbTest extends BaseTestCase
{
	/**
	 * buildDatabase() on a real MySQL connection produces valid SQL (no exceptions).
	 */
	public function testMySQLBuildDatabase(): void
	{
		$db  = $this->getMySQLDb();
		$sql = $db->getGenerator()->buildDatabase();

		self::assertStringContainsString('CREATE TABLE', $sql);
	}

	/**
	 * buildDatabase() output is stable: two calls produce the same SQL.
	 */
	public function testMySQLBuildDatabaseIsIdempotent(): void
	{
		$db   = $this->getMySQLDb();
		$gen  = $db->getGenerator();
		$sql1 = $gen->buildDatabase();
		$sql2 = $gen->buildDatabase();

		self::assertSame($sql1, $sql2);
	}

	/**
	 * Returns a locked MySQL DB with the full test schema, or skips the test.
	 */
	private function getMySQLDb(): RDBMSInterface
	{
		$user = self::env('GOBL_TEST_MYSQL_USER');

		if ('' === $user) {
			self::markTestSkipped('MySQL tests skipped: set GOBL_TEST_MYSQL_USER (and other GOBL_TEST_MYSQL_* vars) to run.');
		}

		$config = new DbConfig([
			'db_name'     => self::env('GOBL_TEST_MYSQL_DB', 'gobl_test'),
			'db_host'     => self::env('GOBL_TEST_MYSQL_HOST', '127.0.0.1'),
			'db_port'     => (int) self::env('GOBL_TEST_MYSQL_PORT', '3306'),
			'db_user'     => $user,
			'db_password' => self::env('GOBL_TEST_MYSQL_PASSWORD', ''),
		]);

		$db = Db::newInstanceOf(MySQL::NAME, $config);
		$db->ns(self::TEST_DB_NAMESPACE)
			->schema(self::getTablesDefinitions());

		return $db->lock();
	}
}
