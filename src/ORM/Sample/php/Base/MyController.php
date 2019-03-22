<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ORM;
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
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function __construct()
		{
			parent::__construct(ORM::getDatabase('MY_PROJECT_DB_NS'), MyEntity::TABLE_NAME, MyEntityReal::class, MyTableQueryReal::class, MyResultsReal::class);
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
			/** @var \MY_PROJECT_DB_NS\MyEntity $result */
			$result = parent::addItem($values);

			return $result;
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
			return parent::updateOneItem($filters, $new_values);
		}

		/**
		 * Updates all items in `my_table` that match the given item filters.
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
			return parent::updateAllItems($filters, $new_values);
		}

		/**
		 * Deletes one item from `my_table`.
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
			return parent::deleteOneItem($filters);
		}

		/**
		 * Deletes all items in `my_table` that match the given item filters.
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
			return parent::deleteAllItems($filters);
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
			/** @var \MY_PROJECT_DB_NS\MyEntity|null $result */
			$result = parent::getItem($filters, $order_by);

			return $result;
		}

		/**
		 * Gets all items from `my_table` that match the given filters.
		 *
		 * @param array    $filters  the row filters
		 * @param int|null $max      maximum row to retrieve
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
			/** @var \MY_PROJECT_DB_NS\MyEntity[] $results */
			$results = parent::getAllItems($filters, $max, $offset, $order_by, $total);

			return $results;
		}

		/**
		 * Gets all items from `my_table` with a custom query builder instance.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $qb
		 * @param int|null                $max    maximum row to retrieve
		 * @param int                     $offset first row offset
		 * @param int|bool                $total  total rows without limit
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity[]
		 * @throws \Gobl\CRUD\Exceptions\CRUDException
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
		{
			/** @var \MY_PROJECT_DB_NS\MyEntity[] $results */
			$results = parent::getAllItemsCustom($qb, $max, $offset, $total);

			return $results;
		}

		/**
		 * Gets collection items from `my_table`.
		 *
		 * @param string   $name
		 * @param array    $filters
		 * @param int|null $max
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
			/** @var \MY_PROJECT_DB_NS\MyEntity[] $results */
			$results = parent::getCollectionItems($name, $filters, $max, $offset, $order_by, $total_records);

			return $results;
		}
	}