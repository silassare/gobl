<?php
//__GOBL_HEAD_COMMENT__

	namespace MY_PROJECT_NS\Base;

	use Gobl\DBAL\Db;
	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\Exceptions\ORMException;

	/**
	 * Class MyResults
	 *
	 * @package MY_PROJECT_NS\Base
	 */
	abstract class MyResults implements \Countable, \Iterator
	{
		/** @var \Gobl\DBAL\Db */
		protected $db;
		/** @var \MY_PROJECT_NS\Base\MyTableQuery */
		protected $table_manager;
		/** @var \Gobl\DBAL\QueryBuilder */
		protected $query;
		/** @var int */
		protected $index = 0;
		/** @var array|null|\MY_PROJECT_NS\MyEntity */
		protected $current = null;
		/** @var  int */
		protected $count_cache = null;
		/** @var bool */
		protected $trust_row_count = true;
		/** @var \MY_PROJECT_NS\MyEntity */
		protected $entity = null;
		/** @var int */
		protected $fetch_style = \PDO::FETCH_ASSOC;
		/** @var int */
		protected $foreach_count = 0;
		/** @var bool */
		protected $iterate_class = true;

		/** @var \PDOStatement */
		private $statement = null;

		/**
		 * MyResults constructor.
		 *
		 * @param \Gobl\DBAL\Db               $db
		 * @param \MY_PROJECT_NS\Base\MyTableQuery $table_manager
		 * @param \Gobl\DBAL\QueryBuilder     $query
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function __construct(Db $db, MyTableQuery $table_manager, QueryBuilder $query)
		{
			if ($query->getType() !== QueryBuilder::QUERY_TYPE_SELECT) {
				throw new ORMException('The query should be a selection.');
			}

			$this->db            = $db;
			$this->table_manager = $table_manager;
			$this->query         = $query;
			$driver              = $db->getConnection()
									  ->getAttribute(\PDO::ATTR_DRIVER_NAME);
			// TODO search and verify source
			//  - we should not trust rowCount
			//  - sqlite 3.x does not support rowCount
			$this->trust_row_count = ($driver === 'sqlite' ? false : true);
		}

		/**
		 * Runs the current query and returns a statement.
		 *
		 * We lazily run query.
		 *
		 * @return \PDOStatement
		 */
		protected function getStatement()
		{
			if (!isset($this->statement)) {
				$this->statement = $this->query->execute();
			}

			return $this->statement;
		}

		/**
		 * Will disable iteration on entity class.
		 *
		 * @return $this|\MY_PROJECT_NS\MyResults
		 */
		public function iterateAssoc()
		{
			$this->iterate_class = false;

			return $this;
		}

		/**
		 * Fetches the next row in foreach mode.
		 *
		 * @return array|null|\MY_PROJECT_NS\MyEntity
		 */
		protected function runFetch()
		{
			if ($this->iterate_class) {
				return $this->fetchClass();
			}

			return $this->fetch();
		}

		/**
		 * Fetches the next row into table MyEntity class instance.
		 *
		 * @return null|\MY_PROJECT_NS\MyEntity
		 */
		public function fetchClass()
		{
			if ($this->entity === null) {
				$this->entity = new \MY_PROJECT_NS\MyEntity(false);
			}

			if ($this->fetch_style !== \PDO::FETCH_INTO) {
				$this->getStatement()
					 ->setFetchMode(\PDO::FETCH_INTO, $this->entity);
			}

			return $this->getStatement()
						->fetch();
		}

		/**
		 * Fetches all rows and return array of MyEntity class instance.
		 *
		 * @return \MY_PROJECT_NS\MyEntity[]
		 */
		public function fetchAllClass()
		{
			$this->fetch_style = \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE;
			$entity_class      = \MY_PROJECT_NS\MyEntity::class;

			return $this->getStatement()
						->fetchAll($this->fetch_style, $entity_class, [false]);
		}

		/**
		 * Fetches the next row.
		 *
		 * @param int $fetch_style
		 *
		 * @return mixed
		 */
		public function fetch($fetch_style = \PDO::FETCH_ASSOC)
		{
			$this->fetch_style = $fetch_style;

			return $this->getStatement()
						->fetch($fetch_style);
		}

		/**
		 * Returns an array containing all of the result set rows.
		 *
		 * @param int $fetch_style
		 *
		 * @return array
		 */
		public function fetchAll($fetch_style = \PDO::FETCH_ASSOC)
		{
			$this->fetch_style = $fetch_style;

			return $this->getStatement()
						->fetchAll($fetch_style);
		}

		/**
		 * Return the current element.
		 *
		 * @return array|null|\MY_PROJECT_NS\MyEntity
		 */
		public function current()
		{
			return $this->current;
		}

		/**
		 * Move forward to next element.
		 */
		public function next()
		{
			$this->current = $this->runFetch();

			if ($this->current) {
				$this->index++;
			}
		}

		/**
		 * Return the key of the current element.
		 *
		 * @return mixed scalar on success, or null on failure.
		 */
		public function key()
		{
			return $this->index;
		}

		/**
		 * Checks if current position is valid.
		 *
		 * @return boolean Returns true on success or false on failure.
		 */
		public function valid()
		{
			return !($this->current === null OR $this->current === false);
		}

		/**
		 * Rewind the Iterator to the first element.
		 *
		 * @return void Any returned value is ignored.
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function rewind()
		{
			// not supported
			if ($this->foreach_count) {
				throw new ORMException('You cannot use the same result set in multiple foreach.');
			}

			$this->current = $this->runFetch();

			$this->foreach_count++;
		}

		/**
		 * Count elements of an object.
		 *
		 * @return int The custom count as an integer.
		 */
		public function count()
		{
			if ($this->count_cache === null) {
				if ($this->trust_row_count === false) {
					$sql               = $this->query->getSqlQuery();
					$sql               = 'SELECT ' . 'COUNT(*) FROM (' . $sql . ')';
					$req               = $this->db->execute($sql, $this->query->getBoundValues(), $this->query->getBoundValuesTypes());
					$this->count_cache = (int)$req->fetchColumn();
				} else {
					$this->count_cache = $this->getStatement()
											  ->rowCount();
				}
			}

			return $this->count_cache;
		}
	}