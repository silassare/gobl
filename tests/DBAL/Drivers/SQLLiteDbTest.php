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

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLLite\SQLLite;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Tests\BaseTestCase;

/**
 * Class SQLLiteDbTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class SQLLiteDbTest extends BaseTestCase
{
	/**
	 * buildDatabase() on a real SQLite connection produces valid SQL.
	 */
	public function testSQLiteBuildDatabase(): void
	{
		$db  = $this->getSQLiteDb();
		$sql = $db->getGenerator()->buildDatabase();

		self::assertStringContainsString('CREATE TABLE', $sql);
	}

	/**
	 * Returns a locked SQLite DB with the full test schema, or skips the test.
	 */
	private function getSQLiteDb(): RDBMSInterface
	{
		$file = self::env('GOBL_TEST_SQLITE_FILE');

		if ('' === $file) {
			self::markTestSkipped('SQLite tests skipped: set GOBL_TEST_SQLITE_FILE (use ":memory:" for in-memory DB) to run.');
		}

		$config = new DbConfig([
			'db_name' => $file,
		]);

		$db = Db::newInstanceOf(SQLLite::NAME, $config);
		$db->ns(self::TEST_DB_NAMESPACE)
			->schema(self::getTablesDefinitions());

		return $db->lock();
	}
}
