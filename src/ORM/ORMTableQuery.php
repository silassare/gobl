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

namespace Gobl\ORM;

use BadMethodCallException;
use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\FiltersTableScope;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBDelete;
use Gobl\DBAL\Queries\QBInsert;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\DBAL\Relations\Interfaces\LinkInterface;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Interfaces\ORMDeleteOptionsInterface;
use Gobl\ORM\Interfaces\ORMSelectOptionsInterface;
use Gobl\ORM\Interfaces\ORMUpdateOptionsInterface;
use Gobl\ORM\Interfaces\WithExpectedColumnsInterface;
use Gobl\ORM\Interfaces\WithFiltersInterface;
use Gobl\ORM\Interfaces\WithPaginationInterface;
use Gobl\ORM\Utils\Helpers;
use Gobl\ORM\Utils\ORMClassKind;
use Override;
use PHPUtils\Str;
use Throwable;

/**
 * Class ORMTableQuery.
 *
 * @template TEntity of \Gobl\ORM\ORMEntity
 */
abstract class ORMTableQuery extends FiltersTableScope
{
	public const BATCH_HOST_IDENTITY_KEY = 'batch_host_identity_key';

	/** @var RDBMSInterface */
	protected RDBMSInterface $db;

	/** @var QBInterface */
	protected QBInterface $qb;

	/** @var Filters */
	protected Filters $filters;

	protected bool $include_soft_deleted_rows = false;

	/**
	 * ORMTableQuery constructor.
	 *
	 * @param string $namespace  the table namespace
	 * @param string $table_name the table name
	 */
	public function __construct(string $namespace, protected string $table_name)
	{
		$this->db = ORM::getDatabase($namespace);

		parent::__construct($this->db->getTableOrFail($table_name));

		$this->qb = $qb = new QBSelect($this->db);
		// Register the table alias in the QB immediately so that FilterOperand::normalizeOperand()
		// can resolve `table_alias.column#json_path` notation during filter-add time (before find()
		// adds the FROM clause).
		$qb->alias($this->table->getFullName(), $this->table_alias);
		$this->filters = $qb->filters($this);
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		unset($this->db, $this->table, $this->qb, $this->filters);
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo(): array
	{
		return ['instance_of' => static::class];
	}

	/**
	 * Magic method to handle dynamically generated per-column filter methods.
	 *
	 * On the first call for a given table, builds a whitelist of allowed filter methods by
	 * iterating all table columns and their `Type::getAllowedFilterOperators()`. Each entry
	 * maps a camelCase method name (e.g. `whereNameEq`, `whereAgeGt`) to a `[column, Operator]`
	 * pair. The whitelist is cached in a static variable indexed by table name.
	 *
	 * Throws `BadMethodCallException` for any method name not in the whitelist.
	 *
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return ORMTableQuery|static
	 */
	public function __call(string $name, array $arguments)
	{
		/** @var array<string, array<string, array{0:Column, 1:Operator}>> $filters_methods */
		static $filters_methods = [];

		if (!isset($filters_methods[$this->table_name])) {
			/** @var array<string, array{0:Column, 1:Operator}> $methods */
			$methods = [];

			foreach ($this->table->getColumns() as $column) {
				$type = $column->getType();
				foreach ($type->getAllowedFilterOperators() as $operator) {
					$method           = Str::toMethodName('where_' . $operator->getFilterSuffix($column));
					$methods[$method] = [$column, $operator];
				}
			}

			$filters_methods[$this->table_name] = $methods;
		} else {
			$methods = $filters_methods[$this->table_name];
		}

		if (isset($methods[$name])) {
			[$column, $op] = $methods[$name];

			$column->getType()->queryBuilderApplyFilter($this, $column, $op, $arguments);

			return $this;
		}

		throw new BadMethodCallException('Call to undefined method ' . static::class . '::' . $name . '()');
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ORMQueryException
	 */
	#[Override]
	public function assertFilterAllowed(Filter $filter, ?QBInterface $qb = null): void
	{
		try {
			parent::assertFilterAllowed($filter, $qb);
		} catch (Throwable $t) {
			throw new ORMQueryException('GOBL_ORM_FILTER_NOT_ALLOWED', $filter->toArray(), $t);
		}
	}

	/**
	 * Exclude soft deleted rows in select/update query.
	 *
	 * Soft deleted rows are excluded by default.
	 *
	 * @return static
	 */
	public function excludeSoftDeletedRows(): static
	{
		$this->include_soft_deleted_rows = false;

		return $this;
	}

	/**
	 * Alias of {@see Filters::and}.
	 *
	 * @param array|(callable(static):void)|static ...$filters
	 *
	 * @return static
	 */
	public function and(array|callable|self ...$filters): static
	{
		$this->filters->and();

		return $this->where(...$filters);
	}

	/**
	 * Merge a list of filters to the current filters.
	 *
	 * Accepts three filter forms:
	 * - **`static` instance** - merged directly into the current filters; passing `$this` throws.
	 * - **`callable`** - invoked with a fresh `subGroup()` instance to build a nested filter group;
	 * the sub-group filters are merged into the current filters as a single nested group; passing
	 * a callable that does not add any filter throws.
	 * - **`array`** - forwarded as-is to `Filters::where()`.
	 *
	 * @param array|(callable(static):void)|static ...$filters
	 *
	 * @return static
	 */
	public function where(array|callable|self ...$filters): static
	{
		foreach ($filters as $entry) {
			if ($entry instanceof self) {
				if ($entry === $this) {
					throw new ORMRuntimeException(
						\sprintf(
							'Current instance used as sub group, you may need to create a sub group with: %s',
							Str::callableName([$this, 'subGroup'])
						)
					);
				}
				$this->filters->where($entry->filters);
			} elseif (\is_callable($entry)) {
				$sub = $this->subGroup();

				$entry($sub);

				if ($sub->filters->isEmpty()) {
					throw (new ORMRuntimeException(
						\sprintf(
							'The sub-filters group callable should add at least one filter to the provided %s instance, got empty group from callable.',
							static::class
						)
					))
						->suspectCallable($entry);
				}

				$this->filters->where($sub->filters);
			} else {
				$this->filters->where($entry);
			}
		}

		return $this;
	}

	/**
	 * Create a sub group for complex filters.
	 *
	 * The returned instance **shares the same `QBSelect`** as the parent (same bindings, same
	 * alias map) but gets its own `Filters` sub-group instance so its conditions are nested
	 * separately. Pass the returned instance to `where()` or a callable to add grouped conditions.
	 *
	 * @return static
	 */
	public function subGroup(): static
	{
		$instance              = static::new();
		$instance->qb          = $this->qb;
		$instance->filters     = $this->filters->subGroup();
		$instance->table_alias = $this->table_alias;

		return $instance;
	}

	/**
	 * Returns new instance.
	 *
	 * @return static
	 */
	abstract public static function new(): static;

	/**
	 * Alias of {@see Filters::or}.
	 *
	 * @param array|(callable(static):void)|static ...$filters
	 *
	 * @return static
	 */
	public function or(array|callable|self ...$filters): static
	{
		$this->filters->or();

		return $this->where(...$filters);
	}

	/**
	 * Resets this instance filters.
	 *
	 * @return static
	 */
	public function reset(): static
	{
		$this->qb      = $qb = new QBSelect($this->db);
		$this->filters = $qb->filters($this);

		return $this;
	}

	/**
	 * Create a {@see QBInsert} instance for single row insertion.
	 *
	 * @param array $values the column => value map
	 *
	 * @return QBInsert
	 */
	public function insert(array $values): QBInsert
	{
		$ins = new QBInsert($this->db);
		$ins->into($this->table->getFullName())
			->values($this->prepareValuesForInsertion($values));

		return $ins;
	}

	/**
	 * Create a {@see QBInsert} instance for multiple rows insertion.
	 *
	 * @param array<array> $values a two dimensional array of column => value map
	 *
	 * @return QBInsert
	 */
	public function insertMulti(array $values): QBInsert
	{
		$ins = new QBInsert($this->db);
		$ins->into($this->table->getFullName());

		foreach ($values as $entry) {
			$ins->values($this->prepareValuesForInsertion($entry));
		}

		return $ins;
	}

	/**
	 * Delete rows in the table.
	 *
	 * @return QBDelete
	 */
	public function delete(?ORMDeleteOptionsInterface $options = null): QBDelete
	{
		$del = new QBDelete($this->db);
		$del->from($this->table->getFullName(), $this->getTableAlias())
			->bindMergeFrom($this->getBindingSource());

		$this->applyFiltersLogic($del, $options);
		$this->applyPaginationLogic($del, $options);

		return $del;
	}

	/**
	 * Gets table alias.
	 *
	 * @return string
	 */
	public function getTableAlias(): string
	{
		return $this->table_alias;
	}

	/**
	 * Gets a copy of the current filters.
	 *
	 * @return Filters
	 */
	public function getFilters(): Filters
	{
		return $this->filters;
	}

	/**
	 * Gets query data binding source.
	 */
	public function getBindingSource(): QBInterface
	{
		return $this->qb;
	}

	/**
	 * Soft delete rows in the table.
	 *
	 * @return QBUpdate
	 *
	 * @throws DBALException
	 */
	public function softDelete(?ORMDeleteOptionsInterface $options = null): QBUpdate
	{
		$this->table->assertSoftDeletable();

		$del = new QBUpdate($this->db);
		$del->update($this->table->getFullName(), $this->getTableAlias())
			->set([
				Table::COLUMN_SOFT_DELETED    => true,
				Table::COLUMN_SOFT_DELETED_AT => \time(),
			])
			->bindMergeFrom($this->getBindingSource());

		$this->applyFiltersLogic($del, $options);
		$this->applyPaginationLogic($del, $options);
		$this->applySoftDeletedLogic($del, true);

		return $del;
	}

	/**
	 * Update rows in the table.
	 *
	 * @param array $values new values
	 *
	 * @return QBUpdate
	 */
	public function update(array $values, ?ORMUpdateOptionsInterface $options = null): QBUpdate
	{
		if (!\count($values)) {
			throw new ORMRuntimeException('Empty columns, can\'t update.');
		}

		$values = $this->table->doPhpToDbConversion($values, $this->db);

		$upd = new QBUpdate($this->db);
		$upd->update($this->table->getFullName(), $this->getTableAlias())
			->set($values)
			->bindMergeFrom($this->getBindingSource());

		$this->applyFiltersLogic($upd, $options);
		$this->applyPaginationLogic($upd, $options);
		$this->applySoftDeletedLogic($upd);

		return $upd;
	}

	/**
	 * Finds rows in the table and returns a new instance of the table's result iterator.
	 *
	 * @param null|ORMDeleteOptionsInterface|ORMSelectOptionsInterface|ORMUpdateOptionsInterface $options
	 * @param null|list<string>                                                                  $restrict_to_columns optional list of column names to scope the expected columns to (e.g. for relations); if provided, only columns in this list are allowed to be selected even if present in `$options->getExpectedColumns()`
	 *
	 * @return ORMResults<TEntity>
	 */
	public function find(ORMDeleteOptionsInterface|ORMSelectOptionsInterface|ORMUpdateOptionsInterface|null $options = null, ?array $restrict_to_columns = []): ORMResults
	{
		$sel = $this->select($options, $restrict_to_columns);

		return $this->wrapResults($sel);
	}

	/**
	 * Create a {@see QBSelect} and apply the options.
	 *
	 * @param null|ORMDeleteOptionsInterface|ORMSelectOptionsInterface|ORMUpdateOptionsInterface $options
	 * @param null|list<string>                                                                  $restrict_to_columns optional list of column names to scope the expected columns to (e.g. for relations); if provided, only columns in this list are allowed to be selected even if present in `$options->getExpectedColumns()`
	 *
	 * @return QBSelect
	 */
	public function select(ORMDeleteOptionsInterface|ORMSelectOptionsInterface|ORMUpdateOptionsInterface|null $options = null, ?array $restrict_to_columns = []): QBSelect
	{
		$table            = $this->table;
		$rtc_map          = \array_fill_keys($restrict_to_columns ?? [], true);
		$expected_columns = $restrict_to_columns ?? [];
		$allowed          = [];

		if (null !== $options && $options instanceof WithExpectedColumnsInterface) {
			$expected_columns = $options->getExpectedColumns() ?? $expected_columns;
		}

		foreach ($expected_columns as $name) {
			if (!empty($rtc_map) && !isset($rtc_map[$name])) {
				throw new ORMRuntimeException(\sprintf('Column "%s" is not allowed in the expected columns list for this query.', $name));
			}

			$col       = $table->getColumnOrFail($name);
			$allowed[] = $col->getFullName();
		}

		$alias = $this->getTableAlias();
		$sel   = new QBSelect($this->db);

		// Register FROM first so the alias is declared before SELECT resolves expected column names if not empty.
		$sel->from($this->table->getFullName(), $alias)
			->select($alias, $allowed)
			->bindMergeFrom($this->getBindingSource());

		$this->applyFiltersLogic($sel, $options);
		$this->applyPaginationLogic($sel, $options);
		$this->applySoftDeletedLogic($sel);

		return $sel;
	}

	/**
	 * Create a {@see QBSelect} and apply the current filters and the relation's filters.
	 *
	 * When the relation has a column projection ({@see Relation::getSelect()} is not empty),
	 * only those columns are included in the SELECT clause.
	 *
	 * Returns `null` when the link cannot be applied - e.g. a column-based link where a
	 * required entity column value is `null`, making the relation unsatisfiable.
	 */
	public function selectRelatives(
		Relation $relation,
		?ORMEntity $host_entity = null,
		?ORMSelectOptionsInterface $options = null,
	): ?QBSelect {
		Helpers::assertCanManageRelatives($this->table, $relation, $host_entity ? [$host_entity] : []);

		$sel = $this->select($options, $relation->resolveSelectColumns());
		$l   = $relation->getLink();

		if ($l->apply($sel, $host_entity)) {
			return $sel;
		}

		return null;
	}

	/**
	 * Create a {@see QBSelect} and apply the current filters and the batch relation's filters.
	 *
	 * Uses {@see LinkInterface::applyBatch()} to build a single
	 * IN-clause query for all host entities at once.
	 *
	 * When the relation has a column projection ({@see Relation::getSelect()} is not empty),
	 * only those columns are included in the SELECT clause.
	 *
	 * Returns `null` when:
	 * - `$host_entities` is empty, or
	 * - the link type does not support batching (applyBatch returns false).
	 *
	 * @param ORMEntity[] $host_entities non-empty, all from the host table
	 */
	public function selectRelativesBatch(
		Relation $relation,
		array $host_entities,
		ORMDeleteOptionsInterface|ORMSelectOptionsInterface|ORMUpdateOptionsInterface|null $options = null,
	): ?QBSelect {
		if (empty($host_entities)) {
			return null;
		}

		if (!$relation->getHostTable()->hasSinglePKColumn()) {
			return null;
		}

		Helpers::assertCanManageRelatives($this->table, $relation, $host_entities);

		$sel = $this->select($options, $relation->resolveSelectColumns());
		$l   = $relation->getLink();

		if ($l->apply($sel)) {
			$key_col     = $relation->getHostTable()->getPrimaryKeyConstraint()->getColumns()[0];
			$key_col_fqn = $sel->fullyQualifiedName($relation->getHostTable(), $key_col);

			$sel->selectComputed(self::BATCH_HOST_IDENTITY_KEY, $key_col_fqn);

			$ids = [];
			foreach ($host_entities as $host_entity) {
				$ids[] = $host_entity->toIdentityKey();
			}

			$sel->andWhere(
				Filters::fromArray([$key_col_fqn, Operator::IN, $ids], $sel)
			);

			return $sel;
		}

		return null;
	}

	/**
	 * Finds relatives and returns a result set.
	 *
	 * @param Relation                       $relation    the relation to use for fetching relatives
	 * @param null|ORMEntity                 $host_entity the host entity, or null to skip host filtering
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return null|ORMResults<TEntity>
	 */
	public function findRelatives(
		Relation $relation,
		?ORMEntity $host_entity,
		?ORMSelectOptionsInterface $options = null,
	): ?ORMResults {
		$sel = $this->selectRelatives($relation, $host_entity, $options);

		if (null === $sel) {
			return null;
		}

		return $this->wrapResults($sel);
	}

	/**
	 * Finds relatives for multiple host entities in a single batch query and returns a result set.
	 *
	 * @param Relation                       $relation      the relation to use for fetching relatives
	 * @param ORMEntity[]                    $host_entities the host entities
	 * @param null|ORMSelectOptionsInterface $options
	 *
	 * @return null|ORMResults<TEntity>
	 */
	public function findRelativesBatch(
		Relation $relation,
		array $host_entities,
		?ORMSelectOptionsInterface $options = null
	): ?ORMResults {
		$sel = $this->selectRelativesBatch($relation, $host_entities, $options);

		if (null === $sel) {
			return null;
		}

		return $this->wrapResults($sel);
	}

	/**
	 * Include soft deleted rows in select/update/delete query.
	 *
	 * Soft deleted rows are excluded by default.
	 *
	 * @return static
	 */
	public function includeSoftDeletedRows(): static
	{
		$this->include_soft_deleted_rows = true;

		return $this;
	}

	/**
	 * Filters rows in the table.
	 *
	 * @param string      $column   the column name or full name
	 * @param Operator    $operator the operator to use
	 * @param mixed       $value    the filter value
	 * @param null|string $at_path  optional JSON path for JSON column filters
	 *
	 * @return static
	 */
	public function filterBy(string $column, Operator $operator, mixed $value = null, ?string $at_path = null): static
	{
		$this->table->assertHasColumn($column);

		$left = null === $at_path ? $column : $this->table_alias . '.' . $column . '#' . $at_path;

		$this->filters->add($operator, $left, $value);

		return $this;
	}

	/**
	 * Wraps a QBSelect in the table's ORMResults class.
	 *
	 * @return ORMResults<TEntity>
	 */
	protected function wrapResults(QBSelect $sel): ORMResults
	{
		/** @var class-string<ORMResults<TEntity>> $class_name */
		$class_name = ORMClassKind::RESULTS->getClassFQN($this->table);

		/** @var ORMResults<TEntity> */
		return $class_name::new($sel);
	}

	/**
	 * Applies pagination/ordering logic (cursor-based or offset-based) on the given QB.
	 */
	protected function applyPaginationLogic(QBDelete|QBSelect|QBUpdate $qb, ?WithPaginationInterface $options): void
	{
		if (null === $options) {
			return;
		}

		if ($options->isCursorBased()) {
			$cursor_column = Helpers::requireCursorColumn($this->table, $options);
			$cursor        = $options->getCursor();
			$max           = $options->getMax();
			$direction     = \strtoupper($options->getCursorDirection() ?? 'ASC');

			$col_fqn = $this->getTableAlias() . '.' . $cursor_column->getFullName();

			$qb->orderBy([$col_fqn => $direction]);

			// Fetch max + 1 rows so ORMResults::getItemsWithCursorMeta() can detect has_more without an extra COUNT query.
			if (null !== $max) {
				$qb->limit($max + 1, 0);
			}

			// Add cursor condition when we have a cursor value.
			if (null !== $cursor) {
				$op = ('ASC' === $direction) ? Operator::GT : Operator::LT;
				$qb->andWhere(
					Filters::fromArray([[$col_fqn, $op->value, $cursor]], $qb)
				);
			}
		} else {
			$max    = $options->getMax();
			$offset = $options->getOffset() ?? 0;

			if (null !== $max) {
				$qb->limit($max, $offset);
			}
		}

		$order_by = $options->getOrderBy() ?? [];
		if (!empty($order_by)) {
			$qb->orderBy($order_by);
		}
	}

	/**
	 * Applies the current filters to the given QBSelect/QBUpdate/QBDelete.
	 */
	protected function applyFiltersLogic(QBDelete|QBSelect|QBUpdate $qb, ?WithFiltersInterface $options = null): void
	{
		$safe_filters = $this->getFilters();

		if (!$safe_filters->isEmpty()) {
			$qb->where($safe_filters);
		}

		$additional_filters = $options?->getFilters();

		if (!empty($additional_filters)) {
			try {
				$qb->where(Filters::fromArray($additional_filters, $qb));
			} catch (Throwable $t) {
				throw new ORMQueryException('Failed to apply filters to query.', [
					'_filters' => $additional_filters,
					'_table'   => $this->table->getName(),
				], $t);
			}
		}
	}

	/**
	 * Make sure the soft deleted logic is applied to the query.
	 *
	 * Appends an `IS FALSE` filter on the `soft_deleted` column when both:
	 * - `$include_soft_deleted_rows` is `false` **or** `$force_exclude` is `true`, **and**
	 * - the table actually has a `soft_deleted` column (`isSoftDeletable()`).
	 *
	 * Called automatically by `select()`, `update()`, etc. so callers rarely need this directly.
	 *
	 * @param QBSelect|QBUpdate $qb
	 * @param bool              $force_exclude bypass `$include_soft_deleted_rows` and always exclude
	 */
	protected function applySoftDeletedLogic(QBSelect|QBUpdate $qb, bool $force_exclude = false): void
	{
		if ((!$this->include_soft_deleted_rows || $force_exclude) && $this->table->isSoftDeletable()) {
			$column = $this->table->getColumnOrFail(Table::COLUMN_SOFT_DELETED);
			$filter = $qb->filters()->isFalse($this->getTableAlias() . '.' . $column->getFullName());
			$qb->andWhere($filter);
		}
	}

	/**
	 * Prepare values for insertion by adding missing values, doing a PHP to DB conversion and removing null auto-increment columns.
	 *
	 * @param array $values the column => value map
	 *
	 * @return array the cleaned row ready for insertion
	 */
	private function prepareValuesForInsertion(array $values): array
	{
		$completed = ORM::entity($this->table)->hydrate($values)->toRow();
		$row       = $this->table->doPhpToDbConversion($completed, $this->db);

		// Remove null auto-increment columns: the RDBMS generates the value via
		// AUTO_INCREMENT (MySQL), SERIAL sequence (PostgreSQL), or ROWID (SQLite).
		// Passing an explicit NULL would violate NOT NULL on PostgreSQL SERIAL columns.
		foreach ($this->table->getColumns() as $column) {
			$full_name = $column->getFullName();

			if (
				$column->getType()->isAutoIncremented()
				&& \array_key_exists($full_name, $row)
				&& null === $row[$full_name]
			) {
				unset($row[$full_name]);
			}
		}

		return $row;
	}
}
