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

use Gobl\DBAL\QueryBuilder;
use Gobl\ORM\ORM;
use Gobl\ORM\ORMControllerBase;
use MY_DB_NS\MyEntity as MyEntityReal;
use MY_DB_NS\MyResults as MyResultsReal;
use MY_DB_NS\MyTableQuery as MyTableQueryReal;

/**
 * Class MyController
 */
abstract class MyController extends ORMControllerBase
{
	/**
	 * MyController constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct(
			ORM::getDatabase('MY_DB_NS'),
			MyEntity::TABLE_NAME,
			MyEntityReal::class,
			MyTableQueryReal::class,
			MyResultsReal::class
		);
	}

	/**
	 * Adds item to `my_table`.
	 *
	 * @param array $values the row values
	 *
	 * @throws \Throwable
	 *
	 * @return \MY_DB_NS\MyEntity
	 */
	public function addItem(array $values = [])
	{
		/* @var \MY_DB_NS\MyEntity $result */
		$result = parent::addItem($values);

		return $result;
	}

	/**
	 * Updates one item in `my_table`.
	 *
	 * The returned value will be:
	 * - `false` when the item was not found
	 * - `MyEntity` when the item was successfully updated,
	 * when there is an error updating you can catch the exception
	 *
	 * @param array $filters    the row filters
	 * @param array $new_values the new values
	 *
	 * @throws \Throwable
	 *
	 * @return bool|\MY_DB_NS\MyEntity
	 */
	public function updateOneItem(array $filters, array $new_values)
	{
		return parent::updateOneItem($filters, $new_values);
	}

	/**
	 * Updates all items in `my_table` that match the given item filters.
	 *
	 * @param array $filters    the row filters
	 * @param array $new_values the new values
	 *
	 * @throws \Throwable
	 *
	 * @return int affected row count
	 */
	public function updateAllItems(array $filters, array $new_values)
	{
		return parent::updateAllItems($filters, $new_values);
	}

	/**
	 * Deletes one item from `my_table`.
	 *
	 * The returned value will be:
	 * - `false` when the item was not found
	 * - `MyEntity` when the item was successfully deleted,
	 * when there is an error deleting you can catch the exception
	 *
	 * @param array $filters the row filters
	 *
	 * @throws \Throwable
	 *
	 * @return bool|\MY_DB_NS\MyEntity
	 */
	public function deleteOneItem(array $filters)
	{
		return parent::deleteOneItem($filters);
	}

	/**
	 * Deletes all items in `my_table` that match the given item filters.
	 *
	 * @param array $filters the row filters
	 *
	 * @throws \Throwable
	 *
	 * @return int affected row count
	 */
	public function deleteAllItems(array $filters)
	{
		return parent::deleteAllItems($filters);
	}

	/**
	 * Gets item from `my_table` that match the given filters.
	 *
	 * The returned value will be:
	 * - `null` when the item was not found
	 * - `MyEntity` otherwise
	 *
	 * @param array $filters  the row filters
	 * @param array $order_by order by rules
	 *
	 * @throws \Throwable
	 *
	 * @return null|\MY_DB_NS\MyEntity
	 */
	public function getItem(array $filters, array $order_by = [])
	{
		/* @var null|\MY_DB_NS\MyEntity $result */
		$result = parent::getItem($filters, $order_by);

		return $result;
	}

	/**
	 * Gets all items from `my_table` that match the given filters.
	 *
	 * @param array    $filters  the row filters
	 * @param null|int $max      maximum row to retrieve
	 * @param int      $offset   first row offset
	 * @param array    $order_by order by rules
	 * @param bool|int $total    total rows without limit
	 *
	 * @throws \Throwable
	 *
	 * @return \MY_DB_NS\MyEntity[]
	 */
	public function getAllItems(array $filters = [], $max = null, $offset = 0, array $order_by = [], &$total = false)
	{
		/** @var \MY_DB_NS\MyEntity[] $results */
		$results = parent::getAllItems($filters, $max, $offset, $order_by, $total);

		return $results;
	}

	/**
	 * Gets all items from `my_table` with a custom query builder instance.
	 *
	 * @param \Gobl\DBAL\QueryBuilder $qb
	 * @param null|int                $max    maximum row to retrieve
	 * @param int                     $offset first row offset
	 * @param bool|int                $total  total rows without limit
	 *
	 * @throws \Throwable
	 *
	 * @return \MY_DB_NS\MyEntity[]
	 */
	public function getAllItemsCustom(QueryBuilder $qb, $max = null, $offset = 0, &$total = false)
	{
		/** @var \MY_DB_NS\MyEntity[] $results */
		$results = parent::getAllItemsCustom($qb, $max, $offset, $total);

		return $results;
	}
}
