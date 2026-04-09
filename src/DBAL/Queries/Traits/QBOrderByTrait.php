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
	 * Accepts an array of columns with optional direction.
	 * Direction can be specified as "ASC"/"DESC" string or as a boolean (true for ASC, false for DESC).
	 * When direction is not specified (key is an integer), the column is treated as a raw ORDER BY clause (e.g. "name ASC").
	 *
	 * Example:
	 * ```php
	 * $qb->orderBy([
	 *    'client_last_name ASC',  // raw syntax
	 *    'client_id DESC', // raw syntax
	 *    'client_first_name' => 'DESC',  // column => direction syntax
	 *    'client_email' => false, // falsy value treated as DESC
	 *    'client_created_at' => true, // truthy value treated as ASC
	 * ]);
	 * ```
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
