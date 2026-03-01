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
		self::assertSame('3.14', $t->validate('3.14'));
		self::assertSame('0', $t->validate(0));
		self::assertSame('-1.5', $t->validate(-1.5));
		self::assertSame('42', $t->validate(42));
	}

	public function testDecimalRejectsNonNumeric(): void
	{
		$t = new TypeDecimal();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('hello');
	}

	public function testDecimalUnsignedRejectsNegative(): void
	{
		$t = (new TypeDecimal())->unsigned();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(-1);
	}

	public function testDecimalUnsignedAcceptsZero(): void
	{
		$t = (new TypeDecimal())->unsigned();
		self::assertSame('0', $t->validate(0));
	}

	public function testDecimalNullWithNullable(): void
	{
		$t = (new TypeDecimal())->nullable();
		self::assertNull($t->validate(null));
	}

	public function testDecimalNullWithDefault(): void
	{
		$t = (new TypeDecimal())->default('0.00');
		self::assertSame('0.00', $t->validate(null));
	}

	public function testDecimalMinConstraint(): void
	{
		$t = (new TypeDecimal())->min('10');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('5');
	}

	public function testDecimalMaxConstraint(): void
	{
		$t = (new TypeDecimal())->max('100');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('200');
	}
}
