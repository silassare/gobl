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

use Gobl\ORM\ORM;
use Gobl\ORM\ORMTableQueryBase;

/**
 * Class MyTableQuery
 */
abstract class MyTableQuery extends ORMTableQueryBase
{
	/**
	 * MyTableQuery constructor.
	 */
	public function __construct()
	{
		parent::__construct(
			ORM::getDatabase('MY_DB_NS'),
			MyEntity::TABLE_NAME,
			\MY_DB_NS\MyResults::class
		);
	}

	/**
	 * Finds rows in the table `my_table` and returns a new instance of the table's result iterator.
	 *
	 * @param null|int $max
	 * @param int      $offset
	 * @param array    $order_by
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 *
	 * @return \MY_DB_NS\MyResults
	 */
	public function find($max = null, $offset = 0, array $order_by = [])
	{
		/* @var \MY_DB_NS\MyResults $results */
		return parent::find($max, $offset, $order_by);
	}

	//__GOBL_QUERY_FILTER_BY_COLUMNS__
}
