<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MY_DB_NS\Base;

use Gobl\DBAL\Db;
use Gobl\DBAL\QueryBuilder;
use Gobl\ORM\ORMResultsBase;

/**
 * Class MyResults
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
		parent::__construct($db, $query, \MY_DB_NS\MyEntity::class);
	}

	/**
	 * This is to help editor infer type in loop (foreach or for...)
	 *
	 * @return null|array|\MY_DB_NS\MyEntity
	 */
	public function current()
	{
		return parent::current();
	}

	/**
	 * Fetches  the next row into table of the entity class instance.
	 *
	 * @param bool $strict
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return null|\MY_DB_NS\MyEntity
	 */
	public function fetchClass($strict = true)
	{
		return parent::fetchClass($strict);
	}

	/**
	 * Fetches  all rows and return array of the entity class instance.
	 *
	 * @param bool $strict
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \MY_DB_NS\MyEntity[]
	 */
	public function fetchAllClass($strict = true)
	{
		return parent::fetchAllClass($strict);
	}
}
