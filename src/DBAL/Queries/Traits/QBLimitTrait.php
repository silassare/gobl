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

use Gobl\DBAL\Exceptions\DBALRuntimeException;

/**
 * Trait QBLimitTrait.
 */
trait QBLimitTrait
{
	protected ?int $options_limit_offset = null;
	protected ?int $options_limit_max    = null;

	/**
	 * Returns the current LIMIT max value, or `null` when no limit is set.
	 *
	 * @return null|int
	 */
	public function getOptionsLimitMax(): ?int
	{
		return $this->options_limit_max;
	}

	/**
	 * Returns the current OFFSET value, or `null` when no limit is set.
	 *
	 * @return null|int
	 */
	public function getOptionsLimitOffset(): ?int
	{
		return $this->options_limit_offset;
	}

	/**
	 * Sets a limit on the query result.
	 *
	 * When `$max` is `null` this call is a **no-op** - the existing limit (if any) is
	 * not cleared. Pass `null` intentionally when you want to call `limit()` without
	 * touching the current pagination state.
	 *
	 * @param null|int $max    maximum number of rows to return; `null` leaves the current limit unchanged
	 * @param int      $offset zero-based offset of the first row to return
	 *
	 * @return static
	 *
	 * @throws DBALRuntimeException when `$max <= 0` or `$offset < 0`
	 */
	public function limit(?int $max = null, int $offset = 0): static
	{
		if (null !== $max) {
			if ($max <= 0) {
				throw new DBALRuntimeException(\sprintf('invalid limit max "%s".', $max));
			}

			if ($offset < 0) {
				throw new DBALRuntimeException(\sprintf('invalid limit offset "%s".', $offset));
			}

			$this->options_limit_max    = $max;
			$this->options_limit_offset = $offset;
		}

		return $this;
	}
}
