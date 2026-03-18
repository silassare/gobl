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
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\TypeJson;
use Gobl\Tests\BaseTestCase;
use Gobl\Tests\DBAL\Types\Utils\JsonPathTest;
use JsonSerializable;
use OLIUP\CG\PHPMethod;

/**
 * Class FiltersJsonOperatorsTest.
 *
 * Unit tests for JSON-aware Filters/TypeJson operations:
 * - Filters::add() CONTAINS right-operand auto-serialization (int, float, array, JsonSerializable)
 * - Filters::add() rejection of array/JsonSerializable on non-CONTAINS operators
 * - FiltersOperatorsHelpersTrait::contains() broadened signature
 * - TypeJson::getAllowedFilterOperators() with native_json=true vs false
 * - TypeJson::queryBuilderEnhanceFilterMethod() arg order: ($value, $path) for non-unary,
 *   ($path) for unary and HAS_KEY
 *
 * @covers \Gobl\DBAL\Filters\Filters
 * @covers \Gobl\DBAL\Filters\Traits\FiltersOperatorsHelpersTrait
 * @covers \Gobl\DBAL\Types\TypeJson
 *
 * @internal
 */
final class FiltersJsonOperatorsTest extends BaseTestCase
{
	// =========================================================================
	// Filters::add() - CONTAINS auto-serialization
	// =========================================================================

	/** CONTAINS with a pre-encoded string passes through without modification. */
	public function testAddContainsStringPassthrough(): void
	{
		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->add(Operator::CONTAINS, 'td_data', '"admin"');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('"admin"', $bound[0] ?? null);
	}

	/** CONTAINS with int auto-serializes to a JSON number string via json_encode. */
	public function testAddContainsIntAutoSerializes(): void
	{
		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->add(Operator::CONTAINS, 'td_data', 42);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('42', $bound[0] ?? null);
	}

	/** CONTAINS with float auto-serializes to a JSON number string via json_encode. */
	public function testAddContainsFloatAutoSerializes(): void
	{
		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->add(Operator::CONTAINS, 'td_data', 3.14);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('3.14', $bound[0] ?? null);
	}

	/** CONTAINS with array auto-serializes via json_encode. */
	public function testAddContainsArrayAutoSerializes(): void
	{
		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->add(Operator::CONTAINS, 'td_data', ['foo', 'bar']);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('["foo","bar"]', $bound[0] ?? null);
	}

	/** CONTAINS with JsonSerializable auto-serializes via json_encode. */
	public function testAddContainsJsonSerializableAutoSerializes(): void
	{
		$obj = new class implements JsonSerializable {
			public function jsonSerialize(): mixed
			{
				return ['role' => 'admin'];
			}
		};

		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->add(Operator::CONTAINS, 'td_data', $obj);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('{"role":"admin"}', $bound[0] ?? null);
	}

	/** CONTAINS with null passes through as null (no binding emitted). */
	public function testAddContainsNullPassthrough(): void
	{
		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->add(Operator::CONTAINS, 'td_data', null);

		// null is passed through as-is and bound as null
		$bound = \array_values($qb->getBoundValues());
		self::assertSame([null], $bound);
	}

	/** array right-operand on a non-CONTAINS operator throws DBALRuntimeException. */
	public function testAddArrayOnNonContainsOperatorThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/CONTAINS/');

		[$filters] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->add(Operator::EQ, 'td_data', ['bad']);
	}

	/** JsonSerializable right-operand on non-CONTAINS operator throws DBALRuntimeException. */
	public function testAddJsonSerializableOnNonContainsOperatorThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/CONTAINS/');

		$serializable = new class implements JsonSerializable {
			public function jsonSerialize(): mixed
			{
				return 'value';
			}
		};

		[$filters] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->add(Operator::LIKE, 'td_data', $serializable);
	}

	// =========================================================================
	// FiltersOperatorsHelpersTrait::contains() - broadened signature
	// =========================================================================

	/** contains() fluent helper with a pre-encoded JSON string passes through. */
	public function testContainsTraitWithString(): void
	{
		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->contains('td_data', '"hello"');

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('"hello"', $bound[0] ?? null);
	}

	/** contains() fluent helper with int auto-serializes. */
	public function testContainsTraitWithInt(): void
	{
		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->contains('td_data', 99);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('99', $bound[0] ?? null);
	}

	/** contains() fluent helper with array auto-serializes. */
	public function testContainsTraitWithArray(): void
	{
		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->contains('td_data', [1, 2]);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('[1,2]', $bound[0] ?? null);
	}

	/** contains() fluent helper with JsonSerializable auto-serializes. */
	public function testContainsTraitWithJsonSerializable(): void
	{
		$obj = new class implements JsonSerializable {
			public function jsonSerialize(): mixed
			{
				return ['x' => true];
			}
		};

		[$filters, $qb] = JsonPathTest::makeFiltersOnNativeJsonTable();
		$filters->contains('td_data', $obj);

		$bound = \array_values($qb->getBoundValues());
		self::assertSame('{"x":true}', $bound[0] ?? null);
	}

	// =========================================================================
	// TypeJson::getAllowedFilterOperators - native_json=true vs false
	// =========================================================================

	/** native_json=true: CONTAINS and HAS_KEY are included; EQ/LIKE also present. */
	public function testNativeJsonTrueAllowedOperatorsIncludeJsonOps(): void
	{
		$type = new TypeJson();
		$ops  = $type->getAllowedFilterOperators();

		self::assertContains(Operator::CONTAINS, $ops, 'CONTAINS must be allowed when native_json=true');
		self::assertContains(Operator::HAS_KEY, $ops, 'HAS_KEY must be allowed when native_json=true');
		self::assertContains(Operator::EQ, $ops, 'EQ must be allowed');
		self::assertContains(Operator::LIKE, $ops, 'LIKE must be allowed');
	}

	/** native_json=false (explicit opt-out): CONTAINS and HAS_KEY are excluded; EQ/LIKE still present. */
	public function testNativeJsonFalseAllowedOperatorsExcludeJsonOps(): void
	{
		$type = (new TypeJson())->nativeJson(false); // explicit opt-out
		$ops  = $type->getAllowedFilterOperators();

		self::assertNotContains(Operator::CONTAINS, $ops, 'CONTAINS must not be allowed when native_json=false');
		self::assertNotContains(Operator::HAS_KEY, $ops, 'HAS_KEY must not be allowed when native_json=false');
		self::assertContains(Operator::EQ, $ops, 'EQ must be allowed when native_json=false');
		self::assertContains(Operator::LIKE, $ops, 'LIKE must be allowed when native_json=false');
	}

	/** IS_NULL / IS_NOT_NULL only appear when the type is nullable. */
	public function testNullableJsonTypeAllowsNullChecks(): void
	{
		$nullable     = (new TypeJson())->nullable();
		$non_nullable = new TypeJson();

		self::assertContains(Operator::IS_NULL, $nullable->getAllowedFilterOperators());
		self::assertContains(Operator::IS_NOT_NULL, $nullable->getAllowedFilterOperators());
		self::assertNotContains(Operator::IS_NULL, $non_nullable->getAllowedFilterOperators());
		self::assertNotContains(Operator::IS_NOT_NULL, $non_nullable->getAllowedFilterOperators());
	}

	// =========================================================================
	// TypeJson::queryBuilderEnhanceFilterMethod - arg order
	// =========================================================================

	/** native_json=false: enhance does nothing - no arguments are added to the method. */
	public function testEnhanceNativeJsonFalseDoesNothing(): void
	{
		$type   = (new TypeJson())->nativeJson(false); // explicit opt-out
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::EQ, $method);

		self::assertCount(0, $method->getArguments(), 'native_json=false: no args should be added');
	}

	/** CONTAINS: ($value, $path=null) - value comes first, path is optional. */
	public function testEnhanceContainsValueFirstPathSecondOptional(): void
	{
		$type   = new TypeJson();
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::CONTAINS, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(2, $args, 'CONTAINS must add exactly 2 arguments');
		self::assertSame('value', $args[0]->getName(), 'First arg must be $value');
		self::assertSame('path', $args[1]->getName(), 'Second arg must be $path');
		// $path must have a default of null (optional)
		self::assertNotNull($args[1]->getValue(), '$path must have a default value (null)');
	}

	/** EQ (non-unary): ($value, $path) - value first, then path. */
	public function testEnhanceEqValueFirstPathSecond(): void
	{
		$type   = new TypeJson();
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::EQ, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(2, $args, 'EQ must add exactly 2 arguments');
		self::assertSame('value', $args[0]->getName(), 'First arg must be $value');
		self::assertSame('path', $args[1]->getName(), 'Second arg must be $path');
	}

	/** NEQ (non-unary): ($value, $path) - value first, then path. */
	public function testEnhanceNeqValueFirstPathSecond(): void
	{
		$type   = new TypeJson();
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::NEQ, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(2, $args);
		self::assertSame('value', $args[0]->getName());
		self::assertSame('path', $args[1]->getName());
	}

	/** LIKE (non-unary): ($value: string, $path) - value first with string type. */
	public function testEnhanceLikeValueFirstHasStringType(): void
	{
		$type   = new TypeJson();
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::LIKE, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(2, $args);
		self::assertSame('value', $args[0]->getName());
		self::assertSame('path', $args[1]->getName());
		self::assertStringContainsString('string', (string) $args[0]->getType(), 'LIKE $value must be typed as string');
	}

	/** NOT_LIKE (non-unary): ($value: string, $path) - value first with string type. */
	public function testEnhanceNotLikeValueFirstHasStringType(): void
	{
		$type   = new TypeJson();
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::NOT_LIKE, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(2, $args);
		self::assertSame('value', $args[0]->getName());
		self::assertSame('path', $args[1]->getName());
		self::assertStringContainsString('string', (string) $args[0]->getType(), 'NOT_LIKE $value must be typed as string');
	}

	/** IS_NULL (unary): only ($path) - no value arg. */
	public function testEnhanceIsNullOnlyPath(): void
	{
		$type   = new TypeJson();
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::IS_NULL, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(1, $args, 'IS_NULL must add exactly 1 argument');
		self::assertSame('path', $args[0]->getName(), 'Only arg must be $path');
	}

	/** IS_NOT_NULL (unary): only ($path) - no value arg. */
	public function testEnhanceIsNotNullOnlyPath(): void
	{
		$type   = new TypeJson();
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::IS_NOT_NULL, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(1, $args, 'IS_NOT_NULL must add exactly 1 argument');
		self::assertSame('path', $args[0]->getName(), 'Only arg must be $path');
	}

	/** HAS_KEY (single path arg): only ($path). */
	public function testEnhanceHasKeyOnlyPath(): void
	{
		$type   = new TypeJson();
		$method = new PHPMethod('testMethod');
		$type->queryBuilderEnhanceFilterMethod($this->makeTable(), $this->makeDataColumn(), Operator::HAS_KEY, $method);

		$args = \array_values($method->getArguments());
		self::assertCount(1, $args, 'HAS_KEY must add exactly 1 argument');
		self::assertSame('path', $args[0]->getName(), 'Only arg must be $path');
	}

	/**
	 * Returns a locked Table instance for use in enhance/comment tests.
	 * This table has a single `map data` column (native_json configurable separately via $type).
	 */
	private function makeTable(): Table
	{
		// Use a thread-safe unique namespace per call to avoid conflicts between test methods.
		static $counter = 0;
		++$counter;
		$ns = 'GoblEnhanceTest' . $counter;

		$db = self::getNewDbInstance();
		$db->ns($ns)->table('t_enh', static function (TableBuilder $t) {
			$t->columnPrefix('te');
			$t->id();
			$t->map('data');
		});
		$db->lock();

		return $db->getTableOrFail('t_enh');
	}

	/**
	 * Returns the `data` Column from `makeTable()`.
	 */
	private function makeDataColumn(): Column
	{
		return $this->makeTable()->getColumnOrFail('data');
	}
}
