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

namespace Gobl\DBAL\Relations\Traits;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Relations\RelationType;
use Gobl\DBAL\Table;

/**
 * Trait SimpleRelationTrait.
 */
trait SimpleRelationTrait
{
	protected bool $target_is_slave;

	protected bool $use_auto_detected_foreign_key = false;

	/**
	 * Gets the relation master table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getMasterTable(): Table
	{
		return $this->target_is_slave ? $this->host_table : $this->target_table;
	}

	/**
	 * Gets the relation slave table.
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function getSlaveTable(): Table
	{
		return $this->target_is_slave ? $this->target_table : $this->host_table;
	}

	/**
	 * Gets the relation type.
	 *
	 * @return RelationType
	 */
	public function getType(): RelationType
	{
		return $this->type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	protected function checkRelationColumns(
		?array $columns = null
	): void {
		if (RelationType::MANY_TO_MANY !== $this->type) {
			// the relation is based on foreign keys
			if (empty($columns)) {
				$this->use_auto_detected_foreign_key = true;

				// the slave table contains foreign key from the master table
				if ($this->target_table->hasDefaultForeignKeyConstraint($this->host_table)) {
					$this->target_is_slave = true;
					$columns               = \array_flip($this->target_table->getDefaultForeignKeyConstraintFrom($this->host_table)
						->getColumnsMapping());
				} elseif ($this->host_table->hasDefaultForeignKeyConstraint($this->target_table)) {
					$this->target_is_slave = false;
					$columns               = $this->host_table->getDefaultForeignKeyConstraintFrom($this->target_table)
						->getColumnsMapping();
				} else {
					throw new DBALException(\sprintf(
						'There is no columns to link the table "%s" to the table "%s".',
						$this->host_table->getName(),
						$this->target_table->getName()
					));
				}
			} else {
				$cols = [];

				foreach ($columns as $from_column => $target_column) {
					$f_col = $this->host_table->getColumnOrFail($from_column);
					$t_col = $this->target_table->getColumnOrFail($target_column);

					$cols[$f_col->getFullName()] = $t_col->getFullName();
				}

				$this->target_is_slave = true;
				$columns               = $cols;
			}

			$this->relation_columns = $columns;
		}
	}
}
