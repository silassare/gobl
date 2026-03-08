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
}
