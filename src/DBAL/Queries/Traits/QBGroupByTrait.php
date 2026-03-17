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

/**
 * Trait QBGroupByTrait.
 */
trait QBGroupByTrait
{
	protected array $options_group_by = [];

	/**
	 * Returns the raw GROUP BY expression list accumulated by {@see groupBy()} calls.
	 *
	 * @return array
	 */
	public function getOptionsGroupBy(): array
	{
		return $this->options_group_by;
	}

	/**
	 * Appends expressions to the GROUP BY clause.
	 *
	 * Each entry in `$group_by` must be a pre-qualified SQL expression
	 * (e.g. a column reference like `u.user_type`). Empty strings are silently skipped.
	 *
	 * @param array $group_by list of SQL GROUP BY expressions
	 *
	 * @return static
	 */
	public function groupBy(array $group_by): static
	{
		foreach ($group_by as $group) {
			if (empty($group)) {
				continue;
			}

			$this->options_group_by[] = $group;
		}

		return $this;
	}
}
