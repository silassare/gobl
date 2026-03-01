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

namespace Gobl\Tests\DBAL\Types;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeInt;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeIntTest.
 *
 * @covers \Gobl\DBAL\Types\TypeInt
 *
 * @internal
 */
final class TypeIntTest extends BaseTestCase
{
	public function testIntValid(): void
	{
		$t = new TypeInt();

		self::assertSame(0, $t->validate(0));
		self::assertSame(42, $t->validate(42));
		self::assertSame(-1, $t->validate(-1));
	}

	public function testIntAcceptsIntegerNumericString(): void
	{
		$t = new TypeInt();
		self::assertSame(5, $t->validate('5'));
	}

	public function testIntRejectsFloat(): void
	{
		$t = new TypeInt();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(3.14);
	}

	public function testIntRejectsFloatString(): void
	{
		$t = new TypeInt();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('3.14');
	}

	public function testIntRejectsNonNumericString(): void
	{
		$t = new TypeInt();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('hello');
	}

	public function testIntNullWithNullable(): void
	{
		$t = (new TypeInt())->nullable();
		self::assertNull($t->validate(null));
	}

	public function testIntNullWithDefault(): void
	{
		$t = (new TypeInt())->default(99);
		self::assertSame(99, $t->validate(null));
	}

	public function testIntNullThrowsWithoutNullableOrDefault(): void
	{
		$t = new TypeInt();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(null);
	}

	public function testIntUnsignedRejectsNegative(): void
	{
		$t = (new TypeInt())->unsigned();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(-1);
	}

	public function testIntUnsignedAcceptsZero(): void
	{
		$t = (new TypeInt())->unsigned();
		self::assertSame(0, $t->validate(0));
	}

	public function testIntMinConstraint(): void
	{
		$t = (new TypeInt())->min(10);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(5);
	}

	public function testIntMaxConstraint(): void
	{
		$t = (new TypeInt())->max(10);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(15);
	}

	public function testIntMinMaxAccepted(): void
	{
		$t = (new TypeInt())->min(-5)->max(5);
		self::assertSame(-5, $t->validate(-5));
		self::assertSame(0, $t->validate(0));
		self::assertSame(5, $t->validate(5));
	}

	public function testIntAutoIncrementNullReturnsNull(): void
	{
		$t = (new TypeInt())->autoIncrement();
		self::assertNull($t->validate(null));
	}

	public function testIntMinGreaterThanMaxThrows(): void
	{
		$this->expectException(TypesException::class);
		(new TypeInt())->min(10)->max(5);
	}
}
