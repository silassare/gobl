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
use Gobl\DBAL\Drivers\PostgreSQL\PostgreSQL;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Tests\BaseTestCase;

/**
 * Class PostgreSQLDbTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class PostgreSQLDbTest extends BaseTestCase
{
	/**
	 * buildDatabase() on a real PostgreSQL connection produces valid SQL (no exceptions).
	 */
	public function testPostgreSQLBuildDatabase(): void
	{
		$db  = $this->getPostgreSQLDb();
		$sql = $db->getGenerator()->buildDatabase();

		self::assertStringContainsString('CREATE TABLE', $sql);
	}

	/**
	 * buildDatabase() output is stable: two calls produce the same SQL.
	 */
	public function testPostgreSQLBuildDatabaseIsIdempotent(): void
	{
		$db   = $this->getPostgreSQLDb();
		$gen  = $db->getGenerator();
		$sql1 = $gen->buildDatabase();
		$sql2 = $gen->buildDatabase();

		self::assertSame($sql1, $sql2);
	}

	/**
	 * Returns a locked PostgreSQL DB with the full test schema, or skips the test.
	 */
	private function getPostgreSQLDb(): RDBMSInterface
	{
		$config = self::getDbConfig(PostgreSQL::NAME);

		if ('' === $config->getDbUser()) {
			self::markTestSkipped('PostgreSQL tests skipped: set GOBL_TEST_POSTGRESQL_USER (and other GOBL_TEST_POSTGRESQL_* vars) to run.');
		}

		$db = Db::newInstanceOf(PostgreSQL::NAME, $config);

		$db->ns(self::TEST_DB_NAMESPACE)
			->schema(self::getTablesDefinitions());

		return $db->lock();
	}
}
