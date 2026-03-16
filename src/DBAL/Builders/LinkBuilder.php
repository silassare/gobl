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

use Gobl\DBAL\Relations\LinkType;
use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class LinkBuilder.
 *
 * Base class and entry point for the fluent, type-safe link-option builders.
 *
 * Use the static factory methods to start a specific builder path.  Each path
 * returns a dedicated sub-builder so that the IDE only offers methods that are
 * relevant to that link type:
 *
 * | Factory                              | Returns                 | Extra fluent methods              |
 * |--------------------------------------|-------------------------|-----------------------------------|
 * | `LinkBuilder::columns()`             | `LinkColumnsBuilder`    | `->map(array)`                    |
 * | `LinkBuilder::morph(string $prefix)` | `LinkMorphBuilder`      | `->parentType()`, `->parentKey()` |
 * | `LinkBuilder::morphExplicit(...)`      | `LinkMorphBuilder`      | `->parentType()`, `->parentKey()` |
 *
 * All sub-builders share the common `->filter()`, `->filters()`, and `->toArray()` methods
 * defined here.  Chaining is immutable - every call returns a new clone.
 *
 * ### Usage examples
 *
 * ```php
 * // Columns link - auto-detect FK
 * $link = LinkBuilder::columns();
 *
 * // Columns link - explicit map (two equivalent forms)
 * $link = LinkBuilder::columns(['session_id' => 'for_id']);
 * $link = LinkBuilder::columns()->map(['session_id' => 'for_id']);
 *
 * // Columns link with an extra filter
 * $link = LinkBuilder::columns(['session_id' => 'for_id'])
 *     ->filter('for_type', 'eq', 'session');
 *
 * // Morph link by prefix
 * $link = LinkBuilder::morph('taggable')
 *     ->parentType('article')   // optional
 *     ->parentKey('art_id');    // optional
 *
 * // Morph link with explicit columns
 * $link = LinkBuilder::morphExplicit('taggable_id', 'taggable_type')
 *     ->parentType('post');
 *
 * // Use in RelationBuilder
 * $builder->through('pivot_table', null, $link);
 * ```
 *
 * @see LinkColumnsBuilder
 * @see LinkMorphBuilder
 * @see RelationBuilder::through()
 * @see RelationBuilder::usingJoin()
 */
class LinkBuilder implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * LinkBuilder constructor.
	 *
	 * @param array $options raw link-option array being constructed
	 */
	protected function __construct(protected array $options) {}

	// -------------------------------------------------------------------------
	// Factory methods - each returns the dedicated sub-builder for that path
	// -------------------------------------------------------------------------

	/**
	 * Creates a `columns`-type link builder.
	 *
	 * When `$host_to_target_map` is empty the link will auto-detect the column mapping
	 * from the host table's FK constraints at query-build time.
	 * Pass an explicit map here or call {@see LinkColumnsBuilder::map()} on the returned
	 * builder when the auto-detection would be ambiguous.
	 *
	 * @param array<string, string> $host_to_target_map host column -> target column;
	 *                                                  omit to use auto-detection
	 *
	 * @return LinkColumnsBuilder
	 */
	public static function columns(array $host_to_target_map = []): LinkColumnsBuilder
	{
		$options = ['type' => LinkType::COLUMNS->value];

		if (!empty($host_to_target_map)) {
			$options['columns'] = $host_to_target_map;
		}

		return new LinkColumnsBuilder($options);
	}

	/**
	 * Creates a `morph`-type link builder using a column-name **prefix**.
	 *
	 * The prefix is expanded to:
	 * - child-key column:  `{prefix}_id`
	 * - child-type column: `{prefix}_type`
	 *
	 * Chain {@see LinkMorphBuilder::parentType()} and/or {@see LinkMorphBuilder::parentKey()}
	 * to set the optional parent constraints.
	 *
	 * @param string $prefix prefix for the child-key and child-type columns
	 *
	 * @return LinkMorphBuilder
	 */
	public static function morph(string $prefix): LinkMorphBuilder
	{
		return new LinkMorphBuilder([
			'type'   => LinkType::MORPH->value,
			'prefix' => $prefix,
		]);
	}

	/**
	 * Creates a `morph`-type link builder with **explicit** child-key and child-type column names.
	 *
	 * Use this variant when the morph columns do not follow the `{prefix}_id` /
	 * `{prefix}_type` naming convention.
	 *
	 * Chain {@see LinkMorphBuilder::parentType()} and/or {@see LinkMorphBuilder::parentKey()}
	 * to set the optional parent constraints.
	 *
	 * @param string $child_key_column  column on the child table that holds the parent PK value
	 * @param string $child_type_column column on the child table that holds the parent type string
	 *
	 * @return LinkMorphBuilder
	 */
	public static function morphExplicit(
		string $child_key_column,
		string $child_type_column,
	): LinkMorphBuilder {
		return new LinkMorphBuilder([
			'type'              => LinkType::MORPH->value,
			'child_key_column'  => $child_key_column,
			'child_type_column' => $child_type_column,
		]);
	}

	// -------------------------------------------------------------------------
	// Fluent filter methods (shared by all sub-builders)
	// -------------------------------------------------------------------------

	/**
	 * Appends a single filter triple to the link's `filters` option (AND-combined).
	 *
	 * Each call adds an `'and'` separator before the new triple when filters already
	 * exist, so multiple calls are logically ANDed together.
	 *
	 * ```php
	 * LinkBuilder::columns(['session_id' => 'for_id'])
	 *     ->filter('for_type', 'eq', 'session')
	 *     ->filter('is_active', 'eq', 1);
	 * // produces: [['for_type','eq','session'], 'and', ['is_active','eq',1]]
	 * ```
	 *
	 * @param string $column   left operand (column name or FQN)
	 * @param string $operator filter operator (e.g. `'eq'`, `'neq'`, `'lt'`, `'gt'`)
	 * @param mixed  $value    right operand value
	 *
	 * @return static
	 */
	public function filter(string $column, string $operator, mixed $value): static
	{
		if (!isset($this->options['filters'])) {
			$this->options['filters'] = [];
		} elseif (!empty($this->options['filters'])) {
			$this->options['filters'][] = 'and';
		}

		$this->options['filters'][] = [$column, $operator, $value];

		return $this;
	}

	/**
	 * Replaces the link's entire `filters` array with the given raw filters array.
	 *
	 * Use this when you already have a pre-built filters array and want to set it
	 * directly instead of building it up with individual {@see filter()} calls.
	 *
	 * @param array $filters raw filters array (same format accepted by `Filters::fromArray()`)
	 *
	 * @return static
	 */
	public function filters(array $filters): static
	{
		$this->options['filters'] = $filters;

		return $this;
	}

	// -------------------------------------------------------------------------
	// Export
	// -------------------------------------------------------------------------

	/**
	 * Returns the raw link-option array built by this builder.
	 *
	 * Pass the result directly to `Relation::createLink()`, or use via
	 * {@see RelationBuilder::through()} / {@see RelationBuilder::usingJoin()}.
	 *
	 * @return array
	 */
	#[Override]
	public function toArray(): array
	{
		return $this->options;
	}
}
