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

use Gobl\DBAL\Column;
use Gobl\DBAL\Db;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Drivers\SQLLite\SQLLite;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;

/**
 * Class DBTest.
 *
 * @covers \Gobl\DBAL\Db
 *
 * @internal
 */
final class DBTest extends BaseTestCase
{
	public function testInstantiate(): void
	{
		static::assertInstanceOf(MySQL::class, Db::createInstanceWithName(MySQL::NAME, self::getDbConfig(MySQL::NAME)));

		static::assertInstanceOf(SQLLite::class, Db::createInstanceWithName(SQLLite::NAME, self::getDbConfig(SQLLite::NAME)));
	}

	public function testParseColumnReference(): void
	{
		static::assertSame([
			'clone'  => false,
			'table'  => 'users',
			'column' => 'id',
		], Db::parseColumnReference('ref:users.id'));

		static::assertSame([
			'clone'  => true,
			'table'  => 'users',
			'column' => 'id',
		], Db::parseColumnReference('cp:users.id'));

		static::assertNull(Db::parseColumnReference('cp:users.'));
	}

	public function testIsColumnReference(): void
	{
		static::assertTrue(Db::isColumnReference('ref:users.id'));

		static::assertTrue(Db::isColumnReference('cp:users.id'));

		static::assertFalse(Db::isColumnReference('cp:users.'));
	}

	public function testHasTable(): void
	{
		$db       = self::getDb();
		$tables   = self::getTablesDefinitions();
		$found    = [];
		$expected = [];

		foreach ($tables as $name => $props) {
			$found[$name]    = $db->hasTable($name);
			$expected[$name] = true;
		}

		static::assertSame($expected, $found);

		$found    = [];
		$expected = [];
		foreach ($tables as $name => $props) {
			$full_name            = $db->getTable($name)
				->getFullName();
			$found[$full_name]    = $db->hasTable($full_name);
			$expected[$full_name] = true;
		}

		static::assertSame($expected, $found);
	}

	public function testAssertHasTable(): void
	{
		$db   = self::getDb();
		$name = \uniqid('table_', false);

		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage(\sprintf('The table "%s" is not defined.', $name));
		$db->assertHasTable($name);
	}

	public function testGetTable(): void
	{
		$db   = self::getDb();
		$name = \uniqid('table_', false);

		static::assertNull($db->getTable($name));

		$tables = self::getTablesDefinitions();
		$first  = \key($tables);

		static::assertInstanceOf(Table::class, $table = $db->getTable($first));
		static::assertSame($table, $db->getTable($table->getFullName()));
	}

	public function testGetType(): void
	{
		$types = \array_keys(self::getTestRDBMSList());
		foreach ($types as $type) {
			$db = self::getDb($type);
			static::assertSame($type, $db->getType());
		}
	}

	public function testAddTable(): void
	{
		$db = self::getDb();
		$db->addTable((new Table('users', 'pr'))->addColumn(new Column('id')));

		static::assertTrue($db->hasTable('users'));
		static::assertTrue($db->hasTable('pr_users'));
		static::assertFalse($db->hasTable($db->getConfig()
			->getDbTablePrefix() . 'users'));

		$db->addTable($member = (new Table('members'))->addColumn(new Column('id')));

		static::assertTrue($db->hasTable('members'));
		static::assertTrue($db->hasTable($db->getConfig()
			->getDbTablePrefix() . '_members'));

		static::assertSame($member, $db->getTable('members'));

		$this->expectException(DBALException::class);
		$this->expectExceptionMessage('The table name conflict with an existing table name or full name: "members".');

		$db->addTable((new Table('members'))->addColumn(new Column('id')));
	}
}
