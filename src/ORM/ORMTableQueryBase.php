<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\ORM;

use Gobl\DBAL\Db;
use Gobl\DBAL\QueryBuilder;
use Gobl\DBAL\Rule;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Interfaces\ORMFiltersScopeInterface;

class ORMTableQueryBase implements ORMFiltersScopeInterface
{
	/** @var string */
	protected $table_alias;

	/** @var \Gobl\DBAL\Db */
	protected $db;

	/** @var \Gobl\ORM\ORMFilters */
	protected $filters;

	/** @var \Gobl\DBAL\Table */
	protected $table;

	/*@var string */
	protected $table_name;

	/*@var string */
	protected $table_results_class;

	/**
	 * ORMTableQueryBase constructor.
	 *
	 * @param \Gobl\DBAL\Db $db                  the database
	 * @param string        $table_name          the table name
	 * @param string        $table_results_class the table's results iterator fully qualified class name
	 */
	public function __construct(Db $db, $table_name, $table_results_class)
	{
		$this->table_name          = $table_name;
		$this->table_results_class = $table_results_class;
		$this->db                  = $db;
		$this->table               = $this->db->getTable($table_name);
		$this->table_alias         = QueryBuilder::genUniqueTableAlias();
		$this->filters             = new ORMFilters($db, $this);
	}

	/**
	 * Destructor.
	 */
	public function __destruct()
	{
		$this->db      = null;
		$this->table   = null;
		$this->filters = null;
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return ['instance_of' => static::class];
	}

	/**
	 * Deletes rows in the table.
	 *
	 * When filters exists only rows that
	 * satisfy the filters are deleted.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\DBAL\QueryBuilder
	 */
	public function delete()
	{
		$qb = $this->filters->getQueryBuilder();
		$qb->delete()
		   ->from($this->table->getFullName(), $this->table_alias);

		$this->reset();

		return $qb;
	}

	/**
	 * Updates rows in the table.
	 *
	 * When filters exists only rows that
	 * satisfy the filters are updated.
	 *
	 * @param array $set_columns new values
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return \Gobl\DBAL\QueryBuilder
	 */
	public function update(array $set_columns)
	{
		if (!\count($set_columns)) {
			throw new ORMException('Empty columns, can\'t update.');
		}

		$qb = $this->filters->getQueryBuilder();

		$qb->update($this->table->getFullName(), $this->table_alias);

		$params  = [];
		$columns = [];

		foreach ($set_columns as $column => $value) {
			$this->table->assertHasColumn($column);
			$param_key          = QueryBuilder::genUniqueParamKey();
			$params[$param_key] = $value;
			$columns[$column]   = ':' . $param_key;
		}

		$qb->set($columns)
		   ->bindArray($params);

		$this->reset();

		return $qb;
	}

	/**
	 * Safely update rows in the table.
	 *
	 * @param array $old_values old values
	 * @param array $new_values new values
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return \Gobl\DBAL\QueryBuilder
	 */
	public function safeUpdate(array $old_values, array $new_values)
	{
		$this->reset();

		foreach ($this->table->getColumns() as $column) {
			$column_name = $column->getFullName();

			if (!\array_key_exists($column_name, $old_values)) {
				throw new ORMException(\sprintf('Missing column "%s" in old_values.', $column_name));
			}

			if (!\array_key_exists($column_name, $new_values)) {
				throw new ORMException(\sprintf('Missing column "%s" in new_values.', $column_name));
			}
		}

		if ($this->table->hasPrimaryKeyConstraint()) {
			$pk = $this->table->getPrimaryKeyConstraint();

			foreach ($pk->getConstraintColumns() as $key) {
				$this->filterBy($key, $old_values[$key]);
			}
		} else {
			foreach ($old_values as $column => $value) {
				$this->filterBy($column, $value);
			}
		}

		return $this->update($new_values);
	}

	/**
	 * Finds rows in the table `my_table` and returns a new instance of the table's result iterator.
	 *
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \Gobl\ORM\ORMResultsBase
	 */
	public function find($max = null, $offset = 0, array $order_by = [])
	{
		$qb = $this->filters->getQueryBuilder();
		$qb->select()
		   ->from($this->table->getFullName(), $this->table_alias);

		if (!empty($order_by)) {
			$qb->orderBy($order_by);
		}

		$qb->limit($max, $offset);

		$class_name = $this->table_results_class;

		$this->reset();

		return new $class_name($this->db, $qb);
	}

	/**
	 * Filters rows in the table.
	 *
	 * @param string $column   the column name or full name
	 * @param mixed  $value    the filter value
	 * @param int    $operator the operator to use
	 * @param bool   $use_and  whether to use AND condition
	 *                         to combine multiple rules on the same column
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return $this
	 */
	public function filterBy($column, $value, $operator = Rule::OP_EQ, $use_and = true)
	{
		$this->filters->addFilter($column, $value, $operator, $use_and);

		return $this;
	}

	/**
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 *
	 * @return $this
	 */
	public function applyFilters(array $filters)
	{
		$this->filters->addFiltersArray($filters);

		return $this;
	}

	/**
	 * Gets table alias.
	 *
	 * @return string
	 */
	public function getTableAlias()
	{
		return $this->table_alias;
	}

	/**
	 * @inheritDoc
	 */
	public function isFieldAllowed($column)
	{
		return $this->table->hasColumn($column);
	}

	/**
	 * @inheritDoc
	 */
	public function isFilterAllowed($column, $value, $operator)
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getColumnFQName($column)
	{
		return $this->table_alias . '.' . $this->table->getColumn($column)
													  ->getFullName();
	}

	/**
	 * Resets this instance.
	 *
	 * @return $this
	 */
	protected function reset()
	{
		$this->filters = new ORMFilters($this->db, $this);

		return $this;
	}
}
