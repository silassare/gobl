<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

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
			parent::__construct(MyEntity::TABLE_NAME);
		}

		/**
		 * Finds rows in the table `my_table`.
		 *
		 * @param null|int $max      maximum row to retrieve
		 * @param int      $offset   first row offset
		 * @param array    $order_by order by rules
		 *
		 * @return \MY_PROJECT_DB_NS\MyResults
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function find($max = null, $offset = 0, array $order_by = [])
		{
			$this->prepareFind($max, $offset, $order_by);

			return new \MY_PROJECT_DB_NS\MyResults($this->db, $this->resetQuery());
		}

		//__GOBL_QUERY_FILTER_BY_COLUMNS__
	}