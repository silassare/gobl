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

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\Tests\BaseTestCase;
use stdClass;

/** Fixture backed enum used only in these tests. */
enum TestStatus: string
{
	case Active   = 'active';
	case Inactive = 'inactive';
	case Pending  = 'pending';
}

/** Fixture int-backed enum. */
enum TestPriority: int
{
	case Low    = 1;
	case Medium = 2;
	case High   = 3;
}

/**
 * Class TypeEnumTest.
 *
 * @covers \Gobl\DBAL\Types\TypeEnum
 *
 * @internal
 */
final class TypeEnumTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// Construction / configuration
	// -------------------------------------------------------------------------

	public function testConstructWithEnumClass(): void
	{
		$t = new TypeEnum(TestStatus::class);
		self::assertSame(TestStatus::class, $t->getEnumClass());
	}

	public function testConstructWithoutEnumClassThrowsOnGetEnumClass(): void
	{
		$t = new TypeEnum();
		$this->expectException(TypesException::class);
		$t->getEnumClass();
	}

	public function testEnumClassRejectsNonBackedEnum(): void
	{
		$this->expectException(TypesException::class);
		// stdClass is not a BackedEnum subclass
		(new TypeEnum())->enumClass(stdClass::class);
	}

	// -------------------------------------------------------------------------
	// validate() string-backed enum
	// -------------------------------------------------------------------------

	public function testValidateWithEnumInstance(): void
	{
		$t = new TypeEnum(TestStatus::class);
		self::assertSame(TestStatus::Active, $t->validate(TestStatus::Active));
	}

	public function testValidateWithValidStringValue(): void
	{
		$t = new TypeEnum(TestStatus::class);
		self::assertSame(TestStatus::Inactive, $t->validate('inactive'));
	}

	public function testValidateWithInvalidStringValueThrows(): void
	{
		$t = new TypeEnum(TestStatus::class);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('deleted');
	}

	public function testValidateWithWrongEnumInstanceThrows(): void
	{
		$t = new TypeEnum(TestStatus::class);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(TestPriority::High);
	}

	// -------------------------------------------------------------------------
	// validate() int-backed enum
	// -------------------------------------------------------------------------

	public function testValidateIntBackedWithValidInt(): void
	{
		$t = new TypeEnum(TestPriority::class);
		self::assertSame(TestPriority::Medium, $t->validate(2));
	}

	public function testValidateIntBackedWithStringValueThrows(): void
	{
		// PHP int-backed enums require int, not string, in ::from()
		$t = new TypeEnum(TestPriority::class);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('1');
	}

	public function testValidateIntBackedWithInvalidValueThrows(): void
	{
		$t = new TypeEnum(TestPriority::class);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(99);
	}

	// -------------------------------------------------------------------------
	// null / default / nullable
	// -------------------------------------------------------------------------

	public function testValidateNullWithNullable(): void
	{
		$t = (new TypeEnum(TestStatus::class))->nullable();
		self::assertNull($t->validate(null));
	}

	public function testValidateNullWithoutNullableThrows(): void
	{
		$t = new TypeEnum(TestStatus::class);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(null);
	}

	public function testValidateNullUsesDefault(): void
	{
		$t = (new TypeEnum(TestStatus::class))->default(TestStatus::Pending->value);
		self::assertSame(TestStatus::Pending, $t->validate(null));
	}

	// -------------------------------------------------------------------------
	// phpToDb / dbToPhp
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testPhpToDbReturnsEnumValue(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeEnum(TestStatus::class);
		self::assertSame('active', $t->phpToDb(TestStatus::Active, $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testPhpToDbAcceptsRawString(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeEnum(TestStatus::class);
		self::assertSame('pending', $t->phpToDb('pending', $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testPhpToDbNullableReturnsNull(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = (new TypeEnum(TestStatus::class))->nullable();
		self::assertNull($t->phpToDb(null, $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDbToPhpReturnsEnumInstance(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeEnum(TestStatus::class);
		self::assertSame(TestStatus::Active, $t->dbToPhp('active', $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDbToPhpNullReturnsNull(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeEnum(TestStatus::class);
		self::assertNull($t->dbToPhp(null, $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDbToPhpIntBackedReturnsEnumInstance(string $driver): void
	{
		// DB stores int-backed enum values as integers; pass int directly
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeEnum(TestPriority::class);
		self::assertSame(TestPriority::High, $t->dbToPhp(3, $db));
	}

	// -------------------------------------------------------------------------
	// getName / getEmptyValueOfType
	// -------------------------------------------------------------------------

	public function testGetName(): void
	{
		self::assertSame('enum', (new TypeEnum(TestStatus::class))->getName());
	}

	public function testGetEmptyValueOfTypeIsNull(): void
	{
		$t = new TypeEnum(TestStatus::class);
		self::assertNull($t->getEmptyValueOfType());
	}
}
