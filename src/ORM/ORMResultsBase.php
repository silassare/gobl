<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
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

class ORMResultsBase implements \Countable, \Iterator
{
	/** @var \Gobl\DBAL\Db */
	protected $db;

	/** @var \Gobl\DBAL\QueryBuilder */
	protected $query;

	/** @var int */
	protected $index = 0;

	/** @var int */
	protected $limited_count_cache;

	/** @var int */
	protected $total_count_cache;

	/** @var bool */
	protected $trust_row_count = true;

	/** @var int */
	protected $foreach_count = 0;

	/** @var bool */
	protected $iterate_class = true;

	/** @var \PDOStatement */
	protected $statement;

	/** @var mixed */
	protected $current;

	/** @var string */
	protected $entity_class;

	/**
	 * ORMResultsBase constructor.
	 *
	 * @param \Gobl\DBAL\Db           $db           the db instance
	 * @param \Gobl\DBAL\QueryBuilder $query        the query builder instance
	 * @param string                  $entity_class the table's entity fully qualified class name
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	protected function __construct(Db $db, QueryBuilder $query, $entity_class)
	{
		if ($query->getType() !== QueryBuilder::QUERY_TYPE_SELECT) {
			throw new ORMException('The query should be a selection.');
		}

		$this->db           = $db;
		$this->entity_class = $entity_class;
		$this->query        = $query;
		$driver             = $db->getConnection()
								 ->getAttribute(\PDO::ATTR_DRIVER_NAME);

		// According to the response at: https://stackoverflow.com/a/4911430
		//  - sqlite 3.x does not support rowCount
		//  - so we should not trust rowCount
		$this->trust_row_count = ($driver === 'sqlite' ? false : true);
	}

	/**
	 * Fetches  the next row.
	 *
	 * @param int $fetch_style
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return mixed
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return array
	 */
	public function fetchAll($fetch_style = \PDO::FETCH_ASSOC)
	{
		return $this->getStatement()
					->fetchAll($fetch_style);
	}

	/**
	 * Fetches  the next row into table of the entity class instance.
	 *
	 * @param bool $strict enable/disable strict mode on class fetch
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return mixed
	 */
	public function fetchClass($strict = true)
	{
		$entity_class = $this->entity_class;
		$entity       = new $entity_class(false, $strict);
		$stmt         = $this->getStatement();

		$stmt->setFetchMode(\PDO::FETCH_INTO, $entity);
		$entity = $stmt->fetch();

		if ($entity instanceof $entity_class) {
			$entity->isSaved(true);// the entity is fetched from the database
		}

		return $entity;
	}

	/**
	 * Fetches  all rows and return array of the entity class instance.
	 *
	 * @param bool $strict enable/disable strict mode on class fetch
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return array
	 */
	public function fetchAllClass($strict = true)
	{
		// according to https://phpdelusions.net/pdo/fetch_modes#FETCH_CLASS
		// in PDO::FETCH_CLASS mode, PDO assign class properties before calling a constructor.
		// To amend this behavior, use the following flag \PDO::FETCH_PROPS_LATE.
		$fetch_style = \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE;

		return $this->getStatement()
					->fetchAll($fetch_style, $this->entity_class, [false, $strict]);
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
	 * Returns the key of the current element.
	 *
	 * @return mixed scalar on success, or null on failure
	 */
	public function key()
	{
		return $this->index;
	}

	/**
	 * Count elements of an object.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return int the custom count as an integer
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
	 * @return bool returns true on success or false on failure
	 */
	public function valid()
	{
		return !($this->current === null || $this->current === false);
	}

	/**
	 * Rewind the Iterator to the first element.
	 *
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
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return int
	 */
	public function totalCount()
	{
		if ($this->total_count_cache === null) {
			$this->total_count_cache = $this->query->runTotalRowsCount(false);
		}

		return $this->total_count_cache;
	}

	/**
	 * Returns the current element.
	 */
	public function current()
	{
		return $this->current;
	}

	/**
	 * Runs the current query and returns a statement.
	 *
	 * We lazily run query.
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
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
	 * Fetches  the next row in foreach mode.
	 *
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
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return ['instance_of' => static::class];
	}
}
