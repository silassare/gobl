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

namespace Gobl\DBAL\Queries;

/**
 * Class QBExpression.
 */
class QBExpression
{
	/**
	 * QBExpression constructor.
	 *
	 * @param string $expression
	 */
	public function __construct(protected string $expression)
	{
	}

	/**
	 * Magic method __toString.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->expression;
	}
}
