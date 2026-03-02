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
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\FiltersExpressionParser;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\Tests\BaseTestCase;

/**
 * Class FiltersExpressionParserTest.
 *
 * @covers \Gobl\DBAL\Filters\FiltersExpressionParser
 *
 * @internal
 */
final class FiltersExpressionParserTest extends BaseTestCase
{
	// -------------------------------------------------------------------------
	// Happy-path tests
	// -------------------------------------------------------------------------

	/** Empty expression produces an empty Filters. */
	public function testEmptyExpression(): void
	{
		$qb     = $this->makeQB();
		$parser = new FiltersExpressionParser('', [], $qb);
		$result = $parser->parse();

		self::assertInstanceOf(Filters::class, $result);
		self::assertTrue($result->isEmpty());
	}

	/** Single binary filter with a binding value. */
	public function testSingleEqFilter(): void
	{
		$sql = $this->parseToSql('name eq :n', ['n' => 'Alice']);

		// column is used as-is when no scope is involved
		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('=', $sql);
	}

	/** Unary IS NULL operator no right operand expected. */
	public function testUnaryIsNull(): void
	{
		$sql = $this->parseToSql('name is_null');

		self::assertStringContainsString('IS NULL', $sql);
	}

	/** Unary IS NOT NULL operator no right operand expected. */
	public function testUnaryIsNotNull(): void
	{
		$sql = $this->parseToSql('name is_not_null');

		self::assertStringContainsString('IS NOT NULL', $sql);
	}

	/** AND chaining of two filters. */
	public function testAndChain(): void
	{
		$sql = $this->parseToSql('name eq :n and age gt :a', ['n' => 'Bob', 'a' => 18]);

		self::assertStringContainsString('AND', $sql);
		// column names are used as-is without a scope
		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('age', $sql);
	}

	/** OR chaining of two filters. */
	public function testOrChain(): void
	{
		$sql = $this->parseToSql('name eq :n or name eq :n2', ['n' => 'Alice', 'n2' => 'Bob']);

		self::assertStringContainsString('OR', $sql);
	}

	/** Parenthesised sub-group. */
	public function testGroupedExpression(): void
	{
		$sql = $this->parseToSql(
			'name eq :n and (age gt :a or age lt :b)',
			['n' => 'Alice', 'a' => 30, 'b' => 10]
		);

		self::assertStringContainsString('AND', $sql);
		self::assertStringContainsString('OR', $sql);
		// sub-group must be wrapped in parentheses
		self::assertMatchesRegularExpression('/\(.*OR.*\)/', $sql);
	}

	/** Nested groups: ((a and b) or c) */
	public function testDeepNestedGroups(): void
	{
		$sql = $this->parseToSql(
			'((name eq :n and age gt :a) or status eq :s)',
			['n' => 'Alice', 'a' => 18, 's' => 'active']
		);

		self::assertStringContainsString('OR', $sql);
		self::assertStringContainsString('AND', $sql);
	}

	/** All comparison operators resolve without error. */
	public function testAllComparisonOperators(): void
	{
		$operators = ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'like', 'not_like'];

		foreach ($operators as $op) {
			$sql = $this->parseToSql("name {$op} :v", ['v' => 'test']);
			self::assertNotEmpty($sql, "Operator '{$op}' produced an empty SQL string.");
		}
	}

	/** Case-insensitive AND / OR / operators. */
	public function testCaseInsensitiveTokens(): void
	{
		// Both variants must parse without throwing and produce AND + both columns.
		// We cannot compare the exact SQL strings because the auto-named bound
		// parameter counter keeps advancing across calls within the same test.
		foreach (['name EQ :n AND age GT :a', 'name eq :n and age gt :a'] as $expr) {
			$sql = $this->parseToSql($expr, ['n' => 'Alice', 'a' => 18]);
			self::assertStringContainsString('AND', $sql);
			self::assertStringContainsString('name', $sql);
			self::assertStringContainsString('age', $sql);
		}
	}

	/** Extra whitespace (tabs/newlines) is ignored. */
	public function testWhitespaceHandling(): void
	{
		// Both forms must produce a non-empty filter with '=' and the column name.
		// Exact SQL strings differ only in the auto-named param counter, so we
		// compare structure rather than the full string.
		foreach (["name\teq\n:n", 'name eq :n'] as $expr) {
			$sql = $this->parseToSql($expr, ['n' => 'Alice']);
			self::assertStringContainsString('name', $sql);
			self::assertStringContainsString('=', $sql);
		}
	}

	/** toArray() returns expression and inject keys. */
	public function testToArray(): void
	{
		$qb     = $this->makeQB();
		$inject = ['n' => 'Alice'];
		$parser = new FiltersExpressionParser('name eq :n', $inject, $qb);

		$arr = $parser->toArray();

		self::assertArrayHasKey('expression', $arr);
		self::assertArrayHasKey('inject', $arr);
		self::assertSame('name eq :n', $arr['expression']);
		self::assertSame($inject, $arr['inject']);
	}

	// -------------------------------------------------------------------------
	// Error / edge-case tests
	// -------------------------------------------------------------------------

	/** Missing binding key throws. */
	public function testMissingBindingThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/Missing inject value for binding ":n"/');

		$this->parseToSql('name eq :n', []); // :n not provided
	}

	/** Missing right operand for binary operator throws. */
	public function testMissingRightOperandThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);

		$this->parseToSql('name eq'); // nothing after operator
	}

	/** Missing operator after identifier throws. */
	public function testMissingOperatorThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);

		$this->parseToSql('name :val', ['val' => 'x']); // no operator between name and binding
	}

	/** Unclosed parenthesis throws. */
	public function testUnclosedParenthesisThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);

		$this->parseToSql('(name eq :n', ['n' => 'Alice']);
	}

	/** Trailing conditional operator (no right-hand operand) throws. */
	public function testTrailingCondThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);

		// 'and' with nothing after it is an error caught in fromArray / parseGroup
		$this->parseToSql('name eq :n and', ['n' => 'Alice']);
	}

	/** Empty binding name (lone colon) throws. */
	public function testEmptyBindingNameThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/Empty binding name/');

		$this->parseToSql('name eq :', []);
	}

	/** Integer literal as right operand. */
	public function testNonStrictModeIntegerLiteral(): void
	{
		$sql = $this->parseToSqlNonStrict('age gt 1');

		self::assertStringContainsString('age', $sql);
		self::assertStringContainsString('>', $sql);
	}

	/** Negative integer literal. */
	public function testNonStrictModeNegativeIntegerLiteral(): void
	{
		$sql = $this->parseToSqlNonStrict('age gt -5');

		self::assertStringContainsString('age', $sql);
		self::assertStringContainsString('>', $sql);
	}

	/** Float literal as right operand. */
	public function testNonStrictModeFloatLiteral(): void
	{
		$sql = $this->parseToSqlNonStrict('age lte 3.14');

		self::assertStringContainsString('age', $sql);
		self::assertStringContainsString('<=', $sql);
	}

	/** Double-quoted string literal as right operand. */
	public function testNonStrictModeDoubleQuotedString(): void
	{
		$sql = $this->parseToSqlNonStrict('name eq "foo bar"');

		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('=', $sql);
	}

	/** Single-quoted string literal as right operand. */
	public function testNonStrictModeSingleQuotedString(): void
	{
		$sql = $this->parseToSqlNonStrict("name eq 'foo bar'");

		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('=', $sql);
	}

	/** Escaped quote inside a string literal. */
	public function testNonStrictModeEscapedQuote(): void
	{
		// "it\"s" -> the string value is: it"s
		$sql = $this->parseToSqlNonStrict('name eq "it\"s"');

		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('=', $sql);
	}

	/** Mix of inline literals and binding in one expression. */
	public function testNonStrictModeMixedLiteralsAndBindings(): void
	{
		$sql = $this->parseToSqlNonStrict('age gt 18 and name eq :n or status eq "active"', ['n' => 'Alice']);

		self::assertStringContainsString('AND', $sql);
		self::assertStringContainsString('OR', $sql);
		self::assertStringContainsString('age', $sql);
		self::assertStringContainsString('name', $sql);
		self::assertStringContainsString('status', $sql);
	}

	/** Full expression from the feature description. */
	public function testNonStrictModeFromDescription(): void
	{
		$sql = $this->parseToSqlNonStrict('age gt 1 or name eq "foo bar"');

		self::assertStringContainsString('OR', $sql);
		self::assertStringContainsString('age', $sql);
		self::assertStringContainsString('name', $sql);
	}

	/** Unterminated string literal throws. */
	public function testNonStrictModeUnterminatedStringThrows(): void
	{
		$this->expectException(DBALRuntimeException::class);
		$this->expectExceptionMessageMatches('/Unterminated string literal/');

		$this->parseToSqlNonStrict('name eq "unclosed');
	}

	/** Inline quoted-string literals (with spaces) are rejected in strict mode (the default). */
	public function testInlineLiteralRejectedInStrictMode(): void
	{
		$this->expectException(DBALRuntimeException::class);

		$this->parseToSql('name eq "foo bar"');
	}
	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds a locked DB containing a single `users` table, returns a SELECT QB on it.
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
	 * Convenience: parse expression with inject and return the SQL filter string.
	 */
	private function parseToSql(string $expression, array $inject = []): string
	{
		$qb     = $this->makeQB();
		$parser = new FiltersExpressionParser($expression, $inject, $qb);

		return (string) $parser->parse();
	}

	// -------------------------------------------------------------------------
	// Non-strict mode tests (inline literals allowed)
	// -------------------------------------------------------------------------

	/** Convenience: parse with strict=false (literals allowed) and return the SQL filter string. */
	private function parseToSqlNonStrict(string $expression, array $inject = []): string
	{
		$qb     = $this->makeQB();
		$parser = new FiltersExpressionParser($expression, $inject, $qb, null, false);

		return (string) $parser->parse();
	}
}
