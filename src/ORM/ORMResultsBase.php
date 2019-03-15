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

	use Gobl\DBAL\Db;
	use Gobl\DBAL\QueryBuilder;
	use Gobl\ORM\Exceptions\ORMException;

	abstract class ORMResultsBase implements \Countable, \Iterator
	{

		/** @var \Gobl\DBAL\Db */
		protected $db;

		/** @var \Gobl\DBAL\QueryBuilder */
		protected $query;

		/** @var int */
		protected $index = 0;

		/** @var  int */
		protected $limited_count_cache = null;

		/** @var  int */
		protected $total_count_cache = null;

		/** @var bool */
		protected $trust_row_count = true;

		/** @var int */
		protected $foreach_count = 0;

		/** @var bool */
		protected $iterate_class = true;

		/** @var \PDOStatement */
		protected $statement = null;

		/** @var mixed */
		protected $current = null;

		/**
		 * ORMResultsBase constructor.
		 *
		 * @param \Gobl\DBAL\Db           $db
		 * @param \Gobl\DBAL\QueryBuilder $query
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public function __construct(Db $db, QueryBuilder $query)
		{
			if ($query->getType() !== QueryBuilder::QUERY_TYPE_SELECT) {
				throw new ORMException('The query should be a selection.');
			}

			$this->db    = $db;
			$this->query = $query;
			$driver      = $db->getConnection()
							  ->getAttribute(\PDO::ATTR_DRIVER_NAME);
			// TODO search and verify source
			//  - sqlite 3.x does not support rowCount
			//  - so we should not trust rowCount
			$this->trust_row_count = ($driver === 'sqlite' ? false : true);
		}

		/**
		 * Runs the current query and returns a statement.
		 *
		 * We lazily run query.
		 *
		 * @return \PDOStatement
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		protected function getStatement()
		{
			if (!isset($this->statement)) {
				$this->statement = $this->query->execute();
			}

			return $this->statement;
		}

		/**
		 * Fetches the next row in foreach mode.
		 *
		 * @return mixed
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		protected function runFetch()
		{
			if ($this->iterate_class) {
				return $this->fetchClass();
			}

			return $this->fetch();
		}

		/**
		 * Fetches the next row.
		 *
		 * @param int $fetch_style
		 *
		 * @return mixed
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function fetch($fetch_style = \PDO::FETCH_ASSOC)
		{
			return $this->getStatement()
						->fetch($fetch_style);
		}

		/**
		 * Returns an array containing all of the result set rows.
		 *
		 * @param int $fetch_style
		 *
		 * @return array
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function fetchAll($fetch_style = \PDO::FETCH_ASSOC)
		{
			return $this->getStatement()
						->fetchAll($fetch_style);
		}

		/**
		 * Will disable iteration on entity class.
		 *
		 * @return $this
		 */
		public function iterateAssoc()
		{
			$this->iterate_class = false;

			return $this;
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
		 * Count elements of an object.
		 *
		 * @return int The custom count as an integer.
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function count()
		{
			if ($this->limited_count_cache === null) {
				if ($this->trust_row_count === false) {
					$this->limited_count_cache = $this->query->runTotalRowsCount();
				} else {
					$this->limited_count_cache = $this->getStatement()
													  ->rowCount();
				}
			}

			return $this->limited_count_cache;
		}

		/**
		 * Move forward to next element.
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function next()
		{
			$this->current = $this->runFetch();

			if ($this->current) {
				$this->index++;
			}
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
		 * @throws \Gobl\DBAL\Exceptions\DBALException
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
		 * Count rows without limit.
		 *
		 * @return int
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function totalCount()
		{
			if ($this->total_count_cache === null) {
				$this->total_count_cache = $this->query->runTotalRowsCount(false);
			}

			return $this->total_count_cache;
		}

		/**
		 * Return the current element.
		 *
		 * @return mixed
		 */
		public function current()
		{
			return $this->current;
		}

		/**
		 * Fetch the next row into table of the entity class instance.
		 *
		 * @param bool $strict enable/disable strict mode on class fetch
		 *
		 * @return mixed
		 */
		abstract public function fetchClass($strict = true);

		/**
		 * Fetch all rows and return array of the entity class instance.
		 *
		 * @param bool $strict enable/disable strict mode on class fetch
		 *
		 * @return mixed
		 */
		abstract public function fetchAllClass($strict = true);

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