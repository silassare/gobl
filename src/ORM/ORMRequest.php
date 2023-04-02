<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\ORM;

use Gobl\DBAL\Collections\Collection;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMQueryException;

/**
 * Class ORMRequest.
 */
class ORMRequest
{
	public const FORM_DATA_PARAM = 'form_data';

	public const RELATIONS_PARAM = 'relations';

	public const COLLECTION_PARAM = 'collection';

	public const FILTERS_PARAM = 'filters';

	public const ORDER_BY_PARAM = 'order_by';

	public const PAGE_PARAM = 'page';

	public const MAX_PARAM = 'max';

	public const DELIMITER = '|';

	/**
	 * @var array
	 */
	private array $key_words = [
		self::FORM_DATA_PARAM,
		self::RELATIONS_PARAM,
		self::COLLECTION_PARAM,
		self::FILTERS_PARAM,
		self::ORDER_BY_PARAM,
		self::PAGE_PARAM,
		self::MAX_PARAM,
	];

	private array $form_data = [];

	private ?string $collection;

	private array $relations = [];

	private array $filters = [];

	private array $order_by = [];

	private int $page = 1;

	private ?int $max;

	/**
	 * Default max value when max is not specified in the request.
	 */
	private int $max_default;

	/**
	 * Max property maximum value.
	 */
	private int $max_allowed;

	/**
	 * ORMRequest constructor.
	 *
	 * @param array $request
	 * @param int   $max_default
	 * @param int   $max_allowed
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function __construct(array $request = [], int $max_default = 10, int $max_allowed = 2000)
	{
		$this->max_default = $max_default;
		$this->max_allowed = $max_allowed;
		$this->parse($request);
	}

	/**
	 * Creates scoped instance.
	 *
	 * @param \Gobl\DBAL\Table $table
	 *
	 * @return \Gobl\ORM\ORMRequest
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function createScopedInstance(Table $table): self
	{
		$request = $this->getParsedRequest($table);

		return new self($request);
	}

	/**
	 * Returns parsed request data.
	 *
	 * @param null|\Gobl\DBAL\Table $table
	 *
	 * @return array
	 */
	public function getParsedRequest(Table $table = null): array
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

		$r[self::PAGE_PARAM] = $this->page;

		return $r;
	}

	/**
	 * Returns form data.
	 *
	 * @param null|\Gobl\DBAL\Table $table
	 *
	 * @return array
	 */
	public function getFormData(Table $table = null): array
	{
		return self::scopedColumns($this->form_data, $table);
	}

	/**
	 * Returns requested collection.
	 *
	 * @param null|\Gobl\DBAL\Table $table
	 *
	 * @return null|string
	 */
	public function getCollection(Table $table = null): ?string
	{
		if ($table && (empty($this->collection) || !$table->hasCollection($this->collection))) {
			return null;
		}

		return $this->collection;
	}

	/**
	 * Sets the requested collection.
	 *
	 * @param string $name
	 *
	 * @return $this
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function setCollection(string $name): self
	{
		if (!self::isValidCollectionName($name)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION_NAME', [self::COLLECTION_PARAM => $name]);
		}

		$this->collection = $name;

		return $this;
	}

	/**
	 * Returns the request filters.
	 *
	 * @param null|\Gobl\DBAL\Table $table
	 *
	 * @return array
	 */
	public function getFilters(Table $table = null): array
	{
		return self::scopedColumns($this->filters, $table);
	}

	/**
	 * Sets filters.
	 *
	 * @param array $filters
	 *
	 * @return $this
	 */
	public function setFilters(array $filters): self
	{
		$this->filters = self::normalizeFilters($filters);

		return $this;
	}

	/**
	 * Returns requested relations.
	 *
	 * @param null|\Gobl\DBAL\Table $table
	 *
	 * @return array
	 */
	public function getRelations(Table $table = null): array
	{
		return self::scopedRelations($this->relations, $table);
	}

	/**
	 * Returns the request order by rules.
	 *
	 * @param null|\Gobl\DBAL\Table $table
	 *
	 * @return array
	 */
	public function getOrderBy(Table $table = null): array
	{
		return self::scopedColumns($this->order_by, $table);
	}

	/**
	 * Gets filters of a given column name.
	 *
	 * @param string $column_name
	 *
	 * @return null|array
	 */
	public function getColumnFilters(string $column_name): ?array
	{
		return $this->filters[$column_name] ?? null;
	}

	/**
	 * Gets the value of the form data field with the given name.
	 *
	 * @param string $name
	 * @param null   $def
	 *
	 * @return mixed
	 */
	public function getFormField(string $name, $def = null): mixed
	{
		return $this->form_data[$name] ?? $def;
	}

	/**
	 * Sets the value of the form data field with the given name.
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function setFormField(string $name, mixed $value): self
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
	public function removeFormField(string $name): self
	{
		unset($this->form_data[$name]);

		return $this;
	}

	/**
	 * Returns request max items count.
	 *
	 * @return null|int
	 */
	public function getMax(): ?int
	{
		return $this->max;
	}

	/**
	 * Sets request max items count.
	 *
	 * @return $this
	 */
	public function setMax(?int $max = null): self
	{
		$this->max = $max;

		return $this;
	}

	/**
	 * Returns request page.
	 *
	 * @return int
	 */
	public function getPage(): int
	{
		return $this->page;
	}

	/**
	 * Sets request page.
	 *
	 * @return $this
	 */
	public function setPage(?int $page = null): self
	{
		$this->page = $page;

		return $this;
	}

	/**
	 * Compute query offset according to page and max items per page.
	 *
	 * @return int
	 */
	public function getOffset(): int
	{
		if (isset($this->max)) {
			return ($this->page - 1) * $this->max;
		}

		return 0;
	}

	/**
	 * Adds the relation to requested relations list.
	 *
	 * @param string $name
	 *
	 * @return $this
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function addRelation(string $name): self
	{
		if (!self::isValidRelationName($name)) {
			throw new ORMQueryException('GOBL_REQUEST_INVALID_RELATION_NAME', ['relation' => $name]);
		}

		if (!\in_array($name, $this->relations, true)) {
			$this->relations[] = $name;
		}

		return $this;
	}

	/**
	 * @param array $request
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private function parse(array $request): void
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
		$pg         = self::paginate($request, $this->max_default, $this->max_allowed);
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
	 * Returns request pagination.
	 *
	 * ```
	 * ?max=8&page=2    => ['max' => 8, 'page' => 2, 'offset' => 8 ]
	 * ?page=2          => ['max' => default|10, 'page' => 2, 'offset' => 10 ]
	 * ?                => ['max' => null, 'page' => 1, 'offset' => 0 ]
	 * ```
	 *
	 * @param array $request
	 * @param int   $max_default default max
	 * @param int   $max_allowed maximum value allowed
	 *
	 * @return array
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private static function paginate(array $request, int $max_default, int $max_allowed): array
	{
		$offset = 0;
		$page   = 1;
		$max    = null;

		if (isset($request[self::MAX_PARAM])) {
			$max = $request[self::MAX_PARAM];

			if (!\is_numeric($max) || ($max = (int) $max) <= 0 || $max > $max_allowed) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_PAGINATION_MAX', $request);
			}
		}

		if (isset($request[self::PAGE_PARAM])) {
			$page = $request[self::PAGE_PARAM];

			if (!\is_numeric($page) || ($page = (int) $page) <= 0) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_PAGINATION_PAGE', $request);
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

	/**
	 * Normalize request filters.
	 *
	 * @param array $filters
	 *
	 * @return array
	 */
	private static function normalizeFilters(array $filters): array
	{
		foreach ($filters as $column => $value) {
			if (!\is_array($value)) {
				$filters[$column] = [['eq', $value]];
			}
		}

		return $filters;
	}

	/**
	 * Decode request collection.
	 *
	 * @param array $request
	 *
	 * @return null|string
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private static function decodeCollection(array $request): ?string
	{
		if (!isset($request[self::COLLECTION_PARAM])) {
			return null;
		}

		if (!\is_string($request[self::COLLECTION_PARAM])) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION', $request);
		}

		if ('' !== $request[self::COLLECTION_PARAM]) {
			if (!self::isValidCollectionName($request[self::COLLECTION_PARAM])) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION', $request);
			}

			return $request[self::COLLECTION_PARAM];
		}

		return null;
	}

	/**
	 * Checks for valid collection name.
	 *
	 * @param mixed $name
	 *
	 * @return bool
	 */
	private static function isValidCollectionName(mixed $name): bool
	{
		return \is_string($name) && '' !== $name && \preg_match(Collection::NAME_REG, $name);
	}

	/**
	 * Decode request relations.
	 *
	 * @param array $request
	 *
	 * @return array
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private static function decodeRelations(array $request): array
	{
		if (!isset($request[self::RELATIONS_PARAM])) {
			return [];
		}

		if (\is_string($request[self::RELATIONS_PARAM])) {
			if ('' === $request[self::RELATIONS_PARAM]) {
				return [];
			}

			$relations = \array_unique(\explode(self::DELIMITER, $request[self::RELATIONS_PARAM]));

			foreach ($relations as $relation) {
				if (!self::isValidRelationName($relation)) {
					throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $request);
				}
			}

			return $relations;
		}

		if (\is_array($request[self::RELATIONS_PARAM])) {
			$relations = \array_unique($request[self::RELATIONS_PARAM]);

			foreach ($relations as $relation) {
				if (!self::isValidRelationName($relation)) {
					throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $request);
				}
			}

			return $relations;
		}

		throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $request);
	}

	/**
	 * Checks for valid relation name.
	 *
	 * @param mixed $name
	 *
	 * @return bool
	 */
	private static function isValidRelationName(mixed $name): bool
	{
		return \is_string($name) && '' !== $name && \preg_match(Relation::NAME_REG, $name);
	}

	/**
	 * Decode request order by.
	 *
	 * @param array $request
	 *
	 * @return array
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	private static function decodeOrderBy(array $request): array
	{
		if (!isset($request[self::ORDER_BY_PARAM])) {
			return [];
		}

		$rules = $request[self::ORDER_BY_PARAM];

		if (!\is_string($rules)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $request);
		}

		if ('' === $rules) {
			return [];
		}

		$rules    = \array_unique(\explode(self::DELIMITER, $rules));
		$order_by = [];

		foreach ($rules as $rule) {
			if (!empty($rule)) {
				$parts = \explode('_', $rule);
				$len   = \count($parts);

				if ($len > 1 && 'desc' === $parts[$len - 1]) {
					\array_pop($parts);
					$rule            = \implode('_', $parts);
					$order_by[$rule] = false;
				} elseif ($len > 1 && 'asc' === $parts[$len - 1]) {
					\array_pop($parts);
					$rule            = \implode('_', $parts);
					$order_by[$rule] = true;
				} else {
					$order_by[$rule] = true;
				}
			} else {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $request);
			}
		}

		return $order_by;
	}

	/**
	 * Returns a filtered map using the table columns.
	 *
	 * @param array                 $map
	 * @param null|\Gobl\DBAL\Table $table
	 *
	 * @return array
	 */
	private static function scopedColumns(array $map, Table $table = null): array
	{
		if (null === $table) {
			return $map;
		}

		$values = [];

		foreach ($map as $field => $value) {
			if ($table->hasColumn($field)) {
				$column    = $table->getColumnOrFail($field);
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
	 * Encode request relations.
	 *
	 * @param array $relations
	 *
	 * @return string
	 */
	private static function encodeRelations(array $relations): string
	{
		return \implode(self::DELIMITER, \array_unique($relations));
	}

	/**
	 * Returns a filtered map using the table relations.
	 *
	 * @param string[]              $map
	 * @param null|\Gobl\DBAL\Table $table
	 *
	 * @return array
	 */
	private static function scopedRelations(array $map, Table $table = null): array
	{
		if (null === $table) {
			return $map;
		}

		$values = [];

		foreach ($map as $value) {
			if ($table->hasRelation($value) || $table->hasVirtualRelation($value)) {
				$values[] = $value;
			}
		}

		return $values;
	}

	/**
	 * Encode request order by.
	 *
	 * @param array $order_by
	 *
	 * @return string
	 */
	private static function encodeOrderBy(array $order_by): string
	{
		$list = [];

		foreach ($order_by as $key => $val) {
			if (\is_int($key)) {
				$list[] = $val;
			} else {
				$list[] = $val ? $key : $key . '_desc';
			}
		}

		return \implode(self::DELIMITER, $list);
	}
}