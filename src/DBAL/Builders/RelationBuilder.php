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

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Relations\LinkThrough;
use Gobl\DBAL\Relations\LinkType;
use Gobl\DBAL\Relations\ManyToMany;
use Gobl\DBAL\Relations\ManyToOne;
use Gobl\DBAL\Relations\OneToMany;
use Gobl\DBAL\Relations\OneToOne;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\RelationType;
use Gobl\DBAL\Table;
use PHPUtils\Str;

/**
 * Class RelationBuilder.
 */
final class RelationBuilder
{
	private ?Table $target_table = null;
	private ?Relation $relation  = null;

	/**
	 * RelationBuilder constructor.
	 *
	 * @param RelationType   $type
	 * @param string         $name
	 * @param Table          $host_table
	 * @param RDBMSInterface $rdbms
	 */
	public function __construct(
		private readonly RelationType $type,
		private readonly string $name,
		private readonly Table $host_table,
		private readonly RDBMSInterface $rdbms,
	) {}

	/**
	 * Gets the relation.
	 *
	 * @return Relation
	 *
	 * @throws DBALException
	 */
	public function getRelation(): Relation
	{
		return $this->relation ?? $this->usingColumns();
	}

	/**
	 * Creates a `columns`-type relation link between the host and target tables.
	 *
	 * When `$host_to_target_columns_map` is empty, the link auto-detects the column
	 * mapping by inspecting the host table's FK constraints that reference the target table.
	 * Pass an explicit map when the auto-detection is ambiguous or incorrect.
	 *
	 * @param array<string, string> $host_to_target_columns_map host column name -> target column name;
	 *                                                          empty string triggers auto-detection
	 * @param array                 $filters                    optional extra filter conditions on the relation
	 *
	 * @return Relation
	 *
	 * @throws DBALException
	 */
	public function usingColumns(array $host_to_target_columns_map = [], array $filters = []): Relation
	{
		return $this->using([
			'type'    => LinkType::COLUMNS->value,
			'columns' => $host_to_target_columns_map,
			'filters' => $filters,
		]);
	}

	/**
	 * Specify a relation link of type "join".
	 *
	 * @param array $link_options
	 *
	 * @return Relation
	 *
	 * @throws DBALException
	 */
	public function usingJoin(array $link_options = []): Relation
	{
		$link_options['type'] = LinkType::JOIN->value;

		return $this->using($link_options);
	}

	/**
	 * Creates a relation using the provided `$link_options` array or a {@see LinkBuilder} instance.
	 *
	 * This is the core link-dispatch method. It instantiates the appropriate
	 * `LinkInterface` implementation based on `$link_options['type']`, then wraps
	 * it in the appropriate `Relation` subclass (`OneToOne`, `OneToMany`, etc.).
	 *
	 * Throws `DBALRuntimeException` if {@see from()} has not been called first
	 * (no target table set).
	 *
	 * @param array|LinkBuilder $link_options link definition array or a fluent `LinkBuilder` instance
	 *
	 * @return Relation
	 *
	 * @throws DBALRuntimeException when no target table is set
	 * @throws DBALException
	 */
	public function using(array|LinkBuilder $link_options): Relation
	{
		if ($link_options instanceof LinkBuilder) {
			$link_options = $link_options->toArray();
		}

		if (!$this->target_table) {
			throw new DBALRuntimeException(\sprintf(
				'Target table is not set you must call the "%s" method first.',
				Str::callableName([$this, 'from'])
			));
		}

		$link = Relation::createLink($this->rdbms, $this->host_table, $this->target_table, $link_options);

		switch ($this->type) {
			case RelationType::ONE_TO_ONE:
				$this->relation = new OneToOne($this->name, $link);

				break;

			case RelationType::ONE_TO_MANY:
				$this->relation = new OneToMany($this->name, $link);

				break;

			case RelationType::MANY_TO_ONE:
				$this->relation = new ManyToOne($this->name, $link);

				break;

			case RelationType::MANY_TO_MANY:
				if (!$link instanceof LinkThrough) {
					throw new DBALRuntimeException(\sprintf(
						'Many to many relation must be linked through a pivot table using the "%s" method.',
						Str::callableName([$this, 'through'])
					));
				}

				$this->relation = new ManyToMany($this->name, $link);

				break;
		}

		return $this->relation;
	}

	/**
	 * Sets the **target** table for this relation (the table being joined to).
	 *
	 * Despite the name `from()`, this sets the **target** table (right-hand side of the
	 * relation), not the host. The host table was provided to the builder's constructor.
	 * Must be called before {@see using()}, {@see usingColumns()}, etc.
	 *
	 * @param string|Table $table target table name or `Table` instance
	 *
	 * @return $this
	 */
	public function from(string|Table $table): static
	{
		if ($table instanceof Table) {
			$this->target_table = $table;
		} else {
			$this->target_table = $this->rdbms->getTableOrFail($table);
		}

		return $this;
	}

	/**
	 * Specify a relation link of type "morph".
	 *
	 * @throws DBALException
	 */
	public function usingMorph(string $prefix, ?string $parent_type = null, array $filters = []): Relation
	{
		$options = [
			'type'    => LinkType::MORPH->value,
			'prefix'  => $prefix,
			'filters' => $filters,
		];

		if ($parent_type) {
			$options['parent_type'] = $parent_type;
		}

		return $this->using($options);
	}

	/**
	 * Specify a relation link of type "through".
	 *
	 * Creates a `THROUGH`-type relation that traverses a **pivot** (junction) table.
	 * The relation is defined by two sub-links:
	 * - `$host_to_pivot`   from the host table to the pivot table.
	 * - `$pivot_to_target` from the pivot table to the ultimate target table.
	 *
	 * Each sub-link can be:
	 * - `null`        auto-detected via FK constraints (same as passing `[]`).
	 * - `array`       raw link-option array (backward-compatible).
	 * - `LinkBuilder` type-safe fluent builder (recommended for new code).
	 *
	 * ### Example
	 *
	 * ```php
	 * // Raw-array style (still works)
	 * $builder->through('sessions_teachers', [], [
	 *     'type'    => 'columns',
	 *     'columns' => ['session_id' => 'for_id'],
	 *     'filters' => [['for_type', 'eq', 'session']],
	 * ]);
	 *
	 * // Recommended LinkBuilder style
	 * $builder->through(
	 *     'sessions_teachers',
	 *     null,
	 *     LinkBuilder::columns(['session_id' => 'for_id'])->filter('for_type', 'eq', 'session'),
	 * );
	 * ```
	 *
	 * @param string|Table           $pivot_table
	 * @param null|array|LinkBuilder $host_to_pivot   sub-link: host to pivot (null = auto)
	 * @param null|array|LinkBuilder $pivot_to_target sub-link: pivot to target (null = auto)
	 * @param array                  $filters         optional extra filters on the through relation
	 *
	 * @return Relation
	 *
	 * @throws DBALException
	 */
	public function through(
		string|Table $pivot_table,
		array|LinkBuilder|null $host_to_pivot = null,
		array|LinkBuilder|null $pivot_to_target = null,
		array $filters = [],
	): Relation {
		if (\is_string($pivot_table)) {
			$pivot_table = $this->rdbms->getTableOrFail($pivot_table);
		}

		$options = [
			'type'            => LinkType::THROUGH->value,
			'pivot_table'     => $pivot_table,
			'host_to_pivot'   => $host_to_pivot instanceof LinkBuilder ? $host_to_pivot->toArray() : ($host_to_pivot ?? []),
			'pivot_to_target' => $pivot_to_target instanceof LinkBuilder ? $pivot_to_target->toArray() : ($pivot_to_target ?? []),
			'filters'         => $filters,
		];

		return $this->using($options);
	}
}
