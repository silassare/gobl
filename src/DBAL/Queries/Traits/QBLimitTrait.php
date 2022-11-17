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

use Gobl\DBAL\Exceptions\DBALException;

/**
 * Trait QBLimitTrait.
 */
trait QBLimitTrait
{
	protected ?int $options_limit_offset = null;
	protected ?int $options_limit_max    = null;

	/**
	 * @return null|int
	 */
	public function getOptionsLimitMax(): ?int
	{
		return $this->options_limit_max;
	}

	/**
	 * @return null|int
	 */
	public function getOptionsLimitOffset(): ?int
	{
		return $this->options_limit_offset;
	}

	/**
	 * Sets limits to the query result.
	 *
	 * @param null|int $max    maximum result to get
	 * @param int      $offset offset of the first result
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function limit(?int $max = null, int $offset = 0): static
	{
		if (null !== $max) {
			if ($max <= 0) {
				throw new DBALException(\sprintf('invalid limit max "%s".', $max));
			}

			if ($offset < 0) {
				throw new DBALException(\sprintf('invalid limit offset "%s".', $offset));
			}

			$this->options_limit_max    = $max;
			$this->options_limit_offset = $offset;
		}

		return $this;
	}
}
