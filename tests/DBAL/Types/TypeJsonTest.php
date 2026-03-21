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

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeJson;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\ORM\ORMUniversalType;
use Gobl\Tests\BaseTestCase;
use JsonSerializable;
use PHPUtils\Exceptions\RuntimeException as PHPUtilsRuntimeException;
use PHPUtils\Store\Map;
use stdClass;

/**
 * Class TypeJsonTest.
 *
 * @covers \Gobl\DBAL\Types\TypeJson
 *
 * @internal
 */
final class TypeJsonTest extends BaseTestCase
{
	// =========================================================================
	// TypeJson::validate - happy paths
	// =========================================================================

	public function testValidateAcceptsAssocArray(): void
	{
		$t      = new TypeJson();
		$result = $t->validate(['name' => 'John', 'age' => 30])->getCleanValue();
		self::assertSame(['name' => 'John', 'age' => 30], $result);
	}

	public function testValidateAcceptsIndexedArray(): void
	{
		$t      = new TypeJson();
		$result = $t->validate([1, 2, 3])->getCleanValue();
		self::assertSame([1, 2, 3], $result);
	}

	public function testValidateAcceptsEmptyArray(): void
	{
		$t      = new TypeJson();
		$result = $t->validate([])->getCleanValue();
		self::assertSame([], $result);
	}

	public function testValidateAcceptsNull(): void
	{
		$t      = (new TypeJson())->nullable();
		$result = $t->validate(null)->getCleanValue();
		self::assertNull($result);
	}

	// TypeJson accepts any JSON-serialisable value including strings.
	public function testValidateAcceptsJsonString(): void
	{
		self::assertSame('foo', (new TypeJson())->validate('foo')->getCleanValue());
	}

	public function testValidateAcceptsJsonArrayString(): void
	{
		self::assertSame('[1,2,3]', (new TypeJson())->validate('[1,2,3]')->getCleanValue());
	}

	// =========================================================================
	// TypeJson::validate - rejection / error paths
	// =========================================================================

	public function testValidateRejectsNullWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJson())->validate(null)->getCleanValue();
	}

	public function testValidateAcceptsPlainString(): void
	{
		self::assertSame('not-json', (new TypeJson())->validate('not-json')->getCleanValue());
	}

	// =========================================================================
	// TypeJson::validate - default value
	// =========================================================================

	public function testValidateUsesDefaultWhenNull(): void
	{
		$t      = (new TypeJson())->default(['key' => 'val']);
		$result = $t->validate(null)->getCleanValue();
		self::assertSame(['key' => 'val'], $result);
	}

	// =========================================================================
	// TypeJson::serializeJsonValue - static helper
	// =========================================================================

	/** null passes through unchanged. */
	public function testSerializeJsonValueNull(): void
	{
		self::assertNull(TypeJson::serializeJsonValue(null));
	}

	/** string passes through unchanged (assumed pre-encoded). */
	public function testSerializeJsonValueStringPassthrough(): void
	{
		self::assertSame('"hello"', TypeJson::serializeJsonValue('"hello"'));
		self::assertSame('{"role":"admin"}', TypeJson::serializeJsonValue('{"role":"admin"}'));
	}

	/** int is JSON-encoded to a numeric string. */
	public function testSerializeJsonValueInt(): void
	{
		self::assertSame('42', TypeJson::serializeJsonValue(42));
		self::assertSame('-1', TypeJson::serializeJsonValue(-1));
	}

	/** float is JSON-encoded to a numeric string. */
	public function testSerializeJsonValueFloat(): void
	{
		self::assertSame('3.14', TypeJson::serializeJsonValue(3.14));
	}

	/** bool true/false are JSON-encoded. */
	public function testSerializeJsonValueBool(): void
	{
		self::assertSame('true', TypeJson::serializeJsonValue(true));
		self::assertSame('false', TypeJson::serializeJsonValue(false));
	}

	/** array is JSON-encoded. */
	public function testSerializeJsonValueArray(): void
	{
		self::assertSame('["a","b"]', TypeJson::serializeJsonValue(['a', 'b']));
		self::assertSame('{"role":"admin"}', TypeJson::serializeJsonValue(['role' => 'admin']));
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
		self::assertSame('{"x":true}', TypeJson::serializeJsonValue($obj));
	}

	// =========================================================================
	// TypeJson lock / assertNotLocked / default validation
	// =========================================================================

	public function testLockPreventsSetOption(): void
	{
		$t = (new TypeJson())->lock();

		$this->expectException(PHPUtilsRuntimeException::class);
		$this->expectExceptionMessage('cannot be modified');
		$t->nativeJson(true); // internally calls setOption()
	}

	public function testLockIsIdempotent(): void
	{
		$t = new TypeJson();
		$t->lock();
		$t->lock(); // second call must not throw
		self::assertTrue(true);
	}

	public function testAssertNotLockedThrowsWhenLocked(): void
	{
		$t = (new TypeJson())->lock();

		$this->expectException(PHPUtilsRuntimeException::class);
		$t->assertNotLocked();
	}

	public function testAssertNotLockedPassesWhenUnlocked(): void
	{
		(new TypeJson())->assertNotLocked(); // must not throw
		self::assertTrue(true);
	}

	public function testCloneResetsLock(): void
	{
		$original = (new TypeJson())->lock();
		$cloned   = clone $original;
		$cloned->assertNotLocked(); // must not throw
		self::assertTrue(true);
	}

	public function testValidDefaultPassesOnLock(): void
	{
		$t = (new TypeJson())->default(['ok' => true]);
		$t->lock(); // must not throw
		self::assertTrue(true);
	}

	public function testScalarDefaultPassesOnLock(): void
	{
		// scalars are now valid for TypeJson when json_of is not set
		$t = (new TypeJson())->default('a scalar string');
		$t->lock(); // must not throw
		self::assertTrue(true);
	}

	public function testInvalidDefaultThrowsOnLock(): void
	{
		// json_of=LIST requires a sequential array; a string default must fail
		$t = (new TypeJson())->jsonOf(ORMUniversalType::LIST)->default('not an array');

		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessage('Default value for type "json" failed validation.');
		$t->lock();
	}

	// =========================================================================
	// ORMUniversalType-based shape enforcement via json_of
	// =========================================================================

	public function testJsonOfListAcceptsIndexedArray(): void
	{
		$t = (new TypeJson())->jsonOf(ORMUniversalType::LIST);
		self::assertSame([1, 2, 3], $t->validate([1, 2, 3])->getCleanValue());
	}

	public function testJsonOfListAcceptsEmptyArray(): void
	{
		$t = (new TypeJson())->jsonOf(ORMUniversalType::LIST);
		self::assertSame([], $t->validate([])->getCleanValue());
	}

	public function testJsonOfListRejectsAssocArray(): void
	{
		$t = (new TypeJson())->jsonOf(ORMUniversalType::LIST);
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(['a' => 1])->getCleanValue();
	}

	public function testJsonOfMapAcceptsAssocArray(): void
	{
		$t = (new TypeJson())->jsonOf(ORMUniversalType::MAP);
		self::assertSame(['a' => 1], $t->validate(['a' => 1])->getCleanValue());
	}

	public function testJsonOfMapAcceptsIndexedArray(): void
	{
		// ORMUniversalType::MAP::isValidValue accepts any PHP array (indexed or associative)
		$t = (new TypeJson())->jsonOf(ORMUniversalType::MAP);
		self::assertSame([1, 2, 3], $t->validate([1, 2, 3])->getCleanValue());
	}

	public function testJsonOfMapAcceptsEmptyArray(): void
	{
		$t = (new TypeJson())->jsonOf(ORMUniversalType::MAP);
		self::assertSame([], $t->validate([])->getCleanValue());
	}

	public function testJsonOfAnyUniversalTypeAcceptsBothShapes(): void
	{
		// ORMUniversalType cases other than LIST/MAP impose no shape restriction
		$t = (new TypeJson())->jsonOf(ORMUniversalType::ANY);
		self::assertSame(['a' => 1], $t->validate(['a' => 1])->getCleanValue());
		self::assertSame([1, 2, 3], $t->validate([1, 2, 3])->getCleanValue());
	}

	// TypeList uses ORMUniversalType::LIST on its base TypeJson -- accepts sequential arrays.
	public function testTypeListBaseUsesUniversalTypeList(): void
	{
		$list = new TypeList();
		// TypeList accepts indexed arrays
		self::assertSame([1, 2], $list->validate([1, 2])->getCleanValue());
		// TypeList rejects plain objects
		$this->expectException(TypesInvalidValueException::class);
		$list->validate(new stdClass())->getCleanValue();
	}

	// TypeMap uses ORMUniversalType::MAP on its base TypeJson for schema reflection only;
	// TypeMap's own runValidation wraps any array in Map without shape restriction.
	public function testTypeMapBaseUsesUniversalTypeMap(): void
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
