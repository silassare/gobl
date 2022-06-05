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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Filters\Filters;

/**
 * Trait QBHavingTrait.
 */
trait QBHavingTrait
{
	protected Filters|string|null $options_having = null;

	/**
	 * @return null|\Gobl\DBAL\Filters\Filters|string
	 */
	public function getOptionsHaving(): Filters|string|null
	{
		return $this->options_having;
	}

	/**
	 * @param null|Filters|string $condition
	 *
	 * @return $this
	 */
	public function having(Filters|string|null $condition): static
	{
		$this->options_having = $condition;

		return $this;
	}
}
