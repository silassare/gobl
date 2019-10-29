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

	class ORMRequestBase
	{
		/**
		 * @var array
		 */
		private $key_words = [
			'data',
			'relations',
			'collection',
			'filters',
			'order_by',
			'max',
			'page'
		];

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
		 * @param array $request
		 */
		public function __construct(array $request = [])
		{
			try {
				$this->parse($request);
			} catch (\Exception $e) {
				throw new \InvalidArgumentException('Invalid request.', null, $e);
			}
		}

		/**
		 * Creates scoped instance.
		 *
		 * @param \Gobl\DBAL\Table $table
		 *
		 * @return \Gobl\ORM\ORMRequestBase
		 */
		public function createScopedInstance(Table $table)
		{
			$request_data = $this->getParsedRequest($table);

			return new self($request_data);
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
			return self::scopedColumns($this->form_data, $table);
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
			return self::scopedColumns($this->filters, $table);
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
		 * Gets the value of the form data field with the given name.
		 *
		 * @param string $name
		 * @param null   $def
		 *
		 * @return mixed
		 */
		public function getFormField($name, $def = null)
		{
			return isset($this->form_data[$name]) ? $this->form_data[$name] : $def;
		}

		/**
		 * Sets the value of the form data field with the given name.
		 *
		 * @param string $name
		 * @param mixed  $value
		 *
		 * @return $this
		 */
		public function setFormField($name, $value)
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
		public function removeFormField($name)
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
		public function getParsedRequest(Table $table = null)
		{
			$r = [];

			if (!empty($this->form_data)) {
				$r['data'] = $this->getFormData($table);
			}

			if (!empty($this->collection)) {
				$r['collection'] = $this->getCollection($table);
			}

			if (!empty($this->filters)) {
				$r['filters'] = $this->getFilters($table);
			}

			if (!empty($this->relations)) {
				$r['relations'] = self::encodeRelations($this->getRelations($table));
			}

			if (!empty($this->order_by)) {
				$r['order_by'] = self::encodeOrderBy($this->getOrderBy($table));
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
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		public function getOrderBy(Table $table = null)
		{
			return self::scopedColumns($this->order_by, $table);
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
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return string
		 */
		public function getCollection(Table $table = null)
		{
			if ($table AND !$table->hasCollection($this->collection)) {
				return '';
			}

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
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		public function getRelations(Table $table = null)
		{
			return $this->scopedRelations($this->relations, $table);
		}

		/**
		 * @param array $request
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		private function parse(array $request)
		{
			// form data
			if (isset($request['data'])) {
				$data = $request['data'];

				if (!is_array($data)) {
					throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_FORM_DATA', $request);
				}

				$this->form_data = $data;
			} else {
				$this->form_data = $request;

				foreach ($this->key_words as $n) {
					unset($this->form_data[$n]);
				}
			}

			// pagination
			$pg         = self::paginate($request);
			$this->page = $pg["page"];
			$this->max  = $pg["max"];

			// filters
			if (isset($request['filters'])) {
				$filters = $request['filters'];

				if (!is_array($filters)) {
					throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_FILTERS', $request);
				}

				$this->filters = self::normalizeFilters($filters);
			} else {
				$this->filters = [];
			}

			// collection
			$this->collection = self::decodeCollection($request);

			// relations
			$this->relations = self::decodeRelations($request);

			// order by
			$this->order_by = self::decodeOrderBy($request);
		}

		/**
		 * Checks for valid collection name.
		 *
		 * @param mixed $name
		 *
		 * @return bool
		 */
		private static function isValidCollectionName($name)
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
		private static function isValidRelationName($name)
		{
			return is_string($name) AND strlen($name) AND preg_match(Relation::NAME_REG, $name);
		}

		/**
		 * Returns a filtered map using the table columns.
		 *
		 * @param array                 $map
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		private static function scopedColumns(array $map, Table $table = null)
		{
			if (is_null($table)) {
				return $map;
			}

			$values = [];

			foreach ($map as $field => $value) {
				if ($table->hasColumn($field)) {
					$column    = $table->getColumn($field);
					$full_name = $column->getFullName();
					// only full name will be used
					if ($full_name === $field) {
						$values[$field] = $value;
					}
				}
			}

			return $values;
		}

		/**
		 * Returns a filtered map using the table relations.
		 *
		 * @param array                 $map
		 * @param \Gobl\DBAL\Table|null $table
		 *
		 * @return array
		 */
		private static function scopedRelations(array $map, Table $table = null)
		{
			if (is_null($table)) {
				return $map;
			}

			$values = [];

			foreach ($map as $key => $value) {
				if ($table->hasRelation($value) OR $table->hasVirtualRelation($value)) {
					$values[] = $value;
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
		 * Decode request collection
		 *
		 * @param array $request
		 *
		 * @return string|null
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		private static function decodeCollection(array $request)
		{
			if (!isset($request['collection'])) {
				return null;
			}

			if (!is_string($request['collection'])) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION', $request);
			}

			if (strlen($request['collection'])) {
				if (!self::isValidCollectionName($request['collection'])) {
					throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION', $request);
				}

				return $request['collection'];
			}

			return null;
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
		 * Decode request relations
		 *
		 * @param array $request
		 *
		 * @return array
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		private static function decodeRelations(array $request)
		{
			if (!isset($request['relations'])) {
				return [];
			}

			if (!is_string($request['relations'])) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $request);
			}

			if (strlen($request['relations'])) {
				$relations = array_unique(explode('|', $request['relations']));

				foreach ($relations as $relation) {
					if (!self::isValidRelationName($relation)) {
						throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $request);
					}
				}

				return $relations;
			}

			return [];
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

			$rules = $request_data['order_by'];

			if (!is_string($rules)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $request_data);
			}

			if (!strlen($rules)) {
				return [];
			}

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
					throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $request_data);
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
		private static function paginate(array $request_data, $max_default = 100)
		{
			$offset = 0;
			$page   = 1;
			$max    = null;

			if (isset($request_data['max'])) {
				$max = $request_data['max'];

				if (!is_numeric($max) OR ($max = intval($max)) <= 0) {
					throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_PAGINATION_MAX', $request_data);
				}
			}

			if (isset($request_data['page'])) {
				$page = $request_data['page'];

				if (!is_numeric($page) OR ($page = intval($page)) <= 0) {
					throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_PAGINATION_PAGE', $request_data);
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
