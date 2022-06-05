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

use Gobl\DBAL\Operator;

/**
 * Class Filter.
 */
final class Filter
{
	/**
	 * Filter constructor.
	 *
	 * @param \Gobl\DBAL\Operator $operator
	 * @param string              $left
	 * @param null|string         $right
	 */
	public function __construct(
		protected Operator $operator,
		protected string $left,
		protected ?string $right,
	) {
	}

	/**
	 * @return string
	 */
	public function getLeftOperand(): string
	{
		return $this->left;
	}

	/**
	 * @return null|string
	 */
	public function getRightOperand(): ?string
	{
		return $this->right;
	}

	/**
	 * @return Operator
	 */
	public function getOperator(): Operator
	{
		return $this->operator;
	}
}
