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
use Gobl\DBAL\Types\TypeJSON;
use Gobl\Tests\BaseTestCase;
use JsonSerializable;

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
		$result = $t->validate(['name' => 'John', 'age' => 30]);
		self::assertSame('{"name":"John","age":30}', $result);
	}

	public function testValidateAcceptsIndexedArray(): void
	{
		$t      = new TypeJSON();
		$result = $t->validate([1, 2, 3]);
		self::assertSame('[1,2,3]', $result);
	}

	public function testValidateAcceptsEmptyArray(): void
	{
		$t      = new TypeJSON();
		$result = $t->validate([]);
		self::assertSame('[]', $result);
	}

	public function testValidateAcceptsNull(): void
	{
		$t      = (new TypeJSON())->nullable();
		$result = $t->validate(null);
		self::assertNull($result);
	}

	// TypeJSON only accepts arrays and objects; any string (even valid JSON) is rejected,
	// because TypeJSON is the *base* type used by TypeMap/TypeList for DB encoding.
	public function testValidateRejectsJsonString(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJSON())->validate('{"foo":"bar"}');
	}

	public function testValidateRejectsJsonArrayString(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJSON())->validate('[1,2,3]');
	}

	// =========================================================================
	// TypeJSON::validate - rejection / error paths
	// =========================================================================

	public function testValidateRejectsNullWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJSON())->validate(null);
	}

	public function testValidateRejectsPlainString(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		(new TypeJSON())->validate('not-json');
	}

	// =========================================================================
	// TypeJSON::validate - default value
	// =========================================================================

	public function testValidateUsesDefaultWhenNull(): void
	{
		$t      = (new TypeJSON())->default(['key' => 'val']);
		$result = $t->validate(null);
		self::assertSame('{"key":"val"}', $result);
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
}
