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
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class FilterGroup.
 */
final class FilterGroup implements FilterInterface
{
	use ArrayCapableTrait;

	/** @var array<FilterInterface|Filters> */
	private array $filters = [];

	/**
	 * FilterGroup constructor.
	 *
	 * @param bool $is_and
	 */
	public function __construct(protected bool $is_and) {}

	/**
	 * Returns filters in this group.
	 *
	 * @param bool $flatten
	 *
	 * @return array<FilterInterface|Filters>
	 */
	public function getFilters(bool $flatten = false): array
	{
		if ($flatten) {
			$out = [];
			foreach ($this->filters as $filter) {
				if ($filter instanceof self && $filter->is_and === $this->is_and) {
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
		return $this->is_and;
	}

	/**
	 * Adds a new filter to this group.
	 *
	 * @param FilterInterface|Filters|string $filter
	 *
	 * @return $this
	 */
	public function push(FilterInterface|Filters|string $filter): self
	{
		// if the given filter is an object we add it as is to the group
		// to be able to keep track of future modification on the filter

		if (\is_string($filter)) {
			$filter = new FilterRaw($filter);
		}

		$this->filters[] = $filter;

		return $this;
	}

	/**
	 * Makes sure the group use the given conditional operator.
	 *
	 * @param bool $is_and
	 *
	 * @return $this
	 */
	public function ensureChainingCondition(bool $is_and): self
	{
		if ($this->is_and !== $is_and) {
			$group          = new self($this->is_and);
			$group->filters = $this->filters;

			$this->filters = [$group];
			$this->is_and  = $is_and;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return $this->getFilters();
	}
}
