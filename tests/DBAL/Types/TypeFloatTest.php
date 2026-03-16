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
use Gobl\DBAL\Types\TypeFloat;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeFloatTest.
 *
 * @covers \Gobl\DBAL\Types\TypeFloat
 *
 * @internal
 */
final class TypeFloatTest extends BaseTestCase
{
	public function testFloatValid(): void
	{
		$t = new TypeFloat();
		self::assertSame(3.14, $t->validate(3.14)->getCleanValue());
		self::assertSame(0.0, $t->validate(0)->getCleanValue());
		self::assertSame(42.0, $t->validate('42')->getCleanValue());
	}

	public function testFloatRejectsNonNumeric(): void
	{
		$t = new TypeFloat();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('not-a-number')->getCleanValue();
	}

	public function testFloatUnsignedRejectsNegative(): void
	{
		$t = (new TypeFloat())->unsigned();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(-1.5)->getCleanValue();
	}

	public function testFloatUnsignedAcceptsZero(): void
	{
		$t = (new TypeFloat())->unsigned();
		self::assertSame(0.0, $t->validate(0)->getCleanValue());
	}

	public function testFloatNullWithNullable(): void
	{
		$t = (new TypeFloat())->nullable();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testFloatNullThrowsWithoutNullableOrDefault(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeFloat())->validate(null)->getCleanValue();
	}

	public function testFloatMinConstraint(): void
	{
		$t = (new TypeFloat())->min(1.0);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(0.5)->getCleanValue();
	}

	public function testFloatMaxConstraint(): void
	{
		$t = (new TypeFloat())->max(100.0);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(100.1)->getCleanValue();
	}

	public function testFloatMinMaxAccepted(): void
	{
		$t = (new TypeFloat())->min(0.0)->max(1.0);
		self::assertSame(0.0, $t->validate(0.0)->getCleanValue());
		self::assertSame(0.5, $t->validate(0.5)->getCleanValue());
		self::assertSame(1.0, $t->validate(1.0)->getCleanValue());
	}
}
