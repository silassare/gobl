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
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeString;
use Gobl\Tests\BaseTestCase;
use InvalidArgumentException;

/**
 * Class ColumnTest.
 *
 * @covers \Gobl\DBAL\Column
 *
 * @internal
 */
final class ColumnTest extends BaseTestCase
{
	public function testConstructor(): void
	{
		$name   = 'name';
		$prefix = 'pr';

		$column = new Column($name, $prefix);

		self::assertSame($name, $column->getName());
		self::assertSame($prefix, $column->getPrefix());
		self::assertSame($prefix . '_' . $name, $column->getFullName());

		self::assertInstanceOf(TypeString::class, $column->getType());

		$column = new Column($name);

		self::assertSame($name, $column->getName());
		self::assertSame('', $column->getPrefix());
		self::assertSame($name, $column->getFullName());

		$column = new Column($name, null, [
			'type' => 'int',
		]);

		self::assertInstanceOf(TypeInt::class, $column->getType());
	}

	/**
	 * @covers \Gobl\DBAL\Column::getName
	 * @covers \Gobl\DBAL\Column::setName
	 */
	public function testSetGetName(): void
	{
		$name = 'name';

		$column = new Column($name);

		self::assertSame($name, $column->getName());

		$column->setName($name = 'new_name');

		self::assertSame($name, $column->getName());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Column name "_new_name" should match');

		$column->setName('_new_name');
	}

	public function testGetFullName(): void
	{
		$name   = 'name';
		$prefix = 'pr';

		$column = new Column($name);

		self::assertSame($name, $column->getFullName());

		$column->setPrefix($prefix);

		self::assertSame($prefix . '_' . $name, $column->getFullName());
	}

	public function testSetGetPrefix(): void
	{
		$column = new Column('name');
		$column->setPrefix('user');

		self::assertSame('user', $column->getPrefix());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Column prefix "invalid_" for column "name" should match');

		$column->setPrefix('invalid_');
	}

	/**
	 * @throws DBALException
	 */
	public function testGetTable(): void
	{
		$table  = new Table('users');
		$column = new Column('name');

		$table->addColumn($column)
			->lock();

		self::assertSame($table, $column->getTable());
	}

	public function testLock(): void
	{
		$table  = new Table('users');
		$column = new Column('name');

		$column->lock($table);

		self::assertSame($table, $column->getTable());
	}

	public function testPrivate(): void
	{
		$column = new Column('name');

		self::assertFalse($column->isPrivate());

		$column->setPrivate();
		self::assertTrue($column->isPrivate());

		$column->setPrivate(false);
		self::assertFalse($column->isPrivate());
	}

	public function testSensitive(): void
	{
		$column = new Column('name');

		self::assertFalse($column->isSensitive());

		$column->setSensitive();
		self::assertTrue($column->isSensitive());

		self::assertNull($column->getSensitiveRedactedValue());

		$column->setSensitive(true, '****');
		self::assertSame($column->getSensitiveRedactedValue(), '****');

		$column->setSensitive(false);
		self::assertFalse($column->isSensitive());
	}

	public function testSetGetType(): void
	{
		$column = new Column('name');

		self::assertInstanceOf(TypeString::class, $column->getType());

		$column->setType($type = new TypeBigint());

		self::assertInstanceOf(TypeBigint::class, $column->getType());

		self::assertSame($type->toArray(), $column->getType()
			->toArray());
	}

	public function testSetTypeFromOptions(): void
	{
		$column = new Column('name');

		self::assertInstanceOf(TypeString::class, $column->getType());

		$column->setTypeFromOptions([
			'type' => 'int',
		]);

		self::assertInstanceOf(TypeInt::class, $column->getType());

		$column->setTypeFromOptions([
			'type'           => 'bigint',
			'auto_increment' => true,
		]);

		self::assertInstanceOf(TypeBigint::class, $type = $column->getType());

		self::assertTrue($type->isAutoIncremented());
	}

	/**
	 * @throws DBALException
	 */
	public function testOldNameAffectsDiffKey(): void
	{
		// Normal column: 'user_email_address'
		$table = new Table('users');
		$table->setNamespace('App\Db');
		$table->setSingularName('user');
		$table->setPluralName('users_list');
		$table->addColumn(new Column('email_address', 'user'));
		$table->lock();

		$col_normal = $table->getColumnOrFail('email_address');
		$normal_key = $col_normal->getDiffKey();

		// Column that was renamed: previously 'user_email', now 'user_email_address'
		$table2 = new Table('users');
		$table2->setNamespace('App\Db');
		$table2->setSingularName('user');
		$table2->setPluralName('users_list');

		$col_renamed = new Column('email_address', 'user');
		$col_renamed->oldName('email');
		$table2->addColumn($col_renamed);
		$table2->lock();

		$old_key = $col_renamed->getDiffKey();
		self::assertNotSame($normal_key, $old_key);

		// old_key must match the previous full name identity
		$expected_old_key = \md5($table2->getDiffKey() . '/user_email');
		self::assertSame($expected_old_key, $old_key);
	}

	/**
	 * @throws DBALException
	 */
	public function testOldNameWithExplicitOldPrefix(): void
	{
		$table = new Table('users');
		$table->setNamespace('App\Db');
		$table->setSingularName('user');
		$table->setPluralName('users_list');

		// column moved from prefix-less 'email' to prefixed 'user_email'
		$col = new Column('email', 'user');
		$col->oldName('email');
		$col->oldPrefix(''); // old column had no prefix
		$table->addColumn($col);
		$table->lock();

		$expected_old_key = \md5($table->getDiffKey() . '/email');
		self::assertSame($expected_old_key, $col->getDiffKey());
	}

	public function testOldNameInvalidNameThrows(): void
	{
		$column = new Column('name');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Column old name "_bad" should match');

		$column->oldName('_bad');
	}

	public function testOldPrefixInvalidPatternThrows(): void
	{
		$column = new Column('name');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Column old prefix "_bad" should match');

		$column->oldPrefix('_bad');
	}

	public function testOldNameNotInToArray(): void
	{
		$column = new Column('email', 'user');
		$column->oldName('email_old');

		$array = $column->toArray();

		self::assertArrayNotHasKey('old_name', $array);
		self::assertArrayNotHasKey('old_prefix', $array);
	}
}
