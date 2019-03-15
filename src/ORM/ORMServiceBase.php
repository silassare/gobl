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
		 * @param array $columns_map
		 * @param array $fields
		 *
		 * @return array
		 */
		protected static function onlyColumns(array $columns_map, array $fields)
		{
			$values = [];

			foreach ($fields as $field => $value) {
				if (isset($columns_map[$field])) {
					$values[$field] = $value;
				}
			}

			return $values;
		}

		/**
		 * Returns request filters parameters
		 *
		 * @param array $columns_map
		 * @param array $request
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getRequestFilters(array $columns_map, array $request)
		{
			if (!isset($request['filters'])) {
				return [];
			}

			$filters = $request['filters'];

			if (!is_array($filters)) {
				throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_FILTERS");
			}

			return self::onlyColumns($columns_map, $filters);
		}

		/**
		 * Returns requested relations list
		 *
		 * @param array $request
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getRequestRelations(array $request)
		{
			if (!isset($request['relations'])) {
				return [];
			}

			$relations = $request['relations'];

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
		 * @param array $request
		 *
		 * @return string|null
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getRequestCollection(array $request)
		{
			if (!isset($request['collection'])) {
				return null;
			}

			if (!is_string($request['collection']) OR !strlen($request['collection'])) {
				throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_COLLECTION");
			}

			return $request['collection'];
		}

		/**
		 * Returns request order by parameters
		 *
		 * @param array $columns_map
		 * @param array $request
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getRequestOrderBy(array $columns_map, array $request)
		{
			if (!isset($request['order_by'])) {
				return [];
			}

			if (!is_string($request['order_by']) OR !strlen($request['order_by'])) {
				throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_ORDER_BY");
			}

			$rules    = $request['order_by'];
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

			return self::onlyColumns($columns_map, $order_by);
		}

		/**
		 * Returns result pagination.
		 *
		 * ?max=8&page=2    => ['max' => 8, 'page' => 2, 'offset' => 8 ]
		 * ?page=2          => ['max' => default|10, 'page' => 2, 'offset' => 10 ]
		 * ?                => ['max' => null, 'page' => 1, 'offset' => 0 ]
		 *
		 * @param array $request
		 * @param int   $max default max
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public static function getPagination(array $request, $max = 10)
		{
			$offset = 0;
			$page   = 1;
			$_max   = null;

			if (isset($request['max'])) {
				$_max = $request['max'];

				if (!is_numeric($_max) OR ($_max = intval($_max)) <= 0) {
					throw new ORMQueryException("GOBL_ORM_QUERY_INVALID_PAGINATION_MAX");
				}
			}

			if (isset($request['page'])) {
				$page = $request['page'];

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