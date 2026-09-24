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

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeDecimalTest.
 *
 * @covers \Gobl\DBAL\Types\TypeDecimal
 *
 * @internal
 */
final class TypeDecimalTest extends BaseTestCase
{
	public function testDecimalValid(): void
	{
		$t = new TypeDecimal();
		self::assertSame('3.14', $t->validate('3.14')->getCleanValue());
		self::assertSame('0', $t->validate(0)->getCleanValue());
		self::assertSame('-1.5', $t->validate(-1.5)->getCleanValue());
		self::assertSame('42', $t->validate(42)->getCleanValue());
	}

	public function testDecimalRejectsNonNumeric(): void
	{
		$t = new TypeDecimal();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('hello')->getCleanValue();
	}

	public function testDecimalUnsignedRejectsNegative(): void
	{
		$t = (new TypeDecimal())->unsigned();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(-1)->getCleanValue();
	}

	public function testDecimalUnsignedAcceptsZero(): void
	{
		$t = (new TypeDecimal())->unsigned();
		self::assertSame('0', $t->validate(0)->getCleanValue());
	}

	public function testDecimalNullWithNullable(): void
	{
		$t = (new TypeDecimal())->nullable();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testDecimalNullWithDefault(): void
	{
		$t = (new TypeDecimal())->default('0.00');
		self::assertSame('0.00', $t->validate(null)->getCleanValue());
	}

	public function testDecimalNullThrowsWithoutNullableOrDefault(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeDecimal())->validate(null)->getCleanValue();
	}

	public function testDecimalMinConstraint(): void
	{
		$t = (new TypeDecimal())->min('10');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('5')->getCleanValue();
	}

	public function testDecimalMaxConstraint(): void
	{
		$t = (new TypeDecimal())->max('100');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('200')->getCleanValue();
	}

	/**
	 * Bounds are compared exactly, however many places they carry. They used to be rounded to six
	 * places through a float, so a min of 0.0000005 accepted 0.0000001.
	 */
	public function testDecimalBoundsHoldPastSixPlaces(): void
	{
		$t = (new TypeDecimal())->min('0.0000005');
		self::assertSame('0.0000005', $t->validate('0.0000005')->getCleanValue());

		$this->expectException(TypesInvalidValueException::class);
		$t->validate('0.0000001');
	}

	/** An exponent is read exactly, so 1e3 is a thousand and not something smaller. */
	public function testDecimalBoundReadsAnExponent(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeDecimal())->max('100')->validate('1e3');
	}

	/** Trailing zeros change nothing: 1.50 is 1.5. */
	public function testDecimalBoundIgnoresTrailingZeros(): void
	{
		self::assertSame('1.50', (new TypeDecimal())->max('1.5')->validate('1.50')->getCleanValue());
		self::assertSame('-1.0', (new TypeDecimal())->min('-1')->validate('-1.0')->getCleanValue());
	}
}
