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

namespace Gobl\Tests\DBAL\Filters;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\FilterFieldNotation;
use Gobl\Tests\BaseTestCase;
use InvalidArgumentException;

/**
 * Class FilterFieldNotationTest.
 *
 * @covers \Gobl\DBAL\Filters\FilterFieldNotation
 *
 * @internal
 */
final class FilterFieldNotationTest extends BaseTestCase
{
	// -------------------------------------------------------------------
	// fromString: field only
	// -------------------------------------------------------------------

	public function testFromStringFieldOnly(): void
	{
		$ffn = FilterFieldNotation::fromString('email');

		self::assertSame('email', $ffn->getField());
		self::assertNull($ffn->getTableOrAlias());
		self::assertNull($ffn->getColumnName());
		self::assertFalse($ffn->hasPathSegments());
		self::assertSame([], $ffn->getPathSegments());
		self::assertFalse($ffn->isResolved());
	}

	// -------------------------------------------------------------------
	// fromString: table.column
	// -------------------------------------------------------------------

	public function testFromStringTableDotField(): void
	{
		$ffn = FilterFieldNotation::fromString('users.email');

		self::assertSame('email', $ffn->getField());
		self::assertSame('users', $ffn->getTableOrAlias());
		self::assertFalse($ffn->hasPathSegments());
	}

	// -------------------------------------------------------------------
	// fromString: field#path
	// -------------------------------------------------------------------

	public function testFromStringFieldWithDotPath(): void
	{
		$ffn = FilterFieldNotation::fromString('data#foo.bar');

		self::assertSame('data', $ffn->getField());
		self::assertNull($ffn->getTableOrAlias());
		self::assertSame(['foo', 'bar'], $ffn->getPathSegments());
		self::assertTrue($ffn->hasPathSegments());
	}

	// -------------------------------------------------------------------
	// fromString: table.column#path
	// -------------------------------------------------------------------

	public function testFromStringTableDotFieldWithPath(): void
	{
		$ffn = FilterFieldNotation::fromString('orders.meta#shipping.address');

		self::assertSame('meta', $ffn->getField());
		self::assertSame('orders', $ffn->getTableOrAlias());
		self::assertSame(['shipping', 'address'], $ffn->getPathSegments());
	}

	// -------------------------------------------------------------------
	// fromString: bracket-integer segment
	// -------------------------------------------------------------------

	public function testFromStringBracketIntegerSegment(): void
	{
		$ffn = FilterFieldNotation::fromString('tags#items[0]');

		self::assertSame(['items', '0'], $ffn->getPathSegments());
	}

	// -------------------------------------------------------------------
	// fromString: bracket-quoted segment with special chars
	// -------------------------------------------------------------------

	public function testFromStringBracketQuotedSegment(): void
	{
		$ffn = FilterFieldNotation::fromString("meta#['key.with.dots']");

		self::assertSame(['key.with.dots'], $ffn->getPathSegments());
	}

	public function testFromStringDoubleQuotedSegment(): void
	{
		$ffn = FilterFieldNotation::fromString('meta#["bar"]["baz"]');

		self::assertSame(['bar', 'baz'], $ffn->getPathSegments());
	}

	// -------------------------------------------------------------------
	// __toString round-trip
	// -------------------------------------------------------------------

	public function testToStringNoPath(): void
	{
		$ffn = FilterFieldNotation::fromString('email');

		self::assertSame('email', (string) $ffn);
	}

	public function testToStringWithTableAndNoPath(): void
	{
		$ffn = FilterFieldNotation::fromString('users.email');

		self::assertSame('users.email', (string) $ffn);
	}

	public function testToStringWithPath(): void
	{
		$ffn = FilterFieldNotation::fromString('data#foo.bar');

		self::assertSame('data#foo.bar', (string) $ffn);
	}

	public function testToStringWithTableAndPath(): void
	{
		$ffn = FilterFieldNotation::fromString('orders.meta#shipping.address');

		self::assertSame('orders.meta#shipping.address', (string) $ffn);
	}

	// -------------------------------------------------------------------
	// getPathSegmentsAsString round-trip
	// -------------------------------------------------------------------

	public function testGetPathSegmentsAsStringSimple(): void
	{
		$ffn = FilterFieldNotation::fromString('col#a.b.c');

		self::assertSame('a.b.c', $ffn->getPathSegmentsAsString());
	}

	public function testGetPathSegmentsAsStringEmpty(): void
	{
		$ffn = FilterFieldNotation::fromString('col');

		self::assertSame('', $ffn->getPathSegmentsAsString());
	}

	// -------------------------------------------------------------------
	// markAsResolved / isResolved / getResolvedColumnOrFail
	// -------------------------------------------------------------------

	public function testMarkAsResolved(): void
	{
		$ffn = FilterFieldNotation::fromString('name');

		self::assertFalse($ffn->isResolved());

		$ffn->markAsResolved('users', 'u_name');

		self::assertTrue($ffn->isResolved());
		self::assertSame('users', $ffn->getTableOrAlias());
		self::assertSame('u_name', $ffn->getColumnName());
	}

	public function testGetResolvedColumnOrFailThrowsWhenNotResolved(): void
	{
		$ffn = FilterFieldNotation::fromString('name');

		$this->expectException(DBALRuntimeException::class);
		$ffn->getResolvedColumnOrFail();
	}

	// -------------------------------------------------------------------
	// Empty field name
	// -------------------------------------------------------------------

	public function testEmptyFieldNameThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		new FilterFieldNotation('');
	}

	// -------------------------------------------------------------------
	// parsePath (deprecated but still callable)
	// -------------------------------------------------------------------

	public function testParsePathSimple(): void
	{
		$segments = FilterFieldNotation::parsePath('foo.bar.baz');

		self::assertSame(['foo', 'bar', 'baz'], $segments);
	}

	public function testParsePathMixed(): void
	{
		$segments = FilterFieldNotation::parsePath("foo[0]['bar.baz']");

		self::assertSame(['foo', '0', 'bar.baz'], $segments);
	}
}
