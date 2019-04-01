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

	final class ORMForm
	{
		/**
		 * @var array
		 */
		private $request_data;

		/**
		 * @var array
		 */
		private $form_data = [];

		/**
		 * @var string|null
		 */
		private $collection = null;

		/**
		 * @var array
		 */
		private $relations = [];

		/**
		 * @var array
		 */
		private $filters = [];

		/**
		 * @var array
		 */
		private $order_by = [];

		/**
		 * @var int
		 */
		private $page = 1;

		/**
		 * @var int|null
		 */
		private $max = null;

		/**
		 * Request constructor.
		 *
		 * @param array $request_data
		 */
		public function __construct(array $request_data = [])
		{
			$this->request_data = $request_data;

			try {
				$this->parse();
			} catch (\Exception $e) {
				throw new \InvalidArgumentException('Invalid form.', null, $e);
			}
		}

		/**
		 * @param array $columns
		 *
		 * @return array
		 */
		public function getFormData(array $columns = [])
		{
			return self::onlyColumns($columns, $this->form_data);
		}

		/**
		 * @param string $name
		 * @param mixed  $value
		 *
		 * @return \Gobl\ORM\ORMForm
		 */
		public function setField($name, $value)
		{
			$this->form_data[$name] = $value;

			return $this;
		}

		/**
		 * @param string $name
		 *
		 * @return $this
		 */
		public function removeField($name)
		{
			unset($this->form_data[$name]);

			return $this;
		}

		/**
		 * Returns parsed request data.
		 *
		 * @return array
		 */
		public function getParsedRequestData()
		{
			$r = [];

			if (!empty($this->form_data)) {
				$r['data'] = $this->form_data;
			}

			if (!empty($this->collection)) {
				$r['collection'] = $this->collection;
			}

			if (!empty($this->filters)) {
				$r['filters'] = $this->filters;
			}

			if (!empty($this->relations)) {
				$r['relations'] = self::encodeRelations($this->relations);
			}

			if (!empty($this->order_by)) {
				$r['order_by'] = self::encodeOrderBy($this->order_by);
			}

			if (isset($this->max)) {
				$r['max'] = $this->max;
			}

			if (isset($this->page)) {
				$r['page'] = $this->page;
			}

			return $r;
		}

		/**
		 * Returns request max items count
		 *
		 * @return int|null
		 */
		public function getMax()
		{
			return $this->max;
		}

		/**
		 * Returns request page
		 *
		 * @return int
		 */
		public function getPage()
		{
			return $this->page;
		}

		/**
		 * Returns query offset
		 *
		 * @return int
		 */
		public function getOffset()
		{
			if (isset($this->max) AND isset($this->page)) {
				return ($this->page - 1) * $this->max;
			}

			return 0;
		}

		/**
		 * Returns the request order by rules.
		 *
		 * @param array $columns
		 *
		 * @return array
		 */
		public function getOrderBy(array $columns = [])
		{
			return self::onlyColumns($columns, $this->order_by);
		}

		/**
		 * Returns the request filters
		 *
		 * @param array $columns
		 *
		 * @return array
		 */
		public function getFilters(array $columns = [])
		{
			return self::onlyColumns($columns, $this->filters);
		}

		/**
		 * Returns requested collection.
		 *
		 * @return string
		 */
		public function getCollection()
		{
			return $this->collection;
		}

		/**
		 * Returns requested relations.
		 *
		 * @return array
		 */
		public function getRelations()
		{
			return $this->relations;
		}

		/**
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		private function parse()
		{
			$request_data    = $this->request_data;
			$this->form_data = [];
			$this->filters   = [];
			$this->order_by  = self::decodeOrderBy($request_data);

			// pagination
			$pg         = self::paginate($request_data);
			$this->page = $pg["page"];
			$this->max  = $pg["max"];

			// filters
			if (isset($request_data['filters'])) {
				$filters = $request_data['filters'];

				if (!is_array($filters)) {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_FILTERS', $request_data);
				}

				$this->filters = self::normalizeFilters($filters);
			}

			// form data
			if (isset($request_data['data'])) {
				$data = $request_data['data'];

				if (!is_array($data)) {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_FORM_DATA', $request_data);
				}
				$this->form_data = $data;
			}

			// relations
			$this->relations = [];

			if (isset($request_data['relations'])) {
				$relations = $request_data['relations'];

				if (!is_string($relations)) {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_RELATIONS', $request_data);
				}

				if (strlen($relations)) {
					$relations = array_unique(explode('|', $relations));

					foreach ($relations as $relation) {
						if (empty($relation)) {
							throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_RELATIONS', $request_data);
						}
					}

					$this->relations = $relations;
				}
			}

			// collection
			if (!isset($request_data['collection'])) {
				$this->collection = null;
			} elseif (!is_string($request_data['collection']) OR !strlen($request_data['collection'])) {
				throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_COLLECTION', $request_data);
			} else {
				$this->collection = $request_data['collection'];
			}
		}

		/**
		 * Returns columns from column_map and they value from fields.
		 *
		 * @param array $columns
		 * @param array $map
		 *
		 * @return array
		 */
		private static function onlyColumns(array $columns, array $map)
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
		 * Normalize request filters
		 *
		 * @param array $filters
		 *
		 * @return array
		 */
		private static function normalizeFilters(array $filters)
		{
			foreach ($filters as $key => $val) {
				if (!is_array($val)) {
					$filters[$key] = [['eq', $val]];
				}
			}

			return $filters;
		}

		/**
		 * Encode request relations
		 *
		 * @param array $relations
		 *
		 * @return string
		 */
		private static function encodeRelations(array $relations)
		{
			return implode("|", array_unique($relations));
		}

		/**
		 * Decode request order_by
		 *
		 * @param array $request_data
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		private static function decodeOrderBy(array $request_data)
		{
			if (!isset($request_data['order_by'])) {
				return [];
			}

			if (!is_string($request_data['order_by']) OR !strlen($request_data['order_by'])) {
				throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_ORDER_BY', $request_data);
			}

			$rules    = $request_data['order_by'];
			$rules    = array_unique(explode('|', $rules));
			$order_by = [];

			foreach ($rules as $rule) {
				if (!empty($rule)) {
					$parts = explode('_', $rule);
					$len   = count($parts);
					if ($len > 1 AND $parts[$len - 1] === 'desc') {
						array_pop($parts);
						$rule            = implode('_', $parts);
						$order_by[$rule] = false;
					} elseif ($len > 1 AND $parts[$len - 1] === 'asc') {
						array_pop($parts);
						$rule            = implode('_', $parts);
						$order_by[$rule] = true;
					} else {
						$order_by[$rule] = true;
					}
				} else {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_ORDER_BY', $request_data);
				}
			}

			return $order_by;
		}

		/**
		 * Encode request order_by
		 *
		 * @param array $order_by
		 *
		 * @return string
		 */
		private static function encodeOrderBy(array $order_by)
		{
			$list = [];
			foreach ($order_by as $key => $val) {
				if (is_int($key)) {
					$list[] = $val;
				} else {
					$list[] = $val ? $key : $key . '_desc';
				}
			}

			return implode('|', $list);
		}

		/**
		 * Returns request pagination.
		 *
		 * ?max=8&page=2    => ['max' => 8, 'page' => 2, 'offset' => 8 ]
		 * ?page=2          => ['max' => default|10, 'page' => 2, 'offset' => 10 ]
		 * ?                => ['max' => null, 'page' => 1, 'offset' => 0 ]
		 *
		 * @param array $request_data
		 * @param int   $max_default default max
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		private static function paginate(array $request_data, $max_default = 10)
		{
			$offset = 0;
			$page   = 1;
			$max    = null;

			if (isset($request_data['max'])) {
				$max = $request_data['max'];

				if (!is_numeric($max) OR ($max = intval($max)) <= 0) {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_PAGINATION_MAX', $request_data);
				}
			}

			if (isset($request_data['page'])) {
				$page = $request_data['page'];

				if (!is_numeric($page) OR ($page = intval($page)) <= 0) {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_PAGINATION_PAGE', $request_data);
				}

				if (!$max) {
					$max = $max_default;
				}

				$offset = ($page - 1) * $max;
			}

			return [
				'offset' => $offset,
				'max'    => $max,
				'page'   => $page
			];
		}
	}