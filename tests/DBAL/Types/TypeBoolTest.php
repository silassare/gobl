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
use Gobl\DBAL\Types\TypeBool;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeBoolTest.
 *
 * @covers \Gobl\DBAL\Types\TypeBool
 *
 * @internal
 */
final class TypeBoolTest extends BaseTestCase
{
	public function testBoolValidBooleans(): void
	{
		$t = new TypeBool();
		self::assertTrue($t->validate(true));
		self::assertFalse($t->validate(false));
		self::assertTrue($t->validate(1));
		self::assertFalse($t->validate(0));
	}

	public function testBoolStringInputsNonStrict(): void
	{
		$t = (new TypeBool())->strict(false);
		self::assertTrue($t->validate('true'));
		self::assertFalse($t->validate('false'));
		self::assertTrue($t->validate('yes'));
		self::assertFalse($t->validate('no'));
		self::assertTrue($t->validate('1'));
		self::assertFalse($t->validate('0'));
	}

	public function testBoolInvalidStringThrows(): void
	{
		$t = new TypeBool();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('maybe');
	}

	public function testBoolNullWithNullable(): void
	{
		$t = (new TypeBool())->nullable();
		self::assertNull($t->validate(null));
	}

	public function testBoolNullWithDefault(): void
	{
		$t = (new TypeBool())->default(true);
		self::assertTrue($t->validate(null));
	}
}
