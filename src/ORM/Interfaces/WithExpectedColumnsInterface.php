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
 * Interface WithExpectedColumnsInterface.
 */
interface WithExpectedColumnsInterface
{
	/**
	 * Gets the expected columns.
	 *
	 * @return null|list<string> an array of column names to select, or null to select all allowed columns
	 */
	public function getExpectedColumns(): ?array;

	/**
	 * Sets the expected columns to select.
	 *
	 * @param null|list<string> $expected_columns an array of column names to select, or null to select all allowed columns
	 *
	 * @return $this
	 */
	public function setExpectedColumns(?array $expected_columns): static;
}
