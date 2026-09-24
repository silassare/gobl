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
		$result = $t->validate(1609459200)->getCleanValue();
		self::assertIsString($result);
		self::assertNotEmpty($result);
	}

	public function testDateAcceptsNumericTimestampString(): void
	{
		$t      = new TypeDate();
		$result = $t->validate('1609459200')->getCleanValue();
		self::assertIsString($result);
	}

	public function testDateAcceptsDateString(): void
	{
		$t      = new TypeDate();
		$result = $t->validate('2021-01-01')->getCleanValue();
		self::assertIsString($result);
	}

	public function testDateRejectsInvalidString(): void
	{
		$t = new TypeDate();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('not-a-date')->getCleanValue();
	}

	public function testDateNullWithNullable(): void
	{
		$t = (new TypeDate())->nullable();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testDateNullThrowsWithoutNullableOrDefault(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeDate())->validate(null)->getCleanValue();
	}

	public function testDateEmptyNullableReturnsNull(): void
	{
		$t = (new TypeDate())->nullable();
		self::assertNull($t->validate('')->getCleanValue());
	}

	public function testDateAutoGeneratesTimestamp(): void
	{
		$t      = (new TypeDate())->auto();
		$result = $t->validate(null)->getCleanValue();   // empty + auto => generates current time
		self::assertIsString($result);
		self::assertNotEmpty($result);
	}

	/**
	 * The order of the calls does not matter: `microseconds()` replaces the base type, and used to drop
	 * the bounds set before it while the type still reported them.
	 */
	public function testMicrosecondsKeepsTheBoundsSetBeforeIt(): void
	{
		$t = (new TypeDate())->min(10)->max(20)->microseconds();

		self::assertSame('15', $t->validate('15')->getCleanValue());

		$this->expectException(TypesInvalidValueException::class);
		$t->validate('5');
	}

	public function testMicrosecondsKeepsTheUpperBoundSetBeforeIt(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeDate())->min(10)->max(20)->microseconds()->validate('99');
	}

	/** A custom message given with a bound survives the base being replaced. */
	public function testMicrosecondsKeepsTheMessageOfABound(): void
	{
		$t = (new TypeDate())->min(10, 'too_early')->microseconds();

		try {
			$t->validate('5');
			self::fail('A value under the bound was accepted.');
		} catch (TypesInvalidValueException $e) {
			self::assertSame('too_early', $e->getPrevious()?->getMessage());
		}
	}

	public function testMicrosecondsKeepsNullableSetBeforeIt(): void
	{
		self::assertNull((new TypeDate())->nullable()->microseconds()->validate(null)->getCleanValue());
	}
}
