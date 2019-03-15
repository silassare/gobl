<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\ORM;

	use Gobl\CRUD\CRUD;
	use Gobl\DBAL\Rule;
	use Gobl\ORM\Exceptions\ORMControllerFormException;

	class ORMControllerBase
	{
		/**
		 * @var array
		 */
		protected $form_fields = [];

		/**
		 * @var array
		 */
		protected $form_fields_mask = [];

		/**
		 * @var \Gobl\DBAL\Db
		 */
		protected $db;

		/**
		 * @var \Gobl\CRUD\CRUD
		 */
		protected $crud;

		/**
		 * @var string
		 */
		protected $table_name;

		/**
		 * ORMController constructor.
		 *
		 * @param string $table_name The table name.
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
		 */
		protected function __construct($table_name)
		{
			$this->table_name = $table_name;
			$this->db         = ORM::getDatabase();
			$table            = $this->db->getTable($table_name);
			$columns          = $table->getColumns();

			// we finds all required fields
			foreach ($columns as $column) {
				$full_name = $column->getFullName();
				$required  = true;
				$type      = $column->getTypeObject();
				if ($type->isAutoIncremented() OR $type->isNullAble() OR !is_null($type->getDefault())) {
					$required = false;
				}

				$this->form_fields[$full_name] = $required;
			}

			$this->crud = new CRUD($table);
		}

		/**
		 * Destructor.
		 */
		public function __destruct()
		{
			$this->db   = null;
			$this->crud = null;
		}

		/**
		 * Get required forms fields.
		 *
		 * @return array
		 */
		protected function getRequiredFields()
		{
			$fields = [];
			foreach ($this->form_fields as $field => $required) {
				if ($required === true) {
					$fields[] = $field;
				}
			}

			return $fields;
		}

		/**
		 * Apply filters to the table query.
		 *
		 * $filters = [
		 *        'name'  => [
		 *            ['eq', 'value1'],
		 *            ['eq', 'value2']
		 *        ],
		 *        'age'   => [
		 *            ['lt' => 40],
		 *            ['gt' => 50]
		 *        ],
		 *        'valid' => 1
		 * ];
		 *
		 * (name = value1 OR name = value2) AND (age < 40 OR age > 50) AND (valid = 1)
		 *
		 * @param \Gobl\ORM\ORMTableQueryBase $query
		 * @param array                       $filters
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
		 */
		final protected function applyFilters(ORMTableQueryBase &$query, array $filters)
		{
			if (empty($filters)) {
				return;
			}

			$operators_map = [
				'eq'          => Rule::OP_EQ,
				'neq'         => Rule::OP_NEQ,
				'lt'          => Rule::OP_LT,
				'lte'         => Rule::OP_LTE,
				'gt'          => Rule::OP_GT,
				'gte'         => Rule::OP_GTE,
				'like'        => Rule::OP_LIKE,
				'not_like'    => Rule::OP_NOT_LIKE,
				'in'          => Rule::OP_IN,
				'not_in'      => Rule::OP_NOT_IN,
				'is_null'     => Rule::OP_IS_NULL,
				'is_not_null' => Rule::OP_IS_NOT_NULL
			];

			$table = $this->db->getTable($this->table_name);

			foreach ($filters as $column => $column_filters) {
				if (!$table->hasColumn($column)) {
					throw new ORMControllerFormException('form_filters_unknown_fields', [$column]);
				}

				if (is_array($column_filters)) {
					foreach ($column_filters as $filter) {
						if (is_array($filter)) {
							if (!isset($filter[0])) {
								throw new ORMControllerFormException('form_filters_invalid', [$column, $filter]);
							}

							$operator_key = $filter[0];

							if (!isset($operators_map[$operator_key])) {
								throw new ORMControllerFormException('form_filters_unknown_operator', [
									$column,
									$filter
								]);
							}

							$safe_value    = true;
							$operator      = $operators_map[$operator_key];
							$value         = null;
							$use_and       = false;
							$value_index   = 1;
							$use_and_index = 2;

							if ($operator === Rule::OP_IS_NULL OR $operator === Rule::OP_IS_NOT_NULL) {
								$use_and_index = 1;// value not needed
							} else {
								if (!isset($filter[$value_index])) {
									throw new ORMControllerFormException('form_filters_missing_value', [
										$column,
										$filter
									]);
								}

								$value = $filter[$value_index];

								if ($operator === Rule::OP_IN OR $operator === Rule::OP_NOT_IN) {
									$safe_value = is_array($value) AND count($value) ? true : false;
								} elseif (!is_scalar($value)) {
									$safe_value = false;
								}

								if (!$safe_value) {
									throw new ORMControllerFormException('form_filters_invalid_value', [
										$column,
										$filter
									]);
								}
							}

							if (isset($filter[$use_and_index])) {
								$a = $filter[$use_and_index];
								if ($a === "and" OR $a === "AND" OR $a === 1 OR $a === true) {
									$use_and = true;
								} elseif ($a === "or" OR $a === "OR" OR $a === 0 OR $a === false) {
									$use_and = false;
								} else {
									throw new ORMControllerFormException('form_filters_invalid', [
										$column,
										$filter
									]);
								}
							}

							$query->filterBy($column, $value, $operator, $use_and);
						} else {
							throw new ORMControllerFormException('form_filters_invalid', [
								$column,
								$filter
							]);
						}
					}
				} else {
					$value = $column_filters;
					$query->filterBy($column, $value, is_null($value) ? Rule::OP_IS_NULL : Rule::OP_EQ);
				}
			}
		}

		/**
		 * Complete form by adding missing fields.
		 *
		 * @param array &$form The form
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		protected function completeForm(array &$form)
		{
			$required_fields = $this->getRequiredFields();
			$completed       = true;
			$missing         = [];

			$table = $this->db->getTable($this->table_name);
			foreach ($required_fields as $field) {
				if (!isset($form[$field])) {
					$column  = $table->getColumn($field);
					$default = $column->getTypeObject()
									  ->getDefault();
					if (is_null($default)) {
						$completed = false;
						$missing[] = $field;
					} else {
						$form[$field] = $default;
					}
				}
			}

			if (!$completed) {
				throw new ORMControllerFormException('form_missing_fields', $missing);
			}
		}

		/**
		 * Asserts that the filters are not empty.
		 *
		 * @param array $filters the row filters
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 */
		protected function assertFiltersNotEmpty(array $filters)
		{
			if (empty($filters)) {
				throw new ORMControllerFormException('form_filters_empty');
			}
		}

		/**
		 * Asserts that there is at least one column to update and
		 * the column(s) to update really exists the table.
		 *
		 * @param array $columns The columns list
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		protected function assertUpdateColumns(array $columns = [])
		{
			if (empty($columns)) {
				throw new ORMControllerFormException('form_no_fields_to_update');
			}

			$table = $this->db->getTable($this->table_name);
			foreach ($columns as $column) {
				if (!$table->hasColumn($column)) {
					throw new ORMControllerFormException('form_unknown_fields', [$column]);
				}
			}
		}

		/**
		 * @return \Gobl\CRUD\CRUD
		 * @throws \Exception
		 */
		public function getCRUD()
		{
			if (!$this->crud) {
				throw new \Exception("Not using CRUD rules");
			}

			return $this->crud;
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
	}