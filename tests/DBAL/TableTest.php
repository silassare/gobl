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

namespace Gobl\Tests\DBAL;

use Gobl\DBAL\Column;
use Gobl\DBAL\Indexes\IndexType;
use Gobl\DBAL\Table;
use Gobl\Tests\BaseTestCase;
use InvalidArgumentException;
use PHPUtils\Exceptions\RuntimeException;

/**
 * Class TableTest.
 *
 * @covers \Gobl\DBAL\Table
 *
 * @internal
 */
final class TableTest extends BaseTestCase
{
	public function testConstructor(): void
	{
		$name   = 'users';
		$prefix = 'pr';

		$table = new Table($name, $prefix);

		self::assertSame($name, $table->getName());
		self::assertSame($prefix, $table->getPrefix());
		self::assertSame($prefix . '_' . $name, $table->getFullName());
		self::assertSame($name . '_entity', $table->getSingularName());
		self::assertSame($name . '_entities', $table->getPluralName());

		$table = new Table($name);

		self::assertSame($name, $table->getName());
		self::assertSame('', $table->getPrefix());
		self::assertSame($name, $table->getFullName());
		self::assertSame($name . '_entity', $table->getSingularName());
		self::assertSame($name . '_entities', $table->getPluralName());
	}

	public function testCustomSingularAndPluralNames(): void
	{
		$table = new Table('people');
		$table->setSingularName('person');
		$table->setPluralName('persons');

		self::assertSame('person', $table->getSingularName());
		self::assertSame('persons', $table->getPluralName());
	}

	public function testSetNamespace(): void
	{
		$table = new Table('items');

		self::assertSame(Table::TABLE_DEFAULT_NAMESPACE, $table->getNamespace());

		$table->setNamespace('My\App\Db');

		self::assertSame('My\App\Db', $table->getNamespace());
	}

	public function testSetCharsetAndCollate(): void
	{
		$table = new Table('items');

		self::assertNull($table->getCharset());
		self::assertNull($table->getCollate());

		$table->setCharset('utf8mb4');
		$table->setCollate('utf8mb4_unicode_ci');

		self::assertSame('utf8mb4', $table->getCharset());
		self::assertSame('utf8mb4_unicode_ci', $table->getCollate());
	}

	public function testAddAndGetColumn(): void
	{
		$table  = new Table('users', 'u');
		$column = new Column('name', 'u');

		$table->addColumn($column);

		self::assertTrue($table->hasColumn('name'));
		self::assertFalse($table->hasColumn('unknown'));
		self::assertSame($column, $table->getColumn('name'));
		self::assertNull($table->getColumn('unknown'));
	}

	public function testGetColumnsNameList(): void
	{
		$table = new Table('users', 'u');
		$table->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));
		$table->addColumn((new Column('secret', 'u'))->setPrivate());
		$table->addColumn((new Column('token', 'u'))->setSensitive());
		$table->addColumn(new Column('name', 'u'));

		// By default all columns are included
		self::assertCount(4, $table->getColumnsNameList());

		// Exclude private columns
		$withoutPrivate = $table->getColumnsNameList(false);
		self::assertNotContains('secret', $withoutPrivate);
		self::assertContains('name', $withoutPrivate);

		// Exclude sensitive columns
		$withoutSensitive = $table->getColumnsNameList(true, false);
		self::assertNotContains('token', $withoutSensitive);
		self::assertContains('name', $withoutSensitive);
	}

	public function testGetPrivateAndSensitiveColumns(): void
	{
		$table = new Table('users', 'u');
		$table->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));
		$table->addColumn((new Column('password', 'u'))->setPrivate());
		$table->addColumn((new Column('token', 'u'))->setSensitive());

		$private   = $table->getPrivateColumns();
		$sensitive = $table->getSensitiveColumns();

		// columns are stored keyed by short name
		self::assertArrayHasKey('password', $private);
		self::assertArrayHasKey('token', $sensitive);
	}

	public function testIsPrivate(): void
	{
		$table = new Table('users');

		self::assertFalse($table->isPrivate());

		$table->setPrivate();
		self::assertTrue($table->isPrivate());

		$table->setPrivate(false);
		self::assertFalse($table->isPrivate());
	}

	public function testLockPreventsChanges(): void
	{
		$table = new Table('users', 'u');
		$table->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));
		$table->lock();

		$this->expectException(RuntimeException::class);
		$table->addColumn(new Column('name', 'u'));
	}

	public function testSetColumnPrefix(): void
	{
		$table = new Table('users');
		$table->setColumnPrefix('user');

		self::assertSame('user', $table->getColumnPrefix());
	}

	public function testPrimaryKeyConstraint(): void
	{
		$table = new Table('users', 'u');
		$table->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));
		$table->addColumn(new Column('name', 'u'));

		self::assertFalse($table->hasPrimaryKeyConstraint());

		$pk = $table->addPrimaryKeyConstraint(['id']);

		self::assertTrue($table->hasPrimaryKeyConstraint());
		self::assertSame($pk, $table->getPrimaryKeyConstraint());
		self::assertTrue($table->isPrimaryKey(['u_id']));
		self::assertFalse($table->isPrimaryKey(['u_name']));
		self::assertTrue($table->isPartOfPrimaryKey($table->getColumn('id')));
		self::assertFalse($table->isPartOfPrimaryKey($table->getColumn('name')));
	}

	public function testUniqueKeyConstraint(): void
	{
		$table = new Table('users', 'u');
		$table->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));
		$table->addColumn(new Column('email', 'u'));

		self::assertFalse($table->hasUniqueKeyConstraint());

		$table->addUniqueKeyConstraint(['email']);

		self::assertTrue($table->hasUniqueKeyConstraint());
		self::assertTrue($table->isUniqueKey(['u_email']));
		self::assertFalse($table->isUniqueKey(['u_id']));
	}

	public function testForeignKeyConstraint(): void
	{
		$users = new Table('users', 'u');
		$users->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));

		$posts = new Table('posts', 'p');
		$posts->addColumn(new Column('id', 'p', ['type' => 'bigint', 'auto_increment' => true]));
		$posts->addColumn(new Column('user_id', 'p', ['type' => 'bigint']));

		$users->addPrimaryKeyConstraint(['id']);
		$fk = $posts->addForeignKeyConstraint(null, $users, ['user_id' => 'id']);

		self::assertSame($users, $fk->getReferenceTable());
		self::assertTrue($posts->isForeignKey(['p_user_id']));
		self::assertFalse($posts->isForeignKey(['p_id']));
	}

	public function testAddIndex(): void
	{
		$table = new Table('users', 'u');
		$table->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));
		$table->addColumn(new Column('email', 'u'));

		$index = $table->addIndex(['email'], IndexType::BTREE);

		self::assertCount(1, $table->getIndexes());
		self::assertSame(['u_email'], $index->getColumns());
		self::assertSame(IndexType::BTREE, $index->getType());
	}

	public function testSoftDeletable(): void
	{
		$table = new Table('users', 'u');
		$table->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));

		self::assertFalse($table->isSoftDeletable());

		$table->addColumn(new Column(Table::COLUMN_SOFT_DELETED, 'u', ['type' => 'bool']));
		$table->addColumn(new Column(Table::COLUMN_SOFT_DELETED_AT, 'u', ['type' => 'bigint', 'unsigned' => true, 'nullable' => true]));

		self::assertTrue($table->isSoftDeletable());
	}

	public function testGetFullNameWithPrefix(): void
	{
		$table = new Table('orders', 'shop');

		self::assertSame('shop_orders', $table->getFullName());
	}

	public function testGetColumnsFullNameList(): void
	{
		$table = new Table('users', 'u');
		$table->addColumn(new Column('id', 'u', ['type' => 'bigint', 'auto_increment' => true]));
		$table->addColumn(new Column('name', 'u'));

		$full = $table->getColumnsFullNameList();

		self::assertContains('u_id', $full);
		self::assertContains('u_name', $full);
	}

	public function testInvalidTableNameThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		new Table('_invalid');
	}

	public function testOldNameAffectsDiffKey(): void
	{
		$table = new Table('users_v2');
		$table->setNamespace('App\Db');

		$normal_key = $table->getDiffKey();

		$table2 = new Table('users_v2');
		$table2->setNamespace('App\Db');
		$table2->oldName('users');

		// diff key must match the old table identity, not the new name
		$old_key = $table2->getDiffKey();
		self::assertNotSame($normal_key, $old_key);

		$expected_old_key = \md5('App\Db/users');
		self::assertSame($expected_old_key, $old_key);
	}

	public function testOldNameInvalidNameThrows(): void
	{
		$table = new Table('users');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Table old name "_bad" should match');

		$table->oldName('_bad');
	}

	public function testOldNameNotInToArray(): void
	{
		$table = new Table('users_v2');
		$table->setNamespace('App\Db');
		$table->oldName('users');
		$table->setPluralName('users_v2_entities');
		$table->setSingularName('users_v2_entity');
		$table->addColumn(new Column('name'));

		$array = $table->toArray();

		self::assertArrayNotHasKey('old_name', $array);
		// diff_key in toArray() reflects the old-name-derived key
		self::assertSame($table->getDiffKey(), $array['diff_key']);
	}
}
