<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\Exceptions\ORMQueryException;
	use Gobl\ORM\ORMControllerBase;
	use MY_PROJECT_DB_NS\MyEntity as MyEntityReal;
	use MY_PROJECT_DB_NS\MyResults as MyResultsReal;
	use MY_PROJECT_DB_NS\MyTableQuery as MyTableQueryReal;

	/**
	 * Class MyController
	 *
	 * @package MY_PROJECT_DB_NS\Base
	 */
	abstract class MyController extends ORMControllerBase
	{
		/**
		 * MyController constructor.
		 *
		 * @inheritdoc
		 */
		public function __construct()
		{
			parent::__construct(MyEntity::TABLE_NAME);
		}

		/**
		 * Adds item to `my_table`.
		 *
		 * @param array $values the row values
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function addItem(array $values = [])
		{
			$this->crud->assertCreate($values);

			$this->completeForm($values);

			$entity = new MyEntityReal();

			$entity->hydrate($values);
			$entity->save();

			$this->crud->getHandler()
					   ->onAfterCreateEntity($entity);

			return $entity;
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
		 * @throws \Gobl\ORM\Exceptions\ORMControllerFormException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 */
		public function updateOneItem(array $filters, array $new_values)
		{
			$this->crud->assertUpdate($filters, $new_values);

			$this->assertFiltersNotEmpty($filters);
			$this->assertUpdateColumns(array_keys($new_values));

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

			$this->assertFiltersNotEmpty($filters);

			$tableQuery = new MyTableQueryReal();

			$this->applyFilters($tableQuery, $filters);

			$affected = $tableQuery->update($new_values)
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

			$this->assertFiltersNotEmpty($filters);

			$results = $this->findAllItems($filters, 1, 0);

			$entity = $results->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
						   ->onBeforeDeleteEntity($entity);

				$tableQuery = new MyTableQueryReal();

				$this->applyFilters($tableQuery, $filters);

				$tableQuery->delete()
						   ->execute();

				$this->crud->getHandler()
						   ->onAfterDeleteEntity($entity);

				return $entity;
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

			$this->assertFiltersNotEmpty($filters);

			$tableQuery = new MyTableQueryReal();

			$this->applyFilters($tableQuery, $filters);

			$affected = $tableQuery->delete()
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

			$this->assertFiltersNotEmpty($filters);

			$results = $this->findAllItems($filters, 1, 0, $order_by);

			$entity = $results->fetchClass();

			if ($entity) {
				$this->crud->getHandler()
						   ->onAfterReadEntity($entity);
			}

			return $entity;
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

			$total = self::totalResultsCount($results, count($items), $max, $offset);

			return $items;
		}

		/**
		 * Gets all items from `my_table` with a custom query builder instance.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $qb
		 * @param null|int                $max    maximum row to retrieve
		 * @param int                     $offset first row offset
		 * @param int|bool                $total  total rows without limit
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity[]
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
		{
			$filters = [];

			$this->crud->assertReadAll($filters);

			$qb->limit($max, $offset);

			$results = new MyResultsReal($this->db, $qb);

			$items = $results->fetchAllClass(false);

			$total = self::totalResultsCount($results, count($items), $max, $offset);

			return $items;
		}

		/**
		 * @param \MY_PROJECT_DB_NS\MyResults $results
		 * @param int                         $found
		 * @param null|int                    $max
		 * @param int                         $offset
		 *
		 * @return int
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		private static function totalResultsCount(MyResultsReal $results, $found = 0, $max = null, $offset = 0)
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
		 * Gets collection items from `my_table`.
		 *
		 * @param string   $name
		 * @param array    $filters
		 * @param null|int $max
		 * @param int      $offset
		 * @param array    $order_by
		 * @param bool     $total_records
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity[]
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public function getCollectionItems($name, array $filters = [], $max = null, $offset = 0, array $order_by = [], &$total_records = false)
		{
			$table      = $this->db->getTable(MyEntity::TABLE_NAME);
			$collection = $table->getCollection($name);

			if (!$collection) {
				throw new ORMQueryException("QUERY_INVALID_COLLECTION");
			}

			return $collection->run($filters, $max, $offset, $order_by, $total_records);
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
			$tableQuery = new MyTableQueryReal();

			if (!empty($filters)) {
				$this->applyFilters($tableQuery, $filters);
			}

			$results = $tableQuery->find($max, $offset, $order_by);

			return $results;
		}
	}