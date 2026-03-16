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

namespace Gobl\DBAL\Filters;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Interfaces\FilterInterface;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Override;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class FiltersExpressionParser.
 *
 * Parses a filter expression string into a {@see Filters} instance.
 *
 * Expression syntax (bindings only):
 * ```
 * foo eq :val1 and bar eq :val2 and (baz eq :val3 or baz gt :val4)
 * ```
 *
 * Expression syntax non-strict (inline static values allowed):
 * ```
 * age gt 1 or name eq "foo bar" and active is_true
 * ```
 *
 * Token types:
 *  - Identifier  : column name, optionally table-qualified and/or with json key path
 *    (`table.column` | `table.column#json.path` | `column#json.path`), e.g. `users.name` or `accounts.data#user.tags`
 *  - Operator    : any {@see Operator} value (e.g. `eq`, `gt`, `is_true`)
 *  - Conditional : `and` | `or`
 *  - Binding     : `:name` resolved from the `$bindings` map
 *  - Grouping    : `(` | `)`
 *  - Number      : integer or float literal, e.g. `42`, `3.14` (non-strict mode only)
 *  - String      : double- or single-quoted literal, e.g. `"foo bar"` (non-strict mode only)
 */
final class FiltersExpressionParser implements FilterInterface
{
	use ArrayCapableTrait;

	private const T_OPEN    = 'T_OPEN';
	private const T_CLOSE   = 'T_CLOSE';
	private const T_COND    = 'T_COND';
	private const T_IDENT   = 'T_IDENT';
	private const T_OP      = 'T_OP';
	private const T_BINDING = 'T_BINDING';

	/** Inline numeric literal available in non-strict mode only. */
	private const T_NUMBER  = 'T_NUMBER';

	/** Inline quoted-string literal available in non-strict mode only. */
	private const T_STRING  = 'T_STRING';

	/** @var list<array{type: string, value: string}> */
	private array $tokens = [];

	private int $pos = 0;

	/**
	 * FiltersExpressionParser constructor.
	 *
	 * @param string $expression The filter expression string to parse
	 * @param array  $bindings   Map of binding name => value (e.g. `['val1' => 'bla']`)
	 * @param bool   $strict     When `true` (default), only `:binding` references are
	 *                           accepted as right operands. Set to `false` to also allow
	 *                           inline numeric and quoted-string literals.
	 */
	public function __construct(
		private string $expression,
		private array $bindings,
		private bool $strict = true
	) {}

	#[Override]
	public function toArray(): array
	{
		return [
			Filters::STR_EXPR_FILTER_KEY   => $this->expression,
			Filters::STR_EXPR_BINDINGS_KEY => $this->bindings,
		];
	}

	/**
	 * Returns the flat-array equivalent of this expression with all binding references
	 * replaced by their resolved values from `$bindings`.
	 *
	 * The returned array is compatible with {@see Filters::fromArray()} and can be used
	 * for debugging, serialization, or persisting a fully-resolved snapshot of the filters.
	 *
	 * Example - given expression `'name eq :n and age gt :a'` with bindings `['n' => 'Bob', 'a' => 18]`:
	 * ```php
	 * $parser->toEquivalentArrayFilters();
	 * // ['name', 'eq', 'Bob', 'AND', 'age', 'gt', 18]
	 * ```
	 *
	 * Groups `(...)` are represented as nested arrays:
	 * ```php
	 * // expression: 'foo eq :x and (bar gt :y or baz is_null)'
	 * // => ['foo', 'eq', $x, 'AND', ['bar', 'gt', $y, 'OR', 'baz', 'is_null']]
	 * ```
	 *
	 * @return array the resolved equivalent flat-array filters
	 */
	public function toEquivalentArrayFilters(): array
	{
		$this->tokenize();
		$this->pos = 0;

		if (empty($this->tokens)) {
			return [];
		}

		$result = $this->buildArrayGroup();

		if ($this->pos < \count($this->tokens)) {
			$token = $this->tokens[$this->pos];

			throw new DBALRuntimeException(
				\sprintf(
					'Unexpected token "%s" (%s) after end of expression.',
					$token['value'],
					$token['type']
				),
				[
					Filters::STR_EXPR_FILTER_KEY => $this->expression,
					'_position'                  => $this->pos,
					'_token'                     => $token,
				]
			);
		}

		$this->pos = 0;

		return $result;
	}

	/**
	 * Parses the expression and returns the built {@see Filters} instance.
	 *
	 * @param QBInterface                $qb    The query builder to use for bindings and table/column resolution
	 * @param null|FiltersScopeInterface $scope Optional filters scope for validating and resolving columns
	 *
	 * @return Filters
	 */
	public function toFilters(QBInterface $qb, ?FiltersScopeInterface $scope = null): Filters
	{
		return Filters::fromArray($this->toEquivalentArrayFilters(), $qb, $scope);
	}

	// -------------------------------------------------------------------------
	// Tokenizer
	// -------------------------------------------------------------------------

	/**
	 * Splits the expression string into tokens stored in {@see $tokens}.
	 */
	private function tokenize(): void
	{
		$this->tokens = [];
		$expr         = $this->expression;
		$len          = \strlen($expr);
		$i            = 0;

		while ($i < $len) {
			// skip whitespace
			if (\ctype_space($expr[$i])) {
				++$i;

				continue;
			}

			if ('(' === $expr[$i]) {
				$this->tokens[] = ['type' => self::T_OPEN, 'value' => '('];
				++$i;

				continue;
			}

			if (')' === $expr[$i]) {
				$this->tokens[] = ['type' => self::T_CLOSE, 'value' => ')'];
				++$i;

				continue;
			}

			// binding :name
			if (':' === $expr[$i]) {
				$j = $i + 1;
				while ($j < $len && (\ctype_alnum($expr[$j]) || '_' === $expr[$j])) {
					++$j;
				}

				$name = \substr($expr, $i + 1, $j - $i - 1);

				if ('' === $name) {
					throw new DBALRuntimeException(
						'Empty binding name in filter expression.',
						[Filters::STR_EXPR_FILTER_KEY => $this->expression, '_position' => $i]
					);
				}

				$this->tokens[] = ['type' => self::T_BINDING, 'value' => $name];
				$i              = $j;

				continue;
			}

			// non-strict mode: quoted string literal "..." or '...'
			if (!$this->strict && ('"' === $expr[$i] || "'" === $expr[$i])) {
				$quote = $expr[$i];
				$j     = $i + 1;
				$buf   = '';

				while ($j < $len) {
					if ('\\' === $expr[$j] && $j + 1 < $len && $expr[$j + 1] === $quote) {
						// escaped quote
						$buf .= $quote;
						$j += 2;
					} elseif ($expr[$j] === $quote) {
						++$j; // consume closing quote

						break;
					} else {
						$buf .= $expr[$j++];
					}
				}

				if ($j > $len || ($j === $len && $expr[$j - 1] !== $quote)) {
					throw new DBALRuntimeException(
						'Unterminated string literal in filter expression.',
						[Filters::STR_EXPR_FILTER_KEY => $this->expression, '_position' => $i]
					);
				}

				$this->tokens[] = ['type' => self::T_STRING, 'value' => $buf];
				$i              = $j;

				continue;
			}

			// non-strict mode: numeric literal optional leading minus, digits, optional decimal
			if (!$this->strict && ('-' === $expr[$i] || \ctype_digit($expr[$i]))) {
				$j = $i;
				if ('-' === $expr[$j]) {
					++$j;
				}

				// must have at least one digit after optional minus
				if ($j < $len && \ctype_digit($expr[$j])) {
					while ($j < $len && \ctype_digit($expr[$j])) {
						++$j;
					}

					if ($j < $len && '.' === $expr[$j] && $j + 1 < $len && \ctype_digit($expr[$j + 1])) {
						++$j; // consume '.'
						while ($j < $len && \ctype_digit($expr[$j])) {
							++$j;
						}
					}

					$this->tokens[] = ['type' => self::T_NUMBER, 'value' => \substr($expr, $i, $j - $i)];
					$i              = $j;

					continue;
				}
			}

			// word: identifier, operator, or conditional
			// Bracket segments (e.g. `col#foo['key with dot']`) are consumed verbatim
			// so the full path is tokenized as a single T_IDENT token.
			$j = $i;

			while ($j < $len && '(' !== $expr[$j] && ')' !== $expr[$j]) {
				if ('[' === $expr[$j]) {
					// Consume an entire bracket segment: ['...'], ["..."], or [integer].
					++$j; // skip '['

					if ($j < $len && ("'" === $expr[$j] || '"' === $expr[$j])) {
						$q = $expr[$j];
						++$j; // skip opening quote

						while ($j < $len) {
							if ('\\' === $expr[$j] && $j + 1 < $len && $expr[$j + 1] === $q) {
								$j += 2; // \' or \" -> escaped quote, keep going
							} elseif ($expr[$j] === $q) {
								++$j; // skip closing quote

								break;
							} else {
								++$j;
							}
						}
					} else {
						// integer or other: consume until ']'
						while ($j < $len && ']' !== $expr[$j]) {
							++$j;
						}
					}

					if ($j < $len && ']' === $expr[$j]) {
						++$j; // skip ']'
					}
				} elseif (\ctype_space($expr[$j])) {
					break; // stop at whitespace outside a bracket
				} else {
					++$j;
				}
			}

			$word  = \substr($expr, $i, $j - $i);
			$lower = \strtolower($word);
			$i     = $j;

			if ('and' === $lower || 'or' === $lower) {
				$this->tokens[] = ['type' => self::T_COND, 'value' => \strtoupper($lower)];
			} elseif (null !== Operator::tryFrom($lower)) {
				$this->tokens[] = ['type' => self::T_OP, 'value' => $lower];
			} else {
				$this->tokens[] = ['type' => self::T_IDENT, 'value' => $word];
			}
		}
	}

	// -------------------------------------------------------------------------
	// Array-builder (single traversal used by both toEquivalentArrayFilters and toFilters)
	// -------------------------------------------------------------------------

	/**
	 * Builds a flat array group: item ( COND item )*.
	 */
	private function buildArrayGroup(): array
	{
		$result = $this->buildArrayItem();

		while (null !== ($token = $this->current()) && self::T_COND === $token['type']) {
			++$this->pos;
			$result[] = $token['value']; // 'AND' or 'OR'

			foreach ($this->buildArrayItem() as $v) {
				$result[] = $v;
			}
		}

		return $result;
	}

	/**
	 * Builds: '(' group ')' -> returns `[$sub_group_array]`
	 *      OR a filter -> returns `[$col, $op, $val]` or `[$col, $op]`.
	 */
	private function buildArrayItem(): array
	{
		$token = $this->current();

		if (null === $token) {
			throw new DBALRuntimeException(
				'Unexpected end of filter expression.',
				[Filters::STR_EXPR_FILTER_KEY => $this->expression, '_position' => $this->pos]
			);
		}

		if (self::T_OPEN === $token['type']) {
			++$this->pos; // consume '('
			$sub = $this->buildArrayGroup();
			$this->expect(self::T_CLOSE);

			return [$sub]; // nested group is a single element in the outer array
		}

		return $this->buildArrayFilter();
	}

	/**
	 * Builds: IDENT OPERATOR [right-value] -> `[$col, $op]` or `[$col, $op, $value]`.
	 */
	private function buildArrayFilter(): array
	{
		$left_token = $this->expect(self::T_IDENT);
		$op_token   = $this->expect(self::T_OP);
		$operator   = Operator::from($op_token['value']);

		if ($operator->isUnary()) {
			return [$left_token['value'], $op_token['value']];
		}

		$right_token = $this->current();

		if (null === $right_token) {
			throw new DBALRuntimeException(
				\sprintf(
					'Missing right operand for operator "%s" after column "%s" in filter expression.',
					$operator->value,
					$left_token['value']
				),
				[Filters::STR_EXPR_FILTER_KEY => $this->expression, '_position' => $this->pos]
			);
		}

		++$this->pos;

		return [$left_token['value'], $op_token['value'], $this->resolveRightTokenValue($right_token)];
	}

	/**
	 * Extracts the resolved PHP value from a right-operand token.
	 *
	 * Bindings are resolved from `$this->bindings`; throws when a binding name is absent.
	 * Numeric and string literals are cast to their native PHP types.
	 *
	 * @param array{type: string, value: string} $token
	 *
	 * @return mixed
	 *
	 * @throws DBALRuntimeException when a `:binding` name is not in `$bindings`
	 */
	private function resolveRightTokenValue(array $token): mixed
	{
		if (self::T_BINDING === $token['type']) {
			$name = $token['value'];

			if (!\array_key_exists($name, $this->bindings)) {
				throw new DBALRuntimeException(
					\sprintf(
						'Missing value for binding ":%s" in filter expression.',
						$name
					),
					$this->toArray()
				);
			}

			return $this->bindings[$name];
		}

		if (self::T_NUMBER === $token['type']) {
			$raw = $token['value'];

			return \str_contains($raw, '.') ? (float) $raw : (int) $raw;
		}

		// T_IDENT or T_STRING -> return as-is string
		if (self::T_IDENT === $token['type'] || self::T_STRING === $token['type']) {
			return $token['value'];
		}

		throw new DBALRuntimeException(
			\sprintf('Unexpected token type "%s" as right operand in filter expression.', $token['type']),
			[Filters::STR_EXPR_FILTER_KEY => $this->expression, '_position' => $this->pos - 1, '_token' => $token]
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** Returns the current token without advancing. */
	private function current(): ?array
	{
		return $this->tokens[$this->pos] ?? null;
	}

	/**
	 * Asserts the current token is of the expected type, advances, and returns it.
	 *
	 * @param string $type
	 *
	 * @return array{type: string, value: string}
	 */
	private function expect(string $type): array
	{
		$token = $this->current();

		if (null === $token) {
			throw new DBALRuntimeException(
				\sprintf('Expected token "%s" but reached end of filter expression.', $type),
				[Filters::STR_EXPR_FILTER_KEY => $this->expression, '_position' => $this->pos]
			);
		}

		if ($token['type'] !== $type) {
			throw new DBALRuntimeException(
				\sprintf(
					'Expected token "%s" but got "%s" (value: "%s").',
					$type,
					$token['type'],
					$token['value']
				),
				[Filters::STR_EXPR_FILTER_KEY => $this->expression, '_position' => $this->pos, '_token' => $token]
			);
		}

		++$this->pos;

		return $token;
	}
}
