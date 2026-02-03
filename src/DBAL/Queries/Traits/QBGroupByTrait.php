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

/**
 * Trait QBGroupByTrait.
 */
trait QBGroupByTrait
{
	protected array $options_group_by = [];

	/**
	 * @return array
	 */
	public function getOptionsGroupBy(): array
	{
		return $this->options_group_by;
	}

	/**
	 * @param array $group_by
	 *
	 * @return $this
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
