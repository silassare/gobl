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

use BackedEnum;
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Filters\Operands\FilterLeftOperand;
use Gobl\DBAL\Filters\Operands\FilterRightOperand;
use Gobl\DBAL\Filters\Traits\FiltersOperatorsHelpersTrait;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBType;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\Utils\TypeUtils;
use JsonSerializable;
use PHPUtils\Str;

/**
 * Class FiltersBuilder.
 */
final class Filters
{
	use FiltersOperatorsHelpersTrait;

	public const STR_EXPR_FILTER_KEY   = '_$filter';
	public const STR_EXPR_BINDINGS_KEY = '_$bindings';

	private FilterGroup $group;

	/**
	 * FiltersBuilder constructor.
	 *
	 * @param QBInterface                $qb
	 * @param null|FiltersScopeInterface $scope
	 */
	public function __construct(protected QBInterface $qb, protected ?FiltersScopeInterface $scope = null)
	{
		$this->group = new FilterGroup(true);
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo(): array
	{
		return [
			'instance_of'        => self::class,
			'filters'            => (string) $this,
			'bound_values'       => $this->qb->getBoundValues(),
			'bound_values_types' => $this->qb->getBoundValuesTypes(),
		];
	}

	/**
	 * Magic string helper.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->qb->getRDBMS()
			->getGenerator()
			->filterToExpression($this->group);
	}

	/**
	 * Creates a `Filters` instance from an array using the new flat format.
	 *
	 * **Format auto-detection:**
	 * - When we find the special key `self::STR_EXPR_FILTER_KEY` (`'_$filter'`)
	 *   the array is treated as the string-expression format and forwarded to {@see self::fromString()}.
	 * - When the **first key is an integer** (or the array is empty) the flat format
	 *   is parsed: `['col', 'op', value, 'AND'|'OR', ['col2', 'op2', value2], ...]`.
	 * - When we have a **string key** the array is treated as the legacy format
	 *   `[col => [operator => value], ...]` and forwarded to {@see self::fromOldFiltersArray()}.
	 *
	 * **Flat array format:**
	 * ```
	 * [
	 *   ['foo', 'eq', 'value'], 'OR', ['bar', 'lt', 8], 'AND', 'baz', 'is_null'
	 * ]
	 * ```
	 *
	 * **String-expression format (`_$filter` / `_$bindings`):**
	 *
	 * When the array has the `'_$filter'` key, it is treated as a string expression
	 * with named bindings. The expression syntax uses column names, operators, and
	 * `:name` binding references. The expression is parsed in strict mode by default
	 * (only `:binding` references are accepted as right-hand operands; inline numeric or
	 * string literals are not allowed and will throw).
	 *
	 * - `'_$filter'` (string, required): the filter expression, e.g. `'foo eq :v1 and bar lt :v2'`.
	 * - `'_$bindings'` (array, optional, default `[]`): map of binding name to value, e.g. `['v1' => 'x', 'v2' => 8]`.
	 * - No other keys are allowed alongside `'_$filter'`; any extra key throws a {@see DBALRuntimeException}.
	 *
	 * ```php
	 * // Standalone string-expression (no bindings needed when expression is unary-only)
	 * Filters::fromArray(
	 *     ['_$filter' => 'name is_null'],
	 *     $qb
	 * );
	 *
	 * // With bindings
	 * Filters::fromArray(
	 *     [
	 *         '_$filter'   => 'foo eq :val1 and bar lt :val2',
	 *         '_$bindings' => ['val1' => 'value', 'val2' => 8],
	 *     ],
	 *     $qb
	 * );
	 * ```
	 *
	 * **Mixed format** - string-expression nested inside a flat-array group:
	 *
	 * A `'_$filter'` map can appear as one element of a flat-array filter alongside
	 * plain flat conditions joined by `'AND'` / `'OR'`:
	 *
	 * ```php
	 * Filters::fromArray(
	 *     [
	 *         [
	 *             '_$filter'   => 'foo eq :val1 and bar lt :val2',
	 *             '_$bindings' => ['val1' => 'value', 'val2' => 8],
	 *         ],
	 *         'OR',
	 *         ['baz', 'is_null'],
	 *     ],
	 *     $qb
	 * );
	 * ```
	 *
	 * @param array                      $filters flat filter array (see format above)
	 * @param QBInterface                $qb      the query builder this filter is attached to
	 * @param null|FiltersScopeInterface $scope   optional scope for column access control
	 *
	 * @return static
	 */
	public static function fromArray(array $filters, QBInterface $qb, ?FiltersScopeInterface $scope = null): self
	{
		// check new string expression format with bindings
		if (isset($filters[self::STR_EXPR_FILTER_KEY])) {
			$expression = $filters[self::STR_EXPR_FILTER_KEY];
			$bindings   = $filters[self::STR_EXPR_BINDINGS_KEY] ?? [];

			// strict: ensure we don't have other keys that would potentially indicate a mistake
			// we can ignore it but we choose to be strict here to fail early and avoid confusion
			foreach ($filters as $k => $_v) {
				if (!\in_array($k, [self::STR_EXPR_FILTER_KEY, self::STR_EXPR_BINDINGS_KEY], true)) {
					throw new DBALRuntimeException(
						\sprintf(
							'Unexpected key "%s" found in filters array with "%s" key; only "%s" and "%s" are allowed when using string expression format.',
							$k,
							self::STR_EXPR_FILTER_KEY,
							self::STR_EXPR_FILTER_KEY,
							self::STR_EXPR_BINDINGS_KEY
						),
						['_filters' => $filters]
					);
				}
			}

			if (!\is_string($expression)) {
				throw new DBALRuntimeException(
					\sprintf('Invalid filter expression, found "%s" while expecting a string.', \get_debug_type($expression)),
					['_filters' => $filters]
				);
			}

			if (!\is_array($bindings)) {
				throw new DBALRuntimeException(
					\sprintf('Invalid filter bindings, found "%s" while expecting an array.', \get_debug_type($bindings)),
					['_filters' => $filters]
				);
			}

			return self::fromString($expression, $bindings, $qb, $scope);
		}

		// check old format
		if (!\is_int(\key($filters))) {
			return self::fromOldFiltersArray($filters, $qb, $scope);
		}

		$instance = new self($qb, $scope);

		$i = 0;
		while ($cur = $filters[$i] ?? null) {
			if (\is_string($cur)) {
				$left = $cur;
				$op   = $filters[++$i] ?? null;

				if ($op instanceof Operator) {
					$op = $op->value;
				} elseif (!\is_string($op)) {
					throw new DBALRuntimeException(
						\sprintf(
							'unexpected "%s" while expecting operator.',
							\get_debug_type($op)
						),
						[
							'_after'      => $left,
							'_unexpected' => $op,
							'_filters'    => $filters,
						]
					);
				}

				$op       = \strtolower($op);
				$operator = Operator::tryFrom($op);

				if (!$operator) {
					throw new DBALRuntimeException('invalid operator.', [
						'_found'   => $op,
						'_filters' => $filters,
					]);
				}

				if ($operator->isUnary()) {
					$instance->add($operator, $left);
				} else {
					$right = $filters[++$i] ?? null;

					if (null === $right) {
						if (Operator::EQ === $operator) {
							$operator = Operator::IS_NULL;
						} elseif (Operator::NEQ === $operator) {
							$operator = Operator::IS_NOT_NULL;
						} else {
							throw new DBALRuntimeException('invalid right operand.', [
								'_filter'  => [$left, $op, $right],
								'_filters' => $filters,
							]);
						}
					}

					$instance->add($operator, $left, $right);
				}
			} elseif (\is_array($cur)) {
				$instance->where(self::fromArray($cur, $qb, $scope));
			} else {
				throw new DBALRuntimeException(
					\sprintf('unexpected "%s" will expecting "string|array".', \get_debug_type($cur)),
					[
						'_unexpected' => $cur,
						'_filters'    => $filters,
					]
				);
			}

			/**
			 * we use {@see array_key_exists} to be sure that we could throw error
			 * when we found null in this situation for example: ['foo', 'eq', 2, null, ...]
			 * false if end of input.
			 */
			if (\array_key_exists(++$i, $filters)) {
				$cond = $filters[$i];
				if (!\is_string($cond)) {
					throw new DBALRuntimeException(
						\sprintf(
							'unexpected "%s" will expecting conditional operator: "and|or".',
							\get_debug_type($cond)
						)
					);
				}

				$cond = \strtoupper($cond);

				if ('AND' === $cond) {
					$instance->and();
				} elseif ('OR' === $cond) {
					$instance->or();
				} else {
					throw new DBALRuntimeException(
						\sprintf('unexpected conditional operator "%s" allowed value are: "and|or".', $cond)
					);
				}

				$next = $filters[++$i] ?? null;
				if (null === $next) {
					throw new DBALRuntimeException(
						'a conditional operator should not be the last item in a filter or filter group.'
					);
				}
			}
		}

		return $instance;
	}

	/**
	 * Create a sub group for complex filters.
	 *
	 * Returns a new `Filters` instance that shares the same `QBInterface` and scope.
	 * Use this when you need a reusable group to pass to `and()`, `or()`, or `where()`.
	 *
	 * ```php
	 * $sub = $filters->subGroup();
	 * $sub->eq('user_role', 'admin')->or()->eq('user_role', 'superadmin');
	 * $filters->or($sub);
	 * ```
	 *
	 * @return $this
	 */
	public function subGroup(): self
	{
		return new self($this->qb, $this->scope);
	}

	/**
	 * Ensures the next condition is joined with AND, then optionally appends filters.
	 *
	 * When called without arguments it only switches the chaining operator so the
	 * subsequent `->eq()`, `->in()`, etc. call is joined with AND (this is also the
	 * default, so calling it bare is rarely necessary).
	 *
	 * When passed one or more arguments each is appended as an AND-joined sub-group:
	 *
	 * ```php
	 * // Inline AND (bare call - usually omitted)
	 * $f->eq('a', 1)->and()->eq('b', 2);
	 *
	 * // AND sub-group via callable - callable MUST return the same $g instance
	 * $f->and(function (Filters $g) {
	 *     return $g->isNotNull('email')->like('email', '%@example.com');
	 * });
	 *
	 * // AND an existing Filters instance (must share the same QBInterface)
	 * $f->and($otherFilters);
	 * ```
	 *
	 * @param array|callable|self ...$filters
	 *
	 * @return $this
	 */
	public function and(array|callable|self ...$filters): self
	{
		$this->group->ensureChainingCondition(true);

		return !empty($filters) ? $this->where(...$filters) : $this;
	}

	/**
	 * Merges one or more filter conditions into the current filter group.
	 *
	 * Three safety guards are enforced when the argument is a `Filters` instance:
	 * 1. **Self-reference** - passing `$this` throws to prevent infinite recursion;
	 *    use {@see subGroup()} instead.
	 * 2. **QBInterface identity** - the incoming `Filters` must share the exact same
	 *    `QBInterface` instance. Cross-query injection is rejected to prevent
	 *    alias conflicts and other query-corruption issues.
	 * 3. **Scope compatibility** - when a scope is attached, the incoming `Filters`
	 *    scope must be permitted by the current scope via
	 *    `FiltersScopeInterface::shouldAllowFiltersScope()`, preventing unauthorized
	 *    column access from leaking in through merged filters.
	 *
	 * @param array|callable|Filters ...$filters
	 *
	 * @return $this
	 */
	public function where(array|callable|self ...$filters): self
	{
		foreach ($filters as $entry) {
			if ($entry instanceof self) {
				if ($entry === $this) {
					throw new DBALRuntimeException(
						\sprintf(
							'Current instance used as sub group, you may need to create a sub group with: %s',
							Str::callableName([$this, 'subGroup'])
						)
					);
				}

				if ($this->qb !== $entry->qb) {
					throw (new DBALRuntimeException(
						\sprintf(
							'Provided filters instance does not share the same "%s" instance as the current filters instance.',
							QBInterface::class
						)
					))->suspectObject($entry->qb);
				}

				if ($this->scope && $this->scope !== $entry->scope && (!$entry->scope || !$this->scope->shouldAllowFiltersScope($entry->scope))) {
					$e = (new DBALRuntimeException(
						'Provided filters instance scope is not allowed by the current filter scope.'
					));
					$entry->scope && $e->suspectObject($entry->scope);

					throw $e;
				}

				$filter = $entry->group;
			} elseif (\is_callable($entry)) {
				$sub    = $this->subGroup();
				$return = $entry($sub);

				if ($return !== $sub) {
					throw (new DBALRuntimeException(
						\sprintf(
							'The sub-filters group callable should return the same instance of "%s" passed as argument.',
							self::class
						)
					))
						->suspectCallable($entry);
				}

				$filter = $sub->group;
			} else {
				$filter = self::fromArray($entry, $this->qb, $this->scope)->group;
			}

			$this->group->push($filter);
		}

		return $this;
	}

	/**
	 * Ensures the next condition is joined with OR, then optionally appends filters.
	 *
	 * When called without arguments it only switches the chaining operator so the
	 * subsequent `->eq()`, `->in()`, etc. call is joined with OR.
	 *
	 * When passed one or more arguments each is appended as an OR-joined sub-group:
	 *
	 * ```php
	 * // Inline OR between two equal checks
	 * $f->eq('role', 'admin')->or()->eq('role', 'superadmin');
	 *
	 * // OR sub-group via callable - callable MUST return the same $g instance
	 * $f->or(function (Filters $g) {
	 *     return $g->lt('age', 13)->or()->gt('age', 65);
	 * });
	 *
	 * // OR an existing Filters instance (must share the same QBInterface)
	 * $f->or($otherFilters);
	 * ```
	 *
	 * @param array|callable|self ...$filters
	 *
	 * @return $this
	 */
	public function or(array|callable|self ...$filters): self
	{
		$this->group->ensureChainingCondition(false);

		return !empty($filters) ? $this->where(...$filters) : $this;
	}

	/**
	 * Appends a single filter condition.
	 *
	 * Operator normalization applied before storage:
	 * - `IS_TRUE` is promoted to `EQ true`.
	 * - `IS_FALSE` is promoted to `EQ false`.
	 *
	 * @param Operator                   $operator the comparison or unary operator to apply
	 * @param FilterFieldNotation|string $left     the left operand: column name, FQN, alias-qualified reference,
	 *                                             or an explicit FilterFieldNotation for typed/pre-parsed references
	 * @param null|mixed                 $right    the right operand; must be `null` for unary operators;
	 *                                             pass a FilterFieldNotation for column-to-column comparisons
	 *                                             (plain strings are NEVER auto-resolved as column refs for security);
	 *                                             arrays and `\JsonSerializable` are auto-serialized to JSON for `CONTAINS`;
	 *                                             arrays are also valid for `IN` and `NOT_IN`
	 *
	 * @return $this
	 */
	public function add(
		Operator $operator,
		FilterFieldNotation|string $left,
		array|BackedEnum|bool|Column|FilterFieldNotation|float|int|JsonSerializable|QBExpression|QBInterface|string|null $u_right = null
	): self {
		if (Operator::IS_TRUE === $operator) {
			$operator   = Operator::EQ;
			$u_right    = true;
		} elseif (Operator::IS_FALSE === $operator) {
			$operator   = Operator::EQ;
			$u_right    = false;
		} elseif (Operator::HAS_KEY === $operator) {
			if (!\is_string($u_right) || empty($u_right)) {
				throw new DBALRuntimeException(
					\sprintf(
						'operator "%s" requires a non-empty string right operand representing the JSON path to check for existence of a key.',
						$operator->name
					)
				);
			}
		}

		// Normalize null right-operand for equality operators to IS_NULL / IS_NOT_NULL.
		// Mirrors Filters::fromArray() behavior: ['col', 'eq', null] -> IS_NULL.
		if (null === $u_right) {
			if (Operator::EQ === $operator) {
				$operator = Operator::IS_NULL;
			} elseif (Operator::NEQ === $operator) {
				$operator = Operator::IS_NOT_NULL;
			}
		}

		$left_operand  = new FilterLeftOperand($left, $this->qb, $this->scope);
		$right_operand =   null;

		if (!$operator->isUnary()) {
			$try_enforce_query_type = true;

			if ($u_right instanceof QBInterface && QBType::SELECT !== $u_right->getType()) {
				throw new DBALRuntimeException(
					\sprintf(
						'right operand of type "%s" should be a "SELECT" not: %s',
						$u_right::class,
						$u_right->getType()->name
					)
				);
			}

			if (\is_array($u_right)) {
				$allow_array = [Operator::IN, Operator::NOT_IN, Operator::CONTAINS];

				if (!\in_array($operator, $allow_array, true)) {
					throw new DBALRuntimeException(
						\sprintf(
							'array type is only supported for right operand of "%s" operator, not: %s',
							\implode('", "', \array_map(static fn ($op) => $op->name, $allow_array)),
							$operator->name
						)
					);
				}
			} elseif ($u_right instanceof JsonSerializable && Operator::CONTAINS !== $operator) {
				throw new DBALRuntimeException(
					\sprintf(
						'%s type is only supported for right operand of "%s" operator, not: %s',
						JsonSerializable::class,
						Operator::CONTAINS->name,
						$operator->name
					)
				);
			}

			// for IN operators we accept only: array, SELECT and expressions
			if (Operator::IN === $operator || Operator::NOT_IN === $operator) {
				if ($u_right instanceof QBSelect || $u_right instanceof QBExpression) {
					$try_enforce_query_type = false;
				} elseif (!\is_array($u_right)) {
					throw new DBALRuntimeException(
						\sprintf(
							'operator "%s" right operand should be of type "%s" not: %s',
							$operator->name,
							\implode('|', ['array', QBSelect::class, QBExpression::class]),
							\get_debug_type($u_right)
						)
					);
				}
			} elseif (Operator::CONTAINS === $operator) {
				// CONTAINS: auto-serialize to JSON string.
				$u_right                = TypeJSON::serializeJsonValue($u_right);
				$try_enforce_query_type = false;
			} elseif (Operator::HAS_KEY === $operator) {
				// HAS_KEY right operand is a key path string, not a typed column value.
				$try_enforce_query_type = false;
			}

			$right_operand = new FilterRightOperand($u_right, $this->qb, $this->scope);
			$right_nz      = $right_operand->getValueNormalized();

			if ($right_operand->canBeSafelyBound()) {
				if (\is_array($right_nz)) {
					$right_nz = $this->qb->bindArrayForInList($right_nz, [], true);
				} else {
					$param_key = QBUtils::newParamKey();

					$this->qb->bindNamed($param_key, $right_nz);

					$right_nz = ':' . $param_key;
				}
			}

			// Enforce query-compatible types for the right operand when possible, using the detected column from the left operand.
			// This is best-effort and only applied when we have a detected column on the left operand
			if ($try_enforce_query_type && $left_operand->hasResolvedColumn()) {
				$resolved = $left_operand->getResolvedColumnOrFail();
				$table    = $resolved->getTable();

				$right_nz = TypeUtils::runEnforceQueryExpressionValueType(
					$table->getFullName(),
					$resolved->getColumnName(),
					$right_nz,
					$this->qb->getRDBMS()
				);
			}

			$right_operand->setValueNormalized($right_nz);
		}

		$filter = new Filter($operator, $left_operand, $right_operand);

		if ($this->scope) {
			$this->scope->assertFilterAllowed($filter, $this->qb);
		}

		$this->group->push($filter);

		return $this;
	}

	/**
	 * Checks if the filters is empty.
	 *
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return empty((string) $this);
	}

	/**
	 * Creates filters builder instance from string expression.
	 *
	 * ```php
	 * <?php
	 *   $filters = [
	 *     ['foo', 'eq', "bla"],
	 *     'and',
	 *     [['bar', 'eq', 'kat']]
	 *     'and'
	 *     [['baz', 'eq', 8], 'or', ['baz', 'gt', 10]]
	 *   ];
	 *
	 *   // same as
	 *   $expression = 'foo eq :val1 and bar eq :val2 and (baz eq :val3 or baz gt :val4)';
	 *   $bindings = ['val1' => 'bla', 'val2' => 'kat', 'val3' => 8, 'val4' => 10];
	 *
	 *   // or with strict mode off (inline static values)
	 *   $expression = 'foo eq "bla" and bar eq "kat" and (baz eq 8 or baz gt 10)';
	 * ```
	 *
	 * @param string                     $expression The filter expression string to parse
	 * @param array                      $bindings   Map of binding name => value (e.g. `['val1' => 'bla']`)
	 * @param QBInterface                $qb         The query builder instance to use for bindings and table/column resolution
	 * @param null|FiltersScopeInterface $scope      Optional filters scope for validating and resolving columns in the expression
	 * @param bool                       $strict     When `true` (default), only `:binding` references are
	 *                                               accepted as right operands. Set to `false` to also allow
	 *                                               inline numeric and quoted-string literals.
	 *
	 * @return Filters
	 */
	public static function fromString(
		string $expression,
		array $bindings,
		QBInterface $qb,
		?FiltersScopeInterface $scope = null,
		bool $strict = true
	): self {
		$parser = new FiltersExpressionParser($expression, $bindings, $strict);

		return $parser->toFilters($qb, $scope);
	}

	/**
	 * Creates filters builder instance from old array filters.
	 *
	 * ```
	 * // old filters array
	 * [
	 *   'foo' => value,
	 *   'bar' => [['eq', value]],
	 *   'baz' => [['lt', value, 'and'], ['lt', value, 'or']]
	 * ]
	 *
	 * // is the same as
	 *
	 * [
	 *   ['foo', 'eq', value],
	 *   'and',
	 *   [['bar', 'eq', value]]
	 *   'and'
	 *   [['baz', 'eq', value], 'or', ['baz', 'lt', value]]
	 * ]
	 * ```
	 *
	 * @param array                      $filters
	 * @param QBInterface                $qb
	 * @param null|FiltersScopeInterface $scope
	 *
	 * @return Filters
	 */
	private static function fromOldFiltersArray(
		array $filters,
		QBInterface $qb,
		?FiltersScopeInterface $scope = null
	): self {
		$instance = new self($qb, $scope);

		foreach ($filters as $left => $group) {
			if (\is_array($group)) {
				$sub_filters_group = $instance->subGroup();

				foreach ($group as $filter) {
					if (\is_array($filter)) { // [[rule, value, cond], ...]
						$operator_name = $filter[0] ?? null;
						if (!\is_string($operator_name)) {
							throw new DBALRuntimeException('GOBL_INVALID_FILTERS', [
								'field'  => $left,
								'filter' => $filter,
							]);
						}

						$operator = Operator::tryFrom($operator_name);

						if (null === $operator) {
							throw new DBALRuntimeException('GOBL_UNKNOWN_OPERATOR_IN_FILTERS', [
								'field'  => $left,
								'filter' => $filter,
							]);
						}

						$value         = null;
						$value_index   = 1;
						$use_and_index = 2;

						if (Operator::IS_NULL === $operator || Operator::IS_NOT_NULL === $operator) {
							$use_and_index = 1; // value not needed
						} else {
							if (!\array_key_exists($value_index, $filter)) {
								throw new DBALRuntimeException('GOBL_MISSING_VALUE_IN_FILTERS', [
									'field'  => $left,
									'filter' => $filter,
								]);
							}

							$value = $filter[$value_index];
						}

						if (isset($filter[$use_and_index])) {
							$a = $filter[$use_and_index];

							if ('and' === $a || 'AND' === $a) {
								$sub_filters_group->and();
							} elseif ('or' === $a || 'OR' === $a) {
								$sub_filters_group->or();
							} else {
								throw new DBALRuntimeException('GOBL_INVALID_FILTERS', [
									'field'  => $left,
									'filter' => $filter,
								]);
							}
						} else {
							$sub_filters_group->and();
						}

						$sub_filters_group->add($operator, $left, $value);
					} else {
						throw new DBALRuntimeException('GOBL_INVALID_FILTERS', [
							'field'  => $left,
							'filter' => $filter,
						]);
					}
				}

				$instance->where($sub_filters_group);
			} else {
				null === $group ? $instance->isNull($left) : $instance->add(Operator::EQ, $left, $group);
			}
		}

		return $instance;
	}
}
