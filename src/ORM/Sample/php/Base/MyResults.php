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
			parent::__construct($db, $query);
		}

		/**
		 * @inheritdoc
		 *
		 * @return null|\MY_PROJECT_DB_NS\MyEntity
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function fetchClass($strict = true)
		{
			$entity = new \MY_PROJECT_DB_NS\MyEntity(false, $strict);
			$stmt   = $this->getStatement();

			$stmt->setFetchMode(\PDO::FETCH_INTO, $entity);

			return $stmt->fetch();
		}

		/**
		 * Fetches all rows and return array of MyEntity class instance.
		 *
		 * @param bool $strict enable/disable strict mode on class fetch
		 *
		 * @return \MY_PROJECT_DB_NS\MyEntity[]
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function fetchAllClass($strict = true)
		{
			$fetch_style  = \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE;
			$entity_class = \MY_PROJECT_DB_NS\MyEntity::class;

			return $this->getStatement()
						->fetchAll($fetch_style, $entity_class, [false, $strict]);
		}

		/**
		 * Return the current element.
		 *
		 * This override is to help editor infer item type.
		 *
		 * @return array|null|\MY_PROJECT_DB_NS\MyEntity
		 */
		public function current()
		{
			return parent::current();
		}
	}