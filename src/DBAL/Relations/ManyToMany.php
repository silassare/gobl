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

namespace Gobl\DBAL\Relations;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Relations\Traits\FilterableRelationTrait;
use Gobl\DBAL\Table;

/**
 * Class ManyToMany.
 */
final class ManyToMany extends Relation
{
	use FilterableRelationTrait;

	protected array $target_fk_columns = [];
	protected array $host_fk_columns   = [];

	/**
	 * ManyToMany constructor.
	 *
	 * @param string           $name
	 * @param \Gobl\DBAL\Table $host_table
	 * @param \Gobl\DBAL\Table $target_table
	 * @param \Gobl\DBAL\Table $junction_table
	 * @param null|array       $columns
	 * @param null|array       $filters
	 */
	public function __construct(
		string $name,
		Table $host_table,
		Table $target_table,
		protected Table $junction_table,
		?array $columns = null,
		?array $filters = null
	) {
		parent::__construct(RelationType::MANY_TO_MANY, $name, $host_table, $target_table, $columns);

		$this->filters = $filters;
	}

	/**
	 * Returns junction table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getJunctionTable(): Table
	{
		return $this->junction_table;
	}

	/**
	 * Returns foreign columns from the relationship host table that are in the junction table.
	 *
	 * @return array
	 */
	public function getHostFkColumns(): array
	{
		return $this->host_fk_columns;
	}

	/**
	 * Returns foreign columns from the relationship target table that are in the junction table.
	 *
	 * @return array
	 */
	public function getTargetFkColumns(): array
	{
		return $this->target_fk_columns;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$options['target']         = $this->target_table->getName();
		$options['type']           = $this->type->value;
		$options['filters']        = $this->filters;
		$options['junction_table'] = $this->junction_table->getName();

		if (!$this->use_auto_detected_foreign_key) {
			$options['columns'] = $this->target_fk_columns + $this->host_fk_columns;
		}

		return $options;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function checkRelationColumns(?array $columns = null): void
	{
		$this->host_fk_columns   = [];
		$this->target_fk_columns = [];

		if (empty($columns)) {
			if ($this->junction_table->hasDefaultForeignKeyConstraint($this->target_table)) {
				$this->target_fk_columns = $this->junction_table->getDefaultForeignKeyConstraintFrom($this->target_table)
					->getColumnsMapping();
			}
			if ($this->junction_table->hasDefaultForeignKeyConstraint($this->host_table)) {
				$this->host_fk_columns = $this->junction_table->getDefaultForeignKeyConstraintFrom($this->host_table)
					->getColumnsMapping();
			}

			$this->use_auto_detected_foreign_key = true;
		} else {
			foreach ($columns as $junction_column => $column) {
				if (!$this->junction_table->hasColumn($junction_column)) {
					throw new DBALException(\sprintf(
						'The column "%s" was not found in junction table "%s".',
						$column,
						$this->junction_table->getName()
					));
				}

				if ($this->host_table->hasColumn($column)) {
					$this->host_fk_columns[$junction_column] = $column;
				} elseif ($this->target_table->hasColumn($column)) {
					$this->target_fk_columns[$junction_column] = $column;
				} else {
					throw new DBALException(\sprintf(
						'The column "%s.%s" has a target column "%s" not defined in table "%s" nor in table "%s".',
						$this->junction_table->getName(),
						$junction_column,
						$column,
						$this->target_table->getName(),
						$this->host_table->getName()
					));
				}
			}
		}

		if (empty($this->target_fk_columns)) {
			throw new DBALException(\sprintf(
				'The junction table "%s" has no columns from "%s" to make a "%s" relationship with the table "%s".',
				$this->junction_table->getName(),
				$this->target_table->getName(),
				RelationType::MANY_TO_MANY->value,
				$this->host_table->getName()
			));
		}

		if (empty($this->host_fk_columns)) {
			throw new DBALException(\sprintf(
				'The junction table "%s" has no columns from "%s" to make a "%s" relationship with the table "%s".',
				$this->junction_table->getName(),
				$this->host_table->getName(),
				RelationType::MANY_TO_MANY->value,
				$this->target_table->getName()
			));
		}
	}
}
