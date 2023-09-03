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

use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Filters\Traits\FiltersOperatorsHelpersTrait;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBType;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Types\Utils\TypeUtils;
use PHPUtils\Str;
use Throwable;

/**
 * Class FiltersBuilder.
 */
final class Filters
{
	use FiltersOperatorsHelpersTrait;

	private FilterGroup $group;

	/**
	 * FiltersBuilder constructor.
	 *
	 * @param \Gobl\DBAL\Queries\Interfaces\QBInterface                $qb
	 * @param null|\Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface $scope
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
	 * Creates filters builder instance from array.
	 *
	 *```
	 * new filters array
	 * [
	 *   ['foo', 'eq', 'value', 'OR', ['bar', 'lt', 8]], 'AND', 'baz', 'is_null']
	 * ]
	 *```
	 *
	 * @param array                                                    $filters
	 * @param \Gobl\DBAL\Queries\Interfaces\QBInterface                $qb
	 * @param null|\Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface $scope
	 *
	 * @return static
	 */
	public static function fromArray(array $filters, QBInterface $qb, ?FiltersScopeInterface $scope = null): self
	{
		$key = \key($filters);

		if (!\is_int($key)) {
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
	 * @return $this
	 */
	public function subGroup(): self
	{
		return new self($this->qb, $this->scope);
	}

	/**
	 * Joins filters and following with AND condition.
	 *
	 * @param array|callable|self ...$filters
	 *
	 * @return $this
	 */
	public function and(array|self|callable ...$filters): self
	{
		$this->group->ensureChainingCondition(true);

		return !empty($filters) ? $this->where(...$filters) : $this;
	}

	/**
	 * Merge a list of filters to the current filters.
	 *
	 * ```
	 * we shouldn't
	 *
	 * - merge a filters instance that
	 * does not share the same {@see QBInterface} instance
	 * this rule is to prevent same table with different
	 * alias bug and many other possible issue...
	 *
	 * - merge a filters instance if its scope
	 * {@see FiltersScopeInterface} is not allowed by the current filters instance scope
	 * as it may lead to some information leak, as a vulnerability may
	 * allow an user to use a filters that is not allowed
	 * in the actual filters scope...
	 * ```
	 *
	 * @param array|callable|\Gobl\DBAL\Filters\Filters ...$filters
	 *
	 * @return $this
	 */
	public function where(array|self|callable ...$filters): self
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

				if ($this->scope && (!$entry->scope || !$this->scope->shouldAllowFiltersScope($entry->scope))) {
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
	 * Joins filters and following with OR condition.
	 *
	 * @return $this
	 */
	public function or(array|self|callable ...$filters): self
	{
		$this->group->ensureChainingCondition(false);

		return !empty($filters) ? $this->where(...$filters) : $this;
	}

	/**
	 * Adds a list of filters.
	 *
	 * @param \Gobl\DBAL\Operator $operator the operator to use
	 * @param string              $left     the left operand
	 * @param null|mixed          $right    the right operand if allowed
	 *
	 * @return $this
	 */
	public function add(
		Operator $operator,
		string $left,
		null|int|bool|float|string|array|QBExpression|QBInterface $right = null
	): self {
		if (Operator::IS_TRUE === $operator) {
			$operator = Operator::EQ;
			$right    = true;
		} elseif (Operator::IS_FALSE === $operator) {
			$operator = Operator::EQ;
			$right    = false;
		}

		$operands_count = $operator->getOperandsCount();

		$real_left  = $left;
		$real_right = $right;

		$left = $this->cleanOperand($left, $detected_left_table, $detected_left_column);

		if ($this->scope) {
			$left = $detected_left_column ?? $left;

			$tmp_filter = new Filter($operator, $real_left, $real_right, $left, null);

			$this->scope->assertFilterAllowed($tmp_filter);

			if (!$detected_left_table) {
				$left = $this->scope->getColumnFQName($left);
			}
		}

		if (2 === $operands_count) {
			$try_enforce_query_type = true;

			if ($right instanceof QBInterface && QBType::SELECT !== $right->getType()) {
				throw new DBALRuntimeException(
					\sprintf(
						'right operand of type "%s" should be a "SELECT" not: %s',
						$right::class,
						$right->getType()->name
					)
				);
			}

			if (Operator::IN === $operator || Operator::NOT_IN === $operator) {
				if (\is_array($right)) {
					$right = $this->qb->bindArrayForInList($right, [], true);
				} elseif ($right instanceof QBSelect) {
					$this->qb->bindMergeFrom($right);
					$right                  = '(' . $right->getSqlQuery() . ')';
					$try_enforce_query_type = false;
				} elseif ($right instanceof QBExpression) {
					$right                  = (string) $right;
					$try_enforce_query_type = false;
				} else {
					throw new DBALRuntimeException(
						\sprintf(
							'operator "%s" right operand should be of type "%s" not: %s',
							$operator->name,
							\implode('|', ['array', QBSelect::class, QBExpression::class]),
							\get_debug_type($right)
						)
					);
				}
			} elseif ($right instanceof QBInterface) {
				$this->qb->bindMergeFrom($right);
				$right = '(' . $right->getSqlQuery() . ')';
			} elseif ($right instanceof QBExpression) {
				$right = (string) $right;
			} else {
				if (\is_array($right)) {
					throw new DBALRuntimeException(
						\sprintf(
							'"array" type for right operand not supported by operator: %s',
							$operator->name
						)
					);
				}

				$right = $this->cleanOperand($right, $detected_right_table, $detected_right_column);

				if (!$detected_right_column && !$this->isRightOperandABinding($right)) {
					$param_key = QBUtils::newParamKey();
					$this->qb->bindNamed($param_key, $right);

					$right = ':' . $param_key;
				}
			}

			if ($detected_left_table && $detected_left_column && $try_enforce_query_type) {
				$right = TypeUtils::runEnforceQueryExpressionValueType(
					$detected_left_table,
					$detected_left_column,
					$right,
					$this->qb->getRDBMS()
				);
			}

			if (\is_array($right)) {
				$right = '(' . \implode(', ', $right) . ')';
			}
		} else {
			$right = null;
		}

		$filter = new Filter($operator, $real_left, $real_right, $left, $right);

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
	 * @param array                                                    $filters
	 * @param \Gobl\DBAL\Queries\Interfaces\QBInterface                $qb
	 * @param null|\Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface $scope
	 *
	 * @return \Gobl\DBAL\Filters\Filters
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
					if (\is_array($filter)) {// [[rule, value, cond], ...]
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

	/**
	 * Cleans operand.
	 *
	 * @param mixed       $operand
	 * @param null|string &$found_table
	 * @param null|string &$found_column
	 *
	 * @return string
	 */
	private function cleanOperand(mixed $operand, string &$found_table = null, string &$found_column = null): mixed
	{
		if ($operand instanceof QBExpression) {
			$operand = (string) $operand;
		} elseif (\is_string($operand) && \strlen($operand) > 2 && ':' !== $operand[0] && '(' !== $operand[0]) {
			$parts = \explode('.', $operand);

			if (2 === \count($parts) && !empty($parts[0]) && !empty($parts[1])) {
				try {
					$table_name = $this->qb->resolveTable($parts[0])
						?->getFullName();
					if ($table_name) {
						$operand = $this->qb->fullyQualifiedName($parts[0], $parts[1]);
						// we found a table and column
						$fqn_parts = \explode('.', $operand);

						if (2 === \count($fqn_parts)) {
							$found_table  = $table_name;
							$found_column = $fqn_parts[1];
						}
					}
				} catch (Throwable) {
				}
			}
		} elseif ($operand instanceof Column) {
			$column = $operand;
			$table  = $column->getTable();

			if (null === $table) {
				throw new DBALRuntimeException(
					\sprintf('attempt to use unlocked column "%s" in a query.', $column->getName())
				);
			}

			$found_table  = $table->getFullName();
			$found_column = $column->getFullName();

			$operand = $this->qb->fullyQualifiedName($found_table, $found_column);
		}

		return $operand;
	}

	/**
	 * Checks if the right operand is a binding.
	 *
	 * @param mixed $right
	 *
	 * @return bool
	 */
	private function isRightOperandABinding(mixed $right): bool
	{
		return \is_string($right) && $right && ':' === $right[0] && $this->qb->isBoundParam(\substr($right, 1));
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
	 *   $inject = ['val1' => 'bla', 'val2' => 'kat', 'val3' => 8, 'val4' => 10];
	 * ```
	 *
	 * @param string                                                   $expression
	 * @param array                                                    $inject
	 * @param \Gobl\DBAL\Queries\Interfaces\QBInterface                $qb
	 * @param null|\Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface $scope
	 *
	 * @return \Gobl\DBAL\Filters\Filters
	 */
	private static function fromString(
		string $expression,
		array $inject,
		QBInterface $qb,
		?FiltersScopeInterface $scope = null
	): self {
		// TODO: implement
		return new self($qb, $scope);
	}
}
