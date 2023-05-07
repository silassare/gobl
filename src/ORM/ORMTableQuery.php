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

use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Queries\Interfaces\QBInterface;
use Gobl\DBAL\Queries\QBDelete;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Queries\QBUpdate;
use Gobl\DBAL\Queries\QBUtils;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Utils\ORMClassKind;
use PHPUtils\Str;
use Throwable;

/**
 * Class ORMTableQuery.
 */
abstract class ORMTableQuery implements FiltersScopeInterface
{
	/** @var string */
	protected string $table_alias;

	/** @var \Gobl\DBAL\Interfaces\RDBMSInterface */
	protected RDBMSInterface $db;

	/** @var \Gobl\DBAL\Queries\Interfaces\QBInterface */
	protected QBInterface $qb;

	/** @var \Gobl\DBAL\Filters\Filters */
	protected Filters $filters;

	/** @var \Gobl\DBAL\Table */
	protected Table $table;

	protected bool $allow_private_column_in_filters = false;

	/**
	 * ORMTableQuery constructor.
	 *
	 * @param string $namespace  the table namespace
	 * @param string $table_name the table name
	 */
	public function __construct(string $namespace, protected string $table_name)
	{
		$this->db = ORM::getDatabase($namespace);

		$this->table       = $this->db->getTableOrFail($table_name);
		$this->table_alias = QBUtils::newAlias();
		$this->qb          = new QBSelect($this->db);
		$this->filters     = $this->qb->filters($this);
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
	 * Enable or disable filtering on private columns.
	 *
	 * @param bool $allow
	 *
	 * @return $this
	 */
	public function allowPrivateColumnInFilters(bool $allow = true): self
	{
		$this->allow_private_column_in_filters = $allow;

		return $this;
	}

	/**
	 * Creates new instance.
	 *
	 * @return static
	 */
	abstract public static function createInstance(): static;

	/**
	 * Alias of {@see \Gobl\DBAL\Filters\Filters::and()}.
	 *
	 * @param array|callable|static ...$filters
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	public function and(array|self|callable ...$filters): static
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
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	public function where(array|self|callable ...$filters): static
	{
		foreach ($filters as $entry) {
			if ($entry instanceof self) {
				if ($entry === $this) {
					throw new ORMException(\sprintf(
						'Current instance used as sub group, you may need to create a sub group with: %s',
						Str::callableName([$this, 'subGroup'])
					));
				}
				$this->filters->where($entry->filters);
			} elseif (\is_callable($entry)) {
				$sub    = $this->subGroup();
				$return = $entry($sub);

				if ($return !== $sub) {
					throw (new ORMException(\sprintf(
						'The sub-filters group callable should return the same instance of "%s" passed as argument.',
						static::class
					)))
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
	 * @return $this
	 */
	abstract public function subGroup(): static;

	/**
	 * Alias of {@see \Gobl\DBAL\Filters\Filters::or()}.
	 *
	 * @param array|callable|static ...$filters
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	public function or(array|self|callable ...$filters): static
	{
		$this->filters->or();

		return $this->where(...$filters);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function assertFilterAllowed(Filter $filter): void
	{
		try {
			// left operand should be a column
			$column = $this->table->getColumnOrFail($filter->getLeftOperand());

			if (!$this->allow_private_column_in_filters && $column->isPrivate()) {
				throw new ORMRuntimeException('Private column not allowed in filters.');
			}

			$column->getType()
				->assertFilterAllowed($filter);
		} catch (Throwable $t) {
			throw new ORMQueryException('GOBL_ORM_FILTER_NOT_ALLOWED', $filter->toArray(), $t);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getColumnFQName($column_name): string
	{
		if ($col = $this->table->getColumn($column_name)) {
			return $this->table_alias . '.' . $col->getFullName();
		}

		return $column_name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldAllowFiltersScope(FiltersScopeInterface $scope): bool
	{
		return \is_a($scope, static::class);
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
	 * Create a {@see QBDelete} and apply the current filters.
	 *
	 * @return \Gobl\DBAL\Queries\QBDelete
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * Gets filters.
	 *
	 * @return \Gobl\DBAL\Filters\Filters
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
	 * Create a {@see QBUpdate} and apply the current filters.
	 *
	 * @param array $columns_values_map new values
	 *
	 * @return \Gobl\DBAL\Queries\QBUpdate
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	public function update(array $columns_values_map): QBUpdate
	{
		if (!\count($columns_values_map)) {
			throw new ORMException('Empty columns, can\'t update.');
		}

		$values = $this->table->doPhpToDbConversion($columns_values_map, $this->db);

		$upd = new QBUpdate($this->db);
		$upd->update($this->table->getFullName(), $this->getTableAlias())
			->set($values)
			->where($this->getFilters())
			->bindMergeFrom($this->getBindingSource());

		return $upd;
	}

	/**
	 * Finds rows in the table and returns a new instance of the table's result iterator.
	 *
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 *
	 * @return ORMResults
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function find(?int $max = null, int $offset = 0, array $order_by = []): ORMResults
	{
		$qb = $this->select($max, $offset, $order_by);

		/** @var \Gobl\ORM\ORMResults $class_name */
		$class_name = ORMClassKind::RESULTS->getClassFQN($this->table);

		return $class_name::createInstance($qb);
	}

	/**
	 * Create a {@see QBSelect} and apply the current filters.
	 *
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 *
	 * @return \Gobl\DBAL\Queries\QBSelect
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function select(?int $max = null, int $offset = 0, array $order_by = []): QBSelect
	{
		$sel = new QBSelect($this->db);
		$sel->select($this->table_alias)
			->from($this->table->getFullName(), $this->getTableAlias())
			->where($this->getFilters())
			->bindMergeFrom($this->getBindingSource());

		if (!empty($order_by)) {
			$sel->orderBy($order_by);
		}

		return $sel->limit($max, $offset);
	}

	/**
	 * Create a {@see QBSelect} and apply the current filters and the relation's filters.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function selectRelation(
		string $relation,
		?ORMEntity $entity = null,
		?int $max = null,
		int $offset = 0,
		array $order_by = []
	): ?QBSelect {
		$r = $this->table
			->getRelation($relation);

		if (!$r) {
			throw new ORMRuntimeException(\sprintf('Relation "%s" not found in table "%s"', $relation, $this->table->getFullName()));
		}

		$target_qb = static::createInstance();

		$sel = $target_qb->select($max, $offset, $order_by);

		$l = $r->getLink();

		if ($l->apply($sel, $entity)) {
			return $sel;
		}

		return null;
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
}
