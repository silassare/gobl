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
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\Utils\JsonOfInterface;
use Gobl\DBAL\Types\Utils\JsonPatch;
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
	// TypeJSON with json_of
	// =========================================================================

	public function testTypeJSONJsonOfValidatesInstance(): void
	{
		$type   = (new TypeJSON())->jsonOf(SampleJsonOf::class);
		$obj    = new SampleJsonOf('Alice', 5);
		$result = $type->validate($obj)->getCleanValue();

		self::assertInstanceOf(SampleJsonOf::class, $result);
		self::assertSame('Alice', $result->name);
		self::assertSame(5, $result->score);
	}

	public function testTypeJSONJsonOfRevivesArray(): void
	{
		$type   = (new TypeJSON())->jsonOf(SampleJsonOf::class);
		$result = $type->validate(['name' => 'Bob', 'score' => 99])->getCleanValue();

		self::assertInstanceOf(SampleJsonOf::class, $result);
		self::assertSame('Bob', $result->name);
		self::assertSame(99, $result->score);
	}

	public function testTypeJSONJsonOfRejectsWrongInstance(): void
	{
		$type = (new TypeJSON())->jsonOf(SampleJsonOf::class);

		$this->expectException(TypesInvalidValueException::class);
		$type->validate(new stdClass())->getCleanValue();
	}

	public function testTypeJSONJsonOfRejectsScalar(): void
	{
		$type = (new TypeJSON())->jsonOf(SampleJsonOf::class);

		$this->expectException(TypesInvalidValueException::class);
		$type->validate('not-an-object')->getCleanValue();
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJSONJsonOfDbToPhp(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = (new TypeJSON())->jsonOf(SampleJsonOf::class);
		$result = $type->dbToPhp('{"name":"Charlie","score":7}', $db);

		self::assertInstanceOf(SampleJsonOf::class, $result);
		self::assertSame('Charlie', $result->name);
		self::assertSame(7, $result->score);
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJSONWithoutJsonOfDbToPhpDecodesJsonObject(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = new TypeJSON();
		$result = $type->dbToPhp('{"a":1}', $db);

		self::assertSame(['a' => 1], $result);
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJSONWithoutJsonOfDbToPhpDecodesJsonArray(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = new TypeJSON();
		$result = $type->dbToPhp('[1,2,3]', $db);

		self::assertSame([1, 2, 3], $result);
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJSONWithoutJsonOfDbToPhpDecodesScalar(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = new TypeJSON();

		self::assertSame(42, $type->dbToPhp('42', $db));
		self::assertTrue($type->dbToPhp('true', $db));
		self::assertSame('hello', $type->dbToPhp('"hello"', $db));
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJSONJsonOfNullDbToPhp(string $driver): void
	{
		$db   = self::getNewDbInstance($driver);
		$type = (new TypeJSON())->jsonOf(SampleJsonOf::class)->nullable();

		self::assertNull($type->dbToPhp(null, $db));
	}

	public function testTypeJSONJsonOfConfigureOption(): void
	{
		$type = TypeJSON::getInstance(['json_of' => SampleJsonOf::class]);
		self::assertSame(SampleJsonOf::class, $type->getJsonOf());
		self::assertSame(SampleJsonOf::class, $type->getJsonOfClass());
	}

	public function testTypeJSONJsonOfGetterWithoutOption(): void
	{
		self::assertNull((new TypeJSON())->getJsonOf());
		self::assertNull((new TypeJSON())->getJsonOfClass());
	}

	public function testTypeJSONJsonOfWithORMUniversalTypeStoresValue(): void
	{
		$type = (new TypeJSON())->jsonOf(ORMUniversalType::MAP);
		self::assertSame(ORMUniversalType::MAP->value, $type->getJsonOf());
		self::assertNull($type->getJsonOfClass());
	}

	/**
	 * @dataProvider Gobl\Tests\BaseTestCase::allDrivers
	 */
	public function testTypeJSONJsonOfWithORMUniversalTypeDecodesWithoutRevival(string $driver): void
	{
		$db     = self::getNewDbInstance($driver);
		$type   = (new TypeJSON())->jsonOf(ORMUniversalType::MAP);
		$result = $type->dbToPhp('{"k":"v"}', $db);

		self::assertSame(['k' => 'v'], $result);
	}

	public function testTypeJSONJsonOfORMUniversalTypeConfigureOption(): void
	{
		$type = TypeJSON::getInstance(['json_of' => 'MAP']);
		self::assertSame(ORMUniversalType::MAP->value, $type->getJsonOf());
		self::assertNull($type->getJsonOfClass());
	}

	public function testTypeJSONReadTypeHintWithORMUniversalType(): void
	{
		$hint  = (new TypeJSON())->jsonOf(ORMUniversalType::MAP)->getReadTypeHint();
		$types = $hint->getUniversalTypes();
		self::assertContains(ORMUniversalType::MAP, $types);
	}

	public function testTypeJSONReadTypeHintWithoutJsonOfIsUnknown(): void
	{
		$hint  = (new TypeJSON())->getReadTypeHint();
		$types = $hint->getUniversalTypes();
		self::assertContains(ORMUniversalType::UNKNOWN, $types);
	}

	public function testTypeJSONJsonOfInvalidClassThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('must implement');
		(new TypeJSON())->jsonOf(stdClass::class);
	}

	public function testTypeJSONReadTypeHintWithJsonOf(): void
	{
		$hint = (new TypeJSON())->jsonOf(SampleJsonOf::class)->getReadTypeHint();
		$php  = $hint->getPHPType();

		self::assertNotNull($php);
		self::assertStringContainsString('SampleJsonOf', (string) $php);
	}

	public function testTypeJSONWriteTypeHintWithJsonOf(): void
	{
		$hint = (new TypeJSON())->jsonOf(SampleJsonOf::class)->getWriteTypeHint();
		$php  = $hint->getPHPType();

		self::assertNotNull($php);
		self::assertStringContainsString('SampleJsonOf', (string) $php);
	}

	public function testTypeJSONWriteTypeHintWithORMUniversalType(): void
	{
		$hint  = (new TypeJSON())->jsonOf(ORMUniversalType::MAP)->getWriteTypeHint();
		$types = $hint->getUniversalTypes();
		self::assertContains(ORMUniversalType::MAP, $types);
	}

	public function testTypeJSONWriteTypeHintWithoutJsonOfIsUnknown(): void
	{
		$hint  = (new TypeJSON())->getWriteTypeHint();
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
}
