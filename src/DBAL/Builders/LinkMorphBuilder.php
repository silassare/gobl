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
 * Class LinkMorphBuilder.
 *
 * Builder for `morph`-type relation links.
 *
 * Returned by {@see LinkBuilder::morph()} and {@see LinkBuilder::morphExplicit()}.
 * In addition to the common {@see LinkBuilder::filter()} / {@see LinkBuilder::filters()} /
 * {@see LinkBuilder::toArray()} methods it exposes:
 *
 * - {@see parentType()} - explicit parent-type string (defaults to parent table full name)
 * - {@see parentKey()}  - explicit parent PK column (defaults to auto-detected)
 *
 * ### Usage
 *
 * ```php
 * // Morph by prefix
 * $link = LinkBuilder::morph('taggable')
 *     ->parentType('article')   // optional
 *     ->parentKey('art_id');    // optional
 *
 * // Morph with explicit column names
 * $link = LinkBuilder::morphExplicit('taggable_id', 'taggable_type')
 *     ->parentType('post')
 *     ->filter('is_published', 'eq', 1);
 * ```
 */
final class LinkMorphBuilder extends LinkBuilder
{
	/**
	 * Sets the explicit parent-type string.
	 *
	 * When omitted, the engine defaults to the parent table's full name.
	 *
	 * @param string $type parent-type discriminator value stored in the child-type column
	 *
	 * @return static
	 */
	public function parentType(string $type): static
	{
		$this->options['parent_type'] = $type;

		return $this;
	}

	/**
	 * Sets the explicit parent primary-key column name.
	 *
	 * When omitted, the engine auto-detects the parent table's PK column.
	 *
	 * @param string $column parent PK column name
	 *
	 * @return static
	 */
	public function parentKey(string $column): static
	{
		$this->options['parent_key_column'] = $column;

		return $this;
	}
}
