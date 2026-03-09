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
		self::assertTrue($t->validate(true)->getCleanValue());
		self::assertFalse($t->validate(false)->getCleanValue());
		self::assertTrue($t->validate(1)->getCleanValue());
		self::assertFalse($t->validate(0)->getCleanValue());
	}

	public function testBoolStringInputsNonStrict(): void
	{
		$t = (new TypeBool())->strict(false);
		self::assertTrue($t->validate('true')->getCleanValue());
		self::assertFalse($t->validate('false')->getCleanValue());
		self::assertTrue($t->validate('yes')->getCleanValue());
		self::assertFalse($t->validate('no')->getCleanValue());
		self::assertTrue($t->validate('1')->getCleanValue());
		self::assertFalse($t->validate('0')->getCleanValue());
	}

	public function testBoolInvalidStringThrows(): void
	{
		$t = new TypeBool();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('maybe')->getCleanValue();
	}

	public function testBoolNullWithNullable(): void
	{
		$t = (new TypeBool())->nullable();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testBoolNullWithDefault(): void
	{
		$t = (new TypeBool())->default(true);
		self::assertTrue($t->validate(null)->getCleanValue());
	}
}
