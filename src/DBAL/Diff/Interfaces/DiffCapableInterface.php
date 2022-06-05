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

namespace Gobl\DBAL\Diff\Interfaces;

/**
 * Interface DiffCapableInterface.
 */
interface DiffCapableInterface
{
	/**
	 * Returns diff action for a given target.
	 *
	 * @param static $target
	 *
	 * @return \Gobl\DBAL\Diff\DiffAction[]
	 */
	public function diff(self $target): array;

	/**
	 * Returns the diff key used to track changes.
	 *
	 * @return string
	 */
	public function getDiffKey(): string;

	/**
	 * Sets a diff key used to track changes.
	 *
	 * @param string $diff_key
	 *
	 * @return $this
	 */
	public function setDiffKey(string $diff_key): static;
}
