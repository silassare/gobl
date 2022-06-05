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

namespace Gobl\DBAL\Diff\Traits;

/**
 * Trait DiffAwareTrait.
 */
trait DiffAwareTrait
{
	private string $diff_key;

	/**
	 * Returns the diff key used to track changes.
	 *
	 * @return string
	 */
	public function getDiffKey(): string
	{
		if (empty($this->diff_key)) {
			$this->diff_key = \md5(\uniqid('', true));
		}

		return $this->diff_key;
	}

	/**
	 * Sets a diff key used to track changes.
	 *
	 * @param string $diff_key
	 *
	 * @return $this
	 */
	public function setDiffKey(string $diff_key): static
	{
		$this->diff_key = $diff_key;

		return $this;
	}
}
