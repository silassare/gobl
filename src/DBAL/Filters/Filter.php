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
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBExpression;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Filter.
 */
final class Filter implements FilterInterface
{
	use ArrayCapableTrait;

	/**
	 * Filter constructor.
	 *
	 * @param \Gobl\DBAL\Operator $operator  The operator
	 * @param string              $left      The left operand as provided by the user
	 * @param mixed               $right     The right operand as provided by the user
	 * @param string              $left_str  The left operand as string to be used in the query
	 * @param null|string         $right_str The right operand as string to be used in the query
	 *                                       or null if not yet defined like for prepared statements
	 */
	public function __construct(
		protected Operator $operator,
		protected string $left,
		protected null|int|float|string|array|QBExpression|QBInterface $right,
		protected string $left_str,
		protected ?string $right_str,
	) {
	}

	/**
	 * Filter destructor.
	 */
	public function __destruct()
	{
		unset($this->operator, $this->left_str, $this->right_str, $this->left, $this->right);
	}

	/**
	 * Get filter left operand.
	 *
	 * @return string
	 */
	public function getLeftOperand(): string
	{
		return $this->left;
	}

	/**
	 * Get filter right operand.
	 *
	 * @return null|array|float|\Gobl\DBAL\Queries\Interfaces\QBInterface|\Gobl\DBAL\Queries\QBExpression|int|string
	 */
	public function getRightOperand(): null|int|float|string|array|QBExpression|QBInterface
	{
		return $this->right;
	}

	/**
	 * Get filter right operand as string.
	 *
	 * @return null|string
	 */
	public function getRightOperandString(): ?string
	{
		return $this->right_str;
	}

	/**
	 * Get filter left operand as a string.
	 *
	 * @return mixed
	 */
	public function getLeftOperandString(): string
	{
		return $this->left_str;
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
		return [
			$this->left,
			$this->operator,
			$this->right,
		];
	}
}
