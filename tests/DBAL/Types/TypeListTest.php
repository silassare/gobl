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
use Gobl\DBAL\Types\TypeList;
use Gobl\Tests\BaseTestCase;
use stdClass;

/**
 * Class TypeListTest.
 *
 * @covers \Gobl\DBAL\Types\TypeList
 *
 * @internal
 */
final class TypeListTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// validate() happy path
	// -------------------------------------------------------------------------

	public function testValidateAcceptsIndexedArray(): void
	{
		$t = new TypeList();
		self::assertSame(['a', 'b', 'c'], $t->validate(['a', 'b', 'c'])->getCleanValue());
	}

	public function testValidateAcceptsEmptyArray(): void
	{
		$t = new TypeList();
		self::assertSame([], $t->validate([])->getCleanValue());
	}

	public function testValidateNormalizesAssocToList(): void
	{
		// assoc arrays are re-indexed with array_values
		$t      = new TypeList();
		$result = $t->validate(['x' => 1, 'y' => 2])->getCleanValue();
		self::assertSame([1, 2], $result);
	}

	public function testValidateAcceptsNestedArray(): void
	{
		$t      = new TypeList();
		$result = $t->validate([[1, 2], [3, 4]])->getCleanValue();
		self::assertSame([[1, 2], [3, 4]], $result);
	}

	public function testValidateMixedTypesPreserved(): void
	{
		$t = new TypeList();
		self::assertSame([1, 'two', true, null], $t->validate([1, 'two', true, null])->getCleanValue());
	}

	// -------------------------------------------------------------------------
	// validate() invalid input
	// -------------------------------------------------------------------------

	public function testValidateRejectsString(): void
	{
		$t = new TypeList();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('not an array')->getCleanValue();
	}

	public function testValidateRejectsInt(): void
	{
		$t = new TypeList();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(42)->getCleanValue();
	}

	public function testValidateRejectsObject(): void
	{
		$t = new TypeList();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(new stdClass())->getCleanValue();
	}

	// -------------------------------------------------------------------------
	// null / nullable / default
	// -------------------------------------------------------------------------

	public function testValidateNullWithNullable(): void
	{
		$t = (new TypeList())->nullable();
		self::assertNull($t->validate(null)->getCleanValue());
	}

	public function testValidateNullWithoutNullableThrows(): void
	{
		$t = new TypeList();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(null)->getCleanValue();
	}

	public function testValidateNullUsesDefault(): void
	{
		$t      = (new TypeList())->default(['default_item']);
		$result = $t->validate(null)->getCleanValue();
		self::assertSame(['default_item'], $result);
	}

	public function testGetEmptyValueOfTypeReturnsEmptyArray(): void
	{
		$t = new TypeList();
		self::assertSame([], $t->getEmptyValueOfType());
	}

	public function testGetEmptyValueOfTypeNullable(): void
	{
		$t = (new TypeList())->nullable();
		self::assertNull($t->getEmptyValueOfType());
	}

	// -------------------------------------------------------------------------
	// phpToDb / dbToPhp
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testPhpToDbSerializesToJson(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeList();
		self::assertSame('[1,"two",true]', $t->phpToDb([1, 'two', true], $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testPhpToDbEmptyArraySerializesAsEmptyJsonArray(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeList();
		self::assertSame('[]', $t->phpToDb([], $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testPhpToDbNullableReturnsNull(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = (new TypeList())->nullable();
		self::assertNull($t->phpToDb(null, $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDbToPhpDeserializesFromJson(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeList();
		self::assertSame([1, 'two', true], $t->dbToPhp('[1,"two",true]', $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDbToPhpNullReturnsNull(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeList();
		self::assertNull($t->dbToPhp(null, $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testDbToPhpEmptyStringReturnsNull(string $driver): void
	{
		$db = self::getNewDbInstanceWithSchema($driver);
		$t  = new TypeList();
		self::assertNull($t->dbToPhp('', $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testPhpToDbRoundtrip(string $driver): void
	{
		$db      = self::getNewDbInstanceWithSchema($driver);
		$t       = new TypeList();
		$input   = ['alpha', 'beta', 'gamma'];
		$encoded = $t->phpToDb($input, $db);
		self::assertSame($input, $t->dbToPhp($encoded, $db));
	}

	// -------------------------------------------------------------------------
	// big() option
	// -------------------------------------------------------------------------

	public function testGetName(): void
	{
		self::assertSame('list', (new TypeList())->getName());
	}
}
