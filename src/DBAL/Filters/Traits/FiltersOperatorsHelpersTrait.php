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
	 * @param string                                                                            $left
	 * @param null|float|\Gobl\DBAL\Queries\QBExpression|\Gobl\DBAL\Queries\QBSelect|int|string $right
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function eq(string $left, null|int|float|string|QBSelect|QBExpression $right): static
	{
		if (null === $right) {
			return $this->add(Operator::IS_NULL, $left);
		}

		return $this->add(Operator::EQ, $left, $right);
	}

	/**
	 * Adds not equal condition.
	 *
	 * @param string                                                                            $left
	 * @param null|float|\Gobl\DBAL\Queries\QBExpression|\Gobl\DBAL\Queries\QBSelect|int|string $right
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function neq(string $left, null|int|float|string|QBSelect|QBExpression $right): static
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
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function notLike(string $left, string $right): static
	{
		return $this->add(Operator::NOT_LIKE, $left, $right);
	}

	/**
	 * Adds less than condition.
	 *
	 * @param string                                                                       $left
	 * @param float|\Gobl\DBAL\Queries\QBExpression|\Gobl\DBAL\Queries\QBSelect|int|string $right
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function lt(string $left, int|float|string|QBSelect|QBExpression $right): static
	{
		return $this->add(Operator::LT, $left, $right);
	}

	/**
	 * Adds less than or equal condition.
	 *
	 * @param string                                                                       $left
	 * @param float|\Gobl\DBAL\Queries\QBExpression|\Gobl\DBAL\Queries\QBSelect|int|string $right
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function lte(string $left, int|float|string|QBSelect|QBExpression $right): static
	{
		return $this->add(Operator::LTE, $left, $right);
	}

	/**
	 * Adds greater than condition.
	 *
	 * @param string                                                                       $left
	 * @param float|\Gobl\DBAL\Queries\QBExpression|\Gobl\DBAL\Queries\QBSelect|int|string $right
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function gt(string $left, int|float|string|QBSelect|QBExpression $right): static
	{
		return $this->add(Operator::GT, $left, $right);
	}

	/**
	 * Adds greater than or equal condition.
	 *
	 * @param string                                                                       $left
	 * @param float|\Gobl\DBAL\Queries\QBExpression|\Gobl\DBAL\Queries\QBSelect|int|string $right
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function gte(string $left, int|float|string|QBSelect|QBExpression $right): static
	{
		return $this->add(Operator::GTE, $left, $right);
	}

	/**
	 * Adds IS NULL condition.
	 *
	 * @param string $left
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function isNotNull(string $left): static
	{
		return $this->add(Operator::IS_NOT_NULL, $left);
	}

	/**
	 * Adds IN list condition.
	 *
	 * @param string                                                            $left
	 * @param array|\Gobl\DBAL\Queries\QBExpression|\Gobl\DBAL\Queries\QBSelect $right
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function in(string $left, array|QBSelect|QBExpression $right): static
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
	 * @param string                                                            $left
	 * @param array|\Gobl\DBAL\Queries\QBExpression|\Gobl\DBAL\Queries\QBSelect $right
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function notIn(string $left, array|QBSelect|QBExpression $right): static
	{
		if ('' === $left) {
			throw new DBALException('the left operand must be a non-empty string.');
		}

		if (empty($left)) {
			throw new DBALException('the right operand must not be empty.');
		}

		return $this->add(Operator::NOT_IN, $left, $right);
	}
}
