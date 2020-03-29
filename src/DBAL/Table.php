<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL;

use Gobl\DBAL\Collections\Collection;
use Gobl\DBAL\Constraints\ForeignKey;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Constraints\Unique;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Relations\CallableVR;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use InvalidArgumentException;

/**
 * Class Table
 */
class Table
{
	const NAME_REG   = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';

	const PREFIX_REG = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';

	/**
	 * The table name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The table namespace.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * The table prefix.
	 *
	 * @var null|string
	 */
	protected $prefix;

	/**
	 * The table options
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * The table columns.
	 *
	 * @var \Gobl\DBAL\Column[]
	 */
	protected $columns = [];

	/**
	 * @var array
	 */
	protected $col_full_name_map = [];

	/**
	 * Foreign keys constraints.
	 *
	 * @var \Gobl\DBAL\Constraints\ForeignKey[]
	 */
	protected $fk_constraints = [];

	/**
	 * Primary key constraint.
	 *
	 * @var \Gobl\DBAL\Constraints\PrimaryKey
	 */
	protected $pk_constraint;

	/**
	 * Unique constraints.
	 *
	 * @var \Gobl\DBAL\Constraints\Unique[]
	 */
	protected $uc_constraints = [];

	/**
	 * Table relations list
	 *
	 * @var \Gobl\DBAL\Relations\Relation[]
	 */
	protected $relations = [];

	/**
	 * Table virtual relations list
	 *
	 * @var \Gobl\DBAL\Relations\VirtualRelation[]
	 */
	protected $virtual_relations = [];

	/**
	 * The collections list
	 *
	 * @var \Gobl\DBAL\Collections\Collection[]
	 */
	protected $collections;

	/**
	 * Table constructor.
	 *
	 * Plural and singular class name are used to generate
	 * table custom class and row class.
	 * Their are used to be concise and ide friendly.
	 * Example:
	 *    table name     ------> users
	 *    plural class   ------> Users
	 *    singular class ------> User
	 *
	 * @param string $name      the table name
	 * @param string $namespace the table namespace
	 * @param string $prefix    the table prefix
	 * @param array  $options   the table raw options
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function __construct($name, $namespace, $prefix, array $options)
	{
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf('Invalid table name "%s".', $name));
		}

		if (!\is_string($namespace) || empty($namespace)) {
			throw new InvalidArgumentException(\sprintf('You should provide namespace for table "%s".', $name));
		}

		if (!empty($prefix)) {
			if (!\preg_match(self::PREFIX_REG, $prefix)) {
				throw new InvalidArgumentException(\sprintf('Invalid table prefix name "%s".', $prefix));
			}
		} else {
			$prefix = '';
		}

		if (!isset($options['plural_name']) || !isset($options['singular_name'])) {
			throw new DBALException(\sprintf(
				'You should define "plural_name" and "singular_name" for table "%s".',
				$name
			));
		}

		$plural_name   = $options['plural_name'];
		$singular_name = $options['singular_name'];

		// table name rules also apply to plural and singular names
		if (!\preg_match(self::NAME_REG, $plural_name)) {
			throw new InvalidArgumentException(\sprintf('Table "%s" "plural_name" option is invalid.', $name));
		}

		if (!\preg_match(self::NAME_REG, $singular_name)) {
			throw new InvalidArgumentException(\sprintf('Table "%s" "singular_name" option is invalid.', $name));
		}

		if ($plural_name === $singular_name) {
			throw new InvalidArgumentException(\sprintf(
				'"plural_name" and "singular_name" should not be equal in table "%s".',
				$name
			));
		}

		$this->name      = \strtolower($name);
		$this->prefix    = $prefix;
		$this->namespace = $namespace;
		$this->options   = $options;
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return ['instance_of' => static::class, 'table_name' => $this->getName()];
	}

	/**
	 * Adds a given column to the current table.
	 *
	 * @param \Gobl\DBAL\Column $column the column to add
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function addColumn(Column $column)
	{
		$this->assertCanAddColumn($column);

		$name                                = $column->getName();
		$full_name                           = $column->getFullName();
		$this->columns[$name]                = $column;
		$this->col_full_name_map[$full_name] = $name;

		return $this;
	}

	/**
	 * Adds relation to this table.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function addRelation(Relation $relation)
	{
		$this->assertCanAddRelation($relation);

		$name = $relation->getName();

		$master_name = $relation->getMasterTable()
								->getName();
		$slave_name  = $relation->getSlaveTable()
								->getName();

		if ($master_name !== $this->name && $slave_name !== $this->name) {
			throw new DBALException(\sprintf(
				'Trying to add relation "%s" between ("%s","%s") to table "%s".',
				$relation->getName(),
				$master_name,
				$slave_name,
				$this->name
			));
		}

		$this->relations[$name] = $relation;

		return $this;
	}

	/**
	 * Adds virtual relation to this table.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function addVirtualRelation(VirtualRelation $virtual_relation)
	{
		$this->assertCanAddVirtualRelation($virtual_relation);

		$name                           = $virtual_relation->getName();
		$this->virtual_relations[$name] = $virtual_relation;

		return $this;
	}

	/**
	 * Adds collection to this table.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function addCollection(Collection $collection)
	{
		$this->assertCanAddCollection($collection);

		$name                     = $collection->getName();
		$this->collections[$name] = $collection;

		return $this;
	}

	/**
	 * Adds a unique constraint on columns.
	 *
	 * @param array $columns the columns
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function addUniqueConstraint(array $columns)
	{
		if (\count($columns)) {
			$count           = \count($this->uc_constraints) + 1;
			$constraint_name = \sprintf('uc_%s_%d', $this->getFullName(), $count);
			$uc              = new Unique($constraint_name, $this);

			foreach ($columns as $column_name) {
				$uc->addColumn($column_name);
			}

			$this->uc_constraints[$constraint_name] = $uc;
		}

		return $this;
	}

	/**
	 * Adds a primary key constraint on columns.
	 *
	 * @param array $columns the columns
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function addPrimaryKeyConstraint(array $columns)
	{
		if (\count($columns)) {
			if (!isset($this->pk_constraint)) {
				$constraint_name     = \sprintf('pk_%s', $this->getFullName());
				$this->pk_constraint = new PrimaryKey($constraint_name, $this);
			}

			foreach ($columns as $column_name) {
				$this->pk_constraint->addColumn($column_name);
			}
		}

		return $this;
	}

	/**
	 * Adds a foreign key constraint on columns.
	 *
	 * @param string           $constraint_name the constraint name
	 * @param \Gobl\DBAL\Table $reference_table the reference table
	 * @param array            $columns         the columns
	 * @param int              $update_action   the reference column update action
	 * @param int              $delete_action   the reference column delete action
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return $this
	 */
	public function addForeignKeyConstraint(
		$constraint_name,
		self $reference_table,
		array $columns,
		$update_action,
		$delete_action
	) {
		if (\count($columns)) {
			$is_named_fk = true;

			if (empty($constraint_name)) {
				$constraint_name = \sprintf('fk_%s_%s', $this->getName(), $reference_table->getName());
				$is_named_fk     = false;
			}

			if (isset($this->fk_constraints[$constraint_name])) {
				if ($is_named_fk) {
					throw new DBALException(\sprintf(
						'Foreign key "%s" is already defined between the tables "%s" and "%s".',
						$constraint_name,
						$this->getName(),
						$reference_table->getName()
					));
				}

				// only one default foreign key between two tables is allowed
				// any other foreign key constraint should be named or unique

				$suffix = [];

				foreach ($columns as $left => $right) {
					$suffix[] = $left . '_' . $right;
				}

				\sort($suffix);

				$suffix = \implode('_', $suffix);

				$constraint_name = 'fk_' . \md5($constraint_name . '_' . $suffix);

				if (isset($this->fk_constraints[$constraint_name])) {
					throw new DBALException(\sprintf(
						'There is already a foreign key constraint between the tables "%s" and "%s".',
						$this->getName(),
						$reference_table->getName()
					));
				}
			}

			$fk = $this->fk_constraints[$constraint_name] = new ForeignKey($constraint_name, $this, $reference_table);

			foreach ($columns as $column_name => $reference_column) {
				$fk->addColumn($column_name, $reference_column);
			}

			$fk->setUpdateAction($update_action);
			$fk->setDeleteAction($delete_action);
		}

		return $this;
	}

	/**
	 * Gets table name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Gets the plural name.
	 *
	 * @return string
	 */
	public function getPluralName()
	{
		return $this->options['plural_name'];
	}

	/**
	 * Gets the singular name.
	 *
	 * @return string
	 */
	public function getSingularName()
	{
		return $this->options['singular_name'];
	}

	/**
	 * Gets table prefix.
	 *
	 * @return string
	 */
	public function getPrefix()
	{
		return $this->prefix;
	}

	/**
	 * Gets table namespace.
	 *
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->namespace;
	}

	/**
	 * Gets table full name.
	 *
	 * @return string
	 */
	public function getFullName()
	{
		if (empty($this->prefix)) {
			return $this->name;
		}

		return $this->prefix . '_' . $this->name;
	}

	/**
	 * Gets columns.
	 *
	 * @param bool $include_private if false private column will not be included.
	 *                              Default is true.
	 *
	 * @return \Gobl\DBAL\Column[]
	 */
	public function getColumns($include_private = true)
	{
		if (false === $include_private) {
			$columns = [];

			foreach ($this->columns as $name => $column) {
				if (!$column->isPrivate()) {
					$columns[$name] = $column;
				}
			}

			return $columns;
		}

		return $this->columns;
	}

	/**
	 * Gets columns fullname list.
	 *
	 * @param bool $include_private if false private column will not be included.
	 *                              Default is true.
	 *
	 * @return array
	 */
	public function getColumnsFullNameList($include_private = true)
	{
		$names = [];

		foreach ($this->columns as $column) {
			if ($include_private || !$column->isPrivate()) {
				$names[] = $column->getFullName();
			}
		}

		return $names;
	}

	/**
	 * Gets columns name list.
	 *
	 * @param bool $include_private if false private column will not be included.
	 *                              Default is true.
	 *
	 * @return array
	 */
	public function getColumnsNameList($include_private = true)
	{
		$names = [];

		foreach ($this->columns as $column) {
			if ($include_private || !$column->isPrivate()) {
				$names[] = $column->getName();
			}
		}

		return $names;
	}

	/**
	 * Gets privates columns.
	 *
	 * @return \Gobl\DBAL\Column[]
	 */
	public function getPrivatesColumns()
	{
		$list = [];

		foreach ($this->columns as $name => $column) {
			if ($column->isPrivate()) {
				$list[$name] = $column;
			}
		}

		return $list;
	}

	/**
	 * Checks if a given column is defined.
	 *
	 * @param string $name the column name or full name
	 *
	 * @return bool
	 */
	public function hasColumn($name)
	{
		return isset($this->columns[$name]) || isset($this->col_full_name_map[$name]);
	}

	/**
	 * Checks if a given collection is defined.
	 *
	 * @param string $name the collection name
	 *
	 * @return bool
	 */
	public function hasCollection($name)
	{
		return isset($this->collections[$name]);
	}

	/**
	 * Asserts if a given column name is defined.
	 *
	 * @param string $name the column name or full name
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function assertHasColumn($name)
	{
		if (!$this->hasColumn($name)) {
			throw new DBALException(\sprintf(
				'The column "%s" does not exists in the table "%s".',
				$name,
				$this->getName()
			));
		}
	}

	/**
	 * Gets column with a given name.
	 *
	 * @param string $name the column name or full name
	 *
	 * @return \Gobl\DBAL\Column
	 */
	public function getColumn($name)
	{
		if ($this->hasColumn($name)) {
			if (isset($this->col_full_name_map[$name])) {
				$name = $this->col_full_name_map[$name];
			}

			return $this->columns[$name];
		}

		return null;
	}

	/**
	 * Checks if a given relation is defined.
	 *
	 * @param string $name the relation name
	 *
	 * @return bool
	 */
	public function hasRelation($name)
	{
		return isset($this->relations[$name]);
	}

	/**
	 * Gets a relation by name.
	 *
	 * @param string $name the relation name
	 *
	 * @return null|\Gobl\DBAL\Relations\Relation
	 */
	public function getRelation($name)
	{
		if ($this->hasRelation($name)) {
			return $this->relations[$name];
		}

		return null;
	}

	/**
	 * Gets relations.
	 *
	 * @return \Gobl\DBAL\Relations\Relation[]
	 */
	public function getRelations()
	{
		return $this->relations;
	}

	/**
	 * Checks if a given virtual relation is defined.
	 *
	 * @param string $name the virtual relation name
	 *
	 * @return bool
	 */
	public function hasVirtualRelation($name)
	{
		return isset($this->virtual_relations[$name]);
	}

	/**
	 * Gets a virtual relation by name.
	 *
	 * @param string $name the virtual relation name
	 *
	 * @return null|\Gobl\DBAL\Relations\VirtualRelation
	 */
	public function getVirtualRelation($name)
	{
		if ($this->hasVirtualRelation($name)) {
			return $this->virtual_relations[$name];
		}

		return null;
	}

	/**
	 * Gets virtual relations.
	 *
	 * @return \Gobl\DBAL\Relations\VirtualRelation[]
	 */
	public function getVirtualRelations()
	{
		return $this->virtual_relations;
	}

	/**
	 * Gets a collection by name.
	 *
	 * @param string $name the collection name
	 *
	 * @return null|\Gobl\DBAL\Collections\Collection
	 */
	public function getCollection($name)
	{
		if ($this->hasCollection($name)) {
			return $this->collections[$name];
		}

		return null;
	}

	/**
	 * Gets collections.
	 *
	 * @return \Gobl\DBAL\Collections\Collection[]
	 */
	public function getCollections()
	{
		return $this->collections;
	}

	/**
	 * Gets unique constraints.
	 *
	 * @return \Gobl\DBAL\Constraints\Unique[]
	 */
	public function getUniqueConstraints()
	{
		return $this->uc_constraints;
	}

	/**
	 * Gets primary key constraint.
	 *
	 * @return null|\Gobl\DBAL\Constraints\PrimaryKey
	 */
	public function getPrimaryKeyConstraint()
	{
		return $this->pk_constraint;
	}

	/**
	 * Gets foreign key constraints.
	 *
	 * @return \Gobl\DBAL\Constraints\ForeignKey[]
	 */
	public function getForeignKeyConstraints()
	{
		return $this->fk_constraints;
	}

	/**
	 * Checks if the current table has foreign key that refer
	 * to the given columns from the reference table.
	 *
	 * @param \Gobl\DBAL\Table $reference the reference table
	 * @param array            $columns   the foreign columns
	 *
	 * @return bool
	 */
	public function hasForeignColumns(self $reference, array $columns)
	{
		$x = \count($columns);

		if ($x) {
			foreach ($this->fk_constraints as $fk_name => $fk) {
				if (
					$fk->getReferenceTable()
					   ->getName() === $reference->getName()
				) {
					$fk_columns = \array_flip($fk->getConstraintColumns());

					$y = 0;

					foreach ($columns as $column) {
						if (isset($fk_columns[$column])) {
							$y++;
						}
					}

					return $x === $y;
				}
			}
		}

		return false;
	}

	/**
	 * Gets default foreign key constraint that have the given table as reference table.
	 *
	 * @param \Gobl\DBAL\Table $reference the reference table
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\DBAL\Constraints\ForeignKey
	 */
	public function getDefaultForeignKeyConstraintFrom(self $reference)
	{
		$fk_name = \sprintf('fk_%s_%s', $this->getName(), $reference->getName());

		if (isset($this->fk_constraints[$fk_name])) {
			return $this->fk_constraints[$fk_name];
		}

		throw new DBALException(\sprintf(
			'Foreign key from table "%s" not found in table "%s".',
			$reference->getName(),
			$this->name
		));
	}

	/**
	 * Checks if the table has a default foreign key constraint with column from a given reference table.
	 *
	 * @param \Gobl\DBAL\Table $reference the reference table
	 *
	 * @return bool
	 */
	public function hasDefaultForeignKeyConstraint(self $reference)
	{
		$fk_name = \sprintf('fk_%s_%s', $this->getName(), $reference->getName());

		return isset($this->fk_constraints[$fk_name]);
	}

	/**
	 * Checks if the table has primary key constraint.
	 *
	 * @return bool
	 */
	public function hasPrimaryKeyConstraint()
	{
		return null !== $this->pk_constraint;
	}

	/**
	 * Checks if the table has unique constraint.
	 *
	 * @return bool
	 */
	public function hasUniqueConstraint()
	{
		return \count($this->uc_constraints);
	}

	/**
	 * Checks if a given columns list are the primary key of this table.
	 *
	 * @param array $columns_full_names columns full name list
	 *
	 * @return bool
	 */
	public function isPrimaryKey(array $columns_full_names)
	{
		$x = \count($columns_full_names);

		if ($x && $this->hasPrimaryKeyConstraint()) {
			$pk_columns = $this->pk_constraint->getConstraintColumns();
			$y          = \count($pk_columns);

			return $x === $y && !\count(\array_diff($pk_columns, $columns_full_names));
		}

		return false;
	}

	/**
	 * Checks if a given column is part of the primary key of this table.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @return bool
	 */
	public function isPartOfPrimaryKey(Column $column)
	{
		if ($this->hasPrimaryKeyConstraint()) {
			$pk_columns = $this->pk_constraint->getConstraintColumns();

			return \in_array($column->getFullName(), $pk_columns);
		}

		return false;
	}

	/**
	 * Checks if a given columns list are foreign key in this table.
	 *
	 * @param array $columns columns full name list
	 *
	 * @return bool
	 */
	public function isForeignKey(array $columns)
	{
		$x = \count($columns);

		if ($x) {
			foreach ($this->fk_constraints as $fk) {
				$fk_columns = \array_keys($fk->getConstraintColumns());

				$y = \count($fk_columns);

				if ($x === $y && !\count(\array_diff($fk_columns, $columns))) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if a given columns list are unique in this table.
	 *
	 * @param array $columns columns full name list
	 *
	 * @return bool
	 */
	public function isUnique(array $columns)
	{
		$x = \count($columns);

		if ($x) {
			foreach ($this->uc_constraints as $uc) {
				$uc_columns = $uc->getConstraintColumns();
				$y          = \count($uc_columns);

				if ($x === $y && !\count(\array_diff($uc_columns, $columns))) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if the table is private
	 *
	 * @return bool
	 */
	public function isPrivate()
	{
		if (!isset($this->options['private'])) {
			return false;
		}

		return (bool) ($this->options['private']);
	}

	/**
	 * Returns table options.
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * @param string $name
	 * @param bool   $handle_list
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function defineVR($name, callable $callable, $handle_list = false)
	{
		$vr = new CallableVR($name, $callable, $handle_list);

		return $this->addVirtualRelation($vr);
	}

	/**
	 * @param string $name
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\DBAL\Table
	 */
	public function defineCollection($name, callable $callable)
	{
		$c = new Collection($name, $callable);

		return $this->addCollection($c);
	}

	/**
	 * Asserts if we can add the column to this table.
	 *
	 * @param \Gobl\DBAL\Column $column
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	private function assertCanAddColumn(Column $column)
	{
		$name      = $column->getName();
		$full_name = $column->getFullName();

		if ($this->hasColumn($name)) {
			throw new DBALException(\sprintf('The column "%s" is already defined in table "%s".', $name, $this->name));
		}

		// prevent column full name conflict
		if ($this->hasColumn($full_name)) {
			$c = $this->col_full_name_map[$full_name];

			throw new DBALException(\sprintf(
				'The columns "%s" and "%s" has the same full name "%s" in table "%s".',
				$name,
				$c,
				$full_name,
				$this->getName()
			));
		}

		if ($this->hasRelation($name)) {
			throw new DBALException(\sprintf(
				'Column name and relation name conflict for "%s" in table "%".',
				$name,
				$this->getName()
			));
		}

		if ($this->hasVirtualRelation($name)) {
			throw new DBALException(\sprintf(
				'Column name and virtual relation name conflict for "%s" in table "%".',
				$name,
				$this->getName()
			));
		}
	}

	/**
	 * Asserts if we can add the relation to this table.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	private function assertCanAddRelation(Relation $relation)
	{
		$name = $relation->getName();

		if ($this->hasColumn($name)) {
			throw new DBALException(\sprintf(
				'Cannot use "%s" as relation name, column "%s" exists in table "%s".',
				$name,
				$name,
				$this->getName()
			));
		}

		if ($this->hasRelation($name)) {
			throw new DBALException(\sprintf(
				'Cannot override relation "%s" in table "%s".',
				$name,
				$this->getName()
			));
		}

		if ($this->hasVirtualRelation($name)) {
			throw new DBALException(\sprintf(
				'Relation name and virtual relation name conflict for "%s" in table "%".',
				$name,
				$this->getName()
			));
		}

		if ($this->hasCollection($name)) {
			throw new DBALException(\sprintf(
				'Relation name and collection name conflict for "%s" in table "%".',
				$name,
				$this->getName()
			));
		}
	}

	/**
	 * Asserts if we can add the virtual relation to this table.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	private function assertCanAddVirtualRelation(VirtualRelation $virtual_relation)
	{
		$name = $virtual_relation->getName();

		if ($this->hasColumn($name)) {
			throw new DBALException(\sprintf(
				'Virtual relation name and column name conflict for "%s" in table "%".',
				$name,
				$this->getName()
			));
		}

		if ($this->hasVirtualRelation($name)) {
			throw new DBALException(\sprintf(
				'Cannot override virtual relation "%s" in table "%s".',
				$name,
				$this->getName()
			));
		}

		if ($this->hasRelation($name)) {
			throw new DBALException(\sprintf(
				'Relation name and virtual relation name conflict for "%s" in table "%".',
				$name,
				$this->getName()
			));
		}

		if ($this->hasCollection($name)) {
			throw new DBALException(\sprintf(
				'Virtual relation name and collection name conflict for "%s" in table "%".',
				$name,
				$this->getName()
			));
		}
	}

	/**
	 * Asserts if we can add the collection to this table.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	private function assertCanAddCollection(Collection $collection)
	{
		$name = $collection->getName();

		if ($this->hasColumn($name)) {
			throw new DBALException(\sprintf(
				'Cannot use "%s" as collection name, column "%s" exists in table "%s".',
				$name,
				$name,
				$this->getName()
			));
		}

		if ($this->hasCollection($name)) {
			throw new DBALException(\sprintf(
				'Cannot override collection "%s" in table "%s".',
				$name,
				$this->getName()
			));
		}

		if ($this->hasRelation($name)) {
			throw new DBALException(\sprintf(
				'Collection name and relation name conflict for "%s" in table "%".',
				$name,
				$this->getName()
			));
		}

		if ($this->hasVirtualRelation($name)) {
			throw new DBALException(\sprintf(
				'Collection name and virtual relation name conflict for "%s" in table "%s".',
				$name,
				$this->getName()
			));
		}
	}
}
