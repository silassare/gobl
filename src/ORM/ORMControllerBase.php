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
	use Gobl\DBAL\Db;
	use Gobl\DBAL\QueryBuilder;
	use Gobl\DBAL\Table;
	use Gobl\ORM\Exceptions\ORMQueryException;

	class ORMControllerBase
	{
		/**
		 * @var array
		 */
		protected $form_fields = [];

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

		/** @var string */
		protected $entity_class;

		/** @var string */
		protected $table_query_class;

		/** @var string */
		protected $table_results_class;

		/**
		 * ORMController constructor.
		 *
		 * @param \Gobl\DBAL\Db $db                  The database instance.
		 * @param string        $table_name          The table name.
		 * @param string        $entity_class        The table's entity fully qualified class name.
		 * @param string        $table_query_class   The table's query fully qualified class name.
		 * @param string        $table_results_class The table's results iterator fully qualified class name.
		 */
		protected function __construct(Db $db, $table_name, $entity_class, $table_query_class, $table_results_class)
		{
			$this->table_name          = $table_name;
			$this->entity_class        = $entity_class;
			$this->table_query_class   = $table_query_class;
			$this->table_results_class = $table_results_class;
			$this->db                  = $db;
			$table                     = $this->db->getTable($table_name);
			$columns                   = $table->getColumns();

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
		 * @return \Gobl\CRUD\CRUD
		 */
		public function getCRUD()
		{
			return $this->crud;
		}

		/**
		 * Gets required forms fields.
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
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
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
				throw new ORMQueryException('GOBL_ORM_REQUEST_MISSING_FIELDS', $missing);
			}
		}

		/**
		 * Adds item to the table.
		 *
		 * @param array $values The row values
		 *
		 * @return \Gobl\ORM\ORMEntityBase
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function addItem(array $values = [])
		{
			$this->crud->assertCreate($values);

			$this->completeForm($values);

			/** @var \Gobl\ORM\ORMEntityBase $entity */
			$entity = new $this->entity_class;

			$entity->hydrate($values);
			$entity->save();

			$this->crud->getHandler()
					   ->onAfterCreateEntity($entity);

			return $entity;
		}

		/**
		 * Updates one item in the table.
		 *
		 * @param array $filters    the row filters
		 * @param array $new_values the new values
		 *
		 * @return bool|\Gobl\ORM\ORMEntityBase
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateOneItem(array $filters, array $new_values)
		{
			$this->crud->assertUpdate($filters, $new_values);

			static::assertFiltersNotEmpty($filters);
			static::assertUpdateColumns($this->db->getTable($this->table_name), array_keys($new_values));

			$results = $this->findAllItems($filters, 1, 0);

			$entity = $results->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
						   ->onBeforeUpdateEntity($entity);

				$entity->hydrate($new_values);
				$entity->save();

				$this->crud->getHandler()
						   ->onAfterUpdateEntity($entity);

				return $entity;
			} else {
				return false;
			}
		}

		/**
		 * Updates all items in the table that match the given item filters.
		 *
		 * @param array $filters    the row filters
		 * @param array $new_values the new values
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateAllItems(array $filters, array $new_values)
		{
			$this->crud->assertUpdateAll($filters, $new_values);

			static::assertFiltersNotEmpty($filters);
			static::assertUpdateColumns($this->db->getTable($this->table_name), array_keys($new_values));

			/** @var \Gobl\ORM\ORMTableQueryBase $query */
			$query = new $this->table_query_class;

			$query->applyFilters($filters);

			return $query->update($new_values)
						 ->execute();
		}

		/**
		 * Deletes one item from the table.
		 *
		 * @param array $filters the row filters
		 *
		 * @return bool|\Gobl\ORM\ORMEntityBase
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function deleteOneItem(array $filters)
		{
			$this->crud->assertDelete($filters);

			static::assertFiltersNotEmpty($filters);

			$results = $this->findAllItems($filters, 1, 0);

			$entity = $results->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
						   ->onBeforeDeleteEntity($entity);

				/** @var \Gobl\ORM\ORMTableQueryBase $query */
				$query = new $this->table_query_class;

				$query->applyFilters($filters);

				$query->delete()
					  ->execute();

				$this->crud->getHandler()
						   ->onAfterDeleteEntity($entity);

				return $entity;
			} else {
				return false;
			}
		}

		/**
		 * Deletes all items in the table that match the given item filters.
		 *
		 * @param array $filters the row filters
		 *
		 * @return int Affected row count.
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function deleteAllItems(array $filters)
		{
			$this->crud->assertDeleteAll($filters);

			static::assertFiltersNotEmpty($filters);

			/** @var \Gobl\ORM\ORMTableQueryBase $query */
			$query = new $this->table_query_class;

			$query->applyFilters($filters);

			return $query->delete()->execute();
		}

		/**
		 * Gets item from the table that match the given filters.
		 *
		 * @param array $filters  the row filters
		 * @param array $order_by order by rules
		 *
		 * @return null|\Gobl\ORM\ORMEntityBase
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function getItem(array $filters, array $order_by = [])
		{
			$this->crud->assertRead($filters);

			static::assertFiltersNotEmpty($filters);

			$results = $this->findAllItems($filters, 1, 0, $order_by);

			$entity = $results->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
						   ->onAfterReadEntity($entity);
			}

			return $entity;
		}

		/**
		 * Gets all items from the table that match the given filters.
		 *
		 * @param array    $filters  the row filters
		 * @param int|null $max      maximum row to retrieve
		 * @param int      $offset   first row offset
		 * @param array    $order_by order by rules
		 * @param int|bool $total    total rows without limit
		 *
		 * @return \Gobl\ORM\ORMEntityBase[]
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function getAllItems(array $filters = [], $max = null, $offset = 0, array $order_by = [], &$total = false)
		{
			$this->crud->assertReadAll($filters);

			$results = $this->findAllItems($filters, $max, $offset, $order_by);

			$items = $results->fetchAllClass();

			$total = static::totalResultsCount($results, count($items), $max, $offset);

			return $items;
		}

		/**
		 * Gets all items from the table with a custom query builder instance.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $qb
		 * @param int|null                $max    maximum row to retrieve
		 * @param int                     $offset first row offset
		 * @param int|bool                $total  total rows without limit
		 *
		 * @return \Gobl\ORM\ORMEntityBase[]
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
		{
			$filters = [];

			$this->crud->assertReadAll($filters);

			$qb->limit($max, $offset);

			/** @var \Gobl\ORM\ORMResultsBase $results */
			$results = new $this->table_results_class($this->db, $qb);

			$items = $results->fetchAllClass(false);

			$total = static::totalResultsCount($results, count($items), $max, $offset);

			return $items;
		}

		/**
		 * Finds all items in the table that match the given filters.
		 *
		 * @param array    $filters  the row filters
		 * @param int|null $max      maximum row to retrieve
		 * @param int      $offset   first row offset
		 * @param array    $order_by order by rules
		 *
		 * @return \Gobl\ORM\ORMResultsBase
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		protected function findAllItems(array $filters = [], $max = null, $offset = 0, array $order_by = [])
		{
			/** @var \Gobl\ORM\ORMTableQueryBase $query */
			$query = new $this->table_query_class;

			if (!empty($filters)) {
				$query->applyFilters($filters);
			}

			return $query->find($max, $offset, $order_by);
		}

		/**
		 * @param \Gobl\ORM\ORMResultsBase $results
		 * @param int                      $found
		 * @param int|null                 $max
		 * @param int                      $offset
		 *
		 * @return int
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public static function totalResultsCount(ORMResultsBase $results, $found = 0, $max = null, $offset = 0)
		{
			$total = 0;
			if ($total !== false) {
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

			return $total;
		}

		/**
		 * Asserts that the filters are not empty.
		 *
		 * @param array $filters the row filters
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function assertFiltersNotEmpty(array $filters)
		{
			if (empty($filters)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_FILTERS_EMPTY');
			}
		}

		/**
		 * Asserts that there is at least one column to update and
		 * the column(s) to update really exists the table.
		 *
		 * @param Table $table   The table
		 * @param array $columns The columns to update
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function assertUpdateColumns(Table $table, array $columns = [])
		{
			if (empty($columns)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_NO_FIELDS_TO_UPDATE');
			}

			foreach ($columns as $column) {
				if (!$table->hasColumn($column)) {
					throw new ORMQueryException('GOBL_ORM_REQUEST_UNKNOWN_FIELDS', [$column]);
				}
			}
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
