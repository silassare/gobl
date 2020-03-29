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

use Exception;
use Gobl\DBAL\Collections\Collection;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMQueryException;
use InvalidArgumentException;

class ORMRequestBase
{
	const FORM_DATA_PARAM  = 'form_data';

	const RELATIONS_PARAM  = 'relations';

	const COLLECTION_PARAM = 'collection';

	const FILTERS_PARAM    = 'filters';

	const ORDER_BY_PARAM   = 'order_by';

	const PAGE_PARAM       = 'page';

	const MAX_PARAM        = 'max';

	/**
	 * @var array
	 */
	private $key_words = [
		self::FORM_DATA_PARAM,
		self::RELATIONS_PARAM,
		self::COLLECTION_PARAM,
		self::FILTERS_PARAM,
		self::ORDER_BY_PARAM,
		self::PAGE_PARAM,
		self::MAX_PARAM,
	];

	/**
	 * @var array
	 */
	private $form_data = [];

	/**
	 * @var null|string
	 */
	private $collection;

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
	 * @var null|int
	 */
	private $max;

	/**
	 * ORMQueryContext constructor.
	 */
	public function __construct(array $request = [])
	{
		try {
			$this->parse($request);
		} catch (Exception $e) {
			throw new InvalidArgumentException('Invalid request.', null, $e);
		}
	}

	/**
	 * Creates scoped instance.
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
	 * @return array
	 */
	public function getFormData(Table $table = null)
	{
		return self::scopedColumns($this->form_data, $table);
	}

	/**
	 * Sets filters.
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
		if (!\is_array($filter)) {
			$filter = ['eq', $filter];
		}

		$this->filters[$column_name][] = $filter;

		return $this;
	}

	/**
	 * Sets filters of a given column name.
	 *
	 * @param string $column_name
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
	 * @return null|array
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
	 * @return array
	 */
	public function getParsedRequest(Table $table = null)
	{
		$r = [];

		if (!empty($this->form_data)) {
			$r[self::FORM_DATA_PARAM] = $this->getFormData($table);
		}

		if (!empty($this->collection)) {
			$r[self::COLLECTION_PARAM] = $this->getCollection($table);
		}

		if (!empty($this->filters)) {
			$r[self::FILTERS_PARAM] = $this->getFilters($table);
		}

		if (!empty($this->relations)) {
			$r[self::RELATIONS_PARAM] = self::encodeRelations($this->getRelations($table));
		}

		if (!empty($this->order_by)) {
			$r[self::ORDER_BY_PARAM] = self::encodeOrderBy($this->getOrderBy($table));
		}

		if (isset($this->max)) {
			$r[self::MAX_PARAM] = $this->max;
		}

		if (isset($this->page)) {
			$r[self::PAGE_PARAM] = $this->page;
		}

		return $r;
	}

	/**
	 * Returns request max items count
	 *
	 * @return null|int
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
		if (isset($this->max, $this->page)) {
			return ($this->page - 1) * $this->max;
		}

		return 0;
	}

	/**
	 * Returns the request order by rules.
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
			throw new InvalidArgumentException('The collection name is invalid.');
		}

		$this->collection = $name;

		return $this;
	}

	/**
	 * Returns requested collection
	 *
	 * @return string
	 */
	public function getCollection(Table $table = null)
	{
		if ($table && !$table->hasCollection($this->collection)) {
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
			throw new InvalidArgumentException('The relation name is invalid.');
		}

		if (!\in_array($name, $this->relations)) {
			$this->relations[] = $name;
		}

		return $this;
	}

	/**
	 * Returns requested relations.
	 *
	 * @return array
	 */
	public function getRelations(Table $table = null)
	{
		return $this->scopedRelations($this->relations, $table);
	}

	/**
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private function parse(array $request)
	{
		if (isset($request[self::FORM_DATA_PARAM]) && \is_array($request[self::FORM_DATA_PARAM])) {
			$this->form_data = $request[self::FORM_DATA_PARAM];
		} else {
			$this->form_data = $request;

			foreach ($this->key_words as $n) {
				unset($this->form_data[$n]);
			}
		}

		// pagination
		$pg         = self::paginate($request);
		$this->page = $pg[self::PAGE_PARAM];
		$this->max  = $pg[self::MAX_PARAM];

		// filters
		if (isset($request[self::FILTERS_PARAM])) {
			$filters = $request[self::FILTERS_PARAM];

			if (!\is_array($filters)) {
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
		return \is_string($name) && \strlen($name) && \preg_match(Collection::NAME_REG, $name);
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
		return \is_string($name) && \strlen($name) && \preg_match(Relation::NAME_REG, $name);
	}

	/**
	 * Returns a filtered map using the table columns.
	 *
	 * @return array
	 */
	private static function scopedColumns(array $map, Table $table = null)
	{
		if (null === $table) {
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
	 * @return array
	 */
	private static function scopedRelations(array $map, Table $table = null)
	{
		if (null === $table) {
			return $map;
		}

		$values = [];

		foreach ($map as $key => $value) {
			if ($table->hasRelation($value) || $table->hasVirtualRelation($value)) {
				$values[] = $value;
			}
		}

		return $values;
	}

	/**
	 * Normalize request filters
	 *
	 * @return array
	 */
	private static function normalizeFilters(array $filters)
	{
		foreach ($filters as $column => $value) {
			if (!\is_array($value)) {
				$filters[$column] = [['eq', $value]];
			}
		}

		return $filters;
	}

	/**
	 * Decode request collection
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 *
	 * @return null|string
	 */
	private static function decodeCollection(array $request)
	{
		if (!isset($request[self::COLLECTION_PARAM])) {
			return null;
		}

		if (!\is_string($request[self::COLLECTION_PARAM])) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION', $request);
		}

		if (\strlen($request[self::COLLECTION_PARAM])) {
			if (!self::isValidCollectionName($request[self::COLLECTION_PARAM])) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION', $request);
			}

			return $request[self::COLLECTION_PARAM];
		}

		return null;
	}

	/**
	 * Encode request relations
	 *
	 * @return string
	 */
	private static function encodeRelations(array $relations)
	{
		return \implode('|', \array_unique($relations));
	}

	/**
	 * Decode request relations
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 *
	 * @return array
	 */
	private static function decodeRelations(array $request)
	{
		if (!isset($request[self::RELATIONS_PARAM])) {
			return [];
		}

		if (!\is_string($request[self::RELATIONS_PARAM])) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $request);
		}

		if (\strlen($request[self::RELATIONS_PARAM])) {
			$relations = \array_unique(\explode('|', $request[self::RELATIONS_PARAM]));

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
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 *
	 * @return array
	 */
	private static function decodeOrderBy(array $request_data)
	{
		if (!isset($request_data[self::ORDER_BY_PARAM])) {
			return [];
		}

		$rules = $request_data[self::ORDER_BY_PARAM];

		if (!\is_string($rules)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $request_data);
		}

		if (!\strlen($rules)) {
			return [];
		}

		$rules    = \array_unique(\explode('|', $rules));
		$order_by = [];

		foreach ($rules as $rule) {
			if (!empty($rule)) {
				$parts = \explode('_', $rule);
				$len   = \count($parts);

				if ($len > 1 && $parts[$len - 1] === 'desc') {
					\array_pop($parts);
					$rule            = \implode('_', $parts);
					$order_by[$rule] = false;
				} elseif ($len > 1 && $parts[$len - 1] === 'asc') {
					\array_pop($parts);
					$rule            = \implode('_', $parts);
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
	 * @return string
	 */
	private static function encodeOrderBy(array $order_by)
	{
		$list = [];

		foreach ($order_by as $key => $val) {
			if (\is_int($key)) {
				$list[] = $val;
			} else {
				$list[] = $val ? $key : $key . '_desc';
			}
		}

		return \implode('|', $list);
	}

	/**
	 * Returns request pagination.
	 *
	 * ?max=8&page=2    => ['max' => 8, 'page' => 2, 'offset' => 8 ]
	 * ?page=2          => ['max' => default|10, 'page' => 2, 'offset' => 10 ]
	 * ?                => ['max' => null, 'page' => 1, 'offset' => 0 ]
	 *
	 * @param int $max_default default max
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 *
	 * @return array
	 */
	private static function paginate(array $request_data, $max_default = 100)
	{
		$offset = 0;
		$page   = 1;
		$max    = null;

		if (isset($request_data[self::MAX_PARAM])) {
			$max = $request_data[self::MAX_PARAM];

			if (!\is_numeric($max) || ($max = (int) $max) <= 0) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_PAGINATION_MAX', $request_data);
			}
		}

		if (isset($request_data[self::PAGE_PARAM])) {
			$page = $request_data[self::PAGE_PARAM];

			if (!\is_numeric($page) || ($page = (int) $page) <= 0) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_PAGINATION_PAGE', $request_data);
			}

			if (!$max) {
				$max = $max_default;
			}

			$offset = ($page - 1) * $max;
		}

		return [
			'offset'         => $offset,
			self::MAX_PARAM  => $max,
			self::PAGE_PARAM => $page,
		];
	}
}
