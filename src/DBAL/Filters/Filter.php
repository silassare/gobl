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

use Gobl\DBAL\Filters\Interfaces\FilterInterface;
use Gobl\DBAL\Filters\Operands\FilterLeftOperand;
use Gobl\DBAL\Filters\Operands\FilterRightOperand;
use Gobl\DBAL\Operator;
use InvalidArgumentException;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Filter.
 */
final class Filter implements FilterInterface
{
	use ArrayCapableTrait;

	/**
	 * The left operand as string to be used in the query.
	 */
	protected ?string $left_for_query = null;

	/**
	 * The right operand as string to be used in the query.
	 */
	protected ?string $right_for_query = null;

	/**
	 * Filter constructor.
	 *
	 * @param Operator                $operator The operator
	 * @param FilterLeftOperand       $left     The left operand
	 * @param null|FilterRightOperand $right    The right operand
	 */
	public function __construct(
		protected Operator $operator,
		protected FilterLeftOperand $left,
		protected ?FilterRightOperand $right,
	) {}

	/**
	 * Filter destructor.
	 */
	public function __destruct()
	{
		unset($this->operator, $this->left, $this->right);
	}

	/**
	 * Get filter left operand.
	 *
	 * @return FilterLeftOperand
	 */
	public function getLeftOperand(): FilterLeftOperand
	{
		return $this->left;
	}

	/**
	 * Get filter right operand.
	 *
	 * @return null|FilterRightOperand
	 */
	public function getRightOperand(): ?FilterRightOperand
	{
		return $this->right;
	}

	/**
	 * Sets the left operand for query.
	 *
	 * @param string $left_for_query
	 *
	 * @return $this
	 */
	public function setLeftOperandForQuery(string $left_for_query): static
	{
		$this->left_for_query = $left_for_query;

		return $this;
	}

	/**
	 * Sets the right operand for query.
	 *
	 * @param null|string $right_for_query
	 *
	 * @return $this
	 */
	public function setRightOperandForQuery(?string $right_for_query): static
	{
		if ($this->operator->isUnary() && null !== $right_for_query) {
			throw new InvalidArgumentException('Cannot set a right operand for a unary operator.');
		}

		$this->right_for_query = $right_for_query;

		return $this;
	}

	/**
	 * Get filter operator.
	 *
	 * @return Operator
	 */
	public function getOperator(): Operator
	{
		return $this->operator;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$out = [$this->left, $this->operator];

		if (!$this->operator->isUnary()) {
			$out[] = $this->right;
		}

		return $out;
	}
}
