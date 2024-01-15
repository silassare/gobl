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

use BackedEnum;
use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\ForeignKey;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Constraints\UniqueKey;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\RelationType;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use Throwable;

/**
 * Class TableBuilder.
 */
final class TableBuilder
{
	private Table $table;

	/** @var RelationBuilder[] */
	private array $collected_relations = [];

	/** @var array<int, callable($this):void> */
	private array $indexes_factories = [];

	/** @var array<int, callable($this):void> */
	private array $fk_factories = [];

	/** @var array<int, callable($this):void> */
	private array $relations_factories = [];

	/**
	 * TableBuilder constructor.
	 */
	public function __construct(
		private readonly RDBMSInterface $rdbms,
		string $namespace,
		string $table_name
	) {
		$this->table = new Table($table_name, $rdbms->getConfig()
			->getDbTablePrefix());
		$this->table->setNamespace($namespace);
	}

	/**
	 * Makes sure the table is ready to be used.
	 *
	 * @param callable($this):void $factory
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function factory(callable $factory): self
	{
		try {
			// run the factory
			$factory($this);

			$this->registerCollectedRelations();
		} catch (Throwable $t) {
			throw (new DBALRuntimeException('Failed to build table', null, $t))->suspectCallable($factory);
		}

		return $this;
	}

	/**
	 * Returns the table.
	 *
	 * @return Table
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 * Sets the table plural name.
	 *
	 * @param string $plural_name
	 *
	 * @return $this
	 */
	public function plural(string $plural_name): self
	{
		$this->table->setPluralName($plural_name);

		return $this;
	}

	/**
	 * Sets the table singular name.
	 *
	 * @param string $singular_name
	 *
	 * @return $this
	 */
	public function singular(string $singular_name): self
	{
		$this->table->setSingularName($singular_name);

		return $this;
	}

	/**
	 * Sets the table columns prefix.
	 *
	 * @param string $prefix
	 *
	 * @return $this
	 */
	public function columnPrefix(string $prefix): self
	{
		$this->table->setColumnPrefix($prefix);

		return $this;
	}

	/**
	 * Creates a new column of type int.
	 *
	 * @param string $column_name
	 *
	 * @return TypeInt
	 *
	 * @throws DBALException
	 */
	public function int(string $column_name): TypeInt
	{
		$this->column($column_name, $type = new TypeInt());

		return $type;
	}

	/**
	 * Adds a new column to the table.
	 *
	 * @param string                   $column_name
	 * @param null|array|TypeInterface $type
	 *
	 * @return Column
	 *
	 * @throws DBALException
	 */
	public function column(string $column_name, array|TypeInterface $type = null): Column
	{
		$column = new Column($column_name, null, $type);

		$this->table->addColumn($column);

		return $column;
	}

	/**
	 * Creates a new column of type decimal.
	 *
	 * @param string $column_name
	 *
	 * @return TypeDecimal
	 *
	 * @throws DBALException
	 */
	public function decimal(string $column_name): TypeDecimal
	{
		$this->column($column_name, $type = new TypeDecimal());

		return $type;
	}

	/**
	 * Creates a new column of type float.
	 *
	 * @param string $column_name
	 *
	 * @return TypeFloat
	 *
	 * @throws DBALException
	 */
	public function float(string $column_name): TypeFloat
	{
		$this->column($column_name, $type = new TypeFloat());

		return $type;
	}

	/**
	 * Creates a new column of type enum.
	 *
	 * @param string                   $column_name
	 * @param class-string<BackedEnum> $enum_class
	 *
	 * @return TypeEnum
	 *
	 * @throws DBALException
	 */
	public function enum(string $column_name, string $enum_class): TypeEnum
	{
		$this->column($column_name, $type = new TypeEnum($enum_class));

		return $type;
	}

	/**
	 * Creates a new column of type date.
	 *
	 * @param string $column_name
	 *
	 * @return TypeDate
	 *
	 * @throws DBALException
	 */
	public function date(string $column_name): TypeDate
	{
		$this->column($column_name, $type = new TypeDate());

		return $type;
	}

	/**
	 * Creates a new column of type list.
	 *
	 * @param string $column_name
	 *
	 * @return TypeList
	 *
	 * @throws DBALException
	 */
	public function list(string $column_name): TypeList
	{
		$this->column($column_name, $type = new TypeList());

		return $type;
	}

	/**
	 * Creates a new column of type map.
	 *
	 * @param string $column_name
	 *
	 * @return TypeMap
	 *
	 * @throws DBALException
	 */
	public function map(string $column_name): TypeMap
	{
		$this->column($column_name, $type = new TypeMap());

		return $type;
	}

	/**
	 * Add a foreign column.
	 *
	 * The column will be created with the same type as the foreign column.
	 * A foreign key constraint will be added to the table.
	 *
	 * @param string                     $column_name
	 * @param string                     $foreign_table
	 * @param string                     $foreign_column
	 * @param null|bool                  $nullable
	 * @param null|callable(Column):void $callable
	 *
	 * @return ForeignKey
	 *
	 * @throws DBALException
	 */
	public function foreign(
		string $column_name,
		string $foreign_table,
		string $foreign_column,
		?bool $nullable = false,
		?callable $callable = null
	): ForeignKey {
		$ref_table = $this->rdbms->getTableOrFail($foreign_table);

		$ref        = 'ref:' . $foreign_table . '.' . $foreign_column;
		$ref_column = $this->rdbms->resolveColumn($ref, $this->table->getName());

		$column = new Column($column_name, null, $ref_column);

		$column->setReference($ref);

		$this->table->addColumn($column);

		$column->getType()->nullable($nullable);

		if ($callable) {
			$callable($column);
		}

		return $this->table->addForeignKeyConstraint(null, $ref_table, [$column_name => $foreign_column]);
	}

	/**
	 * Adds column with same type as another column.
	 *
	 * @param string $column_name
	 * @param string $source_table
	 * @param string $source_column
	 *
	 * @return TypeInterface
	 *
	 * @throws DBALException
	 */
	public function sameAs(string $column_name, string $source_table, string $source_column): TypeInterface
	{
		$ref        = 'cp:' . $source_table . '.' . $source_column;
		$ref_column = $this->rdbms->resolveColumn($ref, $this->table->getName());

		$column = new Column($column_name, null, $ref_column);

		$column->setReference($ref);

		$this->table->addColumn($column);

		return $column->getType();
	}

	/**
	 * Adds a unique constraint to the table.
	 *
	 * @param \Gobl\DBAL\Column|string ...$columns
	 *
	 * @return UniqueKey
	 *
	 * @throws DBALException
	 */
	public function unique(Column|string ...$columns): UniqueKey
	{
		return $this->table->addUniqueKeyConstraint($columns);
	}

	/**
	 * Adds `created_at` and `updated_at` columns.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function timestamps(): self
	{
		$this->timestamp('created_at')
			->auto();
		$this->timestamp('updated_at')
			->auto();

		return $this;
	}

	/**
	 * Creates a new column of type date formatted as timestamp.
	 *
	 * @param string $column_name
	 *
	 * @return TypeDate
	 *
	 * @throws DBALException
	 */
	public function timestamp(string $column_name): TypeDate
	{
		$this->column($column_name, $type = new TypeDate());

		return $type->format('timestamp');
	}

	/**
	 * Adds `deleted_at` and `deleted` columns.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function softDeletable(): self
	{
		$this->bool(Table::COLUMN_SOFT_DELETED)
			->default(false);
		$this->timestamp(Table::COLUMN_SOFT_DELETED_AT)
			->nullable();

		return $this;
	}

	/**
	 * Creates a new column of type bool.
	 *
	 * @param string $column_name
	 *
	 * @return TypeBool
	 *
	 * @throws DBALException
	 */
	public function bool(string $column_name): TypeBool
	{
		$this->column($column_name, $type = new TypeBool());

		return $type;
	}

	/**
	 * Adds an identifier column.
	 *
	 * A primary key constraint will be added to the table.
	 *
	 * @param string $column_name
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function id(string $column_name = 'id'): self
	{
		$this->bigint($column_name)
			->unsigned()
			->autoIncrement();

		$this->primary($column_name);

		return $this;
	}

	/**
	 * Creates a new column of type bigint.
	 *
	 * @param string $column_name
	 *
	 * @return TypeBigint
	 *
	 * @throws DBALException
	 */
	public function bigint(string $column_name): TypeBigint
	{
		$this->column($column_name, $type = new TypeBigint());

		return $type;
	}

	/**
	 * Adds a primary key constraint to the table.
	 *
	 * @param \Gobl\DBAL\Column|string ...$columns
	 *
	 * @return PrimaryKey
	 *
	 * @throws DBALException
	 */
	public function primary(Column|string ...$columns): PrimaryKey
	{
		return $this->table->addPrimaryKeyConstraint($columns);
	}

	/**
	 * Adds polymorphic columns.
	 *
	 * @throws DBALException
	 * @throws TypesException
	 */
	public function morph(
		string $prefix,
		array|TypeInterface $id_column_type = null,
		array|TypeInterface $type_column_type = null,
		bool $nullable = false
	): self {
		$id_column_name   = "{$prefix}_id";
		$type_column_name = "{$prefix}_type";

		if ($id_column_type) {
			$id_col_type = $this->column($id_column_name, $id_column_type)->getType();
		} else {
			$id_col_type = $this->bigint($id_column_name)->unsigned();
		}

		if ($type_column_type) {
			$type_col_type = $this->column($type_column_name, $type_column_type)->getType();
		} else {
			$type_col_type = $this->string($type_column_name)->max(64);
		}

		if ($nullable) {
			$id_col_type->nullable();
			$type_col_type->nullable();
		}

		return $this;
	}

	/**
	 * Creates a new column of type string.
	 *
	 * @param string $column_name
	 *
	 * @return TypeString
	 *
	 * @throws DBALException
	 */
	public function string(string $column_name): TypeString
	{
		$this->column($column_name, $type = new TypeString());

		return $type;
	}

	/**
	 * Creates a one to many relation.
	 *
	 * @param string $relation_name
	 *
	 * @return RelationBuilder
	 */
	public function hasMany(string $relation_name): RelationBuilder
	{
		return $this->collected_relations[] = new RelationBuilder(RelationType::ONE_TO_MANY, $relation_name, $this->table, $this->rdbms);
	}

	/**
	 * Creates a one to one relation.
	 *
	 * @param string $relation_name
	 *
	 * @return RelationBuilder
	 */
	public function hasOne(string $relation_name): RelationBuilder
	{
		return $this->collected_relations[] = new RelationBuilder(RelationType::ONE_TO_ONE, $relation_name, $this->table, $this->rdbms);
	}

	/**
	 * Creates a many to one relation.
	 *
	 * @param string $relation_name
	 *
	 * @return RelationBuilder
	 */
	public function belongsTo(string $relation_name): RelationBuilder
	{
		return $this->collected_relations[] = new RelationBuilder(RelationType::MANY_TO_ONE, $relation_name, $this->table, $this->rdbms);
	}

	/**
	 * Creates a many to many relation.
	 *
	 * @param string $relation_name
	 *
	 * @return RelationBuilder
	 */
	public function belongsToMany(string $relation_name): RelationBuilder
	{
		return $this->collected_relations[] = new RelationBuilder(RelationType::MANY_TO_MANY, $relation_name, $this->table, $this->rdbms);
	}

	/**
	 * Adds relations to the table.
	 *
	 * @throws DBALException
	 */
	public function relations(Relation|RelationBuilder ...$relations): self
	{
		foreach ($relations as $relation) {
			if ($relation instanceof RelationBuilder) {
				$relation = $relation->getRelation();
			}

			$this->table->addRelation($relation);
		}

		return $this;
	}

	/**
	 * Add a collect foreign key factory.
	 *
	 * This solve the problem of foreign key that reference a table that is not yet created.
	 *
	 * @param callable($this):void $factory
	 *
	 * @return $this
	 */
	public function collectFk(callable $factory): self
	{
		$this->fk_factories[] = $factory;

		return $this;
	}

	/**
	 * Add a collect index factory.
	 *
	 * This solve the problem of index that reference a table that is not yet created.
	 *
	 * @param callable($this):void $factory
	 *
	 * @return $this
	 */
	public function collectIndex(callable $factory): self
	{
		$this->indexes_factories[] = $factory;

		return $this;
	}

	/**
	 * Add a collect relation factory.
	 *
	 * This solve the problem of relation that reference a table that is not yet created.
	 *
	 * @param callable($this):void $factory
	 *
	 * @return $this
	 */
	public function collectRelation(callable $factory): self
	{
		$this->relations_factories[] = $factory;

		return $this;
	}

	/**
	 * Collects indexes and foreign keys and register them to the table.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 *
	 * @internal this method should be called only by the RDBMS namespace builder
	 */
	public function packConstraints(): self
	{
		// we collect foreign keys before indexes because
		// indexes may need to reference foreign keys
		foreach ($this->fk_factories as $factory) {
			$factory($this);
		}

		// collect indexes
		foreach ($this->indexes_factories as $factory) {
			$factory($this);
		}

		// clear
		$this->fk_factories      = [];
		$this->indexes_factories = [];

		return $this;
	}

	/**
	 * Collects all relations and register them to the table.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 *
	 * @internal this method should be called only by the RDBMS namespace builder
	 */
	public function packRelations(): self
	{
		// collect relations
		foreach ($this->relations_factories as $factory) {
			$factory($this);
		}

		// register collected relations
		$this->registerCollectedRelations();

		// clear
		$this->relations_factories = [];

		return $this;
	}

	/**
	 * Registers all collected relations.
	 *
	 * @throws DBALException
	 */
	private function registerCollectedRelations(): void
	{
		// register collected relations
		foreach ($this->collected_relations as $rb) {
			$relation = $rb->getRelation();
			$this->table->addRelation($relation);
		}

		// clear
		$this->collected_relations = [];
	}
}
