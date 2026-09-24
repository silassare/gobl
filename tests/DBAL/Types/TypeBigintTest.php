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
use Gobl\DBAL\Types\TypeBigint;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeBigintTest.
 *
 * @covers \Gobl\DBAL\Types\TypeBigint
 *
 * @internal
 */
final class TypeBigintTest extends BaseTestCase
{
	public function testBigintValid(): void
	{
		$t = new TypeBigint();
		self::assertSame('0', $t->validate(0)->getCleanValue());
		self::assertSame('42', $t->validate('42')->getCleanValue());
		self::assertSame('-1', $t->validate('-1')->getCleanValue());
		self::assertSame('9223372036854775807', $t->validate('9223372036854775807')->getCleanValue());
	}

	public function testBigintRejectsNonNumeric(): void
	{
		$t = new TypeBigint();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('not-a-number')->getCleanValue();
	}

	public function testBigintUnsignedAcceptsZero(): void
	{
		$t = (new TypeBigint())->unsigned();
		self::assertSame('0', $t->validate('0')->getCleanValue());
	}

	public function testBigintNullWithNullable(): void
	{
		$t = (new TypeBigint())->nullable();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testBigintNullWithDefault(): void
	{
		$t = (new TypeBigint())->default('100');
		self::assertSame('100', $t->validate(null)->getCleanValue());
	}

	public function testBigintNullThrowsWithoutNullableOrDefault(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeBigint())->validate(null)->getCleanValue();
	}

	public function testBigintAutoIncrementNullReturnsNull(): void
	{
		$t = (new TypeBigint())->autoIncrement();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testBigintMinConstraint(): void
	{
		$t = (new TypeBigint())->min('10');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('5')->getCleanValue();
	}

	public function testBigintMaxConstraint(): void
	{
		$t = (new TypeBigint())->max('100');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('200')->getCleanValue();
	}

	/**
	 * A bigint is a whole integer and nothing else. The pattern was unanchored, so any value that
	 * merely contained digits matched.
	 *
	 * @dataProvider provideNotABigint
	 */
	public function testBigintRejectsWhatIsNotAWholeInteger(string $value): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeBigint())->validate($value);
	}

	/** @return iterable<string, array{string}> */
	public static function provideNotABigint(): iterable
	{
		yield 'a fraction' => ['1.5'];
		yield 'an exponent' => ['1e5'];
		yield 'a trailing newline' => ["5\n"];
		yield 'leading zeros' => ['007'];
	}

	/** An unsigned bigint used to accept "-5", which a strict MySQL then refused on insert. */
	public function testUnsignedBigintRejectsANegative(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeBigint())->unsigned()->validate('-5');
	}

	/**
	 * Bounds past 2^53 are compared exactly. They used to go through a float, so a max of ...992
	 * accepted ...993.
	 */
	public function testBigintBoundsHoldPastTwoToTheFiftyThree(): void
	{
		$max = (new TypeBigint())->max('9007199254740992');
		self::assertSame('9007199254740992', $max->validate('9007199254740992')->getCleanValue());

		$min = (new TypeBigint())->min('9007199254740993');
		self::assertSame('9007199254740993', $min->validate('9007199254740993')->getCleanValue());

		$this->expectException(TypesInvalidValueException::class);
		$max->validate('9007199254740993');
	}

	public function testBigintMinPastTwoToTheFiftyThreeRefusesTheValueBelow(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeBigint())->min('9007199254740993')->validate('9007199254740992');
	}

	/** A schema cannot declare a bound that is not a whole integer either. */
	public function testBigintRefusesAFractionalBoundInItsDeclaration(): void
	{
		$this->expectException(TypesException::class);
		(new TypeBigint())->min('1.5');
	}
}
