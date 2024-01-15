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

namespace Gobl\DBAL\Constraints;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;

/**
 * Class ForeignKey.
 */
final class ForeignKey extends Constraint
{
	/** @var Table */
	private Table $reference_table;

	private ForeignKeyAction $update_action = ForeignKeyAction::NO_ACTION;

	private ForeignKeyAction $delete_action = ForeignKeyAction::NO_ACTION;

	/**
	 * @var array<string , string>
	 */
	private array $columns_map = [];

	/**
	 * ForeignKey constructor.
	 *
	 * @param string $name            the constraint name
	 * @param Table  $host_table      the table in which the constraint was defined
	 * @param Table  $reference_table the reference table
	 */
	public function __construct(string $name, Table $host_table, Table $reference_table)
	{
		parent::__construct($name, $host_table, Constraint::FOREIGN_KEY);

		$this->reference_table = $reference_table;
	}

	/**
	 * Adds column to the constraint.
	 *
	 * @param string $host_column_name      the column name in the host table
	 * @param string $reference_column_name the column name in the reference table
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function addColumn(string $host_column_name, string $reference_column_name): self
	{
		$this->assertNotLocked();

		$host_column      = $this->host_table->getColumnOrFail($host_column_name);
		$reference_column = $this->reference_table->getColumnOrFail($reference_column_name);

		$this->columns_map[$host_column->getFullName()] = $reference_column->getFullName();

		return $this;
	}

	/**
	 * Gets the foreign keys reference table.
	 *
	 * @return Table
	 */
	public function getReferenceTable(): Table
	{
		return $this->reference_table;
	}

	/**
	 * Gets on update action.
	 *
	 * @return ForeignKeyAction
	 */
	public function getUpdateAction(): ForeignKeyAction
	{
		return $this->update_action;
	}

	/**
	 * Gets on delete action.
	 *
	 * @return ForeignKeyAction
	 */
	public function getDeleteAction(): ForeignKeyAction
	{
		return $this->delete_action;
	}

	/**
	 * Sets on update action to cascade.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onUpdateCascade(): self
	{
		return $this->onUpdate(ForeignKeyAction::CASCADE);
	}

	/**
	 * Sets on update action.
	 *
	 * @param ForeignKeyAction $action
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onUpdate(ForeignKeyAction $action): self
	{
		$this->assertNotLocked();

		$this->update_action = $action;

		return $this;
	}

	/**
	 * Sets on update action to set null.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onUpdateSetNull(): self
	{
		return $this->onUpdate(ForeignKeyAction::SET_NULL);
	}

	/**
	 * Sets on update action to set default.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onUpdateSetDefault(): self
	{
		return $this->onUpdate(ForeignKeyAction::SET_DEFAULT);
	}

	/**
	 * Sets on update action to restrict.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onUpdateRestrict(): self
	{
		return $this->onUpdate(ForeignKeyAction::RESTRICT);
	}

	/**
	 * Sets on update action to no action.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onUpdateNoAction(): self
	{
		return $this->onUpdate(ForeignKeyAction::NO_ACTION);
	}

	/**
	 * Sets on delete action to cascade.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onDeleteCascade(): self
	{
		return $this->onDelete(ForeignKeyAction::CASCADE);
	}

	/**
	 * Sets on delete action.
	 *
	 * @param ForeignKeyAction $action
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onDelete(ForeignKeyAction $action): self
	{
		$this->assertNotLocked();

		$this->delete_action = $action;

		return $this;
	}

	/**
	 * Sets on delete action to set null.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onDeleteSetNull(): self
	{
		return $this->onDelete(ForeignKeyAction::SET_NULL);
	}

	/**
	 * Sets on delete action to set default.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onDeleteSetDefault(): self
	{
		return $this->onDelete(ForeignKeyAction::SET_DEFAULT);
	}

	/**
	 * Sets on delete action to restrict.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onDeleteRestrict(): self
	{
		return $this->onDelete(ForeignKeyAction::RESTRICT);
	}

	/**
	 * Sets on delete action to no action.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function onDeleteNoAction(): self
	{
		return $this->onDelete(ForeignKeyAction::NO_ACTION);
	}

	/**
	 * Gets host columns.
	 *
	 * @return string[]
	 */
	public function getHostColumns(): array
	{
		return \array_keys($this->columns_map);
	}

	/**
	 * Gets foreign key columns mapping.
	 *
	 * @return array<string, string>
	 */
	public function getColumnsMapping(): array
	{
		return $this->columns_map;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function assertIsValid(): void
	{
		// necessary when whe have
		// table_a.col_1 => table_b.col_x
		// table_a.col_2 => table_b.col_x
		$columns = \array_unique($this->getReferenceColumns());

		if (!$this->reference_table->isPrimaryKey($columns)) {
			throw new DBALException(
				\sprintf(
					'Foreign key "(%s)" in table "%s" should be primary key in the reference table "%s".',
					\implode(', ', $columns),
					$this->host_table->getName(),
					$this->reference_table->getName(),
				)
			);
		}

		foreach ($this->columns_map as $full_name => $target_full_name) {
			$col = $this->host_table->getColumnOrFail($full_name);

			if (ForeignKeyAction::SET_NULL === $this->delete_action
				&& !$col->getType()
					->isNullable()
			) {
				throw new DBALException(
					\sprintf(
						'The foreign column "%s" found in "%s" should be nullable to be able to "%s" on delete, as defined in the foreign key constraint.',
						$full_name,
						$this->host_table->getFullName(),
						ForeignKeyAction::SET_NULL->value
					)
				);
			}
			if (ForeignKeyAction::SET_NULL === $this->update_action
				&& !$col->getType()
					->isNullable()
			) {
				throw new DBALException(
					\sprintf(
						'The foreign column "%s" found in "%s" should be nullable to be able to "%s" on update, as defined in the foreign key constraint.',
						$full_name,
						$this->host_table->getFullName(),
						ForeignKeyAction::SET_NULL->value
					)
				);
			}
		}
	}

	/**
	 * Gets reference columns.
	 *
	 * @return string[]
	 */
	public function getReferenceColumns(): array
	{
		return \array_values($this->columns_map);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$columns = [];

		foreach ($this->columns_map as $full_name => $target_full_name) {
			$key = $this->host_table->getColumnOrFail($full_name)
				->getName();
			$columns[$key] = $this->reference_table->getColumnOrFail($target_full_name)
				->getName();
		}

		$options = [
			'type'      => 'foreign_key',
			'reference' => $this->reference_table->getName(),
			'columns'   => $columns,
			'update'    => ForeignKeyAction::NO_ACTION->value,
			'delete'    => ForeignKeyAction::NO_ACTION->value,
		];

		if ($this->host_table->defaultForeignKeyName($this->reference_table) !== $this->name) {
			$options['name'] = $this->name;
		}

		if (ForeignKeyAction::NO_ACTION !== $this->delete_action) {
			$options['delete'] = $this->delete_action->value;
		}

		if (ForeignKeyAction::NO_ACTION !== $this->update_action) {
			$options['update'] = $this->update_action->value;
		}

		return $options;
	}
}
