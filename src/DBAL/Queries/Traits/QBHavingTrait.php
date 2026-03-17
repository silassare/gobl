<?php

/**
 * Copyright (c) Emile Silas Sare.
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
	 * Returns the current HAVING condition, or `null` when none has been set.
	 *
	 * @return null|Filters|string
	 */
	public function getOptionsHaving(): Filters|string|null
	{
		return $this->options_having;
	}

	/**
	 * Sets the HAVING clause, replacing any previously set condition.
	 *
	 * Pass `null` to clear the clause. Accepts a `Filters` instance or a raw SQL string.
	 *
	 * @param null|Filters|string $condition the HAVING condition, or `null` to clear
	 *
	 * @return static
	 */
	public function having(Filters|string|null $condition): static
	{
		$this->options_having = $condition;

		return $this;
	}
}
