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
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\Tests\BaseTestCase;

/**
 * Class TypeMapTest.
 *
 * @covers \Gobl\DBAL\Types\TypeMap
 *
 * @internal
 */
final class TypeMapTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// validate() happy path
	// -------------------------------------------------------------------------

	public function testValidateAcceptsAssocArray(): void
	{
		$t      = new TypeMap();
		$result = $t->validate(['name' => 'John', 'age' => 30]);
		self::assertInstanceOf(Map::class, $result);
		self::assertSame(['name' => 'John', 'age' => 30], $result->getData());
	}

	public function testValidateAcceptsEmptyArray(): void
	{
		$t      = new TypeMap();
		$result = $t->validate([]);
		self::assertInstanceOf(Map::class, $result);
		self::assertSame([], $result->getData());
	}

	public function testValidateAcceptsMapInstance(): void
	{
		$t    = new TypeMap();
		$data = ['key' => 'value'];
		$map  = new Map($data);
		self::assertSame($map, $t->validate($map));
	}

	public function testValidateAcceptsNestedMap(): void
	{
		$t      = new TypeMap();
		$result = $t->validate(['user' => ['name' => 'Alice', 'roles' => ['admin', 'editor']]]);
		self::assertInstanceOf(Map::class, $result);
		self::assertSame('Alice', $result->getData()['user']['name']);
	}

	// -------------------------------------------------------------------------
	// validate() invalid input
	// -------------------------------------------------------------------------

	public function testValidateRejectsString(): void
	{
		$t = new TypeMap();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate('not a map');
	}

	public function testValidateRejectsInt(): void
	{
		$t = new TypeMap();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(42);
	}

	public function testValidateRejectsStdClass(): void
	{
		// plain objects that are not Map instances are rejected
		$t = new TypeMap();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(new \stdClass());
	}

	// -------------------------------------------------------------------------
	// null / nullable / default
	// -------------------------------------------------------------------------

	public function testValidateNullWithNullable(): void
	{
		$t = (new TypeMap())->nullable();
		self::assertNull($t->validate(null));
	}

	public function testValidateNullWithoutNullableThrows(): void
	{
		$t = new TypeMap();
		$this->expectException(TypesInvalidValueException::class);
		$t->validate(null);
	}

	public function testValidateNullUsesDefaultArray(): void
	{
		$t      = (new TypeMap())->default(['status' => 'new']);
		$result = $t->validate(null);
		self::assertInstanceOf(Map::class, $result);
		self::assertSame('new', $result->getData()['status']);
	}

	public function testGetDefaultReturnsMapWhenDefaultSet(): void
	{
		$t = (new TypeMap())->default(['x' => 1]);
		$d = $t->getDefault();
		self::assertInstanceOf(Map::class, $d);
		self::assertSame(['x' => 1], $d->getData());
	}

	public function testGetDefaultReturnsNullWhenNotSet(): void
	{
		$t = new TypeMap();
		self::assertNull($t->getDefault());
	}

	public function testGetEmptyValueOfTypeReturnsEmptyMap(): void
	{
		$t = new TypeMap();
		$e = $t->getEmptyValueOfType();
		self::assertInstanceOf(Map::class, $e);
		self::assertSame([], $e->getData());
	}

	public function testGetEmptyValueOfTypeNullableReturnsNull(): void
	{
		$t = (new TypeMap())->nullable();
		self::assertNull($t->getEmptyValueOfType());
	}

	// -------------------------------------------------------------------------
	// phpToDb / dbToPhp
	// -------------------------------------------------------------------------

	public function testPhpToDbSerializesToJson(): void
	{
		$db     = self::getDb(MySQL::NAME);
		$t      = new TypeMap();
		$result = $t->phpToDb(['foo' => 'bar', 'n' => 42], $db);
		self::assertJson($result);
		self::assertSame(['foo' => 'bar', 'n' => 42], \json_decode($result, true));
	}

	public function testPhpToDbEmptyArraySerializesAsEmptyJsonObject(): void
	{
		// phpToDb empty array => {}
		$db     = self::getDb(MySQL::NAME);
		$t      = new TypeMap();
		$result = $t->phpToDb([], $db);
		self::assertSame('{}', $result);
	}

	public function testPhpToDbNullableReturnsNull(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = (new TypeMap())->nullable();
		self::assertNull($t->phpToDb(null, $db));
	}

	public function testDbToPhpDeserializesFromJson(): void
	{
		$db     = self::getDb(MySQL::NAME);
		$t      = new TypeMap();
		$result = $t->dbToPhp('{"city":"Paris","pop":2161000}', $db);
		self::assertInstanceOf(Map::class, $result);
		self::assertSame('Paris', $result->get('city'));
		self::assertSame(2161000, $result->get('pop'));
	}

	public function testDbToPhpNullReturnsNull(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = new TypeMap();
		self::assertNull($t->dbToPhp(null, $db));
	}

	public function testDbToPhpEmptyStringReturnsNull(): void
	{
		$db = self::getDb(MySQL::NAME);
		$t  = new TypeMap();
		self::assertNull($t->dbToPhp('', $db));
	}

	public function testDbToPhpInvalidJsonFallsBackToEmptyMap(): void
	{
		$db     = self::getDb(MySQL::NAME);
		$t      = new TypeMap();
		// for scalars, wraps to empty Map
		$result = $t->dbToPhp('"just_a_string"', $db);
		self::assertInstanceOf(Map::class, $result);
		self::assertSame([], $result->getData());
	}

	public function testPhpToDbRoundtrip(): void
	{
		$db      = self::getDb(MySQL::NAME);
		$t       = new TypeMap();
		$input   = ['alpha' => 1, 'beta' => ['nested' => true]];
		$encoded = $t->phpToDb($input, $db);
		$decoded = $t->dbToPhp($encoded, $db);
		self::assertInstanceOf(Map::class, $decoded);
		self::assertSame($input, $decoded->getData());
	}

	// -------------------------------------------------------------------------
	// big() / getName()
	// -------------------------------------------------------------------------

	public function testGetName(): void
	{
		self::assertSame('map', (new TypeMap())->getName());
	}
}
