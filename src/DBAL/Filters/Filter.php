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
	 * @param \Gobl\DBAL\Operator $operator
	 * @param string              $left
	 * @param null|string         $right
	 * @param string              $raw_left
	 * @param mixed               $raw_right
	 */
	public function __construct(
		protected Operator $operator,
		protected string $left,
		protected ?string $right,
		protected string $raw_left,
		protected null|int|float|string|array|QBExpression|QBInterface $raw_right
	) {
	}

	/**
	 * Filter destructor.
	 */
	public function __destruct()
	{
		unset($this->operator, $this->left, $this->right, $this->raw_left, $this->raw_right);
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
	 * @return null|string
	 */
	public function getRightOperand(): ?string
	{
		return $this->right;
	}

	/**
	 * Get filter raw left operand.
	 *
	 * @return mixed
	 */
	public function getLeftOperandRaw(): string
	{
		return $this->raw_left;
	}

	/**
	 * Get filter raw right operand.
	 *
	 * @return null|array|float|\Gobl\DBAL\Queries\Interfaces\QBInterface|\Gobl\DBAL\Queries\QBExpression|int|string
	 */
	public function getRightOperandRaw(): null|int|float|string|array|QBExpression|QBInterface
	{
		return $this->raw_right;
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
