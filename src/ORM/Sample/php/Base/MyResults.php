<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\DBAL\Db;
	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ORMResultsBase;

	/**
	 * Class MyResults
	 *
	 * @package MY_PROJECT_DB_NS\Base
	 */
	abstract class MyResults extends ORMResultsBase
	{
		/**
		 * MyResults constructor.
		 *
		 * @param \Gobl\DBAL\Db           $db
		 * @param \Gobl\DBAL\QueryBuilder $query
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function __construct(Db $db, QueryBuilder $query)
		{
			parent::__construct($db, $query, \MY_PROJECT_DB_NS\MyEntity::class);
		}

		/**
		 * This is to help editor infer type in loop (foreach or for...)
		 *
		 * @return array|null|\MY_PROJECT_DB_NS\MyEntity
		 */
		public function current()
		{
			return parent::current();
		}

		/**
		 * Fetch the next row into table of the entity class instance.
		 *
		 * @param bool $strict
		 *
		 * @return null|\MY_PROJECT_DB_NS\MyEntity
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function fetchClass($strict = true)
		{
			return parent::fetchClass($strict);
		}

		/**
		 * Fetch all rows and return array of the entity class instance.
		 *
		 * @param bool $strict
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity[]
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function fetchAllClass($strict = true)
		{
			return parent::fetchAllClass($strict);
		}
	}