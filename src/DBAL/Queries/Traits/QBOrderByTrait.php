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
 * Trait QBOrderByTrait.
 */
trait QBOrderByTrait
{
	/** @var array<int, string> */
	protected array $options_order_by = [];

	/**
	 * @return string[]
	 */
	public function getOptionsOrderBy(): array
	{
		return $this->options_order_by;
	}

	/**
	 * @param array $order_by
	 *
	 * @return $this
	 */
	public function orderBy(array $order_by): static
	{
		foreach ($order_by as $key => $value) {
			if (\is_int($key)) {
				$order = $value;
			} else {
				$order = $key . ($value ? ' ASC' : ' DESC');
			}

			$this->options_order_by[] = $order;
		}

		return $this;
	}
}
