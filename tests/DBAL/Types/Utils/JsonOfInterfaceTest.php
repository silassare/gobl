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

namespace Gobl\Tests\DBAL\Types\Utils;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeJson;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\Utils\JsonOfInterface;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\ORM\ORMUniversalType;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\Fixtures\SampleJsonOf;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;

/**
 * Class JsonOfInterfaceTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class JsonOfInterfaceTest extends BaseTestCase
{
	// =========================================================================
	// SampleJsonOf fixture - round-trip
	// =========================================================================

	public function testReviveFromArray(): void
	{
		$obj = SampleJsonOf::revive(['name' => 'Alice', 'score' => 42]);
		self::assertInstanceOf(SampleJsonOf::class, $obj);
		self::assertSame('Alice', $obj->name);
		self::assertSame(42, $obj->score);
	}

	public function testReviveFromEmptyArray(): void
	{
		$obj = SampleJsonOf::revive([]);
		self::assertSame('', $obj->name);
		self::assertSame(0, $obj->score);
	}

	public function testJsonSerializeRoundTrip(): void
	{
		$original = new SampleJsonOf('Bob', 10);
		$json     = \json_encode($original);
		$decoded  = \json_decode($json, true);
		$revived  = SampleJsonOf::revive($decoded);

		self::assertSame($original->name, $revived->name);
		self::assertSame($original->score, $revived->score);
	}

	public function testImplementsJsonSerializable(): void
	{
		$obj = new SampleJsonOf('Test', 1);
		self::assertInstanceOf(JsonSerializable::class, $obj);
		self::assertInstanceOf(JsonOfInterface::class, $obj);
	}

	// =========================================================================
	// TypeJson with json_of
	// =========================================================================

	public function testTypeJsonJsonOfValidatesInstance(): void
	{
		$type   = (new TypeJson())->jsonOf(SampleJsonOf::class);
		$obj    = new SampleJsonOf('Alice', 5);
		$result = $type->validate($obj)->getCleanValue();

		self::assertInstanceOf(SampleJsonOf::class, $result);
		self::assertSame('Alice', $result->name);
		self::assertSame(5, $result->score);
	}

	public function testTypeJsonJsonOfRevivesArray(): void
	{
		$type   = (new TypeJson())->jsonOf(SampleJsonOf::class);
		$result = $type->validate(['name' => 'Bob', 'score' => 99])->getCleanValue();

		self::assertInstanceOf(SampleJsonOf::class, $result);
		self::assertSame('Bob', $result->name);
		self::assertSame(99, $result->score);
	}

	public function testTypeJsonJsonOfRejectsWrongInstance(): void
	{
		$type = (new TypeJson())->jsonOf(SampleJsonOf::class);

		$this->expectException(TypesInvalidValueException::class);
		$type->validate(new stdClass())->getCleanValue();
	}

	public function testTypeJsonJsonOfRejectsScalar(): void
	{
		$type = (new TypeJson())->jsonOf(SampleJsonOf::class);

		$this->expectException(TypesInvalidValueException::class);
		$type->validate('not-an-object')->getCleanValue();
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJsonJsonOfDbToPhp(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = (new TypeJson())->jsonOf(SampleJsonOf::class);
		$result = $type->dbToPhp('{"name":"Charlie","score":7}', $db);

		self::assertInstanceOf(SampleJsonOf::class, $result);
		self::assertSame('Charlie', $result->name);
		self::assertSame(7, $result->score);
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJsonWithoutJsonOfDbToPhpDecodesJsonObject(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = new TypeJson();
		$result = $type->dbToPhp('{"a":1}', $db);

		self::assertSame(['a' => 1], $result);
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJsonWithoutJsonOfDbToPhpDecodesJsonArray(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = new TypeJson();
		$result = $type->dbToPhp('[1,2,3]', $db);

		self::assertSame([1, 2, 3], $result);
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJsonWithoutJsonOfDbToPhpDecodesScalar(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = new TypeJson();

		self::assertSame(42, $type->dbToPhp('42', $db));
		self::assertTrue($type->dbToPhp('true', $db));
		self::assertSame('hello', $type->dbToPhp('"hello"', $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJsonJsonOfNullDbToPhp(string $driver): void
	{
		$db   = self::getNewDbInstance($driver);
		$type = (new TypeJson())->jsonOf(SampleJsonOf::class)->nullable();

		self::assertNull($type->dbToPhp(null, $db));
	}

	public function testTypeJsonJsonOfConfigureOption(): void
	{
		$type = TypeJson::getInstance(['json_of' => SampleJsonOf::class]);
		self::assertSame(SampleJsonOf::class, $type->getJsonOf());
		self::assertSame(SampleJsonOf::class, $type->getJsonOfClass());
	}

	public function testTypeJsonJsonOfGetterWithoutOption(): void
	{
		self::assertNull((new TypeJson())->getJsonOf());
		self::assertNull((new TypeJson())->getJsonOfClass());
	}

	public function testTypeJsonJsonOfWithORMUniversalTypeStoresValue(): void
	{
		$type = (new TypeJson())->jsonOf(ORMUniversalType::MAP);
		self::assertSame(ORMUniversalType::MAP->value, $type->getJsonOf());
		self::assertNull($type->getJsonOfClass());
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJsonJsonOfWithORMUniversalTypeDecodesWithoutRevival(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = (new TypeJson())->jsonOf(ORMUniversalType::MAP);
		$result = $type->dbToPhp('{"k":"v"}', $db);

		self::assertSame(['k' => 'v'], $result);
	}

	public function testTypeJsonJsonOfORMUniversalTypeConfigureOption(): void
	{
		$type = TypeJson::getInstance(['json_of' => 'MAP']);
		self::assertSame(ORMUniversalType::MAP->value, $type->getJsonOf());
		self::assertNull($type->getJsonOfClass());
	}

	public function testTypeJsonReadTypeHintWithORMUniversalType(): void
	{
		$hint  = (new TypeJson())->jsonOf(ORMUniversalType::MAP)->getReadTypeHint();
		$types = $hint->getUniversalTypes();
		self::assertContains(ORMUniversalType::MAP, $types);
	}

	public function testTypeJsonReadTypeHintWithoutJsonOfIsUnknown(): void
	{
		$hint  = (new TypeJson())->getReadTypeHint();
		$types = $hint->getUniversalTypes();
		self::assertContains(ORMUniversalType::UNKNOWN, $types);
	}

	public function testTypeJsonJsonOfInvalidClassThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must implement');
		(new TypeJson())->jsonOf(stdClass::class);
	}

	public function testTypeJsonReadTypeHintWithJsonOf(): void
	{
		$hint = (new TypeJson())->jsonOf(SampleJsonOf::class)->getReadTypeHint();
		$php  = $hint->getPHPType();

		self::assertNotNull($php);
		self::assertStringContainsString('SampleJsonOf', (string) $php);
	}

	public function testTypeJsonWriteTypeHintWithJsonOf(): void
	{
		$hint = (new TypeJson())->jsonOf(SampleJsonOf::class)->getWriteTypeHint();
		$php  = $hint->getPHPType();

		self::assertNotNull($php);
		self::assertStringContainsString('SampleJsonOf', (string) $php);
	}

	public function testTypeJsonWriteTypeHintWithORMUniversalType(): void
	{
		$hint  = (new TypeJson())->jsonOf(ORMUniversalType::MAP)->getWriteTypeHint();
		$types = $hint->getUniversalTypes();
		self::assertContains(ORMUniversalType::MAP, $types);
	}

	public function testTypeJsonWriteTypeHintWithoutJsonOfIsUnknown(): void
	{
		$hint  = (new TypeJson())->getWriteTypeHint();
		$types = $hint->getUniversalTypes();
		self::assertContains(ORMUniversalType::UNKNOWN, $types);
	}

	// =========================================================================
	// TypeList with list_of class
	// =========================================================================

	public function testTypeListListOfClassValidatesElements(): void
	{
		$type   = (new TypeList())->listOf(SampleJsonOf::class);
		$result = $type->validate([
			['name' => 'A', 'score' => 1],
			['name' => 'B', 'score' => 2],
		])->getCleanValue();

		self::assertIsArray($result);
		self::assertCount(2, $result);
		self::assertInstanceOf(SampleJsonOf::class, $result[0]);
		self::assertSame('A', $result[0]->name);
		self::assertInstanceOf(SampleJsonOf::class, $result[1]);
		self::assertSame('B', $result[1]->name);
	}

	public function testTypeListListOfClassAcceptsInstances(): void
	{
		$type   = (new TypeList())->listOf(SampleJsonOf::class);
		$result = $type->validate([new SampleJsonOf('X', 9)])->getCleanValue();

		self::assertInstanceOf(SampleJsonOf::class, $result[0]);
		self::assertSame('X', $result[0]->name);
	}

	public function testTypeListListOfClassRejectsWrongElement(): void
	{
		$type = (new TypeList())->listOf(SampleJsonOf::class);

		$this->expectException(TypesInvalidValueException::class);
		$type->validate(['not-an-array-element'])->getCleanValue();
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeListListOfClassDbToPhpRevivesElements(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = (new TypeList())->listOf(SampleJsonOf::class);
		$result = $type->dbToPhp('[{"name":"P","score":3},{"name":"Q","score":4}]', $db);

		self::assertIsArray($result);
		self::assertCount(2, $result);
		self::assertInstanceOf(SampleJsonOf::class, $result[0]);
		self::assertSame('P', $result[0]->name);
		self::assertInstanceOf(SampleJsonOf::class, $result[1]);
		self::assertSame('Q', $result[1]->name);
	}

	public function testTypeListListOfClassConfigureOption(): void
	{
		$type = TypeList::getInstance(['list_of' => SampleJsonOf::class]);
		self::assertSame(SampleJsonOf::class, $type->getListOfClass());
	}

	public function testTypeListListOfClassGetterWithoutOption(): void
	{
		self::assertNull((new TypeList())->getListOfClass());
	}

	public function testTypeListListOfClassInvalidClassThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must implement');
		(new TypeList())->listOf(stdClass::class);
	}

	public function testTypeListListOfClassReadTypeHint(): void
	{
		$hint = (new TypeList())->listOf(SampleJsonOf::class)->getReadTypeHint();
		self::assertSame(SampleJsonOf::class, $hint->getListOfClass());
	}

	public function testTypeListListOfUniversalTypeConfigureOption(): void
	{
		$type = TypeList::getInstance(['list_of' => 'STRING']);
		self::assertNull($type->getListOfClass());
		self::assertSame(ORMUniversalType::STRING, $type->getReadTypeHint()->getListOfUniversalType());
	}

	// =========================================================================
	// JsonPatch compatibility (JsonSerializable is accepted by set())
	// =========================================================================

	public function testJsonPatchSetAcceptsJsonOfInstance(): void
	{
		$patch = new JsonPatch();
		$obj   = new SampleJsonOf('PatchTest', 77);
		// set() accepts JsonSerializable - no exception should be thrown.
		$patch->set('item', $obj);

		// toArray() stores the value verbatim (no jsonSerialize call).
		$arr = $patch->toArray();
		self::assertInstanceOf(SampleJsonOf::class, $arr['item']);
		self::assertSame('PatchTest', $arr['item']->name);
		self::assertSame(77, $arr['item']->score);
	}

	// =========================================================================
	// TypeMap with map_of class
	// =========================================================================

	public function testTypeMapMapOfClassValidatesValues(): void
	{
		$type   = (new TypeMap())->mapOf(SampleJsonOf::class);
		$result = $type->validate([
			'a' => ['name' => 'Alice', 'score' => 1],
			'b' => ['name' => 'Bob', 'score' => 2],
		])->getCleanValue();

		self::assertInstanceOf(Map::class, $result);
		self::assertInstanceOf(SampleJsonOf::class, $result->get('a'));
		self::assertSame('Alice', $result->get('a')->name);
		self::assertInstanceOf(SampleJsonOf::class, $result->get('b'));
		self::assertSame('Bob', $result->get('b')->name);
	}

	public function testTypeMapMapOfClassAcceptsInstances(): void
	{
		$type   = (new TypeMap())->mapOf(SampleJsonOf::class);
		$result = $type->validate(['x' => new SampleJsonOf('X', 9)])->getCleanValue();

		self::assertInstanceOf(SampleJsonOf::class, $result->get('x'));
		self::assertSame('X', $result->get('x')->name);
	}

	public function testTypeMapMapOfClassRejectsWrongValue(): void
	{
		$type = (new TypeMap())->mapOf(SampleJsonOf::class);

		$this->expectException(TypesInvalidValueException::class);
		$type->validate(['key' => 'not-an-array-or-instance'])->getCleanValue();
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeMapMapOfClassDbToPhpRevivesValues(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = (new TypeMap())->mapOf(SampleJsonOf::class);
		$result = $type->dbToPhp('{"p":{"name":"P","score":3},"q":{"name":"Q","score":4}}', $db);

		self::assertInstanceOf(Map::class, $result);
		self::assertInstanceOf(SampleJsonOf::class, $result->get('p'));
		self::assertSame('P', $result->get('p')->name);
		self::assertInstanceOf(SampleJsonOf::class, $result->get('q'));
		self::assertSame('Q', $result->get('q')->name);
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeMapMapOfClassPhpToDbRoundtrip(string $driver): void
	{
		$db    = self::getNewDbInstance($driver);
		$type  = (new TypeMap())->mapOf(SampleJsonOf::class);
		$input = [
			'first'  => ['name' => 'Foo', 'score' => 10],
			'second' => ['name' => 'Bar', 'score' => 20],
		];

		$encoded = $type->phpToDb($input, $db);
		$result  = $type->dbToPhp($encoded, $db);

		self::assertInstanceOf(Map::class, $result);
		self::assertInstanceOf(SampleJsonOf::class, $result->get('first'));
		self::assertSame('Foo', $result->get('first')->name);
		self::assertInstanceOf(SampleJsonOf::class, $result->get('second'));
		self::assertSame('Bar', $result->get('second')->name);
	}

	public function testTypeMapMapOfClassConfigureOption(): void
	{
		$type = TypeMap::getInstance(['map_of' => SampleJsonOf::class]);
		self::assertSame(SampleJsonOf::class, $type->getMapOfClass());
	}

	public function testTypeMapMapOfClassGetterWithoutOption(): void
	{
		self::assertNull((new TypeMap())->getMapOfClass());
	}

	public function testTypeMapMapOfClassInvalidClassThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must implement');
		(new TypeMap())->mapOf(stdClass::class);
	}

	public function testTypeMapMapOfClassReadTypeHint(): void
	{
		$hint = (new TypeMap())->mapOf(SampleJsonOf::class)->getReadTypeHint();
		self::assertSame(SampleJsonOf::class, $hint->getMapOfClass());
	}

	public function testTypeMapMapOfUniversalTypeConfigureOption(): void
	{
		$type = TypeMap::getInstance(['map_of' => 'STRING']);
		self::assertNull($type->getMapOfClass());
		self::assertSame(ORMUniversalType::STRING, $type->getMapOfUniversalType());
	}

	public function testTypeMapMapOfUniversalTypeValidatesValues(): void
	{
		$type   = (new TypeMap())->mapOf(ORMUniversalType::STRING);
		$result = $type->validate(['a' => 'hello', 'b' => 'world'])->getCleanValue();

		self::assertInstanceOf(Map::class, $result);
		self::assertSame('hello', $result->get('a'));
	}

	public function testTypeMapMapOfUniversalTypeRejectsWrongValue(): void
	{
		$type = (new TypeMap())->mapOf(ORMUniversalType::STRING);

		$this->expectException(TypesInvalidValueException::class);
		$type->validate(['a' => 42])->getCleanValue();
	}
}
