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
	 * Returns the filters in this group.
	 *
	 * When `$flatten` is `true`, any direct child `FilterGroup` that uses the **same**
	 * chaining operator as this group is merged (its filters are inlined), reducing
	 * unnecessary nesting. Child groups with a different operator are kept intact.
	 *
	 * @param bool $flatten when `true`, same-operator child groups are merged into this level
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
	 * Ensures this group uses the requested chaining operator (AND or OR).
	 *
	 * When the requested operator differs from the current one, the existing filters
	 * are **wrapped** into a new child `FilterGroup` (preserving their operator), and
	 * the outer group's operator is changed. This avoids silently reinterpreting
	 * existing conditions under a different conjunction.
	 *
	 * Example: switching an AND group to OR wraps existing AND conditions in a sub-group:
	 * `(a AND b)` becomes `(a AND b) OR ...`
	 *
	 * @param bool $is_and `true` for AND, `false` for OR
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

	public function toArray(): array
	{
		return $this->getFilters();
	}
}
