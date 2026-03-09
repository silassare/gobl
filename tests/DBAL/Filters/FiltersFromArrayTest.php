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
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;

/**
 * Class FiltersFromArrayTest.
 *
 * Tests for {@see Filters::fromArray()} with the '_$filter' / '_$bindings' string-expression format.
 *
 * @covers \Gobl\DBAL\Filters\Filters::fromArray
 *
 * @internal
 */
final class FiltersFromArrayTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// Happy-path: standalone string-expression format
	// -------------------------------------------------------------------------

	/** Basic _$filter with bindings. */
	public function testStringExprFormatWithBindings(): void
	{
		$sql = $this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'name eq :v1 and age lt :v2',
			Filters::STR_EXPR_BINDINGS_KEY => ['v1' => 'Alice', 'v2' => 30],
		]);

		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('=', $sql);
		self::assertStringContainsString('age', $sql);
		self::assertStringContainsString('<', $sql);
		self::assertStringContainsString('AND', $sql);
	}

	/** _$bindings is optional; omitting it defaults to an empty bindings map. */
	public function testStringExprFormatWithoutBindingsKey(): void
	{
		// no right operand: use a unary operator so no bindings are needed
		$sql = $this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY => 'name is_null',
		]);

		self::assertStringContainsString('IS NULL', $sql);
	}

	/** An empty _$bindings array is accepted. */
	public function testStringExprFormatWithEmptyBindings(): void
	{
		$sql = $this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'name is_not_null',
			Filters::STR_EXPR_BINDINGS_KEY => [],
		]);

		self::assertStringContainsString('IS NOT NULL', $sql);
	}

	/** Multiple bindings with OR connector. */
	public function testStringExprFormatOrConnector(): void
	{
		$sql = $this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'name eq :n1 or name eq :n2',
			Filters::STR_EXPR_BINDINGS_KEY => ['n1' => 'Alice', 'n2' => 'Bob'],
		]);

		self::assertStringContainsString('OR', $sql);
		self::assertStringContainsString('name', $sql);
	}

	/** A grouped sub-expression inside _$filter. */
	public function testStringExprFormatGrouped(): void
	{
		$sql = $this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'name eq :n and (age gt :a or age lt :b)',
			Filters::STR_EXPR_BINDINGS_KEY => ['n' => 'Alice', 'a' => 30, 'b' => 10],
		]);

		self::assertStringContainsString('AND', $sql);
		self::assertStringContainsString('OR', $sql);
		self::assertMatchesRegularExpression('/\(.*OR.*\)/', $sql);
	}

	// -------------------------------------------------------------------------
	// Happy-path: mixed format (_$filter map nested in a flat-array group)
	// -------------------------------------------------------------------------

	/** _$filter map as one element of a flat-array group, joined with OR. */
	public function testStringExprFormatMixedWithFlatFormat(): void
	{
		$sql = $this->fromArrayToSql([
			[
				Filters::STR_EXPR_FILTER_KEY   => 'name eq :v1 and age lt :v2',
				Filters::STR_EXPR_BINDINGS_KEY => ['v1' => 'Alice', 'v2' => 30],
			],
			'OR',
			['status', 'is_null'],
		]);

		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('age', $sql);
		self::assertStringContainsString('OR', $sql);
		self::assertStringContainsString('IS NULL', $sql);
	}

	/** _$filter map joined with AND to a plain flat condition. */
	public function testStringExprFormatMixedAndJoin(): void
	{
		$sql = $this->fromArrayToSql([
			[
				Filters::STR_EXPR_FILTER_KEY   => 'name eq :n',
				Filters::STR_EXPR_BINDINGS_KEY => ['n' => 'Alice'],
			],
			'AND',
			['age', 'is_not_null'],
		]);

		self::assertStringContainsString('AND', $sql);
		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('IS NOT NULL', $sql);
	}

	// -------------------------------------------------------------------------
	// Error cases
	// -------------------------------------------------------------------------

	/** Any key other than '_$filter' / '_$bindings' alongside '_$filter' throws. */
	public function testUnexpectedExtraKeyThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/Unexpected key/');

		$this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'name is_null',
			Filters::STR_EXPR_BINDINGS_KEY => [],
			'extra_key'                    => 'value',
		]);
	}

	/** Non-string value for '_$filter' throws. */
	public function testNonStringFilterValueThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/Invalid filter expression/');

		$this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY => 42,
		]);
	}

	/** Non-array value for '_$bindings' throws. */
	public function testNonArrayBindingsValueThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/Invalid filter bindings/');

		$this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'name is_null',
			Filters::STR_EXPR_BINDINGS_KEY => 'not-an-array',
		]);
	}

	/** Missing binding reference in the expression throws. */
	public function testMissingBindingThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);

		$this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'name eq :missing',
			Filters::STR_EXPR_BINDINGS_KEY => [],
		]);
	}

	/**
	 * In strict mode (the default), a bare token that looks like a number (e.g. `5`) is
	 * tokenized as a plain identifier and passed as a string value, NOT an integer.
	 * No exception is raised - strict mode merely disables the `T_NUMBER` / `T_STRING`
	 * token classes so multi-word quoted strings cannot sneak in.
	 *
	 * This means the correct way to supply numeric right-operand values is always via
	 * `_$bindings` (which preserves native PHP types and lets the driver bind them
	 * with the right PDO parameter type).
	 */
	public function testNumericLookingTokenIsAcceptedAsStringIdentifierInStrictMode(): void
	{
		// '5' is treated as a T_IDENT string value, not an integer - no exception thrown.
		$sql = $this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'age gt 5',
			Filters::STR_EXPR_BINDINGS_KEY => [],
		]);

		self::assertStringContainsString('age', $sql);
		self::assertStringContainsString('>', $sql);
	}

	/**
	 * A multi-word quoted string literal (e.g. `"foo bar"`) is rejected in strict mode
	 * because the strict tokenizer does not produce a `T_STRING` token; the space
	 * causes the word after it to be parsed as a stray token, which fails.
	 *
	 * To pass a string value with spaces as a right operand, use `_$bindings`:
	 * `'_$filter' => 'name eq :n', '_$bindings' => ['n' => 'foo bar']`.
	 */
	public function testMultiWordQuotedStringInStrictModeThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);

		// The space inside "foo bar" causes an unexpected trailing token.
		$this->fromArrayToSql([
			Filters::STR_EXPR_FILTER_KEY   => 'name eq "foo bar"',
			Filters::STR_EXPR_BINDINGS_KEY => [],
		]);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds a locked DB with a single `users` table and returns a SELECT QB on it.
	 */
	private function makeQB(): QBSelect
	{
		$db = self::getNewDbInstance();
		$ns = $db->ns('test');
		$ns->table('users', static function (TableBuilder $t) {
			$t->columnPrefix('usr');
			$t->id();
			$t->int('age');
			$t->string('name');
			$t->string('status');
		});
		$db->lock();

		$qb = new QBSelect($db);
		$qb->from('users', 'u');

		return $qb;
	}

	/**
	 * Calls Filters::fromArray() and returns the resulting SQL filter string.
	 */
	private function fromArrayToSql(array $filters): string
	{
		$qb = $this->makeQB();

		return (string) Filters::fromArray($filters, $qb);
	}
}
