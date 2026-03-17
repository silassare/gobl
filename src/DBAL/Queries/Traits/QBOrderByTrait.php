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

use InvalidArgumentException;

/**
 * Trait QBOrderByTrait.
 */
trait QBOrderByTrait
{
	/** @var array<int, string> */
	protected array $options_order_by = [];

	/**
	 * Get the current ORDER BY clauses.
	 *
	 * @return array<int, string> List of ORDER BY clauses, e.g. ["name ASC", "created_at DESC"]
	 */
	public function getOptionsOrderBy(): array
	{
		return $this->options_order_by;
	}

	/**
	 * Add ORDER BY clauses.
	 *
	 * Accepts either an array of column names (defaulting to ASC), or an associative array of column => direction pairs.
	 *
	 * Example:
	 * ```php
	 * // Simple columns (default to ASC)
	 * $qb->orderBy(['name', 'created_at']);
	 *
	 * // Columns with directions
	 * $qb->orderBy([
	 *    'name' => 'ASC',
	 *   'created_at' => 'DESC',
	 * ]);
	 *
	 * // Mixed example
	 * $qb->orderBy([
	 *   'name', // defaults to ASC
	 *  'created_at' => 'DESC',
	 * ]);
	 *
	 * @param array $order_by
	 *
	 * @return static
	 */
	public function orderBy(array $order_by): static
	{
		foreach ($order_by as $key => $value) {
			if (\is_int($key)) {
				$order = $value;
			} else {
				if (\is_string($value)) {
					$dir = \strtoupper($value);
					if ('ASC' !== $dir && 'DESC' !== $dir) {
						throw new InvalidArgumentException(\sprintf(
							'Invalid ORDER BY direction "%s" for column "%s". Allowed values are "ASC" or "DESC".',
							$value,
							$key
						));
					}
				} else {
					// If value is not a string, treat truthy as ASC and falsy as DESC
					$dir = $value ? 'ASC' : 'DESC';
				}

				$order = $key . ' ' . $dir;
			}

			$this->options_order_by[] = $order;
		}

		return $this;
	}
}
