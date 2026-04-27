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

namespace Gobl\ORM\Interfaces;

/**
 * Interface WithFiltersInterface.
 */
interface WithFiltersInterface
{
	/**
	 * Gets the filters.
	 *
	 * @return null|array the filters applied to the query, or null if no filters are set
	 */
	public function getFilters(): ?array;

	/**
	 * Sets the filters.
	 *
	 * @param null|array $filters the filters to apply to the query, or null to remove all filters
	 *
	 * @return $this
	 */
	public function setFilters(?array $filters): static;
}
