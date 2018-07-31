<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\CRUD\CRUD;
	use Gobl\DBAL\Rule;
	use Gobl\ORM\Exceptions\ORMControllerFormException;
	use Gobl\ORM\ORM;
	use MY_PROJECT_DB_NS\MyEntity as MyEntityReal;
	use MY_PROJECT_DB_NS\MyTableQuery as MyTableQueryReal;

	/**
	 * Class MyController
	 *
	 * @package MY_PROJECT_DB_NS\Base
	 */
	abstract class MyController
	{
		/** @var array */
		protected $form_fields = [];
		/** @var array */
		protected $form_fields_mask = [];

		/**
		 * @var \Gobl\CRUD\CRUD
		 */
		protected $crud;

		/**
		 * MyController constructor.
		 *
		 * @param bool $as_relation
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
		 */
		public function __construct($as_relation = false)
		{
			$table   = ORM::getDatabase()
						  ->getTable(MyEntity::TABLE_NAME);
			$columns = $table->getColumns();

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

			$this->crud = new CRUD($table, $as_relation);
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

			$table = ORM::getDatabase()
						->getTable(MyEntity::TABLE_NAME);
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
		 * Asserts that there is at least one column to update and
		 * the column(s) to update really exists in `my_table`.
		 *
		 * @param array $columns The columns list
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		protected static function assertUpdateColumns(array $columns = [])
		{
			if (empty($columns)) {
				throw new ORMControllerFormException('form_no_fields_to_update');
			}

			$table = ORM::getDatabase()
						->getTable(MyEntity::TABLE_NAME);
			foreach ($columns as $column) {
				if (!$table->hasColumn($column)) {
					throw new ORMControllerFormException('form_unknown_fields', [$column]);
				}
			}
		}

		/**
		 * Asserts that the filters are not empty.
		 *
		 * @param array $filters the row filters
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 */
		protected static function assertFiltersNotEmpty(array $filters)
		{
			if (empty($filters)) {
				throw new ORMControllerFormException('form_filters_empty');
			}
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
		 * @param \MY_PROJECT_DB_NS\Base\MyTableQuery $query
		 * @param array                               $filters
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
		 */
		final protected static function applyFilters(MyTableQuery &$query, array $filters)
		{
			if (empty($filters)) {
				return;
			}

			$operators_map = [
				'eq'       => Rule::OP_EQ,
				'neq'      => Rule::OP_NEQ,
				'lt'       => Rule::OP_LT,
				'lte'      => Rule::OP_LTE,
				'gt'       => Rule::OP_GT,
				'gte'      => Rule::OP_GTE,
				'like'     => Rule::OP_LIKE,
				'not_like' => Rule::OP_NOT_LIKE,
				'in'       => Rule::OP_IN,
				'not_in'   => Rule::OP_NOT_IN
			];

			$table = ORM::getDatabase()
						->getTable(MyEntity::TABLE_NAME);

			foreach ($filters as $column => $column_filters) {
				if (!$table->hasColumn($column)) {
					throw new ORMControllerFormException('form_filters_unknown_fields', [$column]);
				}

				if (is_array($column_filters)) {
					foreach ($column_filters as $filter) {
						if (is_array($filter)) {
							if (count($filter) !== 2 OR !isset($filter[0]) OR !isset($filter[1])) {
								throw new ORMControllerFormException('form_filters_invalid', [$column, $filter]);
							}

							$operator_key = $filter[0];
							$value        = $filter[1];

							if (!isset($operators_map[$operator_key])) {
								throw new ORMControllerFormException('form_filters_unknown_operator', [
									$column,
									$filter
								]);
							}

							$operator   = $operators_map[$operator_key];
							$safe_value = true;

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

							$query->filterBy($column, $value, $operator, false);
						}
					}
				} else {
					$value = $column_filters;
					$query->filterBy($column, $value, Rule::OP_EQ);
				}
			}
		}

		/**
		 * Adds item to `my_table`.
		 *
		 * @param array $values the row values
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function addItem(array $values = [])
		{
			$this->crud->assertCreate($values);

			$this->completeForm($values);

			$my_entity = new MyEntityReal();

			$my_entity->hydrate($values);
			$my_entity->save();

			$this->crud->getHandler()->onAfterCreateEntity($my_entity);

			return $my_entity;
		}

		/**
		 * Updates one item in `my_table`.
		 *
		 * The returned value will be:
		 * - `false` when the item was not found
		 * - `MyEntity` when the item was successfully updated,
		 * when there is an error updating you can catch the exception
		 *
		 * @param array $filters    the row filters
		 * @param array $new_values the new values
		 *
		 * @return bool|\MY_PROJECT_DB_NS\MyEntity
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateOneItem(array $filters, array $new_values)
		{
			$this->crud->assertUpdate($filters, $new_values);

			self::assertFiltersNotEmpty($filters);
			self::assertUpdateColumns(array_keys($new_values));

			$results = $this->findAllItems($filters, 1, 0);

			$my_entity = $results->fetchClass();

			if ($my_entity) {
				$this->crud->getHandler()->onBeforeUpdateEntity($my_entity);

				$my_entity->hydrate($new_values);
				$my_entity->save();

				$this->crud->getHandler()->onAfterUpdateEntity($my_entity);
				return $my_entity;
			} else {
				return false;
			}
		}

		/**
		 * Update all items in `my_table` that match the given item filters.
		 *
		 * @param array $filters    the row filters
		 * @param array $new_values the new values
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateAllItems(array $filters, array $new_values)
		{
			$this->crud->assertUpdateAll($filters, $new_values);

			self::assertFiltersNotEmpty($filters);

			$my_query = new MyTableQueryReal();

			self::applyFilters($my_query, $filters);

			$affected = $my_query->update($new_values)
								 ->execute();

			return $affected;
		}

		/**
		 * Delete one item from `my_table`.
		 *
		 * The returned value will be:
		 * - `false` when the item was not found
		 * - `MyEntity` when the item was successfully deleted,
		 * when there is an error deleting you can catch the exception
		 *
		 * @param array $filters the row filters
		 *
		 * @return bool|\MY_PROJECT_DB_NS\MyEntity
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function deleteOneItem(array $filters)
		{
			$this->crud->assertDelete($filters);

			self::assertFiltersNotEmpty($filters);

			$results = $this->findAllItems($filters, 1, 0);

			$my_entity = $results->fetchClass();

			if ($my_entity) {

				$this->crud->getHandler()->onBeforeDeleteEntity($my_entity);

				$my_query = new MyTableQueryReal();

				self::applyFilters($my_query, $filters);

				$my_query->delete()
						 ->execute();

				$this->crud->getHandler()->onAfterDeleteEntity($my_entity);

				return $my_entity;
			} else {
				return false;
			}
		}

		/**
		 * Delete all items in `my_table` that match the given item filters.
		 *
		 * @param array $filters the row filters
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function deleteAllItems(array $filters)
		{
			$this->crud->assertDeleteAll($filters);

			self::assertFiltersNotEmpty($filters);

			$my_query = new MyTableQueryReal();

			self::applyFilters($my_query, $filters);

			$affected = $my_query->delete()
								 ->execute();

			return $affected;
		}

		/**
		 * Gets item from `my_table` that match the given filters.
		 *
		 * The returned value will be:
		 * - `null` when the item was not found
		 * - `MyEntity` otherwise
		 *
		 * @param array $filters  the row filters
		 * @param array $order_by order by rules
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity|null
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function getItem(array $filters, array $order_by = [])
		{
			$this->crud->assertRead($filters);

			self::assertFiltersNotEmpty($filters);

			$results = $this->findAllItems($filters, 1, 0, $order_by);

			$my_entity = $results->fetchClass();

			if ($my_entity) {
				$this->crud->getHandler()->onAfterReadEntity($my_entity);
			}

			return $my_entity;
		}

		/**
		 * Gets all items from `my_table` that match the given filters.
		 *
		 * @param array    $filters  the row filters
		 * @param null|int $max      maximum row to retrieve
		 * @param int      $offset   first row offset
		 * @param array    $order_by order by rules
		 * @param int|bool $total    total rows without limit
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity[]
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function getAllItems(array $filters = [], $max = null, $offset = 0, array $order_by = [], &$total = false)
		{
			$this->crud->assertReadAll($filters);

			$results = $this->findAllItems($filters, $max, $offset, $order_by);

			$items = $results->fetchAllClass();

			if ($total !== false) {
				$found = count($items);
				if (isset($max)) {
					if ($found < $max) {
						$total = $offset + $found;
					} else {
						$total = $results->totalCount();
					}
				} elseif ($offset === 0) {
					$total = $found;
				} else {
					$total = $results->totalCount();
				}
			}

			return $items;
		}

		/**
		 * Find all items in `my_table` that match the given filters.
		 *
		 * @param array    $filters  the row filters
		 * @param int|null $max      maximum row to retrieve
		 * @param int      $offset   first row offset
		 * @param array    $order_by order by rules
		 *
		 * @return \MY_PROJECT_DB_NS\MyResults
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		private function findAllItems(array $filters = [], $max = null, $offset = 0, array $order_by = [])
		{
			$my_query = new MyTableQueryReal();

			if (!empty($filters)) {
				self::applyFilters($my_query, $filters);
			}

			$results = $my_query->find($max, $offset, $order_by);

			return $results;
		}

		/**
		 * @return \Gobl\CRUD\CRUD
		 * @throws \Exception
		 */
		public function getCrud()
		{
			if (!$this->crud) {
				throw new \Exception("Not using CRUD rules");
			}

			return $this->crud;
		}

		// TODO
		// public function addOneItemRelation(array $filters, $relation, array $relation_values){}

		// TODO
		// public function updateOneItemRelation(array $filters, $relation, array $new_values) {}

		// TODO
		// public function deleteOneItemRelation(array $filters, $relation, $delete_max = 1, $delete_offset = 0) {}

		// TODO
		// public function getOneItemWithRelations(array $filters, array $relations, $max = null, $offset = 0) {}
	}