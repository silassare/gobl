<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\DBAL\Rule;
	use Gobl\ORM\ORMTableQueryBase;

	/**
	 * Class MyTableQuery
	 *
	 * @package MY_PROJECT_DB_NS\Base
	 */
	abstract class MyTableQuery extends ORMTableQueryBase
	{
		/**
		 * MyTableQuery constructor.
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function __construct()
		{
			parent::__construct(MyEntity::TABLE_NAME, \MY_PROJECT_DB_NS\MyResults::class);
		}

		/**
		 * Finds rows in the table `my_table` and returns a new instance of the table's result iterator.
		 *
		 * @param int|null $max
		 * @param int      $offset
		 * @param array    $order_by
		 *
		 * @return \MY_PROJECT_DB_NS\MyResults
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function find($max = null, $offset = 0, array $order_by = [])
		{
			/** @var \MY_PROJECT_DB_NS\MyResults $results */
			$results = parent::find($max, $offset, $order_by);

			return $results;
		}
		//__GOBL_QUERY_FILTER_BY_COLUMNS__
	}