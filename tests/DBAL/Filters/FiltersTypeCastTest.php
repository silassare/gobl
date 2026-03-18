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

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\Tests\BaseTestCase;

/**
 * Class FiltersTypeCastTest.
 *
 * Verifies that {@see Filters::add()} automatically converts the right-operand value
 * to its DB-compatible form via the column type's {@see TypeInterface::phpToFilterValue()}
 * hook, so filter comparisons work correctly for every column type.
 *
 * Each test creates a minimal table with a single typed column, builds a QBSelect
 * that joins it under an alias so the left operand can be resolved, applies a filter
 * and then inspects the raw bound PDO values.
 *
 * @covers \Gobl\DBAL\Filters\Filters::add
 * @covers \Gobl\DBAL\Types\Type::castValueForFilter
 * @covers \Gobl\DBAL\Types\TypeBigint::castValueForFilter
 * @covers \Gobl\DBAL\Types\TypeBool::castValueForFilter
 * @covers \Gobl\DBAL\Types\TypeDate::castValueForFilter
 * @covers \Gobl\DBAL\Types\TypeDecimal::castValueForFilter
 * @covers \Gobl\DBAL\Types\TypeFloat::castValueForFilter
 * @covers \Gobl\DBAL\Types\TypeInt::castValueForFilter
 * @covers \Gobl\DBAL\Types\Utils\TypeUtils::runCastValueForFilter
 *
 * @internal
 */
final class FiltersTypeCastTest extends BaseTestCase
{
	// =========================================================================
	// TypeDate
	// =========================================================================

	/** Date string '2024-12-31' on a date column converts to a Unix timestamp string (TypeBigint stores as string). */
	public function testDateStringConvertedToTimestamp(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->date('created_at');
		});

		$filters->lte('t.col_created_at', '2024-12-31');

		$bound = \array_values($qb->getBoundValues());
		self::assertCount(1, $bound);
		self::assertIsNumeric($bound[0]);
		// strtotime('2024-12-31') returns Unix timestamp; TypeDate stores it as a numeric string via TypeBigint
		self::assertSame((string) \strtotime('2024-12-31'), (string) $bound[0]);
	}

	/** An already-numeric value on a date column passes through unchanged. */
	public function testDateNumericTimestampUnchanged(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->date('created_at');
		});

		$ts = \strtotime('2024-12-31');
		$filters->eq('t.col_created_at', (string) $ts);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame((string) $ts, (string) $bound[0]);
	}

	/** An invalid date string on a date column throws TypesInvalidValueException. */
	public function testDateInvalidStringThrows(): void
	{
		$this->expectException(TypesInvalidValueException::class);

		[$filters] = self::makeFilters(static function (TableBuilder $t) {
			$t->date('created_at');
		});

		$filters->eq('t.col_created_at', 'not-a-date');
	}

	/** IN list: each date string element converts to a timestamp. */
	public function testDateInListConvertedToTimestamps(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->date('created_at');
		});

		$filters->in('t.col_created_at', ['2024-01-01', '2024-06-01']);

		$bound = \array_values($qb->getBoundValues());
		self::assertCount(2, $bound);
		self::assertSame((string) \strtotime('2024-01-01'), (string) $bound[0]);
		self::assertSame((string) \strtotime('2024-06-01'), (string) $bound[1]);
	}

	// =========================================================================
	// TypeBool
	// =========================================================================

	/** PHP bool true on a bool column converts to 1. */
	public function testBoolTrueConvertsToOne(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->bool('active');
		});

		$filters->eq('t.col_active', true);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(1, $bound[0]);
	}

	/** PHP bool false on a bool column converts to 0. */
	public function testBoolFalseConvertsToZero(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->bool('active');
		});

		$filters->eq('t.col_active', false);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(0, $bound[0]);
	}

	/** Integer 1 on a bool column converts to 1. */
	public function testBoolIntOneConvertsToOne(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->bool('active');
		});

		$filters->eq('t.col_active', 1);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(1, $bound[0]);
	}

	/** Integer 0 on a bool column converts to 0. */
	public function testBoolIntZeroConvertsToZero(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->bool('active');
		});

		$filters->eq('t.col_active', 0);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(0, $bound[0]);
	}

	/** Non-bool string on a strict bool column throws TypesInvalidValueException. */
	public function testBoolStrictModeRejectsStringThrows(): void
	{
		$this->expectException(TypesInvalidValueException::class);

		[$filters] = self::makeFilters(static function (TableBuilder $t) {
			$t->bool('active'); // strict by default
		});

		$filters->eq('t.col_active', 'yes');
	}

	/** IS_TRUE on a bool column: normalized to EQ true, then cast to 1 via phpToFilterValue. */
	public function testBoolIsTrueCastsToOne(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->bool('active');
		});

		$filters->isTrue('t.col_active');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(1, $bound[0]);
	}

	/** IS_FALSE on a bool column: normalized to EQ false, then cast to 0 via phpToFilterValue. */
	public function testBoolIsFalseCastsToZero(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->bool('active');
		});

		$filters->isFalse('t.col_active');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(0, $bound[0]);
	}

	// =========================================================================
	// TypeInt
	// =========================================================================

	/** String '42' on an int column converts to int 42. */
	public function testIntStringConvertsToInt(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->int('age');
		});

		$filters->eq('t.col_age', '42');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(42, $bound[0]);
	}

	/** Float 3.0 on an int column converts to int 3. */
	public function testIntFloatConvertsToInt(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->int('age');
		});

		$filters->eq('t.col_age', 3.0);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(3, $bound[0]);
	}

	/** Non-numeric string on an int column throws TypesInvalidValueException. */
	public function testIntNonNumericStringThrows(): void
	{
		$this->expectException(TypesInvalidValueException::class);

		[$filters] = self::makeFilters(static function (TableBuilder $t) {
			$t->int('age');
		});

		$filters->eq('t.col_age', 'banana');
	}

	/** IN list: each element converts to int. */
	public function testIntInListConverted(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->int('age');
		});

		$filters->in('t.col_age', ['10', '20', '30']);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame([10, 20, 30], $bound);
	}

	// =========================================================================
	// TypeBigint
	// =========================================================================

	/** String '9876543210' on a bigint column stays a string (bigint stored as string). */
	public function testBigintStringStaysString(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->bigint('score');
		});

		$filters->eq('t.col_score', '9876543210');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('9876543210', $bound[0]);
	}

	/** PHP int 42 on a bigint column converts to string '42' (TypeBigint stores bigints as strings). */
	public function testBigintIntConvertsToString(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->bigint('score');
		});

		$filters->eq('t.col_score', 42);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('42', $bound[0]);
	}

	// =========================================================================
	// TypeFloat
	// =========================================================================

	/** String '3.14' on a float column converts to float 3.14. */
	public function testFloatStringConvertsToFloat(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->float('ratio');
		});

		$filters->eq('t.col_ratio', '3.14');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame(3.14, $bound[0]);
	}

	/** Non-numeric string on a float column throws TypesInvalidValueException. */
	public function testFloatNonNumericThrows(): void
	{
		$this->expectException(TypesInvalidValueException::class);

		[$filters] = self::makeFilters(static function (TableBuilder $t) {
			$t->float('ratio');
		});

		$filters->eq('t.col_ratio', 'banana');
	}

	// =========================================================================
	// TypeDecimal
	// =========================================================================

	/** String '12.50' on a decimal column stays a string. */
	public function testDecimalStringStaysString(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->decimal('amount')->precision(10, 2);
		});

		$filters->eq('t.col_amount', '12.50');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('12.50', $bound[0]);
	}

	/** Float 3.14 on a decimal column converts to string (TypeDecimal stores as string). */
	public function testDecimalFloatConvertsToString(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->decimal('amount')->precision(10, 2);
		});

		$filters->eq('t.col_amount', 3.14);

		$bound = \array_values($qb->getBoundValues());
		self::assertIsString($bound[0]);
	}

	// =========================================================================
	// TypeString - no conversion (default no-op)
	// =========================================================================

	/** String value on a string column passes through unchanged (no-op). */
	public function testStringNoConversion(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->string('name');
		});

		$filters->eq('t.col_name', 'Alice');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('Alice', $bound[0]);
	}

	/**
	 * LIKE pattern on a string column passes through unchanged.
	 * The default Type::phpToFilterValue is a no-op, so patterns like '%a%'
	 * are never run through TypeString's write-path (min/max length) validation.
	 */
	public function testStringLikePatternPassesThrough(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->string('name')->min(2)->max(100);
		});

		$filters->like('t.col_name', '%a%');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('%a%', $bound[0]);
	}

	// =========================================================================
	// No column context: right operand must NOT be cast
	// =========================================================================

	/**
	 * Without a resolvable left column (bare name, no alias prefix, no scope),
	 * phpToFilterValue is NOT called and the raw value is bound unchanged.
	 */
	public function testNoCastWhenColumnNotResolved(): void
	{
		$db = self::getNewDbInstance();
		$db->lock();

		$qb      = new QBSelect($db);
		// No table registered in the QB, so left operand 'some_column' cannot be resolved.
		$filters = new Filters($qb);
		$filters->eq('some_column', '2024-12-31');

		$bound = \array_values($qb->getBoundValues());
		// No type context -> value passes through unchanged regardless of content.
		self::assertSame('2024-12-31', $bound[0]);
	}

	// =========================================================================
	// IS_NULL: unary, no right operand
	// =========================================================================

	/** IS_NULL produces no bound value since it has no right operand. */
	public function testIsNullNoBinding(): void
	{
		[$filters, $qb] = self::makeFilters(static function (TableBuilder $t) {
			$t->int('age')->nullable();
		});

		$filters->isNull('t.col_age');

		$bound = $qb->getBoundValues();
		self::assertEmpty($bound);
	}

	// =========================================================================
	// CONTAINS / HAS_KEY bypass phpToFilterValue
	// =========================================================================

	/**
	 * CONTAINS right-operand goes through TypeJson::serializeJsonValue(), NOT phpToFilterValue.
	 * Strings are passed through as-is (they are assumed to be pre-serialized JSON fragments;
	 * use '"hello"' if the target is the JSON string "hello").
	 */
	public function testContainsSkipsPhpToFilterValue(): void
	{
		$db = self::getNewDbInstance();
		$db->ns('ContainsTest')->table('t', static function (TableBuilder $t) {
			$t->columnPrefix('tc');
			$t->id();
			$t->map('data');
		});
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('t', 'x');

		$filters = new Filters($qb);
		// 'hello' is a plain string: TypeJson::serializeJsonValue passes strings through unchanged.
		// If you want to match the JSON string "hello", pass '"hello"' (with quotes).
		$filters->contains('tc_data', 'hello');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('hello', $bound[0]);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Creates a minimal single-column table with prefix 'col', builds a QBSelect
	 * from it, and returns [Filters, QBSelect].
	 *
	 * @param callable(TableBuilder): void $configure
	 *
	 * @return array{0: Filters, 1: QBSelect}
	 */
	private static function makeFilters(callable $configure): array
	{
		static $counter = 0;
		++$counter;

		$db = self::getNewDbInstance();
		$db->ns('FiltersTypeCastTest' . $counter)->table('t', static function (TableBuilder $t) use ($configure) {
			$t->columnPrefix('col');
			$t->id();
			$configure($t);
		});
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('t', 't');

		return [new Filters($qb), $qb];
	}
}
