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

		self::assertSame(0, $t->validate(0)->getCleanValue());
		self::assertSame(42, $t->validate(42)->getCleanValue());
		self::assertSame(-1, $t->validate(-1)->getCleanValue());
	}

	public function testIntAcceptsIntegerNumericString(): void
	{
		$t = new TypeInt();
		self::assertSame(5, $t->validate('5')->getCleanValue());
	}

	/** A fractional float used to be cut to 3; it is refused since 2026-09-24. */
	public function testIntRefusesAFractionalFloat(): void
	{
		$t = new TypeInt();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(3.14);
	}

	/** A fractional string used to be cut to 3; it is refused since 2026-09-24. */
	public function testIntRefusesAFractionalFloatString(): void
	{
		$t = new TypeInt();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('3.14');
	}

	public function testIntRejectsNonNumericString(): void
	{
		$t = new TypeInt();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('hello')->getCleanValue();
	}

	public function testIntNullWithNullable(): void
	{
		$t = (new TypeInt())->nullable();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testIntNullWithDefault(): void
	{
		$t = (new TypeInt())->default(99);
		self::assertSame(99, $t->validate(null)->getCleanValue());
	}

	public function testIntNullThrowsWithoutNullableOrDefault(): void
	{
		$t = new TypeInt();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(null)->getCleanValue();
	}

	public function testIntUnsignedRejectsNegative(): void
	{
		$t = (new TypeInt())->unsigned();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(-1)->getCleanValue();
	}

	public function testIntUnsignedAcceptsZero(): void
	{
		$t = (new TypeInt())->unsigned();
		self::assertSame(0, $t->validate(0)->getCleanValue());
	}

	public function testIntMinConstraint(): void
	{
		$t = (new TypeInt())->min(10);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(5)->getCleanValue();
	}

	public function testIntMaxConstraint(): void
	{
		$t = (new TypeInt())->max(10);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(15)->getCleanValue();
	}

	public function testIntMinMaxAccepted(): void
	{
		$t = (new TypeInt())->min(-5)->max(5);
		self::assertSame(-5, $t->validate(-5)->getCleanValue());
		self::assertSame(0, $t->validate(0)->getCleanValue());
		self::assertSame(5, $t->validate(5)->getCleanValue());
	}

	public function testIntAutoIncrementNullReturnsNull(): void
	{
		$t = (new TypeInt())->autoIncrement();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testIntMinGreaterThanMaxThrows(): void
	{
		$this->expectException(TypesException::class);
		(new TypeInt())->min(10)->max(5);
	}

	/**
	 * A fractional value is refused, not cut. `(int)` used to turn 3.9 into 3 without a word.
	 *
	 * @dataProvider provideFractional
	 */
	public function testIntRefusesAFractionalValue(mixed $value): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeInt())->validate($value);
	}

	/** @return iterable<string, array{mixed}> */
	public static function provideFractional(): iterable
	{
		yield 'a fractional string' => ['3.9'];
		yield 'a fractional float' => [3.9];
		yield 'a negative fraction' => ['-0.5'];
	}

	/** A whole value is accepted however it is written. */
	public function testIntAcceptsAWholeValueWrittenAnyWay(): void
	{
		$t = new TypeInt();
		self::assertSame(3, $t->validate('3.0')->getCleanValue());
		self::assertSame(3, $t->validate(3.0)->getCleanValue());
		self::assertSame(1000, $t->validate('1e3')->getCleanValue());
	}

	/** -0.5 used to become 0 and pass the unsigned check. */
	public function testUnsignedIntRefusesANegativeFraction(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeInt())->unsigned()->validate('-0.5');
	}

	public function testIntRefusesAValuePastTheSignedMax(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeInt())->validate('2147483648');
	}
}
