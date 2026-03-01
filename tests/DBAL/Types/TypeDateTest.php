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
use Gobl\DBAL\Types\TypeDate;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeDateTest.
 *
 * @covers \Gobl\DBAL\Types\TypeDate
 *
 * @internal
 */
final class TypeDateTest extends BaseTestCase
{
	public function testDateAcceptsNumericTimestamp(): void
	{
		$t      = new TypeDate();
		$result = $t->validate(1609459200);
		self::assertIsString($result);
		self::assertNotEmpty($result);
	}

	public function testDateAcceptsNumericTimestampString(): void
	{
		$t      = new TypeDate();
		$result = $t->validate('1609459200');
		self::assertIsString($result);
	}

	public function testDateAcceptsDateString(): void
	{
		$t      = new TypeDate();
		$result = $t->validate('2021-01-01');
		self::assertIsString($result);
	}

	public function testDateRejectsInvalidString(): void
	{
		$t = new TypeDate();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('not-a-date');
	}

	public function testDateNullWithNullable(): void
	{
		$t = (new TypeDate())->nullable();
		self::assertNull($t->validate(null));
	}

	public function testDateEmptyNullableReturnsNull(): void
	{
		$t = (new TypeDate())->nullable();
		self::assertNull($t->validate(''));
	}

	public function testDateAutoGeneratesTimestamp(): void
	{
		$t      = (new TypeDate())->auto();
		$result = $t->validate(null);   // empty + auto => generates current time
		self::assertIsString($result);
		self::assertNotEmpty($result);
	}
}
