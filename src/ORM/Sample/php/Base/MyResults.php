<?php
	//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_DB_NS\Base;

	use Gobl\DBAL\Db;
	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\ORMResultsBase;

	/**
	 * Class MyResults
	 *
	 * @method array|null|\MY_PROJECT_DB_NS\MyEntity current() This is to help editor infer type in loop (foreach or
	 *         for..).
	 * @method null|\MY_PROJECT_DB_NS\MyEntity fetchClass(bool $strict = true) Fetch the next row into table of the
	 *         entity class instance.
	 * @method \MY_PROJECT_DB_NS\MyEntity[] fetchAllClass(bool $strict = true) Fetch all rows and return array of the
	 *         entity class instance.
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
	}