<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL;

	use Gobl\DBAL\Exceptions\DBALException;

	/**
	 * Class QueryBuilder
	 *
	 * Thanks to Contributors of Doctrine DBAL
	 * some concept and code form Doctrine DBAL query builder
	 * are fully or partially used here.
	 *
	 * @package Gobl\DBAL
	 */
	class QueryBuilder
	{
		const QUERY_TYPE_CREATE_TABLE = 1;
		const QUERY_TYPE_SELECT       = 2;
		const QUERY_TYPE_INSERT       = 3;
		const QUERY_TYPE_UPDATE       = 4;
		const QUERY_TYPE_DELETE       = 5;

		/**
		 * @var \Gobl\DBAL\RDBMS
		 */
		private $db;

		/**
		 * Query type.
		 *
		 * @var int
		 */
		private $type;

		private $auto_prefix_char = '*';

		private $options = [
			'from'             => [],
			'where'            => null,
			'table'            => null,
			'updateTableAlias' => '',
			'select'           => [],
			'columns'          => [],
			'joins'            => [],
			'groupBy'          => [],
			'having'           => '',
			'orderBy'          => [],
			'limitOffset'      => null,
			'limitMax'         => null,
			'createTable'      => null
		];

		private $sql                = '';
		private $alias_map          = [];
		private $bound_values       = [];
		private $bound_values_types = [];

		/**
		 * QueryBuilder constructor.
		 *
		 * @param \Gobl\DBAL\Db $db
		 */
		public function __construct(Db $db)
		{
			$this->db = $db;
		}

		public function getBoundValues()
		{
			return $this->bound_values;
		}

		public function getBoundValuesTypes()
		{
			return $this->bound_values_types;
		}

		/**
		 * Executes the query with current .
		 *
		 * Returns type
		 *        int: affected rows count (for DELETE, UPDATE)
		 *        string: last insert id (for INSERT)
		 *      PDOStatement: the statement (for SELECT ...)
		 *
		 * @return int|\PDOStatement|string
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function execute()
		{
			$sql      = $this->getSqlQuery();
			$values   = $this->getBoundValues();
			$types    = $this->getBoundValuesTypes();
			$routines = [
				QueryBuilder::QUERY_TYPE_CREATE_TABLE => 'execute',
				QueryBuilder::QUERY_TYPE_SELECT       => 'select',
				QueryBuilder::QUERY_TYPE_INSERT       => 'insert',
				QueryBuilder::QUERY_TYPE_UPDATE       => 'update',
				QueryBuilder::QUERY_TYPE_DELETE       => 'delete'
			];

			if (!isset($this->type)) {
				throw new DBALException('no query to execute.');
			}

			return call_user_func_array([$this->db, $routines[$this->type]], [$sql, $values, $types]);
		}

		/**
		 * Gets query rule instance.
		 *
		 * @return \Gobl\DBAL\Rule
		 */
		public function rule()
		{
			return new Rule($this);
		}

		/**
		 * Gets query type.
		 *
		 * @return int
		 */
		public function getType()
		{
			return $this->type;
		}

		/**
		 * Gets options.
		 *
		 * @return array
		 */
		public function getOptions()
		{
			return $this->options;
		}

		/**
		 * Automatically prefix column(s) in a given table.
		 *
		 * ```php
		 * $qb = new QueryBuilder($db);
		 * $qb->alias([
		 *    'u' => '*user',
		 *    'c' => '*command'
		 * ]);
		 *
		 * $qb->prefix('u', 'name'); // u.col_prefix_name
		 * $qb->prefix('c', 'id', 'title'); // c.col_prefix_id, c.col_prefix_title
		 * $qb->prefix('user', 'phone'); // tbl_prefix_user.col_prefix_phone
		 *
		 * ```
		 *
		 * @param string $table  the table to use
		 * @param string $column the column to auto prefix
		 *
		 * @return string
		 */
		public function prefix($table, $column)
		{
			$columns = func_get_args();
			array_shift($columns);

			return implode(' , ', $this->prefixColumnsArray($table, $columns, true));
		}

		/**
		 * Automatically prefix column(s) in a given table.
		 *
		 * The table should be defined
		 *
		 * The table could be an alias that was declared
		 * using QueryBuilder#alias
		 *
		 *
		 * @param string $table   the table to use
		 * @param array  $columns the column to auto prefix
		 *
		 * @param bool   $absolute
		 *
		 * @return array
		 */
		public function prefixColumnsArray($table, array $columns, $absolute = false)
		{
			$is_alias = false;
			$saved    = $table;
			$list     = [];

			if (isset($this->alias_map[$table])) {
				$is_alias = true;
				$table    = $this->alias_map[$table];
			}

			$this->db->assertHasTable($table);

			$t    = $this->db->getTable($table);
			$head = ($is_alias) ? $saved : $t->getFullName();

			foreach ($columns as $col) {
				$t->assertHasColumn($col);
				$col_name = $t->getColumn($col)
							  ->getFullName();
				$list[]   = ($absolute ? $head . '.' . $col_name : $col_name);
			}

			return $list;
		}

		public function prefixTable($table)
		{
			if ($table[0] === $this->auto_prefix_char) {
				$table = substr($table, 1);

				if (!$this->db->hasTable($table)) {
					throw new DBALException(sprintf('The table "%s" is not defined.', $table));
				}

				return $this->db->getTable($table)
								->getFullName();
			}

			return $table;
		}

		/**
		 * Adds table(s) alias(es) to query.
		 *
		 * @param array $map aliases map
		 *
		 * @return $this
		 */
		public function alias(array $map)
		{
			foreach ($map as $alias => $table) {
				$table = $this->prefixTable($table);
				$this->useAlias($table, $alias);
			}

			return $this;
		}

		/**
		 * @param string $table
		 * @param string $alias
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		private function useAlias($table, $alias)
		{
			if (empty($alias) OR !is_string($alias)) {
				throw new DBALException(sprintf('invalid alias "%s".', $alias));
			}

			if (isset($this->alias_map[$alias])) {
				if ($this->alias_map[$alias] !== $table) {
					throw new DBALException(sprintf('alias "%s" is already in use by "%s".', $alias, $this->alias_map[$alias]));
				}
			} else {
				$this->alias_map[$alias] = $table;
			}
		}

		/**
		 * Resets bounds parameters.
		 */
		public function resetParameters()
		{
			$this->bound_values       = [];
			$this->bound_values_types = [];
		}

		/**
		 * Binds parameter to query.
		 *
		 * @param int|string $param     The parameter to bind
		 * @param mixed      $value     The value to bind
		 * @param int|null   $type      Any \PDO::PARAM_* constants
		 * @param bool       $overwrite To overwrite positional parameter
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		private function bind($param, $value, $type = null, $overwrite = false)
		{
			if (!is_int($param) AND !is_string($param)) {
				throw new \InvalidArgumentException('Parameter should be of type int for positional or string for named.');
			}

			$dirty = false;
			$key0  = key($this->bound_values);

			if (is_int($param)) {
				if (is_null($key0) OR is_int($key0)) {
					if ($overwrite) {
						$this->bound_values[$param]       = $value;
						$this->bound_values_types[$param] = $type;
					} else {
						$this->bound_values[]       = $value;
						$this->bound_values_types[] = $type;
					}
				} else {
					$dirty = true;
				}
			} else {
				if (is_null($key0) OR is_string($key0)) {
					$this->bound_values[$param]       = $value;
					$this->bound_values_types[$param] = $type;
				} else {
					$dirty = true;
				}
			}

			if ($dirty === true) {
				throw new DBALException('You should not use both named and positional parameters in the same query.');
			}

			return $this;
		}

		/**
		 * Binds parameters array to query.
		 *
		 * @param array $params The params to bind
		 * @param array $types  The params types
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function bindArray(array $params, array $types = [])
		{
			foreach ($params as $param => $value) {
				$type = isset($types[$param]) ? $types[$param] : null;
				$this->bind($param, $value, $type);
			}

			return $this;
		}

		/**
		 * Binds named parameter.
		 *
		 * @param string   $name  The param name to bind
		 * @param mixed    $value The param value
		 * @param int|null $type  Any \PDO::PARAM_* constants
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function bindNamed($name, $value, $type = null)
		{
			$this->bind($name, $value, $type);

			return $this;
		}

		/**
		 * Binds positional parameter.
		 *
		 * @param int      $offset The param offset
		 * @param mixed    $value  The param value
		 * @param int|null $type   Any \PDO::PARAM_* constants
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function bindPositional($offset, $value, $type = null)
		{
			$this->bind($offset, $value, $type, true);

			return $this;
		}

		/**
		 * @param \Gobl\DBAL\Table $table
		 *
		 * @return $this
		 */
		public function createTable(Table $table)
		{
			$this->type                   = QueryBuilder::QUERY_TYPE_CREATE_TABLE;
			$this->options['createTable'] = $table;

			return $this;
		}

		/**
		 * @param string|null $table
		 * @param array       $columns
		 * @param bool        $auto_prefix
		 *
		 * @return $this
		 */
		public function select($table = null, array $columns = [], $auto_prefix = true)
		{
			$this->type = QueryBuilder::QUERY_TYPE_SELECT;
			if (!empty($table)) {
				$auto_prefix = ($table[0] === $this->auto_prefix_char ? true : $auto_prefix);
				$table       = $this->prefixTable($table);

				if (empty($columns)) {
					$this->options['select'][] = $table . '.*';
				} else {
					if ($auto_prefix) {
						if (is_int(key($columns))) {
							$columns = $this->prefixColumnsArray($table, $columns, true);
						} else {
							$keys    = $this->prefixColumnsArray($table, array_keys($columns), true);
							$values  = array_values($columns);
							$columns = array_combine($keys, $values);
						}

						foreach ($columns as $key => $value) {
							$this->options['select'][] = is_int($key) ? $value : $key . ' as ' . $value;
						}
					} else {
						foreach ($columns as $key => $value) {
							$this->options['select'][] = is_int($key) ? $table . '.' . $value : $table . '.' . $key . ' as ' . $value;
						}
					}
				}
			}

			return $this;
		}

		/**
		 * @return $this
		 */
		public function insert()
		{
			$this->type = QueryBuilder::QUERY_TYPE_INSERT;

			return $this;
		}

		/**
		 * @param string      $table
		 * @param string|null $alias
		 *
		 * @return $this
		 */
		public function update($table, $alias = null)
		{
			$this->type             = QueryBuilder::QUERY_TYPE_UPDATE;
			$this->options['table'] = $this->prefixTable($table);

			if (!empty($alias)) {
				$this->useAlias($this->options['table'], $alias);
				$this->options['updateTableAlias'] = $alias;
			}

			return $this;
		}

		/**
		 * @return $this
		 */
		public function delete()
		{
			$this->type = QueryBuilder::QUERY_TYPE_DELETE;

			return $this;
		}

		/**
		 * @param array $columns
		 * @param bool  $auto_prefix
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function set(array $columns, $auto_prefix = true)
		{
			if ($this->type !== QueryBuilder::QUERY_TYPE_UPDATE) {
				throw new DBALException('You should call "QueryBuilder#update" first.');
			}

			$table                    = $this->options['table'];
			$this->options['columns'] = $this->setColumnsOption($table, $columns, $auto_prefix);

			return $this;
		}

		/**
		 * @param string $table
		 * @param array  $columns
		 * @param bool   $auto_prefix
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function into($table, array $columns = [], $auto_prefix = true)
		{
			if ($this->type !== QueryBuilder::QUERY_TYPE_INSERT) {
				throw new DBALException('You should call "QueryBuilder#insert" first.');
			}

			$this->options['table']   = $this->prefixTable($table);
			$this->options['columns'] = $this->setColumnsOption($table, $columns, $auto_prefix);

			return $this;
		}

		/**
		 * @param string $table
		 * @param array  $columns
		 * @param string $auto_prefix
		 *
		 * @return array
		 */
		protected function setColumnsOption($table, array $columns, $auto_prefix)
		{
			if ($auto_prefix) {
				if (is_int(key($columns))) {
					$columns = $this->prefixColumnsArray($table, $columns);
				} else {
					$keys    = $this->prefixColumnsArray($table, array_keys($columns));
					$values  = array_values($columns);
					$columns = array_combine($keys, $values);
				}
			}

			if (is_int(key($columns))) {
				// positional param
				$columns = array_fill_keys($columns, '?');
			}

			return $columns;
		}

		/**
		 * Alias for QueryBuilder#bindArray
		 *
		 * @param array $values
		 * @param array $types
		 *
		 * @return $this
		 */
		public function values(array $values, array $types = [])
		{
			$this->bindArray($values, $types);

			return $this;
		}

		/**
		 * @param array|string $table
		 * @param string|null  $alias
		 *
		 * ```php
		 * QueryBuilder#from('*users');
		 * QueryBuilder#from('*users','u');
		 * QueryBuilder#from(['*users', '*articles' => 'a', 'another_table']);
		 * ```
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function from($table, $alias = null)
		{
			if ($this->type !== QueryBuilder::QUERY_TYPE_SELECT AND $this->type !== QueryBuilder::QUERY_TYPE_DELETE) {
				throw new DBALException('You should call "QueryBuilder#select" or "QueryBuilder#delete" first.');
			}

			if ($this->type === QueryBuilder::QUERY_TYPE_DELETE AND (count($this->options['from']) OR (is_array($table) AND count($table) > 1))) {
				throw new DBALException('You cannot delete from multiple tables at once.');
			}

			if (is_array($table)) {
				foreach ($table as $key => $value) {
					if (is_int($key)) {
						$this->addFromOptions($value);
					} else {
						$this->addFromOptions($key, $value);
					}
				}
			} elseif (is_string($table)) {
				$this->addFromOptions($table, $alias);
			}

			return $this;
		}

		protected function addFromOptions($table, $alias = null)
		{
			$table = $this->prefixTable($table);
			if (!empty($alias)) {
				$this->useAlias($table, $alias);
			}
			$this->options['from'][$table] = $alias;
		}

		/**
		 * @param \Gobl\DBAL\Rule|string|null $condition
		 *
		 * @return $this
		 */
		public function where($condition)
		{
			$this->options['where'] = $condition;

			return $this;
		}

		/**
		 * @param array $groupBy
		 *
		 * @return $this
		 */
		public function groupBy(array $groupBy)
		{
			foreach ($groupBy as $group) {
				if (!empty($group)) {
					$this->options['groupBy'][] = $group;
				}
			}

			return $this;
		}

		/**
		 * @param \Gobl\DBAL\Rule|string|null $condition
		 *
		 * @return $this
		 */
		public function having($condition)
		{
			$this->options['having'] = $condition;

			return $this;
		}

		/**
		 * @param array $orderBy
		 *
		 * @return $this
		 */
		public function orderBy(array $orderBy)
		{
			foreach ($orderBy as $key => $value) {
				if (is_int($key)) {
					$order = $value;
				} else {
					$order     = $key;
					$direction = $value;
					$order     .= ($direction ? ' ASC' : ' DESC');
				}

				$this->options['orderBy'][] = $order;
			}

			return $this;
		}

		/**
		 * Sets limits to the query result.
		 *
		 * @param int $max    maximum result to get
		 * @param int $offset offset of the first result
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function limit($max = null, $offset = 0)
		{
			if (!is_null($max)) {
				if (!is_int($max) OR $max <= 0) {
					throw new DBALException(sprintf('invalid limit max "%s".', $max));
				}
				if (!is_int($offset) OR $offset < 0) {
					throw new DBALException(sprintf('invalid limit offset "%s".', $offset));
				}
				$this->options['limitMax']    = $max;
				$this->options['limitOffset'] = $offset;
			}

			return $this;
		}

		/**
		 * @param string                      $firstTableAlias
		 * @param string                      $secondTable
		 * @param string                      $secondTableAlias
		 * @param \Gobl\DBAL\Rule|string|null $condition
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function innerJoin($firstTableAlias, $secondTable, $secondTableAlias, $condition = null)
		{
			return $this->join('INNER', $firstTableAlias, $secondTable, $secondTableAlias, $condition);
		}

		/**
		 * @param string                      $firstTableAlias
		 * @param string                      $secondTable
		 * @param string                      $secondTableAlias
		 * @param \Gobl\DBAL\Rule|string|null $condition
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function leftJoin($firstTableAlias, $secondTable, $secondTableAlias, $condition = null)
		{
			return $this->join('LEFT', $firstTableAlias, $secondTable, $secondTableAlias, $condition);
		}

		/**
		 * @param string                      $firstTableAlias
		 * @param string                      $secondTable
		 * @param string                      $secondTableAlias
		 * @param \Gobl\DBAL\Rule|string|null $condition
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function rightJoin($firstTableAlias, $secondTable, $secondTableAlias, $condition = null)
		{
			return $this->join('RIGHT', $firstTableAlias, $secondTable, $secondTableAlias, $condition);
		}

		/**
		 * @param string                      $type
		 * @param string                      $firstTableAlias
		 * @param string                      $secondTable
		 * @param string                      $secondTableAlias
		 * @param \Gobl\DBAL\Rule|string|null $condition
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		private function join($type, $firstTableAlias, $secondTable, $secondTableAlias, $condition = null)
		{
			if (!isset($this->alias_map[$firstTableAlias])) {
				throw new DBALException(sprintf('alias "%s" is not defined.', $firstTableAlias));
			}

			$from_table = $this->alias_map[$firstTableAlias];
			if (!isset($this->options['from'][$from_table])) {
				throw new DBALException(sprintf('The table "%s" alias "%s" is not in the "from" part of the query.', $from_table, $firstTableAlias));
			}

			$secondTable = $this->prefixTable($secondTable);

			$this->useAlias($secondTable, $secondTableAlias);

			$this->options['joins'][$firstTableAlias][] = [
				'type'             => $type,
				'secondTable'      => $secondTable,
				'secondTableAlias' => $secondTableAlias,
				'condition'        => $condition
			];

			return $this;
		}

		/**
		 * Returns query string to be executed by your rdbms
		 *
		 * @return string
		 */
		public function getSqlQuery()
		{
			if (empty($this->sql)) {
				$this->sql = $this->db->getRDBMS()
									  ->getQueryGenerator($this)
									  ->buildQuery();
			}

			return $this->sql;
		}

		/**
		 * Alias for \PDO#quote.
		 *
		 * @param mixed    $value
		 * @param int|null $type
		 *
		 * @return string
		 */
		public function quote($value, $type = \PDO::PARAM_STR)
		{
			return $this->db->getConnection()
							->quote($value, $type);
		}
	}
