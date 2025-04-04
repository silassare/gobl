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

namespace Gobl\ORM;

use BadMethodCallException;
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
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Utils\ORMClassKind;
use PHPUtils\Str;
use Throwable;

/**
 * Class ORMTableQuery.
 *
 * @template TEntity of \Gobl\ORM\ORMEntity
 */
abstract class ORMTableQuery extends FiltersTableScope
{
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

		$this->qb      = new QBSelect($this->db);
		$this->filters = $this->qb->filters($this);
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
	 * Magic method to handle filters.
	 *
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return $this|ORMTableQuery
	 */
	public function __call(string $name, array $arguments)
	{
		/** @var array<string, array<string, array{0:string, 1:Operator}>> $filters_methods */
		static $filters_methods = [];

		if (!isset($filters_methods[$this->table_name])) {
			/** @var array<string, array{0:string, 1:Operator}> $methods */
			$methods = [];

			foreach ($this->table->getColumns() as $column) {
				$type = $column->getType();
				foreach ($type->getAllowedFilterOperators() as $operator) {
					$method           = Str::toMethodName('where_' . $operator->getFilterSuffix($column));
					$methods[$method] = [$column->getFullName(), $operator];
				}
			}

			$filters_methods[$this->table_name] = $methods;
		} else {
			$methods = $filters_methods[$this->table_name];
		}

		if (isset($methods[$name])) {
			[$column, $op] = $methods[$name];
			$value         = $arguments[0] ?? null;

			return $this->filterBy($op, $column, $value);
		}

		throw new BadMethodCallException('Call to undefined method ' . static::class . '::' . $name . '()');
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ORMQueryException
	 */
	public function assertFilterAllowed(Filter $filter): void
	{
		try {
			parent::assertFilterAllowed($filter);
		} catch (Throwable $t) {
			throw new ORMQueryException('GOBL_ORM_FILTER_NOT_ALLOWED', $filter->toArray(), $t);
		}
	}

	/**
	 * Exclude soft deleted rows in select/update query.
	 *
	 * Soft deleted rows are excluded by default.
	 *
	 * @return $this
	 */
	public function excludeSoftDeletedRows(): static
	{
		$this->include_soft_deleted_rows = false;

		return $this;
	}

	/**
	 * Alias of {@see Filters::and}.
	 *
	 * @param array|callable|static ...$filters
	 *
	 * @return $this
	 */
	public function and(array|callable|self ...$filters): static
	{
		$this->filters->and();

		return $this->where(...$filters);
	}

	/**
	 * Merge a list of filters to the current filters.
	 *
	 * @param array|callable|static ...$filters
	 *
	 * @return $this
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
				$sub    = $this->subGroup();
				$return = $entry($sub);

				if ($return !== $sub) {
					throw (new ORMRuntimeException(
						\sprintf(
							'The sub-filters group callable should return the same instance of "%s" passed as argument.',
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
	 * @return static<TEntity>
	 */
	abstract public static function new(): static;

	/**
	 * Alias of {@see Filters::or}.
	 *
	 * @param array|callable|static ...$filters
	 *
	 * @return $this
	 */
	public function or(array|callable|self ...$filters): static
	{
		$this->filters->or();

		return $this->where(...$filters);
	}

	/**
	 * Resets this instance filters.
	 *
	 * @return $this
	 */
	public function reset(): static
	{
		$this->qb      = new QBSelect($this->db);
		$this->filters = $this->qb->filters($this);

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
		$row = $this->table->doPhpToDbConversion($this->completeRow($values), $this->db);
		$qb  = new QBInsert($this->db);
		$qb->into($this->table->getFullName())
			->values($row);

		return $qb;
	}

	/**
	 * Create a {@see QBInsert} instance for multiple rows insertion.
	 *
	 * @param array<int, array> $values a two dimensional array of column => value map
	 *
	 * @return QBInsert
	 */
	public function insertMulti(array $values): QBInsert
	{
		$qb = new QBInsert($this->db);
		$qb->into($this->table->getFullName());

		foreach ($values as $entry) {
			$entry = $this->completeRow($entry);
			$row   = $this->table->doPhpToDbConversion($entry, $this->db);
			$qb->values($row);
		}

		return $qb;
	}

	/**
	 * Create a {@see QBDelete} instance and apply the current filters.
	 *
	 * @return QBDelete
	 */
	public function delete(): QBDelete
	{
		$del = new QBDelete($this->db);
		$del->from($this->table->getFullName(), $this->getTableAlias())
			->where($this->getFilters())
			->bindMergeFrom($this->getBindingSource());

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
	 * Soft delete rows in the table using the current filters.
	 *
	 * @return QBUpdate
	 *
	 * @throws DBALException
	 */
	public function softDelete(): QBUpdate
	{
		$this->table->assertSoftDeletable();

		$upd = new QBUpdate($this->db);
		$upd->update($this->table->getFullName(), $this->getTableAlias())
			->set([
				Table::COLUMN_SOFT_DELETED    => true,
				Table::COLUMN_SOFT_DELETED_AT => \time(),
			])
			->where($this->getFilters())
			->bindMergeFrom($this->getBindingSource());

		$this->applySoftDeletedLogic($upd, true);

		return $upd;
	}

	/**
	 * Create a {@see QBUpdate} instance and apply the current filters.
	 *
	 * @param array $values new values
	 *
	 * @return QBUpdate
	 */
	public function update(array $values): QBUpdate
	{
		if (!\count($values)) {
			throw new ORMRuntimeException('Empty columns, can\'t update.');
		}

		$values = $this->table->doPhpToDbConversion($values, $this->db);

		$upd = new QBUpdate($this->db);
		$upd->update($this->table->getFullName(), $this->getTableAlias())
			->set($values)
			->where($this->getFilters())
			->bindMergeFrom($this->getBindingSource());

		$this->applySoftDeletedLogic($upd);

		return $upd;
	}

	/**
	 * Finds rows in the table and returns a new instance of the table's result iterator.
	 *
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 *
	 * @return ORMResults<TEntity>
	 */
	public function find(?int $max = null, int $offset = 0, array $order_by = []): ORMResults
	{
		$qb = $this->select($max, $offset, $order_by);

		/** @var ORMResults<TEntity> $class_name */
		$class_name = ORMClassKind::RESULTS->getClassFQN($this->table);

		return $class_name::new($qb);
	}

	/**
	 * Create a {@see QBSelect} and apply the current filters.
	 *
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 *
	 * @return QBSelect
	 */
	public function select(?int $max = null, int $offset = 0, array $order_by = []): QBSelect
	{
		$sel = new QBSelect($this->db);
		$sel->select($this->table_alias)
			->from($this->table->getFullName(), $this->getTableAlias())
			->where($this->getFilters())
			->bindMergeFrom($this->getBindingSource());

		$this->applySoftDeletedLogic($sel);

		if (!empty($order_by)) {
			$sel->orderBy($order_by);
		}

		return $sel->limit($max, $offset);
	}

	/**
	 * Create a {@see QBSelect} and apply the current filters and the relation's filters.
	 */
	public function selectRelatives(
		Relation $relation,
		?ORMEntity $host_entity = null,
		?int $max = null,
		int $offset = 0,
		array $order_by = [],
	): ?QBSelect {
		self::assertCanManageRelatives($this->table, $relation, $host_entity);

		$sel = $this->select($max, $offset, $order_by);
		$l   = $relation->getLink();

		if ($l->apply($sel, $host_entity)) {
			return $sel;
		}

		return null;
	}

	/**
	 * Assert if relatives can be retrieved.
	 *
	 * @param Table          $expected_target_table
	 * @param Relation       $relation
	 * @param null|ORMEntity $host_entity
	 *
	 * @internal
	 */
	public static function assertCanManageRelatives(Table $expected_target_table, Relation $relation, ?ORMEntity $host_entity): void
	{
		$target_table = $relation->getTargetTable();
		$host_table   = $relation->getHostTable();

		if ($target_table !== $expected_target_table) {
			throw new ORMRuntimeException(
				\sprintf(
					'The relation "%s" target table "%s" is not the same as the expected target table "%s".',
					$relation->getName(),
					$target_table->getFullName(),
					$expected_target_table->getFullName()
				)
			);
		}

		if ($host_entity) {
			if (!$host_entity->isSaved()) {
				throw new ORMRuntimeException('Entity should be persisted to get relatives.');
			}

			if ($host_entity::table() !== $host_table) {
				$expected_entity_class = ORMClassKind::ENTITY->getClassFQN($host_table);

				throw new ORMRuntimeException(
					\sprintf(
						'To get relatives for the relation "%s" the entity should be an instance of "%s" not "%s".',
						$relation->getName(),
						$expected_entity_class,
						\get_class($host_entity)
					)
				);
			}
		}
	}

	/**
	 * Include soft deleted rows in select/update/delete query.
	 *
	 * Soft deleted rows are excluded by default.
	 *
	 * @return $this
	 */
	public function includeSoftDeletedRows(): static
	{
		$this->include_soft_deleted_rows = true;

		return $this;
	}

	/**
	 * Filters rows in the table.
	 *
	 * @param Operator $operator the operator to use
	 * @param string   $column   the column name or full name
	 * @param mixed    $value    the filter value
	 *
	 * @return $this
	 */
	protected function filterBy(Operator $operator, string $column, mixed $value = null): static
	{
		$this->table->assertHasColumn($column);

		$this->filters->add($operator, $column, $value);

		return $this;
	}

	/**
	 * Make sure the soft deleted logic is applied to the query.
	 *
	 * @param QBSelect|QBUpdate $qb
	 * @param bool              $force_exclude
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
	 * Complete a row with default values if needed.
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	private function completeRow(array $row): array
	{
		return ORM::entity($this->table)->hydrate($row)->toRow();
	}
}
