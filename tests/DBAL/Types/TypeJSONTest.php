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

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\Tests\BaseTestCase;
use JsonSerializable;
use PHPUtils\Exceptions\RuntimeException as PHPUtilsRuntimeException;
use stdClass;

/**
 * Class TypeJSONTest.
 *
 * @covers \Gobl\DBAL\Types\TypeJSON
 *
 * @internal
 */
final class TypeJSONTest extends BaseTestCase
{
	// =========================================================================
	// TypeJSON::validate - happy paths
	// =========================================================================

	public function testValidateAcceptsAssocArray(): void
	{
		$t      = new TypeJSON();
		$result = $t->validate(['name' => 'John', 'age' => 30])->getCleanValue();
		self::assertSame(['name' => 'John', 'age' => 30], $result);
	}

	public function testValidateAcceptsIndexedArray(): void
	{
		$t      = new TypeJSON();
		$result = $t->validate([1, 2, 3])->getCleanValue();
		self::assertSame([1, 2, 3], $result);
	}

	public function testValidateAcceptsEmptyArray(): void
	{
		$t      = new TypeJSON();
		$result = $t->validate([])->getCleanValue();
		self::assertSame([], $result);
	}

	public function testValidateAcceptsNull(): void
	{
		$t      = (new TypeJSON())->nullable();
		$result = $t->validate(null)->getCleanValue();
		self::assertNull($result);
	}

	// TypeJSON only accepts arrays and objects; any string (even valid JSON) is rejected,
	// because TypeJSON is the *base* type used by TypeMap/TypeList for DB encoding.
	public function testValidateRejectsJsonString(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJSON())->validate('{"foo":"bar"}')->getCleanValue();
	}

	public function testValidateRejectsJsonArrayString(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJSON())->validate('[1,2,3]')->getCleanValue();
	}

	// =========================================================================
	// TypeJSON::validate - rejection / error paths
	// =========================================================================

	public function testValidateRejectsNullWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJSON())->validate(null)->getCleanValue();
	}

	public function testValidateRejectsPlainString(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJSON())->validate('not-json')->getCleanValue();
	}

	// =========================================================================
	// TypeJSON::validate - default value
	// =========================================================================

	public function testValidateUsesDefaultWhenNull(): void
	{
		$t      = (new TypeJSON())->default(['key' => 'val']);
		$result = $t->validate(null)->getCleanValue();
		self::assertSame(['key' => 'val'], $result);
	}

	// =========================================================================
	// TypeJSON::serializeJsonValue - static helper
	// =========================================================================

	/** null passes through unchanged. */
	public function testSerializeJsonValueNull(): void
	{
		self::assertNull(TypeJSON::serializeJsonValue(null));
	}

	/** string passes through unchanged (assumed pre-encoded). */
	public function testSerializeJsonValueStringPassthrough(): void
	{
		self::assertSame('"hello"', TypeJSON::serializeJsonValue('"hello"'));
		self::assertSame('{"role":"admin"}', TypeJSON::serializeJsonValue('{"role":"admin"}'));
	}

	/** int is JSON-encoded to a numeric string. */
	public function testSerializeJsonValueInt(): void
	{
		self::assertSame('42', TypeJSON::serializeJsonValue(42));
		self::assertSame('-1', TypeJSON::serializeJsonValue(-1));
	}

	/** float is JSON-encoded to a numeric string. */
	public function testSerializeJsonValueFloat(): void
	{
		self::assertSame('3.14', TypeJSON::serializeJsonValue(3.14));
	}

	/** bool true/false are JSON-encoded. */
	public function testSerializeJsonValueBool(): void
	{
		self::assertSame('true', TypeJSON::serializeJsonValue(true));
		self::assertSame('false', TypeJSON::serializeJsonValue(false));
	}

	/** array is JSON-encoded. */
	public function testSerializeJsonValueArray(): void
	{
		self::assertSame('["a","b"]', TypeJSON::serializeJsonValue(['a', 'b']));
		self::assertSame('{"role":"admin"}', TypeJSON::serializeJsonValue(['role' => 'admin']));
	}

	/** JsonSerializable is JSON-encoded. */
	public function testSerializeJsonValueJsonSerializable(): void
	{
		$obj = new class implements JsonSerializable {
			public function jsonSerialize(): mixed
			{
				return ['x' => true];
			}
		};
		self::assertSame('{"x":true}', TypeJSON::serializeJsonValue($obj));
	}

	// =========================================================================
	// TypeJSON lock / assertNotLocked / default validation
	// =========================================================================

	public function testLockPreventsSetOption(): void
	{
		$t = (new TypeJSON())->lock();

		$this->expectException(PHPUtilsRuntimeException::class);
		$this->expectExceptionMessage('cannot be modified');
		$t->nativeJson(true); // internally calls setOption()
	}

	public function testLockIsIdempotent(): void
	{
		$t = new TypeJSON();
		$t->lock();
		$t->lock(); // second call must not throw
		self::assertTrue(true);
	}

	public function testAssertNotLockedThrowsWhenLocked(): void
	{
		$t = (new TypeJSON())->lock();

		$this->expectException(PHPUtilsRuntimeException::class);
		$t->assertNotLocked();
	}

	public function testAssertNotLockedPassesWhenUnlocked(): void
	{
		(new TypeJSON())->assertNotLocked(); // must not throw
		self::assertTrue(true);
	}

	public function testCloneResetsLock(): void
	{
		$original = (new TypeJSON())->lock();
		$cloned   = clone $original;
		$cloned->assertNotLocked(); // must not throw
		self::assertTrue(true);
	}

	public function testValidDefaultPassesOnLock(): void
	{
		$t = (new TypeJSON())->default(['ok' => true]);
		$t->lock(); // must not throw
		self::assertTrue(true);
	}

	public function testInvalidDefaultThrowsOnLock(): void
	{
		$t = (new TypeJSON())->default('this is a scalar string'); // scalars are rejected by TypeJSON

		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('Default value for type "json" failed validation.');
		$t->lock();
	}

	// =========================================================================
	// TypeJSON::jsonDataType - 'any' / 'array' / 'object'
	// =========================================================================

	public function testJsonDataTypeAnyAcceptsAssocArray(): void
	{
		$t = (new TypeJSON())->jsonDataType('any');
		self::assertSame(['a' => 1], $t->validate(['a' => 1])->getCleanValue());
	}

	public function testJsonDataTypeAnyAcceptsIndexedArray(): void
	{
		$t = (new TypeJSON())->jsonDataType('any');
		self::assertSame([1, 2, 3], $t->validate([1, 2, 3])->getCleanValue());
	}

	public function testJsonDataTypeArrayAcceptsIndexedArray(): void
	{
		$t = (new TypeJSON())->jsonDataType('array');
		self::assertSame([1, 2, 3], $t->validate([1, 2, 3])->getCleanValue());
	}

	public function testJsonDataTypeArrayAcceptsEmptyArray(): void
	{
		$t = (new TypeJSON())->jsonDataType('array');
		self::assertSame([], $t->validate([])->getCleanValue());
	}

	public function testJsonDataTypeArrayRejectsAssocArray(): void
	{
		$t = (new TypeJSON())->jsonDataType('array');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(['a' => 1])->getCleanValue();
	}

	public function testJsonDataTypeObjectAcceptsAssocArray(): void
	{
		$t = (new TypeJSON())->jsonDataType('object');
		self::assertSame(['a' => 1], $t->validate(['a' => 1])->getCleanValue());
	}

	public function testJsonDataTypeObjectRejectsIndexedArray(): void
	{
		$t = (new TypeJSON())->jsonDataType('object');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate([1, 2, 3])->getCleanValue();
	}

	public function testJsonDataTypeObjectRejectsEmptyArray(): void
	{
		// empty array is a list (array_is_list([]) = true), so 'object' rejects it
		$t = (new TypeJSON())->jsonDataType('object');
		$this->expectException(TypesInvalidValueException::class);
		$t->validate([])->getCleanValue();
	}

	public function testGetJsonDataTypeDefaultsToAny(): void
	{
		self::assertSame('any', (new TypeJSON())->getJsonDataType());
	}

	public function testGetJsonDataTypeReturnsSetValue(): void
	{
		self::assertSame('array', (new TypeJSON())->jsonDataType('array')->getJsonDataType());
		self::assertSame('object', (new TypeJSON())->jsonDataType('object')->getJsonDataType());
	}

	// TypeList uses json_data_type='array' internally on its base TypeJSON --
	// validate sequential arrays and reject assoc (via the TypeList layer).
	public function testTypeListBaseHasJsonDataTypeArray(): void
	{
		$list = new TypeList();
		// TypeList accepts indexed arrays
		self::assertSame([1, 2], $list->validate([1, 2])->getCleanValue());
		// TypeList rejects plain objects
		$this->expectException(TypesInvalidValueException::class);
		$list->validate(new stdClass())->getCleanValue();
	}

	// TypeMap wraps any array (assoc or indexed) in a Map instance.
	// The json_data_type='object' on its base TypeJSON is for schema reflection only.
	public function testTypeMapBaseHasJsonDataTypeObject(): void
	{
		$map = new TypeMap();
		// TypeMap accepts assoc arrays and wraps them in Map
		self::assertInstanceOf(Map::class, $map->validate(['a' => 1])->getCleanValue());
		// TypeMap also accepts indexed arrays (wraps them in Map, no assoc-only enforcement)
		self::assertInstanceOf(Map::class, $map->validate([1, 2, 3])->getCleanValue());
		// TypeMap rejects non-array scalars
		$this->expectException(TypesInvalidValueException::class);
		$map->validate('not-an-array')->getCleanValue();
	}
}
