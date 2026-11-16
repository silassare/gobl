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

namespace Gobl\Tests\ORM;

use Gobl\DBAL\Column;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeString;
use Gobl\ORM\ORMShape;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\Fixtures\RegistryCheckedType;
use Gobl\Tests\Fixtures\SampleKind;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class ORMShapeTest.
 *
 * What a table says about its own values, as a shape: the half of the loop that reads a schema.
 *
 * Asserted against the test schema, which has one of most things a real one has -- an
 * auto-incremented unsigned key, strings with a length and a list of values, an unsigned decimal
 * with a default, a float with a maximum, an `auto` date, a boolean with a default and a native
 * json map.
 *
 * @internal
 */
#[CoversClass(ORMShape::class)]
final class ORMShapeTest extends BaseTestCase
{
	public function testATableAnswersWhatIdentifiesItAndBothHalves(): void
	{
		$described = ORMShape::ofTable(
			self::getNewDbInstanceWithSchema()
				->getTableOrFail('accounts')
		);

		self::assertSame('accounts', $described['name']);
		self::assertSame('accounts', $described['table']);
		// The prefix a row's keys carry, while the shape's properties are the unprefixed names.
		self::assertSame('account', $described['prefix']);
		self::assertSame(['id'], $described['key']);
		self::assertNull($described['label']);
		self::assertSame('object', $described['read']['t']);
		self::assertSame('object', $described['write']['t']);
	}

	/**
	 * Reading a row answers every column; writing one takes only what nothing else fills in.
	 */
	public function testWritingTakesOnlyWhatNothingElseFillsIn(): void
	{
		$described = ORMShape::ofTable(
			self::getNewDbInstanceWithSchema()
				->getTableOrFail('accounts')
		);

		self::assertSame([
			'id',
			'client_id',
			'label',
			'balance',
			'currency_code',
			'data',
			'created_at',
			'updated_at',
			'valid',
		], $described['read']['required']);

		// Left out: the key the database gives, the decimal and the boolean with a default, the
		// map with one, and the two `auto` dates -- which are their own default.
		self::assertSame(['client_id', 'label', 'currency_code'], $described['write']['required']);
	}

	public function testAnAutoIncrementedKeyIsNothingUntilItIsSaved(): void
	{
		$described = ORMShape::ofTable(
			self::getNewDbInstanceWithSchema()
				->getTableOrFail('accounts')
		);

		self::assertArrayNotHasKey('nullable', $described['read']['props']['id']);
		self::assertTrue($described['write']['props']['id']['nullable']);
	}

	/**
	 * Each column's own kind, which is the whole of the mapping.
	 */
	public function testEachColumnBecomesTheKindItsValueIs(): void
	{
		$db    = self::getNewDbInstanceWithSchema();
		$props = ORMShape::ofTable($db->getTableOrFail('accounts'))['read']['props'];

		// A bigint travels as text, so it is held exactly rather than as a number: `unsigned` is
		// said as the bound it is.
		self::assertSame(['t' => 'exact', 'scale' => 0, 'min' => '0'], $props['id']);
		// A decimal that declared no scale keeps any number of places, as its validation does.
		self::assertSame(['t' => 'exact', 'min' => '0'], $props['balance']);
		self::assertSame(['t' => 'string', 'min' => 1, 'max' => 60], $props['label']);
		self::assertSame(['t' => 'date'], $props['created_at']);
		self::assertSame(['t' => 'boolean'], $props['valid']);
		self::assertSame(['t' => 'record', 'values' => ['t' => 'json']], $props['data']);

		$clients = ORMShape::ofTable($db->getTableOrFail('clients'))['read']['props'];

		self::assertSame([
			't'      => 'string',
			'values' => ['male', 'female', 'unknown'],
			'min'    => 1,
			'max'    => 60,
		], $clients['gender']);

		$percent = ORMShape::ofType(new TypeFloat(null, 100, true), ORMShape::SIDE_READ);

		self::assertSame(['t' => 'number', 'min' => 0.0, 'max' => 100.0], $percent);
	}

	/**
	 * A project's own type is mapped by what it is stored as, which is already checked to be one
	 * of the seven base types, and already carries the constraints.
	 */
	public function testATypeGoblDoesNotKnowIsMappedByWhatItIsStoredAs(): void
	{
		$shape = ORMShape::ofType(new RegistryCheckedType(), ORMShape::SIDE_READ);

		self::assertSame(['t' => 'string', 'min' => 0, 'max' => 128], $shape);
	}

	/**
	 * And its own name travels beside the shape, because a `string` and an `email` have the same
	 * shape and are not the same thing.
	 */
	public function testTheTypesOwnNameTravelsBesideTheShape(): void
	{
		$table = new Table('things', 'tb')
			->addColumn(new Column('id', 'thing', new TypeBigint()))
			->addColumn(new Column('token', 'thing', new RegistryCheckedType()))
			->addColumn(new Column('at', 'thing', new TypeDate()));

		$described = ORMShape::ofTable($table);

		self::assertSame('registry_checked', $described['types']['token']);
		self::assertSame('bigint', $described['types']['id']);
		self::assertSame('date', $described['types']['at']);
		// And the shape beside it is the one its base type answers.
		self::assertSame('string', $described['read']['props']['token']['t']);
	}

	public function testAnEnumBecomesTheValuesOfItsCases(): void
	{
		$shape = ORMShape::ofType(new TypeEnum(SampleKind::class), ORMShape::SIDE_READ);

		self::assertSame(['t' => 'string', 'values' => ['image', 'video']], $shape);
	}

	/**
	 * A pattern is carried as JavaScript reads one, since that is what checks it in a browser.
	 */
	public function testAPatternTravelsAsJavaScriptReadsIt(): void
	{
		$shape = ORMShape::ofType(new TypeString(1, 8, '~^[a-z]+$~'), ORMShape::SIDE_READ);

		self::assertSame('^[a-z]+$', $shape['pattern']);
	}

	/**
	 * A flagged one has nowhere to go -- a shape's pattern is a source in Unicode mode and nothing
	 * else -- so it is not carried, and the shape says what still checks it.
	 */
	public function testAFlaggedPatternIsNotCarriedAndSaysSo(): void
	{
		$shape = ORMShape::ofType(new TypeString(1, 8, '~^[a-z]+$~i'), ORMShape::SIDE_READ);

		self::assertArrayNotHasKey('pattern', $shape);
		self::assertStringContainsString('~^[a-z]+$~i', $shape['doc']);
	}

	/**
	 * A date keeps its precision and its bounds, as timestamps -- which is what the base type
	 * holds, whatever the bounds were written as.
	 */
	public function testADateKeepsItsPrecisionAndItsBounds(): void
	{
		$plain = ORMShape::ofType(new TypeDate(), ORMShape::SIDE_READ);

		self::assertSame(['t' => 'date'], $plain);

		$bounded = ORMShape::ofType(
			new TypeDate()->min('@0')
				->max('@10'),
			ORMShape::SIDE_READ
		);

		self::assertSame(['t' => 'date', 'min' => 0, 'max' => 10], $bounded);

		$micros = ORMShape::ofType(new TypeDate()->microseconds(), ORMShape::SIDE_READ);

		self::assertSame(['t' => 'date', 'precision' => 'microseconds'], $micros);
	}

	/**
	 * A column that asked to be read back formatted answers that text and not a timestamp, so the
	 * kind that means a timestamp would be a lie about it.
	 */
	public function testADateReadBackFormattedIsText(): void
	{
		$shape = ORMShape::ofType(new TypeDate()->format('Y-m-d'), ORMShape::SIDE_READ);

		self::assertSame('string', $shape['t']);
		self::assertStringContainsString('Y-m-d', $shape['doc']);
	}

	/**
	 * What stands for a record is declared or it is nothing: a guess shows a list of the wrong
	 * thing, and a table whose records have no one readable column is ordinary.
	 */
	public function testTheLabelIsTheColumnTheTableNames(): void
	{
		$named = new Table('people', 'tb')
			->addColumn(new Column('full_name', 'person', new TypeString()));

		$named->setMetaKey(ORMShape::META_LABEL, 'full_name');

		self::assertSame('full_name', ORMShape::ofTable($named)['label']);

		$missing = new Table('people', 'tb')
			->addColumn(new Column('full_name', 'person', new TypeString()));

		$missing->setMetaKey(ORMShape::META_LABEL, 'no_such_column');

		self::assertNull(ORMShape::ofTable($missing)['label']);

		// And a table that names none says none, rather than guessing at a column called `name`.
		self::assertNull(ORMShape::ofTable(
			new Table('people', 'tb')
				->addColumn(new Column('name', 'person', new TypeString()))
		)['label']);
	}

	/**
	 * The bounds of an exact number are text, because a bound of twenty digits does not fit a
	 * number either.
	 */
	public function testTheBoundsOfAnExactNumberAreText(): void
	{
		$shape = ORMShape::ofType(
			new TypeBigint('0', '18446744073709551615'),
			ORMShape::SIDE_READ
		);

		self::assertSame('0', $shape['min']);
		self::assertSame('18446744073709551615', $shape['max']);

		$decimal = ORMShape::ofType(
			new TypeDecimal()->precision(20, 6),
			ORMShape::SIDE_READ
		);

		self::assertSame(['t' => 'exact', 'scale' => 6], $decimal);
	}
}
