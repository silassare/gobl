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

use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeList;
use Gobl\Tests\BaseTestCase;

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
		self::assertSame(['a', 'b', 'c'], $t->validate(['a', 'b', 'c']));
	}

	public function testValidateAcceptsEmptyArray(): void
	{
		$t = new TypeList();
		self::assertSame([], $t->validate([]));
	}

	public function testValidateNormalizesAssocToList(): void
	{
		// assoc arrays are re-indexed with array_values
		$t      = new TypeList();
		$result = $t->validate(['x' => 1, 'y' => 2]);
		self::assertSame([1, 2], $result);
	}

	public function testValidateAcceptsNestedArray(): void
	{
		$t      = new TypeList();
		$result = $t->validate([[1, 2], [3, 4]]);
		self::assertSame([[1, 2], [3, 4]], $result);
	}

	public function testValidateMixedTypesPreserved(): void
	{
		$t = new TypeList();
		self::assertSame([1, 'two', true, null], $t->validate([1, 'two', true, null]));
	}

	// -------------------------------------------------------------------------
	// validate() invalid input
	// -------------------------------------------------------------------------

	public function testValidateRejectsString(): void
	{
		$t = new TypeList();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('not an array');
	}

	public function testValidateRejectsInt(): void
	{
		$t = new TypeList();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(42);
	}

	public function testValidateRejectsObject(): void
	{
		$t = new TypeList();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(new \stdClass());
	}

	// -------------------------------------------------------------------------
	// null / nullable / default
	// -------------------------------------------------------------------------

	public function testValidateNullWithNullable(): void
	{
		$t = (new TypeList())->nullable();
		self::assertNull($t->validate(null));
	}

	public function testValidateNullWithoutNullableThrows(): void
	{
		$t = new TypeList();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(null);
	}

	public function testValidateNullUsesDefault(): void
	{
		$t      = (new TypeList())->default(['default_item']);
		$result = $t->validate(null);
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

	public function testPhpToDbSerializesToJson(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = new TypeList();
		self::assertSame('[1,"two",true]', $t->phpToDb([1, 'two', true], $db));
	}

	public function testPhpToDbEmptyArraySerializesAsEmptyJsonArray(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = new TypeList();
		self::assertSame('[]', $t->phpToDb([], $db));
	}

	public function testPhpToDbNullableReturnsNull(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = (new TypeList())->nullable();
		self::assertNull($t->phpToDb(null, $db));
	}

	public function testDbToPhpDeserializesFromJson(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = new TypeList();
		self::assertSame([1, 'two', true], $t->dbToPhp('[1,"two",true]', $db));
	}

	public function testDbToPhpNullReturnsNull(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = new TypeList();
		self::assertNull($t->dbToPhp(null, $db));
	}

	public function testDbToPhpEmptyStringReturnsNull(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = new TypeList();
		self::assertNull($t->dbToPhp('', $db));
	}

	public function testPhpToDbRoundtrip(): void
	{
		$db      = self::getDb(MySQL::NAME);
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
