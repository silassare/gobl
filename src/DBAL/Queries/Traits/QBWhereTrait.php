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
 * Trait QBWhereTrait.
 */
trait QBWhereTrait
{
	use QBFilterableTrait;

	protected Filters|string|null $options_where = null;

	/**
	 * @param null|\Gobl\DBAL\Filters\Filters|string $condition
	 *
	 * @return $this
	 */
	public function where(Filters|string|null $condition): static
	{
		$this->options_where = $condition;

		return $this;
	}

	/**
	 * @return null|\Gobl\DBAL\Filters\Filters|string
	 */
	public function getOptionsWhere(): Filters|string|null
	{
		return $this->options_where;
	}
}
