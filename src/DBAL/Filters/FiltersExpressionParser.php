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

namespace Gobl\DBAL\Filters;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Interfaces\FilterInterface;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
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
 *  - Identifier  : column name, optionally table-qualified (`table.column`)
 *  - Operator    : any {@see Operator} value (e.g. `eq`, `gt`, `is_true`)
 *  - Conditional : `and` | `or`
 *  - Binding     : `:name` resolved from the `$inject` map
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
	 * @param string                     $expression The filter expression string to parse
	 * @param array                      $inject     Map of binding name => value (e.g. `['val1' => 'bla']`)
	 * @param QBInterface                $qb         The query builder instance to use for bindings and table/column resolution
	 * @param null|FiltersScopeInterface $scope      Optional filters scope for validating and resolving columns in the expression
	 * @param bool                       $strict     When `true` (default), only `:binding` references are
	 *                                               accepted as right operands. Set to `false` to also allow
	 *                                               inline numeric and quoted-string literals.
	 */
	public function __construct(
		private string $expression,
		private array $inject,
		private QBInterface $qb,
		private ?FiltersScopeInterface $scope = null,
		private bool $strict = true
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'expression' => $this->expression,
			'inject'     => $this->inject,
		];
	}

	/**
	 * Parses the expression and returns the built {@see Filters} instance.
	 *
	 * @return Filters
	 */
	public function parse(): Filters
	{
		$this->tokenize();
		$this->pos = 0;

		$filters = new Filters($this->qb, $this->scope);

		if (!empty($this->tokens)) {
			$this->parseGroup($filters);
		}

		if ($this->pos < \count($this->tokens)) {
			$token = $this->tokens[$this->pos];

			throw new DBALRuntimeException(
				\sprintf(
					'Unexpected token "%s" (%s) after end of expression.',
					$token['value'],
					$token['type']
				),
				[
					'_expression' => $this->expression,
					'_position'   => $this->pos,
					'_token'      => $token,
				]
			);
		}

		return $filters;
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

			// binding: :name
			if (':' === $expr[$i]) {
				$j = $i + 1;
				while ($j < $len && (\ctype_alnum($expr[$j]) || '_' === $expr[$j])) {
					++$j;
				}

				$name = \substr($expr, $i + 1, $j - $i - 1);

				if ('' === $name) {
					throw new DBALRuntimeException(
						'Empty binding name in filter expression.',
						['_expression' => $this->expression, '_position' => $i]
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
						['_expression' => $this->expression, '_position' => $i]
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
			$j = $i;
			while ($j < $len && !\ctype_space($expr[$j]) && '(' !== $expr[$j] && ')' !== $expr[$j]) {
				++$j;
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
	// Recursive-descent parser
	// -------------------------------------------------------------------------

	/**
	 * Parses: item ( COND item )*.
	 */
	private function parseGroup(Filters $filters): void
	{
		$this->parseItem($filters);

		while (null !== ($token = $this->current()) && self::T_COND === $token['type']) {
			++$this->pos;

			if ('AND' === $token['value']) {
				$filters->and();
			} else {
				$filters->or();
			}

			$this->parseItem($filters);
		}
	}

	/**
	 * Parses: '(' group ')' | filter.
	 */
	private function parseItem(Filters $filters): void
	{
		$token = $this->current();

		if (null === $token) {
			throw new DBALRuntimeException(
				'Unexpected end of filter expression.',
				['_expression' => $this->expression, '_position' => $this->pos]
			);
		}

		if (self::T_OPEN === $token['type']) {
			++$this->pos; // consume '('
			$sub = $filters->subGroup();
			$this->parseGroup($sub);
			$this->expect(self::T_CLOSE);
			$filters->where($sub);
		} else {
			$this->parseFilter($filters);
		}
	}

	/**
	 * Parses: IDENT OPERATOR [right]
	 * where right is a binding value, a literal identifier, or absent for unary operators.
	 */
	private function parseFilter(Filters $filters): void
	{
		$left_token = $this->expect(self::T_IDENT);
		$left       = $left_token['value'];

		$op_token = $this->expect(self::T_OP);
		$operator = Operator::from($op_token['value']);

		if ($operator->isUnary()) {
			$filters->add($operator, $left);

			return;
		}

		$right_token = $this->current();

		if (null === $right_token) {
			throw new DBALRuntimeException(
				\sprintf(
					'Missing right operand for operator "%s" after column "%s" in filter expression.',
					$operator->value,
					$left
				),
				['_expression' => $this->expression, '_position' => $this->pos]
			);
		}

		++$this->pos;

		if (self::T_BINDING === $right_token['type']) {
			$name = $right_token['value'];

			if (!\array_key_exists($name, $this->inject)) {
				throw new DBALRuntimeException(
					\sprintf(
						'Missing inject value for binding ":%s" in filter expression.',
						$name
					),
					[
						'_expression' => $this->expression,
						'_binding'    => $name,
						'_inject'     => \array_keys($this->inject),
					]
				);
			}

			$right = $this->inject[$name];
		} elseif (self::T_IDENT === $right_token['type']) {
			// unquoted literal used as a column reference or raw value
			$right = $right_token['value'];
		} elseif (self::T_NUMBER === $right_token['type']) {
			// inline numeric literal (non-strict mode)
			$raw   = $right_token['value'];
			$right = \str_contains($raw, '.') ? (float) $raw : (int) $raw;
		} elseif (self::T_STRING === $right_token['type']) {
			// inline quoted-string literal (non-strict mode)
			$right = $right_token['value'];
		} else {
			throw new DBALRuntimeException(
				\sprintf(
					'Unexpected token type "%s" for right operand of operator "%s".',
					$right_token['type'],
					$operator->value
				),
				['_expression' => $this->expression, '_position' => $this->pos - 1]
			);
		}

		$filters->add($operator, $left, $right);
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
				['_expression' => $this->expression, '_position' => $this->pos]
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
				['_expression' => $this->expression, '_position' => $this->pos, '_token' => $token]
			);
		}

		++$this->pos;

		return $token;
	}
}
