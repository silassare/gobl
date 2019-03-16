<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\DBAL\Rule;
	use Gobl\ORM\ORMTableQueryBase;

	/**
	 * Class MyTableQuery
	 *
	 * @method \MY_PROJECT_DB_NS\MyResults  find(int $max = null, int $offset = 0, array $order_by = []) Finds rows in
	 *         the table `my_table` and returns a new instance of the table's result iterator.
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
		//__GOBL_QUERY_FILTER_BY_COLUMNS__
	}