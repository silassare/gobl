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

namespace Gobl\Tests\DBAL\Types\Utils;

use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\Tests\BaseTestCase;
use InvalidArgumentException;

/**
 * Class JsonPatchTest.
 *
 * @covers \Gobl\DBAL\Types\Utils\JsonPatch
 *
 * @internal
 */
final class JsonPatchTest extends BaseTestCase
{
	// =========================================================================
	// Constructor
	// =========================================================================

	public function testConstructorWithEmptyArray(): void
	{
		$patch = new JsonPatch();
		self::assertSame([], $patch->toArray());
	}

	public function testConstructorWithArray(): void
	{
		$patch = new JsonPatch(['a' => 1, 'b' => 2]);
		self::assertSame(['a' => 1, 'b' => 2], $patch->toArray());
	}

	public function testConstructorWithMap(): void
	{
		$data  = ['x' => 'y'];
		$map   = new Map($data);
		$patch = new JsonPatch($map);
		self::assertSame(['x' => 'y'], $patch->toArray());
	}

	// =========================================================================
	// set()
	// =========================================================================

	public function testSetTopLevelKey(): void
	{
		$patch = new JsonPatch(['a' => 1]);
		$patch->set('b', 2);
		self::assertSame(['a' => 1, 'b' => 2], $patch->toArray());
	}

	public function testSetOverwritesExistingKey(): void
	{
		$patch = new JsonPatch(['a' => 'old']);
		$patch->set('a', 'new');
		self::assertSame(['a' => 'new'], $patch->toArray());
	}

	public function testSetNestedKey(): void
	{
		$patch = new JsonPatch([]);
		$patch->set('user.name', 'Alice');
		self::assertSame(['user' => ['name' => 'Alice']], $patch->toArray());
	}

	public function testSetCreatesIntermediateArrays(): void
	{
		$patch = new JsonPatch([]);
		$patch->set('a.b.c', 42);
		self::assertSame(['a' => ['b' => ['c' => 42]]], $patch->toArray());
	}

	public function testSetOverwritesNonArrayIntermediate(): void
	{
		$patch = new JsonPatch(['a' => 'scalar']);
		$patch->set('a.b', 'val');
		self::assertSame(['a' => ['b' => 'val']], $patch->toArray());
	}

	public function testSetNumericSegment(): void
	{
		$patch = new JsonPatch(['tags' => ['php', 'orm']]);
		$patch->set('tags.1', 'gobl');
		self::assertSame(['tags' => ['php', 'gobl']], $patch->toArray());
	}

	public function testSetIsChainable(): void
	{
		$patch  = new JsonPatch();
		$result = $patch->set('a', 1)->set('b', 2);
		self::assertSame($patch, $result);
		self::assertSame(['a' => 1, 'b' => 2], $patch->toArray());
	}

	public function testSetAcceptsNull(): void
	{
		$patch = new JsonPatch(['a' => 'val']);
		$patch->set('a', null);
		self::assertSame(['a' => null], $patch->toArray());
	}

	public function testSetAcceptsArray(): void
	{
		$patch = new JsonPatch();
		$patch->set('config', ['theme' => 'dark']);
		self::assertSame(['config' => ['theme' => 'dark']], $patch->toArray());
	}

	// =========================================================================
	// remove()
	// =========================================================================

	public function testRemoveTopLevelKey(): void
	{
		$patch = new JsonPatch(['a' => 1, 'b' => 2]);
		$patch->remove('a');
		self::assertSame(['b' => 2], $patch->toArray());
	}

	public function testRemoveNestedKey(): void
	{
		$patch = new JsonPatch(['user' => ['name' => 'Alice', 'age' => 30]]);
		$patch->remove('user.age');
		self::assertSame(['user' => ['name' => 'Alice']], $patch->toArray());
	}

	public function testRemoveNonExistentTopLevelIsNoOp(): void
	{
		$patch = new JsonPatch(['a' => 1]);
		$patch->remove('missing');
		self::assertSame(['a' => 1], $patch->toArray());
	}

	public function testRemoveNonExistentNestedIsNoOp(): void
	{
		$patch = new JsonPatch(['a' => ['b' => 1]]);
		$patch->remove('a.c.d');
		self::assertSame(['a' => ['b' => 1]], $patch->toArray());
	}

	public function testRemoveWhenIntermediateNotArrayIsNoOp(): void
	{
		$patch = new JsonPatch(['a' => 'scalar']);
		$patch->remove('a.b');
		self::assertSame(['a' => 'scalar'], $patch->toArray());
	}

	public function testRemoveIsChainable(): void
	{
		$patch  = new JsonPatch(['a' => 1, 'b' => 2]);
		$result = $patch->remove('b');
		self::assertSame($patch, $result);
		self::assertSame(['a' => 1], $patch->toArray());
	}

	// =========================================================================
	// toMap()
	// =========================================================================

	public function testToMapReturnsMapInstance(): void
	{
		$patch = new JsonPatch(['key' => 'val']);
		$map   = $patch->toMap();
		self::assertInstanceOf(Map::class, $map);
		self::assertSame(['key' => 'val'], $map->getData());
	}

	public function testToMapAfterMutations(): void
	{
		$patch = new JsonPatch(['x' => 1]);
		$patch->set('y', 2)->remove('x');
		self::assertSame(['y' => 2], $patch->toMap()->getData());
	}

	// =========================================================================
	// Invalid paths
	// =========================================================================

	public function testSetEmptyPathThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('path portion cannot be empty');
		(new JsonPatch())->set('', 'val');
	}

	public function testRemoveEmptyPathThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('path portion cannot be empty');
		(new JsonPatch())->remove('');
	}

	public function testSetPathWithEmptySegmentThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new JsonPatch())->set('a..b', 'val');
	}

	public function testRemovePathWithEmptySegmentThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new JsonPatch(['a' => ['b' => 1]]))->remove('a..b');
	}

	// =========================================================================
	// parsePath: JS-like bracket notation
	// =========================================================================

	public function testParsePathBracketInteger(): void
	{
		$patch = new JsonPatch();
		$patch->set('foo[0]', 'val');
		self::assertSame(['foo' => ['val']], $patch->toArray());
	}

	public function testParsePathBracketSingleQuoted(): void
	{
		$patch = new JsonPatch();
		$patch->set("foo['bar.baz']", 'val');
		self::assertSame(['foo' => ['bar.baz' => 'val']], $patch->toArray());
	}

	public function testParsePathBracketDoubleQuoted(): void
	{
		$patch = new JsonPatch();
		$patch->set('foo["bar.baz"]', 'val');
		self::assertSame(['foo' => ['bar.baz' => 'val']], $patch->toArray());
	}

	public function testParsePathBracketEscapedSingleQuote(): void
	{
		$patch = new JsonPatch();
		$patch->set("foo['it\\'s']", 'val');
		self::assertSame(['foo' => ["it's" => 'val']], $patch->toArray());
	}

	public function testParsePathBracketEscapedDoubleQuote(): void
	{
		$patch = new JsonPatch();
		$patch->set('foo["say \"hi\""]', 'val');
		self::assertSame(['foo' => ['say "hi"' => 'val']], $patch->toArray());
	}

	public function testParsePathBracketAsFirstSegment(): void
	{
		$patch = new JsonPatch();
		$patch->set('["first"].sub', 'val');
		self::assertSame(['first' => ['sub' => 'val']], $patch->toArray());
	}

	public function testParsePathConsecutiveBrackets(): void
	{
		$patch = new JsonPatch();
		$patch->set('foo["a"]["b"]', 'val');
		self::assertSame(['foo' => ['a' => ['b' => 'val']]], $patch->toArray());
	}

	public function testParsePathTrailingDotThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new JsonPatch())->set('foo.', 'val');
	}

	public function testParsePathUnclosedBracketThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new JsonPatch())->set('foo[0', 'val');
	}

	public function testParsePathInvalidBracketContentThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		(new JsonPatch())->set('foo[bar]', 'val');
	}

	// =========================================================================
	// Type coercion: TypeJSON, TypeMap, TypeList accept JsonPatch in validate()
	// =========================================================================

	public function testTypeJSONAcceptsJsonPatch(): void
	{
		$patch  = (new JsonPatch())->set('name', 'Bob')->set('age', 25);
		$type   = new TypeJSON();
		$result = $type->validate($patch)->getCleanValue();
		self::assertSame(['name' => 'Bob', 'age' => 25], $result);
	}

	public function testTypeMapAcceptsJsonPatch(): void
	{
		$patch  = (new JsonPatch())->set('key', 'value');
		$type   = new TypeMap();
		$result = $type->validate($patch)->getCleanValue();
		self::assertInstanceOf(Map::class, $result);
		self::assertSame(['key' => 'value'], $result?->getData());
	}

	public function testTypeListAcceptsJsonPatch(): void
	{
		$patch  = (new JsonPatch())->set('0', 'first')->set('1', 'second');
		$type   = new TypeList();
		$result = $type->validate($patch)->getCleanValue();
		self::assertIsArray($result);
		self::assertSame(['first', 'second'], $result);
	}

	public function testTypeMapAcceptsJsonPatchFromMapInitial(): void
	{
		$initialData = ['existing' => 'val'];
		$initial     = new Map($initialData);
		$patch       = (new JsonPatch($initial))->set('new', 'added');
		$type        = new TypeMap();
		$result      = $type->validate($patch)->getCleanValue();
		self::assertInstanceOf(Map::class, $result);
		self::assertSame(['existing' => 'val', 'new' => 'added'], $result?->getData());
	}
}
