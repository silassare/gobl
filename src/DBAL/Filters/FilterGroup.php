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

/**
 * Class FilterGroup.
 */
final class FilterGroup
{
	/** @var Filter[]|FilterGroup[] */
	private array $filters = [];

	/**
	 * FilterGroup constructor.
	 *
	 * @param bool $use_and
	 */
	public function __construct(protected bool $use_and)
	{
	}

	/**
	 * Returns filters in this group.
	 *
	 * @param bool $flatten
	 *
	 * @return array
	 */
	public function getFilters(bool $flatten = false): array
	{
		if ($flatten) {
			$out = [];
			foreach ($this->filters as $filter) {
				if ($filter instanceof self && $filter->use_and === $this->use_and) {
					foreach ($filter->filters as $f) {
						$out[] = $f;
					}
				} else {
					$out[] = $filter;
				}
			}

			return $out;
		}

		return $this->filters;
	}

	/**
	 * Checks if this group use AND conditional operator.
	 *
	 * @return bool
	 */
	public function isAnd(): bool
	{
		return $this->use_and;
	}

	/**
	 * Adds a new filter or filter group.
	 *
	 * @param \Gobl\DBAL\Filters\Filter|\Gobl\DBAL\Filters\FilterGroup $filter
	 *
	 * @return $this
	 */
	public function push(Filter|self $filter): self
	{
		// to be able to keep track of future modification
		// we just add it without any modification
		$this->filters[] = $filter;

		return $this;
	}
}
