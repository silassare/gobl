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

namespace Gobl\DBAL\Builders;

/**
 * Class LinkColumnsBuilder.
 *
 * Builder for `columns`-type relation links.
 *
 * Returned by {@see LinkBuilder::columns()}.  In addition to the common
 * {@see LinkBuilder::filter()} / {@see LinkBuilder::filters()} / {@see LinkBuilder::toArray()}
 * methods it exposes {@see map()} to set or replace the explicit host->target column map.
 *
 * ### Usage
 *
 * ```php
 * // Auto-detect FK mapping
 * $link = LinkBuilder::columns();
 *
 * // Explicit map - pass to factory or chain ->map()
 * $link = LinkBuilder::columns(['session_id' => 'for_id']);
 * $link = LinkBuilder::columns()->map(['session_id' => 'for_id']);
 *
 * // Map + filter
 * $link = LinkBuilder::columns(['session_id' => 'for_id'])
 *     ->filter('for_type', 'eq', 'session');
 * ```
 */
final class LinkColumnsBuilder extends LinkBuilder
{
	/**
	 * Sets (or replaces) the explicit host->target column map.
	 *
	 * Pass an empty array to revert to FK auto-detection.
	 *
	 * @param array<string, string> $host_to_target_map host column -> target column
	 *
	 * @return static
	 */
	public function map(array $host_to_target_map): static
	{
		if (empty($host_to_target_map)) {
			unset($this->options['columns']);
		} else {
			$this->options['columns'] = $host_to_target_map;
		}

		return $this;
	}
}
