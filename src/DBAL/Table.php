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

namespace Gobl\DBAL;

use Gobl\DBAL\Collections\Collection;
use Gobl\DBAL\Constraints\Constraint;
use Gobl\DBAL\Constraints\ForeignKey;
use Gobl\DBAL\Constraints\ForeignKeyAction;
use Gobl\DBAL\Constraints\PrimaryKey;
use Gobl\DBAL\Constraints\UniqueKey;
use Gobl\DBAL\Diff\Interfaces\DiffCapableInterface;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Relations\VirtualRelation;
use Gobl\DBAL\Traits\MetadataTrait;
use InvalidArgumentException;
use OLIUP\CG\PHPNamespace;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;
use Throwable;

/**
 * Class Table.
 */
final class Table implements ArrayCapableInterface, DiffCapableInterface
{
	use ArrayCapableTrait;
	use MetadataTrait;

	public const ALIAS_PATTERN  = '[a-zA-Z_][a-zA-Z0-9_]*';
	public const ALIAS_REG      = '~^' . self::ALIAS_PATTERN . '$~';
	public const NAME_PATTERN   = '[a-z](?:[a-z0-9_]*[a-z0-9])?';
	public const NAME_REG       = '~^' . self::NAME_PATTERN . '$~';
	public const PREFIX_PATTERN = '[a-zA-Z](?:[a-zA-Z0-9_]*[a-zA-Z0-9])?';

	public const PREFIX_REG = '~^' . self::PREFIX_PATTERN . '$~';

	public const TABLE_DEFAULT_NAMESPACE = 'Gobl\DefaultNamespace';
	public const COLUMN_SOFT_DELETED     = 'deleted';
	public const COLUMN_SOFT_DELETED_AT  = 'deleted_at';

	/**
	 * @var array<string, array{morph_type_column:Column, morph_id_column :Column}>
	 */
	public array $morphs = [];

	/**
	 * The table diff key.
	 *
	 * @var null|string
	 */
	private ?string $diff_key = null;

	/**
	 * The table name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The value used to identify the table in relations using morph link.
	 *
	 * @var null|string
	 */
	private ?string $morph_type =  null;

	/**
	 * The table singular name.
	 *
	 * @var string
	 */
	private string $singular_name;

	/**
	 * The table plural name.
	 *
	 * @var string
	 */
	private string $plural_name;

	/**
	 * The column prefix.
	 *
	 * @var string
	 */
	private string $column_prefix = '';

	/**
	 * @var bool
	 */
	private bool $column_prefix_override = false;

	/**
	 * The table namespace.
	 *
	 * @var string
	 */
	private string $namespace = self::TABLE_DEFAULT_NAMESPACE;

	/**
	 * The table prefix.
	 *
	 * @var string
	 */
	private string $prefix = '';

	/**
	 * The table charset.
	 *
	 * @var null|string
	 */
	private ?string $charset = null;

	/**
	 * The table collate.
	 *
	 * @var null|string
	 */
	private ?string $collate = null;

	/**
	 * Table private state.
	 *
	 * @var bool
	 */
	private bool $private = false;

	private bool $locked = false;

	private bool $locked_name = false;

	/**
	 * The table columns.
	 *
	 * @var Column[]
	 */
	private array $columns = [];

	/**
	 * @var array
	 */
	private array $col_full_name_map = [];

	/**
	 * Foreign keys constraints.
	 *
	 * @var ForeignKey[]
	 */
	private array $fk_constraints = [];

	/**
	 * Primary key constraint.
	 *
	 * @var null|PrimaryKey
	 */
	private ?PrimaryKey $pk_constraint = null;

	/**
	 * Unique constraints.
	 *
	 * @var UniqueKey[]
	 */
	private array $uc_constraints = [];

	/**
	 * Table relations list.
	 *
	 * @var Relation[]
	 */
	private array $relations = [];

	/**
	 * Table virtual relations list.
	 *
	 * @var VirtualRelation[]
	 */
	private array $virtual_relations = [];

	/**
	 * The collections list.
	 *
	 * @var Collection[]
	 */
	private array $collections = [];

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
	 * @param string      $name   the table name
	 * @param null|string $prefix the table prefix
	 */
	public function __construct(string $name, ?string $prefix = null)
	{
		$this->setName($name);

		if (!empty($prefix)) {
			$this->setPrefix($prefix);
		}

		$this->setPluralName($name . '_entities');
		$this->setSingularName($name . '_entity');
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo(): array
	{
		return ['instance_of' => self::class, 'table_name' => $this->getName()];
	}

	private function __clone() {}

	/**
	 * Gets table name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Sets table name.
	 *
	 * @param string $name
	 *
	 * @return $this
	 */
	public function setName(string $name): self
	{
		$this->assertNotLocked();
		$this->assertNameNotLocked();

		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf('Table name "%s" should match: %s', $name, self::NAME_PATTERN));
		}

		$this->name = $name;

		return $this;
	}

	/**
	 * Sets the value used to identify the table in relations using morph link.
	 *
	 * @param string $value
	 *
	 * @return $this
	 */
	public function setMorphType(string $value): self
	{
		$this->assertNotLocked();

		if (!\preg_match(self::NAME_REG, $value)) {
			throw new InvalidArgumentException(\sprintf('Morph type value "%s" should match: %s', $value, self::NAME_PATTERN));
		}

		$this->morph_type = $value;

		return $this;
	}

	/**
	 * Gets the value used to identify the table in relations using morph link.
	 */
	public function getMorphType(): string
	{
		return $this->morph_type ?? $this->getName();
	}

	/**
	 * Adds a morph relation's columns to this table.
	 *
	 * @internal
	 *
	 * @param string $name              the morph name
	 * @param Column $morph_type_column the morph type column
	 * @param Column $morph_id_column   the morph id column
	 *
	 * @return $this
	 */
	public function addMorph(string $name, Column $morph_type_column, Column $morph_id_column): self
	{
		$this->assertNotLocked();

		if ($this->getMorphOptions($morph_id_column->getName())) {
			throw new InvalidArgumentException(
				\sprintf(
					'Column "%s" is already defined in a morph relation in table "%s".',
					$morph_id_column->getName(),
					$this->getFullName()
				)
			);
		}

		if ($this->getMorphOptions($morph_type_column->getName())) {
			throw new InvalidArgumentException(
				\sprintf(
					'Column "%s" is already defined in a morph relation in table "%s".',
					$morph_type_column->getName(),
					$this->getFullName()
				)
			);
		}

		if (isset($this->morphs[$name])) {
			throw new InvalidArgumentException(
				\sprintf(
					'Morph "%s" is already defined in table "%s".',
					$name,
					$this->getFullName()
				)
			);
		}

		$this->morphs[$name] = [
			'morph_type_column' => $morph_type_column,
			'morph_id_column'   => $morph_id_column,
		];

		return $this;
	}

	/**
	 * Checks if a given column is in a morph relation.
	 *
	 * @param string $column
	 *
	 * @return bool
	 */
	public function isMorphColumn(string $column): bool
	{
		return null !== $this->getMorphOptions($column);
	}

	/**
	 * Returns a morph options from this table in which a given column is defined.
	 *
	 * @param string $column the column name
	 *
	 * @return null|array{
	 *     morph_name: string,
	 *     morph_type_column: Column,
	 *     morph_id_column: Column,
	 * }
	 */
	public function getMorphOptions(string $column): ?array
	{
		$column = $this->getColumnOrFail($column);

		foreach ($this->morphs as $name => $morph) {
			if ($morph['morph_id_column'] === $column || $morph['morph_type_column'] === $column) {
				return [
					'morph_name'        => $name,
					'morph_type_column' => $morph['morph_type_column'],
					'morph_id_column'   => $morph['morph_id_column'],
				];
			}
		}

		return null;
	}

	/**
	 * Locks this table to prevent further changes.
	 *
	 * @return $this
	 */
	public function lock(): self
	{
		if (!$this->locked) {
			$this->assertIsValid();

			foreach ($this->columns as $column) {
				$column->lock($this);
			}

			$this->pk_constraint?->lock();

			foreach ($this->uc_constraints as $uc) {
				$uc->lock();
			}

			foreach ($this->fk_constraints as $fk) {
				$fk->lock();
			}

			$this->locked = true;
		}

		return $this;
	}

	/**
	 * Asserts if this column definition/instance is valid.
	 */
	public function assertIsValid(): void
	{
		if (empty($this->columns)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Table "%s" should have at least one column.',
					$this->name
				)
			);
		}

		$missing = [];
		if (empty($this->plural_name)) {
			$missing[] = 'plural_name';
		}

		if (empty($this->singular_name)) {
			$missing[] = 'singular_name';
		}

		if (!empty($missing)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Invalid table "%s" missing required properties: %s',
					$this->name,
					\implode(', ', $missing)
				)
			);
		}

		if (!empty($this->plural_name) && $this->plural_name === $this->singular_name) {
			throw new InvalidArgumentException(
				\sprintf(
					'"plural_name" and "singular_name" should not be equal in table "%s".',
					$this->name
				)
			);
		}
	}

	/**
	 * Gets the plural name.
	 *
	 * @return string
	 */
	public function getPluralName(): string
	{
		return $this->plural_name;
	}

	/**
	 * Sets table plural name.
	 *
	 * Table name rules also apply to plural and singular names
	 *
	 * @param string $plural_name
	 *
	 * @return $this
	 */
	public function setPluralName(string $plural_name): self
	{
		$this->assertNotLocked();

		if (!\preg_match(self::NAME_REG, $plural_name)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Table "%s" plural name "%s" should match: %s',
					$this->name,
					$plural_name,
					self::NAME_PATTERN
				)
			);
		}

		$this->plural_name = $plural_name;

		return $this;
	}

	/**
	 * Gets the singular name.
	 *
	 * @return string
	 */
	public function getSingularName(): string
	{
		return $this->singular_name;
	}

	/**
	 * Sets table singular name.
	 *
	 * Table name rules also apply to plural and singular names
	 *
	 * @param string $singular_name
	 *
	 * @return $this
	 */
	public function setSingularName(string $singular_name): self
	{
		$this->assertNotLocked();

		if (!\preg_match(self::NAME_REG, $singular_name)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Table "%s" singular name "%s" should match: %s',
					$this->name,
					$singular_name,
					self::NAME_PATTERN
				)
			);
		}

		$this->singular_name = $singular_name;

		return $this;
	}

	/**
	 * Gets table columns prefix.
	 *
	 * @return string
	 */
	public function getColumnPrefix(): string
	{
		return $this->column_prefix;
	}

	/**
	 * Sets table column prefix.
	 *
	 * @param string $column_prefix
	 * @param bool   $override
	 *
	 * @return $this
	 */
	public function setColumnPrefix(string $column_prefix, bool $override = false): self
	{
		$this->assertNotLocked();

		if (!empty($column_prefix) && !\preg_match(Column::PREFIX_REG, $column_prefix)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Table "%s" column prefix "%s" should match: %s',
					$this->name,
					$column_prefix,
					Column::PREFIX_PATTERN
				)
			);
		}

		$this->column_prefix          = $column_prefix;
		$this->column_prefix_override = $override;

		return $this;
	}

	/**
	 * Asserts if this table is not locked.
	 */
	public function assertNotLocked(): void
	{
		if ($this->locked) {
			throw new DBALRuntimeException(
				\sprintf(
					'You should not try to edit locked table "%s".',
					$this->name
				)
			);
		}
	}

	/**
	 * Asserts if this table name is not locked.
	 */
	public function assertNameNotLocked(): void
	{
		if ($this->locked_name) {
			throw new DBALRuntimeException(
				\sprintf(
					'You should not try to edit locked table (%s) name or prefix.',
					$this->name
				)
			);
		}
	}

	/**
	 * Gets table collate.
	 *
	 * @return null|string
	 */
	public function getCollate(): ?string
	{
		return $this->collate;
	}

	/**
	 * Sets table collate.
	 *
	 * @param null|string $collate
	 *
	 * @return Table
	 */
	public function setCollate(?string $collate): self
	{
		$this->collate = empty($collate) ? null : $collate;

		return $this;
	}

	/**
	 * Gets table charset.
	 *
	 * @return null|string
	 */
	public function getCharset(): ?string
	{
		return $this->charset;
	}

	/**
	 * Sets table charset.
	 *
	 * @param null|string $charset
	 *
	 * @return Table
	 */
	public function setCharset(?string $charset): self
	{
		$this->charset = empty($charset) ? null : $charset;

		return $this;
	}

	/**
	 * Gets table namespace.
	 *
	 * @return string
	 */
	public function getNamespace(): string
	{
		return $this->namespace;
	}

	/**
	 * Sets table namespace.
	 *
	 * @param string $namespace
	 *
	 * @return $this
	 */
	public function setNamespace(string $namespace): self
	{
		$this->assertNotLocked();

		if (empty($namespace)) {
			throw new InvalidArgumentException(\sprintf('Table "%s" namespace should not be empty', $this->name));
		}

		if (!\preg_match(PHPNamespace::NAMESPACE_PATTERN, $namespace)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Table "%s" namespace "%s" should match: %s',
					$this->name,
					$namespace,
					PHPNamespace::NAMESPACE_PATTERN
				)
			);
		}

		$this->namespace = $namespace;

		return $this;
	}

	/**
	 * Gets columns full names list.
	 *
	 * @param bool $include_private if false private column will not be included.
	 *                              Default is true.
	 *
	 * @return array
	 */
	public function getColumnsFullNameList(bool $include_private = true): array
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
	 * Checks if the table is private.
	 *
	 * @return bool
	 */
	public function isPrivate(): bool
	{
		return $this->private;
	}

	/**
	 * Sets this table as private.
	 *
	 * @return $this
	 */
	public function setPrivate(bool $private = true): self
	{
		$this->assertNotLocked();

		$this->private = $private;

		return $this;
	}

	/**
	 * Gets table full name.
	 *
	 * @return string
	 */
	public function getFullName(): string
	{
		if (empty($this->prefix)) {
			return $this->name;
		}

		return $this->prefix . '_' . $this->name;
	}

	/**
	 * Gets columns name list.
	 *
	 * @param bool $include_private if false private column will not be included.
	 *                              Default is true.
	 *
	 * @return array
	 */
	public function getColumnsNameList(bool $include_private = true): array
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
	 * @return Column[]
	 */
	public function getPrivatesColumns(): array
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
	 * Adds relation to this table.
	 *
	 * @param Relation $relation
	 *
	 * @return Table
	 *
	 * @throws DBALException
	 */
	public function addRelation(Relation $relation): self
	{
		$this->assertNotLocked();
		$this->assertCanAddRelation($relation);

		$this->relations[$relation->getName()] = $relation;

		return $this;
	}

	/**
	 * Checks if a given column is defined.
	 *
	 * @param string $name the column name or full name
	 *
	 * @return bool
	 */
	public function hasColumn(string $name): bool
	{
		return isset($this->columns[$name]) || isset($this->col_full_name_map[$name]);
	}

	/**
	 * Checks if a given relation is defined.
	 *
	 * @param string $name the relation name
	 *
	 * @return bool
	 */
	public function hasRelation(string $name): bool
	{
		return isset($this->relations[$name]);
	}

	/**
	 * Checks if a given virtual relation is defined.
	 *
	 * @param string $name the virtual relation name
	 *
	 * @return bool
	 */
	public function hasVirtualRelation(string $name): bool
	{
		return isset($this->virtual_relations[$name]);
	}

	/**
	 * Checks if a given collection is defined.
	 *
	 * @param string $name the collection name
	 *
	 * @return bool
	 */
	public function hasCollection(string $name): bool
	{
		return isset($this->collections[$name]);
	}

	/**
	 * Asserts if the table entities are soft deletable.
	 *
	 * @throws DBALException
	 */
	public function assertSoftDeletable(): void
	{
		if (!$this->isSoftDeletable()) {
			throw new DBALException(
				\sprintf(
					'Table "%s" is not soft deletable.',
					$this->getFullName()
				)
			);
		}
	}

	/**
	 * Checks if the table entities are soft deletable.
	 *
	 * @return bool
	 */
	public function isSoftDeletable(): bool
	{
		return $this->hasColumn(self::COLUMN_SOFT_DELETED) && $this->hasColumn(self::COLUMN_SOFT_DELETED_AT);
	}

	/**
	 * Adds virtual relation to this table.
	 *
	 * @param VirtualRelation $virtual_relation
	 *
	 * @return Table
	 *
	 * @throws DBALException
	 */
	public function addVirtualRelation(VirtualRelation $virtual_relation): self
	{
		$this->assertCanAddVirtualRelation($virtual_relation);

		$name                           = $virtual_relation->getName();
		$this->virtual_relations[$name] = $virtual_relation;

		return $this;
	}

	/**
	 * Adds collection to this table.
	 *
	 * @param Collection $collection
	 *
	 * @return Table
	 *
	 * @throws DBALException
	 */
	public function addCollection(Collection $collection): self
	{
		$this->assertCanAddCollection($collection);

		$name                     = $collection->getName();
		$this->collections[$name] = $collection;

		return $this;
	}

	/**
	 * Adds a unique key constraint on columns.
	 *
	 * @param array<Column|string> $columns the columns
	 *
	 * @return UniqueKey
	 *
	 * @throws DBALException
	 */
	public function addUniqueKeyConstraint(array $columns): UniqueKey
	{
		$this->assertNotLocked();

		if (empty($columns)) {
			throw new DBALException(
				\sprintf(
					'Unique key constraint on table "%s" should have at least one column.',
					$this->getFullName()
				)
			);
		}

		$c_names = [];

		foreach ($columns as $column) {
			if ($column instanceof Column) {
				$c_names[] = $column->getName();
			} else {
				$c_names[] = $column;
			}
		}

		\sort($c_names);

		$key = \md5(\implode('_', $c_names));

		$constraint_name = Constraint::normalizeName(\sprintf('uc_%s_%s', $this->getFullName(), $key));

		if (!isset($this->uc_constraints[$constraint_name])) {
			$uc = new UniqueKey($constraint_name, $this);

			foreach ($columns as $column_name) {
				if ($column_name instanceof Column) {
					$column_name = $column_name->getName();
				}
				$uc->addColumn($column_name);
			}

			$this->uc_constraints[$constraint_name] = $uc;
		}

		return $this->uc_constraints[$constraint_name];
	}

	/**
	 * Adds a given column to the current table.
	 *
	 * @param Column $column the column to add
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function addColumn(Column $column): self
	{
		$this->assertNotLocked();
		$this->assertCanAddColumn($column);

		if (!empty($this->column_prefix)) {
			$prefix = $column->getPrefix();

			if (empty($prefix) || ($this->column_prefix_override && $prefix !== $this->column_prefix)) {
				$column->setPrefix($this->column_prefix);
			}
		}

		// this column name and prefix should not be modified
		$column->lockName();

		$name                                = $column->getName();
		$full_name                           = $column->getFullName();
		$this->columns[$name]                = $column;
		$this->col_full_name_map[$full_name] = $name;

		return $this;
	}

	/**
	 * Gets table prefix.
	 *
	 * @return string
	 */
	public function getPrefix(): string
	{
		return $this->prefix;
	}

	/**
	 * Sets table prefix.
	 *
	 * @param string $prefix
	 *
	 * @return $this
	 */
	public function setPrefix(string $prefix): self
	{
		$this->assertNotLocked();
		$this->assertNameNotLocked();

		if (!empty($prefix) && !\preg_match(self::PREFIX_REG, $prefix)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Table "%s" prefix "%s" should match: %s',
					$this->name,
					$prefix,
					self::PREFIX_PATTERN
				)
			);
		}

		$this->prefix = $prefix;

		return $this;
	}

	/**
	 * Lock table name.
	 *
	 * @return $this
	 */
	public function lockName(): self
	{
		$this->locked_name = true;

		return $this;
	}

	/**
	 * Adds a primary key constraint on columns.
	 *
	 * @param array<Column|string> $columns the columns
	 *
	 * @return PrimaryKey
	 *
	 * @throws DBALException
	 */
	public function addPrimaryKeyConstraint(array $columns): PrimaryKey
	{
		$this->assertNotLocked();

		if (!$this->pk_constraint && empty($columns)) {
			throw new DBALException(
				\sprintf(
					'Primary key constraint on table "%s" should have at least one column.',
					$this->getFullName()
				)
			);
		}

		if (!isset($this->pk_constraint)) {
			$constraint_name     = Constraint::normalizeName(\sprintf('pk_%s', $this->getFullName()));
			$this->pk_constraint = new PrimaryKey($constraint_name, $this);
		}

		foreach ($columns as $column_name) {
			if ($column_name instanceof Column) {
				$column_name = $column_name->getName();
			}
			$this->pk_constraint->addColumn($column_name);
		}

		return $this->pk_constraint;
	}

	/**
	 * Adds a foreign key constraint on columns.
	 *
	 * @param null|string           $constraint_name the constraint name
	 * @param Table                 $reference_table the reference table
	 * @param array                 $columns         the columns
	 * @param null|ForeignKeyAction $update_action   the reference column update action
	 * @param null|ForeignKeyAction $delete_action   the reference column delete action
	 *
	 * @return ForeignKey
	 *
	 * @throws DBALException
	 */
	public function addForeignKeyConstraint(
		?string $constraint_name,
		self $reference_table,
		array $columns,
		?ForeignKeyAction $update_action = null,
		?ForeignKeyAction $delete_action = null
	): ForeignKey {
		$this->assertNotLocked();

		if (empty($columns)) {
			throw new DBALException(
				\sprintf(
					'Foreign key constraint on table "%s" cannot be defined without columns.',
					$this->getFullName()
				)
			);
		}

		$is_user_named_fk = true;

		if (empty($constraint_name)) {
			$constraint_name  = $this->defaultForeignKeyName($reference_table);
			$is_user_named_fk = false;
		}

		if (isset($this->fk_constraints[$constraint_name])) {
			if ($is_user_named_fk) {
				throw new DBALException(
					\sprintf(
						'Foreign key "%s" is already defined between the tables "%s" and "%s".',
						$constraint_name,
						$this->getName(),
						$reference_table->getName()
					)
				);
			}

			// only one default foreign key between two tables is allowed
			// any other foreign key constraint should be named or unique

			$suffix = [];

			foreach ($columns as $left => $right) {
				$suffix[] = $left . '_' . $right;
			}

			\sort($suffix);

			$suffix = \implode('_', $suffix);

			$constraint_name = Constraint::normalizeName('fk_' . \md5($constraint_name . '_' . $suffix));

			if (isset($this->fk_constraints[$constraint_name])) {
				throw new DBALException(
					\sprintf(
						'Foreign key constraint between the tables "%s" and "%s" with the same options already exists.',
						$this->getName(),
						$reference_table->getName()
					)
				);
			}
		}

		$fk = $this->fk_constraints[$constraint_name] = new ForeignKey($constraint_name, $this, $reference_table);

		foreach ($columns as $column_name => $reference_column) {
			$fk->addColumn($column_name, $reference_column);
		}

		$update_action && $fk->onUpdate($update_action);
		$delete_action && $fk->onDelete($delete_action);

		return $fk;
	}

	/**
	 * Returns default foreign key name for a given reference table.
	 *
	 * @param Table $reference
	 *
	 * @return string
	 */
	public function defaultForeignKeyName(self $reference): string
	{
		return Constraint::normalizeName(\sprintf('fk_%s_%s', $this->getName(), $reference->getName()));
	}

	/**
	 * Gets a relation by name.
	 *
	 * @param string $name the relation name
	 *
	 * @return null|Relation
	 */
	public function getRelation(string $name): ?Relation
	{
		if ($this->hasRelation($name)) {
			return $this->relations[$name];
		}

		return null;
	}

	/**
	 * Gets relations.
	 *
	 * @return Relation[]
	 */
	public function getRelations(): array
	{
		return $this->relations;
	}

	/**
	 * Gets a virtual relation by name.
	 *
	 * @param string $name the virtual relation name
	 *
	 * @return null|VirtualRelation
	 */
	public function getVirtualRelation(string $name): ?VirtualRelation
	{
		if ($this->hasVirtualRelation($name)) {
			return $this->virtual_relations[$name];
		}

		return null;
	}

	/**
	 * Gets virtual relations.
	 *
	 * @return VirtualRelation[]
	 */
	public function getVirtualRelations(): array
	{
		return $this->virtual_relations;
	}

	/**
	 * Gets a collection by name.
	 *
	 * @param string $name the collection name
	 *
	 * @return null|Collection
	 */
	public function getCollection(string $name): ?Collection
	{
		if ($this->hasCollection($name)) {
			return $this->collections[$name];
		}

		return null;
	}

	/**
	 * Gets collections.
	 *
	 * @return Collection[]
	 */
	public function getCollections(): array
	{
		return $this->collections;
	}

	/**
	 * Gets unique constraints.
	 *
	 * @return UniqueKey[]
	 */
	public function getUniqueKeyConstraints(): array
	{
		return $this->uc_constraints;
	}

	/**
	 * Gets primary key constraint.
	 *
	 * @return null|PrimaryKey
	 */
	public function getPrimaryKeyConstraint(): ?PrimaryKey
	{
		return $this->pk_constraint;
	}

	/**
	 * Gets foreign key constraints.
	 *
	 * @return ForeignKey[]
	 */
	public function getForeignKeyConstraints(): array
	{
		return $this->fk_constraints;
	}

	/**
	 * Checks if the current table has foreign key that refer
	 * to the given columns from the reference table.
	 *
	 * @param Table $reference the reference table
	 * @param array $columns   the foreign columns
	 *
	 * @return bool
	 */
	public function hasForeignColumns(self $reference, array $columns): bool
	{
		$x = \count($columns);

		if ($x) {
			foreach ($this->fk_constraints as /* $fk_name => */ $fk) {
				if (
					$fk->getReferenceTable()
						->getName() === $reference->getName()
				) {
					$fk_columns = \array_flip($fk->getColumnsMapping());

					$y = 0;

					foreach ($columns as $column) {
						if (isset($fk_columns[$column])) {
							++$y;
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
	 * @param Table $reference the reference table
	 *
	 * @return ForeignKey
	 *
	 * @throws DBALException
	 */
	public function getDefaultForeignKeyConstraintFrom(self $reference): ForeignKey
	{
		$fk_name = $this->defaultForeignKeyName($reference);

		if (isset($this->fk_constraints[$fk_name])) {
			return $this->fk_constraints[$fk_name];
		}

		throw new DBALException(
			\sprintf(
				'Foreign key from table "%s" not found in table "%s".',
				$reference->getName(),
				$this->name
			)
		);
	}

	/**
	 * Checks if the table has a default foreign key constraint with column from a given reference table.
	 *
	 * @param Table $reference the reference table
	 *
	 * @return bool
	 */
	public function hasDefaultForeignKeyConstraint(self $reference): bool
	{
		return isset($this->fk_constraints[$this->defaultForeignKeyName($reference)]);
	}

	/**
	 * Checks if the table has unique key constraint.
	 *
	 * @return bool
	 */
	public function hasUniqueKeyConstraint(): bool
	{
		return !empty($this->uc_constraints);
	}

	/**
	 * Checks if a given columns list are the primary key of this table.
	 *
	 * @param array $columns_full_names columns full name list
	 *
	 * @return bool
	 */
	public function isPrimaryKey(array $columns_full_names): bool
	{
		$x = \count($columns_full_names);

		if ($x && $this->pk_constraint) {
			$pk_columns = $this->pk_constraint->getColumns();
			$y          = \count($pk_columns);

			return $x === $y && empty(\array_diff($pk_columns, $columns_full_names));
		}

		return false;
	}

	/**
	 * Gets columns.
	 *
	 * @param bool $include_private if false private column will not be included.
	 *                              Default is true.
	 *
	 * @return Column[]
	 */
	public function getColumns(bool $include_private = true): array
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
	 * Checks if the table has primary key constraint.
	 *
	 * @return bool
	 */
	public function hasPrimaryKeyConstraint(): bool
	{
		return null !== $this->pk_constraint;
	}

	/**
	 * Checks if a given column is part of the primary key of this table.
	 *
	 * @param Column $column
	 *
	 * @return bool
	 */
	public function isPartOfPrimaryKey(Column $column): bool
	{
		if ($this->pk_constraint) {
			$pk_columns = $this->pk_constraint->getColumns();

			return \in_array($column->getFullName(), $pk_columns, true);
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
	public function isForeignKey(array $columns): bool
	{
		$x = \count($columns);

		if ($x) {
			foreach ($this->fk_constraints as $fk) {
				$fk_columns = $fk->getHostColumns();

				$y = \count($fk_columns);

				if ($x === $y && empty(\array_diff($fk_columns, $columns))) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Checks if a given columns list are unique key in this table.
	 *
	 * @param array $columns columns full name list
	 *
	 * @return bool
	 */
	public function isUniqueKey(array $columns): bool
	{
		$x = \count($columns);

		if ($x) {
			foreach ($this->uc_constraints as $uc) {
				$uc_columns = $uc->getColumns();
				$y          = \count($uc_columns);

				if ($x === $y && empty(\array_diff($uc_columns, $columns))) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Prepare data for database query.
	 *
	 * @param array          $row
	 * @param RDBMSInterface $rdbms
	 *
	 * @return array
	 */
	public function doPhpToDbConversion(array $row, RDBMSInterface $rdbms): array
	{
		$out = [];

		foreach ($row as $column => $value) {
			$col = $this->getColumn($column);

			if ($col) {
				$value = $col->getType()
					->phpToDb($value, $rdbms);
			}

			$out[$column] = $value;
		}

		return $out;
	}

	/**
	 * Gets column with a given name.
	 *
	 * @param string $name the column name or full name
	 *
	 * @return null|Column
	 */
	public function getColumn(string $name): ?Column
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
	 * Gets column with a given name or fail.
	 *
	 * @param string $name the column name or full name
	 *
	 * @return Column
	 */
	public function getColumnOrFail(string $name): Column
	{
		$this->assertHasColumn($name);

		if (isset($this->col_full_name_map[$name])) {
			$name = $this->col_full_name_map[$name];
		}

		return $this->columns[$name];
	}

	/**
	 * Asserts if a given column name is defined.
	 *
	 * @param string $name the column name or full name
	 */
	public function assertHasColumn(string $name): void
	{
		if (!$this->hasColumn($name)) {
			throw new DBALRuntimeException(
				\sprintf(
					'The column "%s" does not exists in the table "%s".',
					$name,
					$this->getName()
				)
			);
		}
	}

	/**
	 * Convert db raw data to php data type.
	 *
	 * @param array          $row
	 * @param RDBMSInterface $rdbms
	 *
	 * @return array
	 */
	public function doDbToPhpConversion(array $row, RDBMSInterface $rdbms): array
	{
		foreach ($row as $column => $value) {
			$col = $this->getColumn($column);

			if ($col) {
				$row[$column] = $col->getType()
					->dbToPhp($value, $rdbms);
			}
		}

		return $row;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$options = [
			'diff_key'      => $this->getDiffKey(),
			'singular_name' => $this->singular_name,
			'plural_name'   => $this->plural_name,
		];

		if (!empty($this->meta)) {
			$options['meta'] = $this->meta->toArray();
		}
		if (!empty($this->morph_type)) {
			$options['morph_type'] = $this->morph_type;
		}

		if (self::TABLE_DEFAULT_NAMESPACE !== $this->namespace) {
			$options['namespace'] = $this->namespace;
		}

		if (!empty($this->prefix)) {
			$options['prefix'] = $this->prefix;
		}

		if (!empty($this->charset)) {
			$options['charset'] = $this->charset;
		}

		if (!empty($this->collate)) {
			$options['collate'] = $this->collate;
		}

		if (!empty($this->column_prefix)) {
			$options['column_prefix'] = $this->column_prefix;
		}

		if ($this->private) {
			$options['private'] = $this->private;
		}

		foreach ($this->columns as $column_name => $column) {
			$options['columns'][$column_name] = $column->toArray();
		}

		if ($this->pk_constraint) {
			$options['constraints'][] = $this->pk_constraint->toArray();
		}

		foreach ($this->uc_constraints as $uc) {
			$options['constraints'][] = $uc->toArray();
		}

		foreach ($this->fk_constraints as $fk) {
			$options['constraints'][] = $fk->toArray();
		}

		foreach ($this->relations as $relation) {
			$options['relations'][$relation->getName()] = $relation->toArray();
		}

		return $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDiffKey(): string
	{
		if (empty($this->diff_key)) {
			$this->diff_key = \md5($this->namespace . '/' . $this->name);
		}

		return $this->diff_key;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setDiffKey(string $diff_key): self
	{
		$this->assertNotLocked();

		if (empty($diff_key)) {
			throw new InvalidArgumentException(\sprintf('Table "%s" diff key should not be empty', $this->name));
		}

		$this->diff_key = $diff_key;

		return $this;
	}

	/**
	 * Asserts if we can add the relation to this table.
	 *
	 * @param Relation $relation
	 *
	 * @throws DBALException
	 */
	private function assertCanAddRelation(Relation $relation): void
	{
		$relation_name = $relation->getName();

		if ($relation->getHostTable() !== $this) {
			throw new DBALException(
				\sprintf(
					'Table "%s" should be the host table of the relation "%s" between "%s"(host) and "%s"(target).',
					$this->name,
					$relation_name,
					$relation->getHostTable()
						->getName(),
					$relation->getTargetTable()
						->getName()
				)
			);
		}

		if ($this->hasColumn($relation_name)) {
			throw new DBALException(
				\sprintf(
					'Relation name "%s" conflict with an existing column name or full name in table "%s".',
					$relation_name,
					$this->getName()
				)
			);
		}

		if ($this->hasRelation($relation_name)) {
			throw new DBALException(
				\sprintf(
					'Relation name "%s" conflict with an existing relation name in table "%s".',
					$relation_name,
					$this->getName()
				)
			);
		}

		if ($this->hasVirtualRelation($relation_name)) {
			throw new DBALException(
				\sprintf(
					'Relation name "%s" conflict with an existing virtual relation name in table "%s".',
					$relation_name,
					$this->getName()
				)
			);
		}

		if ($this->hasCollection($relation_name)) {
			throw new DBALException(
				\sprintf(
					'Relation name "%s" conflict with an existing collection name in table "%s".',
					$relation_name,
					$this->getName()
				)
			);
		}
	}

	/**
	 * Asserts if we can add the virtual relation to this table.
	 *
	 * @param VirtualRelation $virtual_relation
	 *
	 * @throws DBALException
	 */
	private function assertCanAddVirtualRelation(VirtualRelation $virtual_relation): void
	{
		$relation_name        = $virtual_relation->getName();
		$host_table_full_name = $virtual_relation->getHostTable()
			->getFullName();

		if ($this->getFullName() !== $host_table_full_name) {
			throw new DBALException(
				\sprintf(
					'Virtual relation "%s" is for table "%s" not table "%s".',
					$relation_name,
					$host_table_full_name,
					$this->getName()
				)
			);
		}

		if ($this->hasColumn($relation_name)) {
			throw new DBALException(
				\sprintf(
					'Virtual relation name "%s" conflict with an existing column name or full name in table "%s".',
					$relation_name,
					$this->getName()
				)
			);
		}

		if ($this->hasVirtualRelation($relation_name)) {
			throw new DBALException(
				\sprintf(
					'Virtual relation name "%s" conflict with an existing virtual relation name in table "%s".',
					$relation_name,
					$this->getName()
				)
			);
		}

		if ($this->hasRelation($relation_name)) {
			throw new DBALException(
				\sprintf(
					'Virtual relation name "%s" conflict with an existing relation name in table "%s".',
					$relation_name,
					$this->getName()
				)
			);
		}

		if ($this->hasCollection($relation_name)) {
			throw new DBALException(
				\sprintf(
					'Virtual relation name "%s" conflict with an existing collection name in table "%s".',
					$relation_name,
					$this->getName()
				)
			);
		}
	}

	/**
	 * Asserts if we can add the collection to this table.
	 *
	 * @param Collection $collection
	 *
	 * @throws DBALException
	 */
	private function assertCanAddCollection(Collection $collection): void
	{
		$name = $collection->getName();

		if ($this->hasColumn($name)) {
			throw new DBALException(
				\sprintf(
					'Cannot use "%s" as collection name, column "%s" exists in table "%s".',
					$name,
					$name,
					$this->getName()
				)
			);
		}

		if ($this->hasCollection($name)) {
			throw new DBALException(
				\sprintf(
					'Cannot override collection "%s" in table "%s".',
					$name,
					$this->getName()
				)
			);
		}

		if ($this->hasRelation($name)) {
			throw new DBALException(
				\sprintf(
					'Collection name and relation name conflict for "%s" in table "%s".',
					$name,
					$this->getName()
				)
			);
		}

		if ($this->hasVirtualRelation($name)) {
			throw new DBALException(
				\sprintf(
					'Collection name and virtual relation name conflict for "%s" in table "%s".',
					$name,
					$this->getName()
				)
			);
		}
	}

	/**
	 * Asserts if we can add the column to this table.
	 *
	 * @param Column $column
	 *
	 * @throws DBALException
	 */
	private function assertCanAddColumn(Column $column): void
	{
		$name = $column->getName();

		try {
			$column->assertIsValid();
		} catch (Throwable $t) {
			throw new DBALException(
				\sprintf(
					'Column "%s" could not be added to table "%s".',
					$name,
					$this->name
				),
				null,
				$t
			);
		}

		// prevents column "name" conflict with another column "name" or "full name"
		if ($this->hasColumn($name)) {
			throw new DBALException(
				\sprintf(
					'The column name "%s" conflict with an existing column name or full name in table "%s".',
					$name,
					$this->getName()
				)
			);
		}

		$full_name = $column->getFullName();

		// prevents column "full name" conflict with another column "name" or "full name"
		if ($this->hasColumn($full_name)) {
			throw new DBALException(
				\sprintf(
					'The column "%s" full name "%s" conflict with an existing column name or full name in table "%s".',
					$name,
					$full_name,
					$this->getName()
				)
			);
		}

		if ($this->hasRelation($name)) {
			throw new DBALException(
				\sprintf(
					'Column name "%s" conflict with an existing relation name in table "%s".',
					$name,
					$this->getName()
				)
			);
		}

		if ($this->hasVirtualRelation($name)) {
			throw new DBALException(
				\sprintf(
					'Column name "%s" conflict with an existing virtual relation name in table "%s".',
					$name,
					$this->getName()
				)
			);
		}
	}
}
