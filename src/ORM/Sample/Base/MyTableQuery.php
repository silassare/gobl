<?php
//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\DBAL\Rule;
	use Gobl\ORM\Exceptions\ORMException;
	use Gobl\ORM\ORM;

	/**
	 * Class MyTableQuery
	 *
	 * @package MY_PROJECT_DB_NS\Base
	 */
	abstract class MyTableQuery
	{
		/** @var \Gobl\DBAL\Table */
		protected $table;
		/** @var string */
		protected $table_alias;
		/** @var \Gobl\DBAL\Db */
		protected $db;
		/** @var int */
		protected $gen_identifier_counter = 0;
		/** @var \Gobl\DBAL\QueryBuilder */
		protected $qb;
		/** @var \Gobl\DBAL\Rule[] */
		protected $filters = [];
		/** @var array */
		protected $params = [];

		/**
		 * MyTableQuery constructor.
		 */
		public function __construct()
		{
			$this->db          = ORM::getDatabase();
			$this->table       = $this->db->getTable('my_table');
			$this->table_alias = $this->genUniqueAlias();
			$this->qb          = new QueryBuilder($this->db);
		}

		/**
		 * Finds rows in the table `my_table`.
		 *
		 * When filters exists only rows that
		 * satisfy the filters are returned.
		 *
		 * @param null|int $max      maximum row to retrieve
		 * @param int      $offset   first row offset
		 * @param array    $order_by order by rules
		 *
		 * @return \MY_PROJECT_DB_NS\MyResults
		 */
		public function find($max = null, $offset = 0, array $order_by = [])
		{
			$this->qb->select()
					 ->from($this->table->getFullName(), $this->table_alias);

			$rule = $this->_getFiltersRule();
			if (!is_null($rule)) {
				$this->qb->where($rule);
			}

			if (!empty($order_by)) {
				$this->qb->orderBy($order_by);
			}

			$this->qb->limit($max, $offset)
					 ->bindArray($this->params);

			return new \MY_PROJECT_DB_NS\MyResults($this->db, $this->resetQuery());
		}

		/**
		 * Delete rows in the table `my_table`.
		 *
		 * When filters exists only rows that
		 * satisfy the filters are deleted.
		 *
		 * @return \Gobl\DBAL\QueryBuilder
		 */
		public function delete()
		{
			$this->qb->delete()
					 ->from($this->table->getFullName(), $this->table_alias);

			$rule = $this->_getFiltersRule();

			if (!is_null($rule)) {
				$this->qb->where($rule);
			}

			$this->qb->bindArray($this->params);

			return $this->resetQuery();
		}

		/**
		 * Update rows in the table `my_table`.
		 *
		 * When filters exists only rows that
		 * satisfy the filters are updated.
		 *
		 * @param array $set_columns new values
		 *
		 * @return \Gobl\DBAL\QueryBuilder
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function update(array $set_columns)
		{
			if (!count($set_columns)) {
				throw new ORMException('Empty columns, cannot update.');
			}

			$this->qb->update($this->table->getFullName(), $this->table_alias);

			$rule = $this->_getFiltersRule();
			if (!is_null($rule)) {
				$this->qb->where($rule);
			}

			$columns = [];
			foreach ($set_columns as $column => $value) {
				$this->table->assertHasColumn($column);
				$param_key                = $this->genUniqueParamKey();
				$this->params[$param_key] = $value;
				$columns[$column]         = ':' . $param_key;
			}

			$this->qb->set($columns)
					 ->bindArray($this->params);

			return $this->resetQuery();
		}

		/**
		 * Safely update rows in the table `my_table`.
		 *
		 * @param array $old_values old values
		 * @param array $new_values new values
		 *
		 * @return \Gobl\DBAL\QueryBuilder
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function safeUpdate(array $old_values, array $new_values)
		{
			$this->resetQuery();

			foreach ($this->table->getColumns() as $column) {
				$column_name = $column->getFullName();
				if (!array_key_exists($column_name, $old_values)) {
					throw new ORMException(sprintf('Missing column "%s" in old_values.', $column_name));
				}
				if (!array_key_exists($column_name, $new_values)) {
					throw new ORMException(sprintf('Missing column "%s" in new_values.', $column_name));
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
		 * Filters rows in the table `my_table`.
		 *
		 * @param string $column   the column name or full name
		 * @param mixed  $value    the filter value
		 * @param int    $operator the operator to use
		 * @param bool   $use_and  whether to use AND condition
		 *                         to combine multiple rules on the same column
		 *
		 * @return $this|\MY_PROJECT_DB_NS\MyTableQuery
		 *
		 * @throws \Exception
		 */
		public function filterBy($column, $value, $operator = Rule::OP_EQ, $use_and = true)
		{
			// maybe user make change to the table without regenerating the classes
			$this->table->assertHasColumn($column);

			$full_name = $this->table->getColumn($column)
									 ->getFullName();

			if (!isset($this->filters[$full_name])) {
				$rule = $this->filters[$full_name] = new Rule($this->qb);
			} else {
				$rule = $this->filters[$full_name];

				if ($use_and) {
					$rule->andX();
				} else {
					$rule->orX();
				}
			}

			if ($operator === Rule::OP_IN OR $operator === Rule::OP_NOT_IN) {
				if (!is_array($value)) {
					throw new \Exception("IN and NOT IN operators require an array value.");
				}
				$value = $this->qb->arrayToListItems($value);
			} else {
				$param_key                = $this->genUniqueParamKey();
				$this->params[$param_key] = $value;
				$value                    = ':' . $param_key;
			}

			$a = $this->table_alias . '.' . $full_name;
			$rule->conditions([$a => $value], $operator, false);

			return $this;
		}

//__GOBL_QUERY_FILTER_BY_COLUMNS__

		/**
		 * Creates new query builder and returns the old query builder.
		 *
		 * @return \Gobl\DBAL\QueryBuilder
		 */
		protected function resetQuery()
		{
			$qb           = $this->qb;
			$this->qb     = new QueryBuilder($this->db);
			$this->params = [];

			return $qb;
		}

		/**
		 * Returns a rule that include all filters rules.
		 *
		 * @return \Gobl\DBAL\Rule|null
		 */
		protected function _getFiltersRule()
		{
			if (count($this->filters)) {
				/** @var \Gobl\DBAL\Rule $rule */
				$rule = null;
				foreach ($this->filters as $r) {
					if (!$rule) {
						$rule = $r;
					} else {
						$rule->andX($r);
					}
				}

				return $rule;
			}

			return null;
		}

		/**
		 * Generate unique alias.
		 *
		 * @return string
		 */
		protected function genUniqueAlias()
		{
			return '_' . $this->genIdentifier() . '_';
		}

		/**
		 * Generate unique parameter key.
		 *
		 * @return string
		 */
		protected function genUniqueParamKey()
		{
			return '_val_' . $this->genIdentifier();
		}

		/**
		 * Generate unique char sequence.
		 *
		 * infinite possibilities
		 * a,  b  ... z
		 * aa, ab ... az
		 * ba, bb ... bz
		 *
		 * @return string
		 */
		protected function genIdentifier()
		{
			$x    = $this->gen_identifier_counter++;
			$list = range('a', 'z');
			$len  = count($list);
			$a    = '';
			do {
				$r = ($x % $len);
				$n = ($x - $r) / $len;
				$x = $n - 1;
				$a = $list[$r] . $a;
			} while ($n);

			return $a;
		}
	}