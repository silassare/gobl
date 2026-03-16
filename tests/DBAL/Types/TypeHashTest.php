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

use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\Map;
use Gobl\Tests\BaseTestCase;

/** Fixture string-backed enum. */
enum HashTestStatus: string
{
	case Active   = 'active';
	case Inactive = 'inactive';
}

/** Fixture int-backed enum. */
enum HashTestPriority: int
{
	case Low  = 1;
	case High = 3;
}

/**
 * Class TypeHashTest.
 *
 * Tests for the TypeInterface::hash() contract and its default implementation in Type.
 *
 * @covers \Gobl\DBAL\Types\Type::hash
 *
 * @internal
 */
final class TypeHashTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// null
	// -------------------------------------------------------------------------

	public function testHashNullReturnsEmptyString(): void
	{
		$t = new TypeString();
		self::assertSame('', $t->hash(null));
	}

	// -------------------------------------------------------------------------
	// scalars
	// -------------------------------------------------------------------------

	public function testHashStringPassthrough(): void
	{
		$t = new TypeString();
		self::assertSame('hello', $t->hash('hello'));
	}

	public function testHashIntCastToString(): void
	{
		$t = new TypeBigint();
		self::assertSame('42', $t->hash(42));
	}

	public function testHashFloatCastToString(): void
	{
		$t = new TypeString();
		self::assertSame('3.14', $t->hash(3.14));
	}

	public function testHashBoolTrueIsOne(): void
	{
		$t = new TypeString();
		self::assertSame('1', $t->hash(true));
	}

	public function testHashBoolFalseIsEmptyString(): void
	{
		$t = new TypeString();
		// (string) false === ''
		self::assertSame('', $t->hash(false));
	}

	// -------------------------------------------------------------------------
	// BackedEnum - hash is the backing scalar value only
	// -------------------------------------------------------------------------

	public function testHashStringEnum(): void
	{
		$t = new TypeEnum(HashTestStatus::class);
		self::assertSame('active', $t->hash(HashTestStatus::Active));
		self::assertSame('inactive', $t->hash(HashTestStatus::Inactive));
	}

	public function testHashIntEnum(): void
	{
		$t = new TypeEnum(HashTestPriority::class);
		self::assertSame('1', $t->hash(HashTestPriority::Low));
		self::assertSame('3', $t->hash(HashTestPriority::High));
	}

	/**
	 * Two separately obtained instances of the same enum case have the same hash.
	 */
	public function testHashSameEnumCaseEquality(): void
	{
		$t = new TypeEnum(HashTestStatus::class);
		$a = HashTestStatus::from('active');
		$b = HashTestStatus::from('active');
		self::assertSame($t->hash($a), $t->hash($b));
	}

	/**
	 * Different enum cases have different hashes.
	 */
	public function testHashDifferentEnumCasesNotEqual(): void
	{
		$t = new TypeEnum(HashTestStatus::class);
		self::assertNotSame($t->hash(HashTestStatus::Active), $t->hash(HashTestStatus::Inactive));
	}

	// -------------------------------------------------------------------------
	// array -> json_encode
	// -------------------------------------------------------------------------

	public function testHashArrayUsesJsonEncode(): void
	{
		$t = new TypeJSON();
		self::assertSame(\json_encode(['a' => 1, 'b' => 2]), $t->hash(['a' => 1, 'b' => 2]));
	}

	public function testHashArrayOrderMatters(): void
	{
		$t = new TypeJSON();
		// Different key order -> different JSON -> different hash
		self::assertNotSame($t->hash(['a' => 1, 'b' => 2]), $t->hash(['b' => 2, 'a' => 1]));
	}

	public function testHashSameArrayContentEquals(): void
	{
		$t = new TypeJSON();
		self::assertSame($t->hash(['x' => 'y']), $t->hash(['x' => 'y']));
	}

	// -------------------------------------------------------------------------
	// Map (JsonSerializable object) -> json_encode
	// -------------------------------------------------------------------------

	public function testHashMapUsesJsonEncode(): void
	{
		$t    = new TypeMap();
		$data = ['key' => 'value'];
		$map  = new Map($data);
		self::assertSame(\json_encode($data), $t->hash($map));
	}

	public function testHashSameMapContentEquals(): void
	{
		$t    = new TypeMap();
		$data = ['key' => 'value'];
		$ma   = new Map($data);
		$mb   = new Map($data);
		self::assertSame($t->hash($ma), $t->hash($mb));
	}

	public function testHashDifferentMapContentNotEqual(): void
	{
		$t   = new TypeMap();
		$da  = ['key' => 'a'];
		$db  = ['key' => 'b'];
		$ma  = new Map($da);
		$mb  = new Map($db);
		self::assertNotSame($t->hash($ma), $t->hash($mb));
	}

	/**
	 * When the same Map instance is mutated, its hash changes.
	 * This is the core guarantee that dirty tracking relies on.
	 */
	public function testHashMapMutationChangesHash(): void
	{
		$t    = new TypeMap();
		$data = ['key' => 'original'];
		$map  = new Map($data);

		$before = $t->hash($map);

		$map->set('key', 'mutated');

		$after = $t->hash($map);

		self::assertNotSame($before, $after, 'hash() must change after Map mutation');
	}
}
