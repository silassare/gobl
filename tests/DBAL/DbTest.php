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
 * Class DbTest.
 *
 * @covers \Gobl\DBAL\Db
 *
 * @internal
 */
final class DbTest extends BaseTestCase
{
	public function testInstantiate(): void
	{
		self::assertInstanceOf(MySQL::class, Db::newInstanceOf(MySQL::NAME, self::getDbConfig(MySQL::NAME)));

		self::assertInstanceOf(SQLLite::class, Db::newInstanceOf(SQLLite::NAME, self::getDbConfig(SQLLite::NAME)));
	}

	public function testParseColumnReference(): void
	{
		self::assertSame([
			'clone'  => false,
			'table'  => 'users',
			'column' => 'id',
		], Db::parseColumnReference('ref:users.id'));

		self::assertSame([
			'clone'  => true,
			'table'  => 'users',
			'column' => 'id',
		], Db::parseColumnReference('cp:users.id'));

		self::assertNull(Db::parseColumnReference('cp:users.'));
	}

	public function testIsColumnReference(): void
	{
		self::assertTrue(Db::isColumnReference('ref:users.id'));

		self::assertTrue(Db::isColumnReference('cp:users.id'));

		self::assertFalse(Db::isColumnReference('cp:users.'));
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

		self::assertSame($expected, $found);

		$found    = [];
		$expected = [];
		foreach ($tables as $name => $props) {
			$full_name            = $db->getTable($name)
				->getFullName();
			$found[$full_name]    = $db->hasTable($full_name);
			$expected[$full_name] = true;
		}

		self::assertSame($expected, $found);
	}

	public function testAssertHasTable(): void
	{
		$db   = self::getEmptyDb();
		$name = \uniqid('table_', false);

		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage(\sprintf('The table "%s" is not defined.', $name));
		$db->assertHasTable($name);
	}

	public function testGetTable(): void
	{
		$db   = self::getDb();
		$name = \uniqid('table_', false);

		self::assertNull($db->getTable($name));

		$tables = self::getTablesDefinitions();
		$first  = \key($tables);

		self::assertInstanceOf(Table::class, $table = $db->getTable($first));
		self::assertSame($table, $db->getTable($table->getFullName()));
	}

	public function testGetType(): void
	{
		$types = \array_keys(self::getTestRDBMSList());
		foreach ($types as $type) {
			$db = self::getEmptyDb($type);
			self::assertSame($type, $db->getType());
		}
	}

	public function testAddTable(): void
	{
		$db         = self::getEmptyDb();
		$tbl_prefix = $db->getConfig()
			->getDbTablePrefix();

		$db->addTable((new Table('users', 'pr'))->addColumn(new Column('id')));

		self::assertTrue($db->hasTable('users'));
		self::assertTrue($db->hasTable('pr_users'));
		self::assertFalse($db->hasTable($tbl_prefix . '_users'));

		$db->addTable($member = (new Table('members'))->addColumn(new Column('id')));

		self::assertTrue($db->hasTable('members'));
		self::assertTrue($db->hasTable($tbl_prefix . '_members'));

		self::assertSame($member, $db->getTable('members'));

		$this->expectException(DBALException::class);
		$this->expectExceptionMessage('The table name conflict with an existing table name or full name: "members".');

		$db->addTable((new Table('members'))->addColumn(new Column('id')));
	}

	/**
	 * @throws DBALException
	 */
	public function testAddTables(): void
	{
		$db         = self::getEmptyDb();
		$tbl_prefix = $db->getConfig()
			->getDbTablePrefix();

		$custom_namespace  = 'App\Db\Custom';
		$foo_bar_namespace = 'App\Foo\Bar';

		// without desired namespace
		$db->loadSchema([
			'users'   => (new Table('users', 'ur'))->addColumn(new Column('id'))
				->setNamespace($custom_namespace),
			'members' => (new Table('members'))->addColumn(new Column('id')),
		]);

		// with desired namespace
		$db->loadSchema([
			'tags'  => [
				'namespace'     => $custom_namespace,
				'singular_name' => 'tag',
				'plural_name'   => 'tags',
				'columns'       => [
					'id' => [
						'type'           => 'int',
						'unsigned'       => true,
						'auto_increment' => true,
					],
				],
			],
			'posts' => (new Table('posts'))->addColumn(new Column('id'))
				->setNamespace($foo_bar_namespace),
		], $foo_bar_namespace);

		self::assertTrue($db->hasTable('users'));
		self::assertTrue($db->hasTable('ur_users'));
		self::assertFalse($db->hasTable($tbl_prefix . '_users'));
		self::assertTrue($db->hasTable('tags'));

		self::assertTrue($db->hasTable('members'));
		self::assertTrue($db->hasTable($tbl_prefix . '_members'));

		self::assertSame(['users', 'members', 'tags', 'posts'], \array_keys($db->getTables()));
		self::assertSame(['users'], \array_keys($db->getTables($custom_namespace)));

		self::assertSame(
			Table::TABLE_DEFAULT_NAMESPACE,
			$db->getTable('members')
				->getNamespace()
		);

		self::assertSame(
			$custom_namespace,
			$db->getTable('users')
				->getNamespace()
		);

		self::assertSame(
			$foo_bar_namespace,
			$db->getTable('posts')
				->getNamespace()
		);

		self::assertSame(['tags', 'posts'], \array_keys($db->getTables($foo_bar_namespace)));
	}

	public function testGetTables(): void
	{
		$db = self::getEmptyDb();
		$db->addTable((new Table('users', 'pr'))->addColumn(new Column('id')));
		$db->addTable((new Table('members'))->addColumn(new Column('id')));

		$expected = [
			'users',
			'members',
		];

		self::assertSame($expected, \array_keys($db->getTables()));
	}
}
