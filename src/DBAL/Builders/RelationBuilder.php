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
	 * @param array<string, string> $host_to_target_columns_map host column name → target column name;
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
	 * Creates a relation using the provided `$link_options` array.
	 *
	 * This is the core link-dispatch method. It instantiates the appropriate
	 * `LinkInterface` implementation based on `$link_options['type']`, then wraps
	 * it in the appropriate `Relation` subclass (`OneToOne`, `OneToMany`, etc.).
	 *
	 * Throws `DBALRuntimeException` if {@see from()} has not been called first
	 * (no target table set).
	 *
	 * @param array $link_options link definition array (must include `type`)
	 *
	 * @return Relation
	 *
	 * @throws DBALRuntimeException when no target table is set
	 * @throws DBALException
	 */
	public function using(array $link_options): Relation
	{
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
	public function from(string|Table $table): self
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
	 * @param string|Table         $pivot_table
	 * @param array<string, mixed> $host_to_pivot_link_options
	 * @param array<string, mixed> $pivot_to_target_link_options
	 * @param array                $filters
	 *
	 * @return Relation
	 *
	 * @throws DBALException
	 */
	public function through(
		string|Table $pivot_table,
		array $host_to_pivot_link_options = [],
		array $pivot_to_target_link_options = [],
		array $filters = [],
	): Relation {
		if (\is_string($pivot_table)) {
			$pivot_table = $this->rdbms->getTableOrFail($pivot_table);
		}

		$options = [
			'type'            => LinkType::THROUGH->value,
			'pivot_table'     => $pivot_table,
			'host_to_pivot'   => $host_to_pivot_link_options,
			'pivot_to_target' => $pivot_to_target_link_options,
			'filters'         => $filters,
		];

		return $this->using($options);
	}
}
