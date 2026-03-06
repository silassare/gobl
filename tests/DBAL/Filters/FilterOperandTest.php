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

namespace Gobl\Tests\DBAL\Filters;

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Filters\FilterFieldNotation;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;

/**
 * Class FilterOperandTest.
 *
 * Tests for FilterOperand resolution behavior:
 *  - Explicit FilterFieldNotation on left and right produces a raw column expression (no binding).
 *  - Explicit FilterFieldNotation with a JSON path on the right generates the extraction expression.
 *  - SECURITY: plain strings on the right are NEVER auto-resolved as column references,
 *    regardless of whether a matching table/column exists in the current QB context.
 *
 * @covers \Gobl\DBAL\Filters\Operands\FilterLeftOperand
 * @covers \Gobl\DBAL\Filters\Operands\FilterOperand
 * @covers \Gobl\DBAL\Filters\Operands\FilterRightOperand
 *
 * @internal
 */
final class FilterOperandTest extends BaseTestCase
{
	// =========================================================================
	// Explicit FilterFieldNotation on left operand
	// =========================================================================

	/**
	 * FilterFieldNotation passed as $left works identically to the equivalent string notation.
	 * The literal right value is bound as a normal parameter.
	 */
	public function testExplicitFFNOnLeftProducesSameResultAsString(): void
	{
		[$qb_str, $filters_str] = $this->makeQB();
		[$qb_fn, $filters_fn]   = $this->makeQB();

		$filters_str->add(Operator::EQ, 'th.th_code_a', 'hello');
		$filters_fn->add(Operator::EQ, FilterFieldNotation::fromString('th.th_code_a', $qb_fn), 'hello');

		// Both produce exactly one bound value.
		$bound_str = \array_values($qb_str->getBoundValues());
		$bound_fn  = \array_values($qb_fn->getBoundValues());

		self::assertCount(1, $bound_str);
		self::assertCount(1, $bound_fn);
		self::assertSame($bound_str[0], $bound_fn[0]);

		// Both SQL expressions reference the same column name.
		self::assertStringContainsString('th_code_a', (string) $filters_str);
		self::assertStringContainsString('th_code_a', (string) $filters_fn);
	}

	// =========================================================================
	// Explicit FilterFieldNotation on right operand -- plain column
	// =========================================================================

	/**
	 * Explicit FilterFieldNotation on $right produces a raw column expression with no bound parameters.
	 * This enables column-to-column comparisons (e.g. ON a.col = b.col).
	 */
	public function testExplicitFFNOnRightProducesColumnRefNoBind(): void
	{
		[$qb, $filters] = $this->makeQB();

		$right_fn = FilterFieldNotation::fromString('th.th_code_b', $qb);
		$filters->add(Operator::EQ, 'th.th_code_a', $right_fn);

		$bound = $qb->getBoundValues();
		self::assertEmpty($bound, 'column=column comparison must not bind any parameter');

		$sql = (string) $filters;
		self::assertStringContainsString('th_code_a', $sql);
		self::assertStringContainsString('th_code_b', $sql);
		// No bound-parameter placeholder must appear.
		self::assertStringNotContainsString(':_val_', $sql);
	}

	/**
	 * Explicit FilterFieldNotation built without a QB context is resolved lazily
	 * when the operand is normalized inside add().
	 */
	public function testExplicitFFNWithoutQBContextResolvesLazily(): void
	{
		[$qb, $filters] = $this->makeQB();

		// Build the notation *without* passing $qb -- resolution is deferred.
		$right_fn = FilterFieldNotation::fromString('th.th_code_b');
		self::assertFalse($right_fn->isResolved(), 'should not be resolved yet without QB');

		$filters->add(Operator::EQ, 'th.th_code_a', $right_fn);

		$bound = $qb->getBoundValues();
		self::assertEmpty($bound, 'column=column comparison must not bind any parameter');

		$sql = (string) $filters;
		self::assertStringContainsString('th_code_b', $sql);
	}

	// =========================================================================
	// Explicit FilterFieldNotation on right operand -- JSON path
	// =========================================================================

	/**
	 * Explicit FilterFieldNotation with a JSON path on $right generates the dialect
	 * extraction expression (e.g. JSON_UNQUOTE(JSON_EXTRACT(...))).
	 * No parameter is bound -- it is a column expression, not a value.
	 */
	public function testExplicitFFNWithJsonPathOnRightGeneratesExtraction(): void
	{
		[$qb, $filters] = $this->makeQB();

		$right_fn = FilterFieldNotation::fromString('th.th_meta#role', $qb);
		$filters->add(Operator::EQ, 'th.th_code_a', $right_fn);

		$bound = $qb->getBoundValues();
		self::assertEmpty($bound, 'JSON path right operand is a column expression, not a bound value');

		$sql = (string) $filters;
		self::assertStringContainsString('JSON_UNQUOTE(JSON_EXTRACT(', $sql);
		self::assertStringContainsString("'$.role'", $sql);
	}

	// =========================================================================
	// Security: plain strings on $right are NEVER auto-resolved as column refs
	// =========================================================================

	/**
	 * SECURITY: a plain string that looks exactly like a valid column reference in the current QB
	 * context must always be bound as a literal value when passed as $right.
	 *
	 * Threat model: an attacker controlling a search-box value could pass `"users.password"` as a
	 * filter value. Without this protection that string could be silently promoted to a column
	 * reference, leaking data from an arbitrary column instead of comparing against the literal text.
	 */
	public function testPlainStringOnRightIsNeverAutoResolvedAsColumnRef(): void
	{
		[$qb, $filters] = $this->makeQB();

		// 'th.th_code_b' is a valid column reference in the current QB,
		// but as a plain string on the right it must be bound verbatim.
		$filters->add(Operator::EQ, 'th.th_code_a', 'th.th_code_b');

		$bound = \array_values($qb->getBoundValues());
		self::assertNotEmpty($bound, 'plain string on right must be bound as a literal value');
		self::assertSame('th.th_code_b', $bound[0], 'value must be preserved verbatim, not resolved as a column ref');
	}

	/**
	 * The security guarantee holds even when the QB has no scope -- the plain string
	 * must remain a literal regardless of table/alias registration.
	 */
	public function testPlainStringOnRightIsNeverAutoResolvedWithoutScope(): void
	{
		[$qb, $filters] = $this->makeQB(); // no scope on these filters

		$filters->add(Operator::EQ, 'th.th_code_a', 'th.th_code_a'); // same as left

		$bound = \array_values($qb->getBoundValues());
		self::assertNotEmpty($bound, 'plain string must be bound even when it matches the left operand verbatim');
		self::assertSame('th.th_code_a', $bound[0]);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Creates a fresh DB with a table containing two string columns and a native-JSON map column.
	 * Registers the table in a QBSelect under alias `th`.
	 *
	 * @return array{0: QBSelect, 1: Filters}
	 */
	private function makeQB(): array
	{
		static $counter = 0;
		++$counter;
		$db = self::getNewDbInstance();
		$db->ns('FFNOperandTest' . $counter)->table('things', static function (TableBuilder $t) {
			$t->columnPrefix('th');
			$t->id();
			$t->string('code_a');
			$t->string('code_b');
			$t->map('meta')->nativeJson();
		});
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('things', 'th');

		return [$qb, new Filters($qb)];
	}
}
