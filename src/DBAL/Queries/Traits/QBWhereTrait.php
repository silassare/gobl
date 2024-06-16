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

use Gobl\DBAL\Filters\FilterGroup;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\Interfaces\FilterInterface;

/**
 * Trait QBWhereTrait.
 */
trait QBWhereTrait
{
	use QBFilterableTrait;

	protected ?FilterGroup $options_where = null;

	/**
	 * Adds a where condition.
	 *
	 * @param callable|FilterInterface|Filters|string $condition
	 *
	 * @return $this
	 */
	public function where(callable|FilterInterface|Filters|string $condition): static
	{
		return $this->ensureWhere(true, $condition);
	}

	/**
	 * Adds a where condition with an AND chaining condition.
	 *
	 * @param callable|FilterInterface|Filters|string $condition
	 *
	 * @return $this
	 */
	public function andWhere(callable|FilterInterface|Filters|string $condition): static
	{
		return $this->ensureWhere(true, $condition);
	}

	/**
	 * Adds a where condition with an OR chaining condition.
	 *
	 * @param callable|FilterInterface|Filters|string $condition
	 *
	 * @return $this
	 */
	public function orWhere(callable|FilterInterface|Filters|string $condition): static
	{
		return $this->ensureWhere(false, $condition);
	}

	/**
	 * @return null|FilterGroup
	 */
	public function getOptionsWhere(): ?FilterGroup
	{
		return $this->options_where;
	}

	/**
	 * Ensures the where condition is set.
	 *
	 * If not, it creates a new one.
	 * If yes, it ensures the chaining condition is the same as the given one.
	 *
	 * @param bool                                    $is_and
	 * @param callable|FilterInterface|Filters|string $condition
	 *
	 * @return $this
	 */
	private function ensureWhere(bool $is_and, callable|FilterInterface|Filters|string $condition): static
	{
		if (!$this->options_where) {
			$this->options_where = new FilterGroup($is_and);
		} else {
			$this->options_where->ensureChainingCondition($is_and);
		}

		if (\is_callable($condition)) {
			$filter = $this->filters();

			$condition = $filter->where($condition);
		}

		$this->options_where->push($condition);

		return $this;
	}
}
