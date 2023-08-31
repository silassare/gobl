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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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

	public function testSetPrivate(): void
	{
		$column = new Column('name');

		$column->setPrivate();
		self::assertTrue($column->isPrivate());
		$column->setPrivate(false);
		self::assertFalse($column->isPrivate());
	}

	public function testIsPrivate(): void
	{
		$column = new Column('name');

		self::assertFalse($column->isPrivate());

		$column->setPrivate();

		self::assertTrue($column->isPrivate());
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
}
