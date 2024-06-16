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

namespace Gobl\DBAL\Filters\Traits;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\QBExpression;
use Gobl\DBAL\Queries\QBSelect;

/**
 * Trait FiltersOperatorsHelpersTrait.
 */
trait FiltersOperatorsHelpersTrait
{
	/**
	 * Adds equal condition.
	 *
	 * @param string                                           $left
	 * @param null|bool|float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function eq(string $left, null|bool|float|int|QBExpression|QBSelect|string $right): static
	{
		if (null === $right) {
			return $this->add(Operator::IS_NULL, $left);
		}

		return $this->add(Operator::EQ, $left, $right);
	}

	/**
	 * Adds not equal condition.
	 *
	 * @param string                                           $left
	 * @param null|bool|float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function neq(string $left, null|bool|float|int|QBExpression|QBSelect|string $right): static
	{
		if (null === $right) {
			return $this->add(Operator::IS_NOT_NULL, $left);
		}

		return $this->add(Operator::NEQ, $left, $right);
	}

	/**
	 * Adds like condition.
	 *
	 * @param string $left
	 * @param string $right
	 *
	 * @return $this
	 */
	public function like(string $left, string $right): static
	{
		return $this->add(Operator::LIKE, $left, $right);
	}

	/**
	 * Adds not like condition.
	 *
	 * @param string $left
	 * @param string $right
	 *
	 * @return $this
	 */
	public function notLike(string $left, string $right): static
	{
		return $this->add(Operator::NOT_LIKE, $left, $right);
	}

	/**
	 * Adds less than condition.
	 *
	 * @param string                                 $left
	 * @param float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function lt(string $left, float|int|QBExpression|QBSelect|string $right): static
	{
		return $this->add(Operator::LT, $left, $right);
	}

	/**
	 * Adds less than or equal condition.
	 *
	 * @param string                                 $left
	 * @param float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function lte(string $left, float|int|QBExpression|QBSelect|string $right): static
	{
		return $this->add(Operator::LTE, $left, $right);
	}

	/**
	 * Adds greater than condition.
	 *
	 * @param string                                 $left
	 * @param float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function gt(string $left, float|int|QBExpression|QBSelect|string $right): static
	{
		return $this->add(Operator::GT, $left, $right);
	}

	/**
	 * Adds greater than or equal condition.
	 *
	 * @param string                                 $left
	 * @param float|int|QBExpression|QBSelect|string $right
	 *
	 * @return $this
	 */
	public function gte(string $left, float|int|QBExpression|QBSelect|string $right): static
	{
		return $this->add(Operator::GTE, $left, $right);
	}

	/**
	 * Adds IS NULL condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 */
	public function isNull(string $left): static
	{
		return $this->add(Operator::IS_NULL, $left);
	}

	/**
	 * Adds IS NOT NULL condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 */
	public function isNotNull(string $left): static
	{
		return $this->add(Operator::IS_NOT_NULL, $left);
	}

	/**
	 * Adds IN list condition.
	 *
	 * @param string                      $left
	 * @param array|QBExpression|QBSelect $right
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function in(string $left, array|QBExpression|QBSelect $right): static
	{
		if ('' === $left) {
			throw new DBALException('the left operand must be a non-empty string.');
		}

		if (empty($left)) {
			throw new DBALException('the right operand must not be empty.');
		}

		return $this->add(Operator::IN, $left, $right);
	}

	/**
	 * Adds NOT IN list condition.
	 *
	 * @param string                      $left
	 * @param array|QBExpression|QBSelect $right
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function notIn(string $left, array|QBExpression|QBSelect $right): static
	{
		if ('' === $left) {
			throw new DBALException('the left operand must be a non-empty string.');
		}

		if (empty($left)) {
			throw new DBALException('the right operand must not be empty.');
		}

		return $this->add(Operator::NOT_IN, $left, $right);
	}

	/**
	 * Adds IS TRUE condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 */
	public function isTrue(string $left): static
	{
		return $this->add(Operator::IS_TRUE, $left);
	}

	/**
	 * Adds IS FALSE condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 */
	public function isFalse(string $left): static
	{
		return $this->add(Operator::IS_FALSE, $left);
	}
}
