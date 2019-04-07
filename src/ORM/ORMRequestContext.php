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

	use Gobl\DBAL\Collections\Collection;
	use Gobl\DBAL\Relations\Relation;
	use Gobl\DBAL\Table;
	use Gobl\ORM\Exceptions\ORMQueryException;

	final class ORMRequestContext
	{
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
		 * ORMQueryContext constructor.
		 *
		 * @param array $request_data
		 */
		public function __construct(array $request_data = [])
		{
			try {
				$this->parse($request_data);
			} catch (\Exception $e) {
				throw new \InvalidArgumentException('Invalid form.', null, $e);
			}
		}

		/**
		 * Returns form data
		 *
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		public function getFormData(Table $table = null)
		{
			return self::scoped($this->form_data, $table);
		}

		/**
		 * Sets filters.
		 *
		 * @param array $filters
		 *
		 * @return $this
		 */
		public function setFilters(array $filters)
		{
			$this->filters = self::normalizeFilters($filters);

			return $this;
		}

		/**
		 * Returns the request filters
		 *
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		public function getFilters(Table $table = null)
		{
			return self::scoped($this->filters, $table);
		}

		/**
		 * Adds a filter.
		 *
		 * @param string $column_name
		 * @param mixed  $filter
		 *
		 * @return $this
		 */
		public function addColumnFilter($column_name, $filter)
		{
			if (!is_array($filter)) {
				$filter = ['eq', $filter];
			}

			$this->filters[$column_name][] = $filter;

			return $this;
		}

		/**
		 * Sets filters of a given column name.
		 *
		 * @param string $column_name
		 * @param array  $filters
		 *
		 * @return $this
		 */
		public function setColumnFilters($column_name, array $filters)
		{
			$this->filters[$column_name] = $filters;
			return $this;
		}

		/**
		 * Gets filters of a given column name.
		 *
		 * @param string $column_name
		 *
		 * @return array|null
		 */
		public function getColumnFilters($column_name)
		{
			if (isset($this->filters[$column_name])) {
				return $this->filters[$column_name];
			}

			return null;
		}

		/**
		 * Sets the value of the form data field with the given name.
		 *
		 * @param string $name
		 * @param mixed  $value
		 *
		 * @return $this
		 */
		public function setField($name, $value)
		{
			$this->form_data[$name] = $value;

			return $this;
		}

		/**
		 * Removes the form data field with the given name.
		 *
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
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		public function getParsedRequestData(Table $table = null)
		{
			$r = [];

			if (!empty($this->form_data)) {
				$r['data'] = $this->form_data;
			}

			if (!empty($this->collection)) {
				$r['collection'] = $this->collection;
			}

			if (!empty($this->filters)) {
				$r['filters'] = self::scoped($this->filters, $table);
			}

			if (!empty($this->relations)) {
				$r['relations'] = self::encodeRelations(self::scoped($this->relations, $table));
			}

			if (!empty($this->order_by)) {
				$r['order_by'] = self::encodeOrderBy(self::scoped($this->order_by, $table));
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
		 * Creates scoped instance.
		 *
		 * @param \Gobl\DBAL\Table $table
		 *
		 * @return \Gobl\ORM\ORMRequestContext
		 */
		public function createScopedInstance(Table $table)
		{
			$request_data = $this->getParsedRequestData($table);

			return new self($request_data);
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
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		public function getOrderBy(Table $table = null)
		{
			return self::scoped($this->order_by, $table);
		}

		/**
		 * Sets the requested collection.
		 *
		 * @param string $name
		 *
		 * @return string
		 */
		public function setCollection($name)
		{
			if (!self::isValidCollectionName($name)) {
				throw new \InvalidArgumentException('The collection name is invalid.');
			}

			$this->collection = $name;

			return $this;
		}

		/**
		 * Returns requested collection
		 *
		 * @return string
		 */
		public function getCollection()
		{
			return $this->collection;
		}

		/**
		 * Adds the relation to requested relations list.
		 *
		 * @param string $name
		 *
		 * @return string
		 */
		public function addRelation($name)
		{
			if (!self::isValidRelationName($name)) {
				throw new \InvalidArgumentException('The relation name is invalid.');
			}

			if (!in_array($name, $this->relations)) {
				$this->relations[] = $name;
			}

			return $this;
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
		 * @param array $request_data
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		private function parse(array $request_data)
		{
			// form data
			if (isset($request_data['data'])) {
				$data = $request_data['data'];

				if (!is_array($data)) {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_FORM_DATA', $request_data);
				}

				$this->form_data = $data;
			} else {
				$this->form_data = [];
			}

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
			} else {
				$this->filters = [];
			}

			// relations
			if (isset($request_data['relations'])) {
				if (!self::isNonEmptyString($request_data['relations'])) {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_RELATIONS', $request_data);
				}

				$relations = array_unique(explode('|', $request_data['relations']));

				foreach ($relations as $relation) {
					if (!self::isValidRelationName($relation)) {
						throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_RELATIONS', $request_data);
					}
				}

				$this->relations = $relations;
			} else {
				$this->relations = [];
			}

			// collection
			if (isset($request_data['collection'])) {
				if (!self::isValidCollectionName($request_data['collection'])) {
					throw new ORMQueryException('GOBL_ORM_QUERY_INVALID_COLLECTION', $request_data);
				}

				$this->collection = $request_data['collection'];
			} else {
				$this->collection = null;
			}

			// order by
			$this->order_by = self::decodeOrderBy($request_data);
		}

		/**
		 * Checks for non-empty string.
		 *
		 * @param mixed $name
		 *
		 * @return bool
		 */
		private function isNonEmptyString($name)
		{
			return is_string($name) AND strlen($name);
		}

		/**
		 * Checks for valid collection name.
		 *
		 * @param mixed $name
		 *
		 * @return bool
		 */
		private function isValidCollectionName($name)
		{
			return is_string($name) AND strlen($name) AND preg_match(Collection::NAME_REG, $name);
		}

		/**
		 * Checks for valid relation name.
		 *
		 * @param $name
		 *
		 * @return bool
		 */
		private function isValidRelationName($name)
		{
			return is_string($name) AND strlen($name) AND preg_match(Relation::NAME_REG, $name);
		}

		/**
		 * Returns columns from column_map and they value from fields.
		 *
		 * @param array                 $map
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		private static function scoped(array $map, Table $table = null)
		{
			if (is_null($table)) {
				return $map;
			}

			$values = [];

			foreach ($map as $field => $value) {
				if ($table->hasColumn($field)) {
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
			foreach ($filters as $column => $value) {
				if (!is_array($value)) {
					$filters[$column] = [['eq', $value]];
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