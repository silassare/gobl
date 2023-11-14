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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function factory(callable $factory): self
	{
		try {
			// run the factory
			$factory($this);

			// register collected relations
			foreach ($this->collected_relations as $rb) {
				$relation = $rb->getRelation();
				$this->table->addRelation($relation);
			}

			// clear
			$this->collected_relations = [];
		} catch (Throwable $t) {
			throw (new DBALRuntimeException('Failed to build table', null, $t))->suspectCallable($factory);
		}

		return $this;
	}

	/**
	 * Returns the table.
	 *
	 * @return \Gobl\DBAL\Table
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
	 * @return \Gobl\DBAL\Types\TypeInt
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Column
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function column(string $column_name, TypeInterface|array $type = null): Column
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
	 * @return \Gobl\DBAL\Types\TypeDecimal
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Types\TypeFloat
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Types\TypeEnum
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Types\TypeDate
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Types\TypeList
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Types\TypeMap
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
		?callable $callable = null
	): ForeignKey {
		$ref_table = $this->rdbms->getTableOrFail($foreign_table);

		$ref        = 'ref:' . $foreign_table . '.' . $foreign_column;
		$ref_column = $this->rdbms->resolveColumn($ref, $this->table->getName());

		$column = new Column($column_name, null, $ref_column);

		$column->setReference($ref);

		$this->table->addColumn($column);

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
	 * @return \Gobl\DBAL\Types\Interfaces\TypeInterface
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function unique(string|Column ...$columns): UniqueKey
	{
		return $this->table->addUniqueKeyConstraint($columns);
	}

	/**
	 * Adds `created_at` and `updated_at` columns.
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Types\TypeDate
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function softDeletable(): self
	{
		$this->bool('deleted')
			->default(false);
		$this->timestamp('deleted_at')
			->nullable();

		return $this;
	}

	/**
	 * Creates a new column of type bool.
	 *
	 * @param string $column_name
	 *
	 * @return \Gobl\DBAL\Types\TypeBool
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Types\TypeBigint
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function primary(string|Column ...$columns): PrimaryKey
	{
		return $this->table->addPrimaryKeyConstraint($columns);
	}

	/**
	 * Adds polymorphic columns.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function morph(
		string $prefix,
		TypeInterface|array $target_id_column_type = null,
		TypeInterface|array $target_type_column_type = null,
		bool $nullable = false
	): self {
		$target_id_column_name   = "{$prefix}_id";
		$target_type_column_name = "{$prefix}_type";

		if ($target_id_column_type) {
			$id_col = $this->column($target_id_column_name, $target_id_column_type);
		} else {
			$id_col = $this->bigint($target_id_column_name)
				->unsigned();
		}

		if ($target_type_column_type) {
			$type_col = $this->column($target_type_column_name, $target_type_column_type);
		} else {
			$type_col = $this->string($target_type_column_name)
				->max(64);
		}

		if ($nullable) {
			$id_col->nullable();
			$type_col->nullable();
		}

		return $this;
	}

	/**
	 * Creates a new column of type string.
	 *
	 * @param string $column_name
	 *
	 * @return \Gobl\DBAL\Types\TypeString
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * @return \Gobl\DBAL\Builders\RelationBuilder
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
	 * @return \Gobl\DBAL\Builders\RelationBuilder
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
	 * @return \Gobl\DBAL\Builders\RelationBuilder
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
	 * @return \Gobl\DBAL\Builders\RelationBuilder
	 */
	public function belongsToMany(string $relation_name): RelationBuilder
	{
		return $this->collected_relations[] = new RelationBuilder(RelationType::MANY_TO_MANY, $relation_name, $this->table, $this->rdbms);
	}

	/**
	 * Adds relations to the table.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
}
