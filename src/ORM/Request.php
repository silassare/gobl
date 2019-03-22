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

	final class Request
	{
		/**
		 * @var array
		 */
		private $request = [];

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
		 */
		public function __construct() { }

		/**
		 * Parse request array.
		 *
		 * @param array $request The request data
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMQueryException
		 */
		public function parse(array $request)
		{
			$this->request = $request;

			$this->collection = ORMServiceBase::getRequestCollection($request);
			$this->relations  = ORMServiceBase::getRequestRelations($request);
			$this->filters    = ORMServiceBase::getRequestFilters([], $request);
			$this->order_by   = ORMServiceBase::getRequestOrderBy([], $request);

			$pg = ORMServiceBase::getPagination($request);

			$this->page = $pg["page"];
			$this->max  = $pg["max"];
		}

		/**
		 * Returns request data.
		 *
		 * @return array
		 */
		public function getRequest()
		{
			$r = $this->request;

			if (isset($this->max)) {
				$r["max"] = $this->max;
			} else {
				unset($r["max"]);
			}

			if (isset($this->page)) {
				$r["page"] = $this->page;
			} else {
				unset($r["page"]);
			}

			if (!empty($this->collection)) {
				$r["collection"] = $this->collection;
			} else {
				unset($r["collection"]);
			}

			if (count($this->filters)) {
				$r["filters"] = $this->filters;
			} else {
				unset($r["filters"]);
			}

			if (!empty($this->relations)) {
				$r["relations"] = self::encodeRelations($this->relations);
			} else {
				unset($r["relations"]);
			}

			if (count($this->order_by)) {
				$r["order_by"] = self::encodeOrderBy($this->order_by);
			} else {
				unset($r["order_by"]);
			}

			return $r;
		}

		/**
		 * Encode relations list to string.
		 *
		 * @param array $relations
		 *
		 * @return string
		 */
		public static function encodeRelations(array $relations)
		{
			return implode("|", array_unique($relations));
		}

		/**
		 * Encode request order by to string.
		 *
		 * @param array $order_by
		 *
		 * @return string
		 */
		public static function encodeOrderBy(array $order_by)
		{
			$list = [];
			foreach ($order_by as $key => $val) {
				if (is_int($key)) {
					$list[] = $val;
				} else {
					$list[] = $val ? $key : $key . "_desc";
				}
			}

			return implode("|", $list);
		}

		/**
		 * Returns the request order by rules.
		 *
		 * @return array
		 */
		public function getOrderBy()
		{
			return $this->order_by;
		}

		/**
		 * Sets the request order by rules.
		 *
		 * @param array $order_by
		 *
		 * @return $this
		 */
		public function setOrderBy(array $order_by)
		{
			$this->order_by = $order_by;

			return $this;
		}

		/**
		 * Returns the request filters
		 *
		 * @return array
		 */
		public function getFilters()
		{
			return $this->filters;
		}

		/**
		 * Sets the request filters
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
		 * Normalize filters
		 *
		 * @param array $filters
		 *
		 * @return array
		 */
		static function normalizeFilters(array $filters)
		{
			foreach ($filters as $key => $val) {
				if (!is_array($val)) {
					$filters[$key] = [["eq", $val]];
				}
			}

			return $filters;
		}

		/**
		 * Sets a filter, will overwrite existing rules
		 *
		 * @param string $name  the filter name
		 * @param mixed  $rules the filter rules
		 *
		 * @return $this
		 */
		public function setFilter($name, $rules)
		{
			if (!is_array($rules)) {
				$rules = [["eq", $rules]];
			}

			$this->filters[$name] = $rules;

			return $this;
		}

		/**
		 * Adds a new filter rule to an existing rules list
		 *
		 * @param string $name the filter name
		 * @param mixed  $rule the filter rule
		 *
		 * @return $this
		 */
		public function addFilter($name, $rule)
		{
			if (!is_array($rule)) {
				$rule = ["eq", $rule];
			}

			$this->filters[$name][] = $rule;

			return $this;
		}

		/**
		 * Removes the filter with the given name
		 *
		 * @param string $name
		 *
		 * @return $this
		 */
		public function removeFilter($name)
		{
			unset($this->filters[$name]);

			return $this;
		}

		/**
		 * Returns filter rules for a given filter name
		 *
		 * @param string $name
		 *
		 * @return array|null
		 */
		public function getFilter($name)
		{
			if (isset($this->filters[$name])) {
				return $this->filters[$name];
			}

			return null;
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
		 * Sets requested collection.
		 *
		 * @param string $collection
		 *
		 * @return $this
		 */
		public function setCollection($collection)
		{
			$this->collection = $collection;

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
		 * @param array $relations
		 *
		 * @return $this
		 */
		public function setRelations(array $relations)
		{
			$this->relations = array_unique($relations);

			return $this;
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
		 * Sets request max items count
		 *
		 * @param int $max
		 *
		 * @return $this
		 */
		public function setMax($max)
		{
			$this->max = $max;

			return $this;
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
		 * Sets request page
		 *
		 * @param int $page
		 *
		 * @return $this
		 */
		public function setPage($page)
		{
			$this->page = $page;

			return $this;
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
	}