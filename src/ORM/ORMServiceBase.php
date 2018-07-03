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

	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Exceptions\BadRequestException;

	abstract class ORMServiceBase extends BaseService
	{
		/**
		 * Removes columns mask.
		 *
		 * @param array $columns_map
		 * @param array $fields
		 *
		 * @return array
		 */
		protected static function removeColumnsMask(array $columns_map, array $fields)
		{
			$values = [];

			foreach ($fields as $field => $value) {
				if (isset($columns_map[$field])) {
					$column_name          = $columns_map[$field];
					$values[$column_name] = $value;
				}
			}

			return $values;
		}

		/**
		 * Mask column name.
		 *
		 * @param array  $columns_map the columns map
		 * @param string $column_name the column name
		 *
		 * @return string
		 */
		protected static function maskColumn(array $columns_map, $column_name)
		{
			$flip = array_flip($columns_map);

			if (isset($flip[$column_name])) {
				return $flip[$column_name];
			}

			return $column_name;
		}

		/**
		 * Remove relation name mask.
		 *
		 * @param array  $relations_map
		 * @param string $relation_name
		 *
		 * @return string
		 */
		protected static function removeRelationMask(array $relations_map, $relation_name)
		{
			if (isset($relations_map[$relation_name])) {
				return $relations_map[$relation_name];
			}

			return $relation_name;
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
		 * @throws \OZONE\OZ\Exceptions\BadRequestException
		 */
		static function getPagination(array $request, $max = 10)
		{
			$offset = 0;
			$page   = 1;
			$_max   = null;

			if (isset($request['max'])) {
				$_max = intval($request['max']);
				if (!is_int($_max) OR $_max <= 0) {
					throw new BadRequestException();
				}
			}

			if (isset($request['page'])) {
				$page = intval($request['page']);
				if (!is_int($page) OR $page <= 0) {
					throw new BadRequestException();
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
	}