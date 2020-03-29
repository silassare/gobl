<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\ORM;

use Gobl\DBAL\Db;
use Gobl\DBAL\QueryBuilder;
use Gobl\DBAL\Rule;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Interfaces\ORMFiltersScopeInterface;

class ORMFilters
{
	public static $OPERATORS_NAME_MAP = [
		'eq'          => Rule::OP_EQ,
		'neq'         => Rule::OP_NEQ,
		'lt'          => Rule::OP_LT,
		'lte'         => Rule::OP_LTE,
		'gt'          => Rule::OP_GT,
		'gte'         => Rule::OP_GTE,
		'like'        => Rule::OP_LIKE,
		'not_like'    => Rule::OP_NOT_LIKE,
		'in'          => Rule::OP_IN,
		'not_in'      => Rule::OP_NOT_IN,
		'is_null'     => Rule::OP_IS_NULL,
		'is_not_null' => Rule::OP_IS_NOT_NULL,
	];

	/** @var \Gobl\DBAL\Db */
	protected $db;

	/** @var \Gobl\DBAL\Rule[] */
	protected $rules;

	/** @var array */
	protected $params;

	/**
	 * @var \Gobl\ORM\Interfaces\ORMFiltersScopeInterface
	 */
	protected $scope;

	/**
	 * ORMFilters constructor.
	 *
	 * @param \Gobl\DBAL\Db                                 $db
	 * @param \Gobl\ORM\Interfaces\ORMFiltersScopeInterface $scope
	 */
	public function __construct(Db $db, ORMFiltersScopeInterface $scope)
	{
		$this->db     = $db;
		$this->scope  = $scope;
		$this->params = [];
		$this->rules  = [];
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		$this->db    = null;
		$this->scope = null;
	}

	/**
	 * Add a new filter.
	 *
	 * @param string $column   the column full name
	 * @param mixed  $value    the filter value
	 * @param int    $operator the operator to use
	 * @param bool   $use_and  whether to use AND condition
	 *                         to combine multiple rules on the same column
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return $this
	 */
	public function addFilter($column, $value, $operator = Rule::OP_EQ, $use_and = true)
	{
		if (!$this->scope->isFieldAllowed($column)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_FILTER_FIELD_NOT_ALLOWED', [
				'column'   => $column,
				'value'    => $value,
				'operator' => self::operatorName($operator),
			]);
		}

		if (!$this->scope->isFilterAllowed($column, $value, $operator)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_FILTER_NOT_ALLOWED', [
				'column'   => $column,
				'value'    => $value,
				'operator' => self::operatorName($operator),
			]);
		}

		$column_fqn = $this->scope->getColumnFQName($column);
		$qb         = new QueryBuilder($this->db);

		if (!isset($this->rules[$column_fqn])) {
			$rule = $this->rules[$column_fqn] = new Rule($qb);
		} else {
			$rule = $this->rules[$column_fqn];

			if ($use_and) {
				$rule->andX();
			} else {
				$rule->orX();
			}
		}

		if ($operator === Rule::OP_IN || $operator === Rule::OP_NOT_IN) {
			if (!\is_array($value)) {
				throw new ORMException('GOBL_ORM_REQUEST_FILTER_IN_AND_NOT_IN_REQUIRE_ARRAY', [
					'column' => $column,
					'value'  => $value,
				]);
			}

			$value = $qb->arrayToListItems($value);
			$rule->conditions([$column_fqn => $value], $operator, false);
		} elseif ($operator === Rule::OP_IS_NULL || $operator === Rule::OP_IS_NOT_NULL) {
			$rule->conditions([$column_fqn], $operator, false);
		} else {
			$param_key                = QueryBuilder::genUniqueParamKey();
			$this->params[$param_key] = $value;
			$value                    = ':' . $param_key;
			$rule->conditions([$column_fqn => $value], $operator, false);
		}

		return $this;
	}

	/**
	 * Add filters array.
	 *
	 * $filters = [
	 *        'user_name'  => [
	 *            ['eq', 'value1'],
	 *            ['eq', 'value2']
	 *        ],
	 *        'user_add_time'   => [
	 *            ['gt', 1558522500],
	 *            ['lt', 1458522500, 'or']
	 *        ],
	 *        'user_valid' => 1
	 * ];
	 *
	 * (user_name = value1 AND user_name = value2) AND (user_add_time > 1558522500 OR user_add_time < 1458522500)
	 * AND (user_valid = 1)
	 *
	 * @param array $filters
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 *
	 * @return $this
	 */
	public function addFiltersArray(array $filters)
	{
		if (empty($filters)) {
			return $this;
		}

		foreach ($filters as $column => $column_filters) {
			if (\is_array($column_filters)) {
				foreach ($column_filters as $filter) {
					if (\is_array($filter)) {
						if (!isset($filter[0])) {
							throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_FILTERS', [
								'column' => $column,
								'filter' => $filter,
							]);
						}

						$operator_name = $filter[0];

						if (!isset(self::$OPERATORS_NAME_MAP[$operator_name])) {
							throw new ORMQueryException('GOBL_ORM_REQUEST_UNKNOWN_OPERATOR_IN_FILTERS', [
								'column' => $column,
								'filter' => $filter,
							]);
						}

						$safe_value    = true;
						$operator      = self::$OPERATORS_NAME_MAP[$operator_name];
						$value         = null;
						$use_and       = true;
						$value_index   = 1;
						$use_and_index = 2;

						if ($operator === Rule::OP_IS_NULL || $operator === Rule::OP_IS_NOT_NULL) {
							$use_and_index = 1;// value not needed
						} else {
							if (!\array_key_exists($value_index, $filter)) {
								throw new ORMQueryException('GOBL_ORM_REQUEST_MISSING_VALUE_IN_FILTERS', [
									'column' => $column,
									'filter' => $filter,
								]);
							}

							$value = $filter[$value_index];

							if ($value === null) {
								if ($operator === Rule::OP_EQ) {
									$operator = Rule::OP_IS_NULL;
								} elseif ($operator === Rule::OP_NEQ) {
									$operator = Rule::OP_IS_NOT_NULL;
								} else {
									throw new ORMQueryException('GOBL_ORM_REQUEST_NULL_VALUE_IN_FILTERS', [
										'column' => $column,
										'filter' => $filter,
									]);
								}
							} else {
								if ($operator === Rule::OP_IN || $operator === Rule::OP_NOT_IN) {
									$safe_value = \is_array($value) && \count($value) ? true : false;
								} elseif (!\is_scalar($value)) {
									$safe_value = false;
								}

								if (!$safe_value) {
									throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_VALUE_IN_FILTERS', [
										'column' => $column,
										'filter' => $filter,
									]);
								}
							}
						}

						if (isset($filter[$use_and_index])) {
							$a = $filter[$use_and_index];

							if ($a === 'and' || $a === 'AND') {
								$use_and = true;
							} elseif ($a === 'or' || $a === 'OR') {
								$use_and = false;
							} else {
								throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_FILTERS', [
									'column' => $column,
									'filter' => $filter,
								]);
							}
						}

						$this->addFilter($column, $value, $operator, $use_and);
					} else {
						throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_FILTERS', [
							'column' => $column,
							'filter' => $filter,
						]);
					}
				}
			} else {
				$value = $column_filters;
				$this->addFilter($column, $value, null === $value ? Rule::OP_IS_NULL : Rule::OP_EQ);
			}
		}

		return $this;
	}

	/**
	 * Returns a rule that include all applied filters rules.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return null|\Gobl\DBAL\Rule
	 */
	public function getFiltersRule()
	{
		if (\count($this->rules)) {
			/** @var \Gobl\DBAL\Rule $rule */
			$rule = null;

			foreach ($this->rules as $r) {
				if (!$rule) {
					$rule = $r;
				} else {
					$rule->andX($r);
				}
			}

			return $rule;
		}

		return null;
	}

	/**
	 * Gets params.
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->params;
	}

	/**
	 * Gets a query builder instance with the current rule.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\DBAL\QueryBuilder
	 */
	public function getQueryBuilder()
	{
		$qb   = new QueryBuilder($this->db);
		$rule = $this->getFiltersRule();

		if (null !== $rule) {
			$qb->where($rule);
		}

		$qb->bindArray($this->params);

		return $qb;
	}

	/**
	 * Returns operator name.
	 *
	 * @param int $operator
	 *
	 * @return null|string
	 */
	protected static function operatorName($operator)
	{
		$rev = \array_flip(self::$OPERATORS_NAME_MAP);

		return isset($rev[$operator]) ? $rev[$operator] : null;
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return ['instance_of' => static::class];
	}
}
