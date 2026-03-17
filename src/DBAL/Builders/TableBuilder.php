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

use BackedEnum;
use Gobl\DBAL\Column;
use Gobl\DBAL\Constraints\ForeignKey;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Constraints\UniqueKey;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Indexes\Index;
use Gobl\DBAL\Indexes\IndexType;
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
use Gobl\DBAL\Types\TypeJSON;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\Map;
use Throwable;

/**
 * Class TableBuilder.
 */
final class TableBuilder
{
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
		private readonly Table $table
	) {}

	/**
	 * Runs a user-supplied factory closure to declare columns, constraints, and relations inline.
	 *
	 * The `$factory` callable receives `$this` (the `TableBuilder`) as its only argument.
	 * After the callable returns, all collected relations are immediately registered on the
	 * table via `registerCollectedRelations()`.
	 *
	 * @param callable($this):void $factory closure that receives this builder and declares table structure
	 *
	 * @return $this
	 *
	 * @throws DBALRuntimeException when the factory throws
	 */
	public function factory(callable $factory): static
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
	public function plural(string $plural_name): static
	{
		$this->table->setPluralName($plural_name);

		return $this;
	}

	/**
	 * Sets the value used to identify the table in relations using morph link.
	 *
	 * @param string $type
	 *
	 * @return $this
	 */
	public function morphType(string $type): static
	{
		$this->table->setMorphType($type);

		return $this;
	}

	/**
	 * Sets the table singular name.
	 *
	 * @param string $singular_name
	 *
	 * @return $this
	 */
	public function singular(string $singular_name): static
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
	public function columnPrefix(string $prefix): static
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
	public function column(string $column_name, array|TypeInterface|null $type = null): Column
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
	 * Creates a new column of type json.
	 *
	 * Use {@see TypeJSON::nativeJson()} to opt-in to a native JSON column type
	 * (MySQL >= 5.7, PostgreSQL). Without it the column is stored as TEXT.
	 *
	 * @param string $column_name
	 *
	 * @return TypeJSON
	 *
	 * @throws DBALException
	 */
	public function json(string $column_name): TypeJSON
	{
		$this->column($column_name, $type = new TypeJSON());

		return $type;
	}

	/**
	 * Adds a foreign key to the table.
	 *
	 * If the column already exists, it will be updated with the reference type.
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
		$column    = $this->referenceColumn($column_name, $foreign_table, $foreign_column);

		$column->getType()->nullable($nullable);

		if ($callable) {
			$callable($column);
		}

		return $this->table->addForeignKeyConstraint(null, $ref_table, [$column_name => $foreign_column]);
	}

	/**
	 * Adds a collect foreign key factory.
	 *
	 * This solve the problem of foreign key that reference a table that is not yet created.
	 *
	 * @param callable($this):void $factory
	 *
	 * @return $this
	 */
	public function collectFk(callable $factory): static
	{
		$this->fk_factories[] = $factory;

		return $this;
	}

	/**
	 * Adds or updates a column to have the same type as another column.
	 *
	 * The type options are **copied** (cloned) from the source column at the time
	 * this method is called. Subsequent changes to the source column's type definition
	 * are **not** reflected in the column created here.
	 *
	 * @param string $column_name   the name of the column to create/update
	 * @param string $source_table  the table that owns the source column
	 * @param string $source_column the column whose type to copy
	 *
	 * @return TypeInterface the type instance applied to the column
	 *
	 * @throws DBALException
	 */
	public function sameAs(string $column_name, string $source_table, string $source_column): TypeInterface
	{
		return $this->referenceColumn($column_name, $source_table, $source_column, true)->getType();
	}

	/**
	 * Adds a unique constraint to the table.
	 *
	 * @param Column|string ...$columns
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
	 * Adds an index to the table.
	 *
	 * @param array<Column|string> $columns    the columns
	 * @param null|IndexType       $index_type the RDBMS-specific index type, or null for the default B-Tree index
	 *
	 * @return Index
	 *
	 * @throws DBALException
	 */
	public function index(array $columns, ?IndexType $index_type = null): Index
	{
		return $this->table->addIndex($columns, $index_type);
	}

	/**
	 * Adds `created_at` and `updated_at` columns.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function timestamps(?string $format = null): static
	{
		$c = $this->timestamp('created_at')
			->auto();
		$u = $this->timestamp('updated_at')
			->auto();

		if ($format) {
			$c->format($format);
			$u->format($format);
		}

		return $this;
	}

	/**
	 * Creates a new column of type date formatted as timestamp.
	 *
	 * @param string      $column_name
	 * @param null|string $format
	 *
	 * @return TypeDate
	 *
	 * @throws DBALException
	 */
	public function timestamp(string $column_name, ?string $format = null): TypeDate
	{
		$this->column($column_name, $type = new TypeDate());

		if ($format) {
			$type->format($format);
		}

		return $type;
	}

	/**
	 * Adds `deleted_at` and `deleted` columns.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function softDeletable(): static
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
	public function id(string $column_name = 'id'): static
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
	 * @param Column|string ...$columns
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
	 * @return $this
	 *
	 * @throws DBALException
	 * @throws TypesException
	 */
	public function morph(
		string $prefix,
		array|TypeInterface|null $id_column_type = null,
		array|TypeInterface|null $type_column_type = null,
		bool $nullable = false
	): static {
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

		$this->table->addMorph(
			$prefix,
			$this->table->getColumnOrFail($type_column_name),
			$this->table->getColumnOrFail($id_column_name)
		);

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
		return $this->collected_relations[] = new RelationBuilder(
			RelationType::ONE_TO_MANY,
			$relation_name,
			$this->table,
			$this->rdbms
		);
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
		return $this->collected_relations[] = new RelationBuilder(
			RelationType::ONE_TO_ONE,
			$relation_name,
			$this->table,
			$this->rdbms
		);
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
		return $this->collected_relations[] = new RelationBuilder(
			RelationType::MANY_TO_ONE,
			$relation_name,
			$this->table,
			$this->rdbms
		);
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
		return $this->collected_relations[] = new RelationBuilder(
			RelationType::MANY_TO_MANY,
			$relation_name,
			$this->table,
			$this->rdbms
		);
	}

	// -------------------------------------------------------------------------
	// LinkBuilder shortcuts - available as $t::linkColumns() etc. inside
	// factory() / collectRelation() callbacks without importing LinkBuilder.
	// -------------------------------------------------------------------------

	/**
	 * Shortcut for {@see LinkBuilder::columns()} - no import of `LinkBuilder` needed.
	 *
	 * Creates a `columns`-type link builder. When `$host_to_target_map` is empty, the
	 * column mapping is auto-detected from FK constraints at query-build time.
	 * Chain {@see LinkColumnsBuilder::map()} on the returned builder to set or replace
	 * the map after construction.
	 *
	 * @param array<string, string> $host_to_target_map host column -> target column;
	 *                                                  omit for auto-detection
	 *
	 * @return LinkColumnsBuilder
	 */
	public static function linkColumns(array $host_to_target_map = []): LinkColumnsBuilder
	{
		return LinkBuilder::columns($host_to_target_map);
	}

	/**
	 * Shortcut for {@see LinkBuilder::morph()} - no import of `LinkBuilder` needed.
	 *
	 * Creates a `morph`-type link builder using a column-name prefix
	 * (`{prefix}_id` / `{prefix}_type`).
	 * Chain {@see LinkMorphBuilder::parentType()} and/or {@see LinkMorphBuilder::parentKey()}
	 * to set the optional parent constraints.
	 *
	 * @param string $prefix prefix for the child-key and child-type columns
	 *
	 * @return LinkMorphBuilder
	 */
	public static function linkMorph(string $prefix): LinkMorphBuilder
	{
		return LinkBuilder::morph($prefix);
	}

	/**
	 * Shortcut for {@see LinkBuilder::morphExplicit()} - no import of `LinkBuilder` needed.
	 *
	 * Creates a `morph`-type link builder with explicit child-key and child-type column names,
	 * for when the morph columns do not follow the `{prefix}_id` / `{prefix}_type` convention.
	 * Chain {@see LinkMorphBuilder::parentType()} and/or {@see LinkMorphBuilder::parentKey()}
	 * to set the optional parent constraints.
	 *
	 * @param string $child_key_column  column on the child table holding the parent PK value
	 * @param string $child_type_column column on the child table holding the parent type string
	 *
	 * @return LinkMorphBuilder
	 */
	public static function linkMorphExplicit(
		string $child_key_column,
		string $child_type_column,
	): LinkMorphBuilder {
		return LinkBuilder::morphExplicit($child_key_column, $child_type_column);
	}

	/**
	 * Adds relations to the table.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function relations(Relation|RelationBuilder ...$relations): static
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
	 * Adds a collect index factory.
	 *
	 * This solve the problem of index that reference a table that is not yet created.
	 *
	 * @param callable($this):void $factory
	 *
	 * @return $this
	 */
	public function collectIndex(callable $factory): static
	{
		$this->indexes_factories[] = $factory;

		return $this;
	}

	/**
	 * Adds a collect relation factory.
	 *
	 * This solve the problem of relation that reference a table that is not yet created.
	 *
	 * @param callable($this):void $factory
	 *
	 * @return $this
	 */
	public function collectRelation(callable $factory): static
	{
		$this->relations_factories[] = $factory;

		return $this;
	}

	/**
	 * Runs all deferred FK and index factories, registering them on the table.
	 *
	 * FK factories are always run **before** index factories because indexes may
	 * depend on FKs (e.g. an index on a FK column). Both factory lists are cleared
	 * after execution so this method is idempotent on subsequent calls.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 *
	 * @internal this method should be called only by the RDBMS namespace builder
	 */
	public function packConstraints(): static
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
	 * Runs all deferred relation factories and registers the resulting relations on the table.
	 *
	 * Two-phase execution:
	 *  1. Each `$relations_factories` callback is called (allows cross-table relation setup).
	 *  2. `registerCollectedRelations()` flushes the `$collected_relations` list to the table.
	 *
	 * Both lists are cleared afterwards so this method is idempotent on subsequent calls.
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 *
	 * @internal this method should be called only by the RDBMS namespace builder
	 */
	public function packRelations(): static
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
	 * Sets the table metadata.
	 *
	 * @param array|Map|string $key   the meta key or the meta data/map
	 * @param null|mixed       $value the meta value
	 *
	 * @return $this
	 */
	public function meta(array|Map|string $key, mixed $value = null): static
	{
		$this->table->setMeta($key, $value);

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

	/**
	 * Creates or updates a column to reference another column.
	 *
	 * If the column already exists, it will be updated with the reference type.
	 * Otherwise, a new column will be created with the reference type.
	 *
	 * @param string $column_name
	 * @param string $source_table
	 * @param string $source_column
	 * @param bool   $is_copy
	 *
	 * @return Column
	 *
	 * @throws DBALException
	 */
	private function referenceColumn(
		string $column_name,
		string $source_table,
		string $source_column,
		bool $is_copy = false
	): Column {
		$ref             = ($is_copy ? 'cp:' : 'ref:') . $source_table . '.' . $source_column;
		$ref_column_type = $this->rdbms->resolveColumn($ref, $this->table->getName());

		$column = $this->table->getColumn($column_name);

		if ($column) {
			$column->setTypeFromOptions($ref_column_type);
		} else {
			$column = new Column($column_name, null, $ref_column_type);
			$this->table->addColumn($column);
		}

		$column->setReference($ref);

		return $column;
	}
}
