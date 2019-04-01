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

	use Gobl\ORM\Exceptions\ORMQueryException;
	use OZONE\OZ\Core\BaseService;

	abstract class ORMServiceBase extends BaseService
	{
		/**
		 * Returns columns from column_map and they value from fields.
		 *
		 * @param array $columns
		 * @param array $map
		 *
		 * @return array
		 */
		protected static function onlyColumns(array $columns, array $map)
		{
			if (empty($columns)) {
				return $map;
			}
			$values = [];

			foreach ($map as $field => $value) {
				if (isset($columns[$field])) {
					$values[$field] = $value;
				}
			}

			return $values;
		}

		/**
		 * Returns request filters parameters
		 *
		 * @param array $columns_map
		 * @param array $form
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getRequestFilters(array $columns_map, array $form)
		{
			if (!isset($form['filters'])) {
				return [];
			}

			$filters = $form['filters'];

			if (!is_array($filters)) {
				throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_FILTERS");
			}

			return static::onlyColumns($columns_map, $filters);
		}

		/**
		 * Returns requested relations list
		 *
		 * @param array $form
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getRequestRelations(array $form)
		{
			if (!isset($form['relations'])) {
				return [];
			}

			$relations = $form['relations'];

			if (!is_string($relations)) {
				throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_RELATIONS");
			}

			if (!strlen($relations)) {
				return [];
			}

			$relations = array_unique(explode('|', $relations));

			foreach ($relations as $relation) {
				if (empty($relation)) {
					throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_RELATIONS");
				}
			}

			return $relations;
		}

		/**
		 * Returns requested collection
		 *
		 * @param array $form
		 *
		 * @return string|null
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getRequestCollection(array $form)
		{
			if (!isset($form['collection'])) {
				return null;
			}

			if (!is_string($form['collection']) OR !strlen($form['collection'])) {
				throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_COLLECTION");
			}

			return $form['collection'];
		}

		/**
		 * Returns request order by parameters
		 *
		 * @param array $columns_map
		 * @param array $form
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getRequestOrderBy(array $columns_map, array $form)
		{
			if (!isset($form['order_by'])) {
				return [];
			}

			if (!is_string($form['order_by']) OR !strlen($form['order_by'])) {
				throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_ORDER_BY");
			}

			$rules    = $form['order_by'];
			$rules    = array_unique(explode("|", $rules));
			$order_by = [];

			foreach ($rules as $rule) {
				if (!empty($rule)) {
					$parts = explode('_', $rule);
					$len   = count($parts);
					if ($len > 1 AND $parts[$len - 1] === 'desc') {
						array_pop($parts);
						$rule            = implode("_", $parts);
						$order_by[$rule] = false;
					} elseif ($len > 1 AND $parts[$len - 1] === 'asc') {
						array_pop($parts);
						$rule            = implode("_", $parts);
						$order_by[$rule] = true;
					} else {
						$order_by[$rule] = true;
					}
				} else {
					throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_ORDER_BY");
				}
			}

			return static::onlyColumns($columns_map, $order_by);
		}

		/**
		 * Returns result pagination.
		 *
		 * ?max=8&page=2    => ['max' => 8, 'page' => 2, 'offset' => 8 ]
		 * ?page=2          => ['max' => default|10, 'page' => 2, 'offset' => 10 ]
		 * ?                => ['max' => null, 'page' => 1, 'offset' => 0 ]
		 *
		 * @param array $form
		 * @param int   $max default max
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getPagination(array $form, $max = 10)
		{
			$offset = 0;
			$page   = 1;
			$_max   = null;

			if (isset($form['max'])) {
				$_max = $form['max'];

				if (!is_numeric($_max) OR ($_max = intval($_max)) <= 0) {
					throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_PAGINATION_MAX");
				}
			}

			if (isset($form['page'])) {
				$page = $form['page'];

				if (!is_numeric($page) OR ($page = intval($page)) <= 0) {
					throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_PAGINATION_PAGE");
				}

				if (!$_max) {
					$_max = $max;
				}

				$offset = ($page - 1) * $_max;
			}

			return [
				"offset" => $offset,
				"max"    => $_max,
				"page"   => $page
			];
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