<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\ORM;

use Gobl\DBAL\Collections\Collection;
use Gobl\DBAL\Column;
use Gobl\DBAL\Relations\Relation;
use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\Interfaces\ORMOptionsInterface;
use Override;

/**
 * Class ORMOptions.
 */
class ORMOptions implements ORMOptionsInterface
{
	public const COLLECTION_PARAM               = 'collection';
	public const FILTERS_PARAM                  = 'filters';
	public const FORM_DATA_PARAM                = 'form_data';
	public const MAX_PARAM                      = 'max';
	public const ORDER_BY_PARAM                 = 'order_by';
	public const ORDER_BY_DELIMITER             = '|';
	public const ORDER_BY_DELIMITER_ASC_DESC    = ':';
	public const CURSOR_PARAM                   = 'cursor';
	public const CURSOR_COLUMN_PARAM            = 'cursor_column';
	public const CURSOR_DIR_PARAM      		       = 'cursor_dir';
	public const EXPECTED_COLUMNS_PARAM         = '_columns';
	public const DELETE_PARAM                   = '_delete';

	public const PAGE_PARAM          = 'page';
	public const RELATIONS_PARAM     = 'relations';
	public const RELATIONS_DELIMITER = '|';
	public const SCOPES_PARAM        = 'scopes';

	private array $key_words = [
		self::FORM_DATA_PARAM,
		self::RELATIONS_PARAM,
		self::CURSOR_PARAM,
		self::CURSOR_COLUMN_PARAM,
		self::CURSOR_DIR_PARAM,
		self::COLLECTION_PARAM,
		self::FILTERS_PARAM,
		self::ORDER_BY_PARAM,
		self::PAGE_PARAM,
		self::MAX_PARAM,
		self::EXPECTED_COLUMNS_PARAM,
		self::SCOPES_PARAM,
	];

	private array $form_data = [];

	private ?string $collection = null;

	/**
	 * @var array<string, true> the requested relations as keys (values are ignored, we only care about keys for quick lookup)
	 */
	private array $relations  = [];

	private array $filters = [];

	/**
	 * Filters to limit request filters.
	 *
	 * Applied before user filters and combined with them using "AND" condition.
	 */
	private array $ensure_only_filters = [];

	/**
	 * @var list<string> List of expected columns for the main table (empty means all allowed columns)
	 */
	private array $expected_columns = [];

	/**
	 * @var array<string, 'ASC'|'DESC'> Map of column name to sort direction
	 *
	 * e.g. `['name' => 'ASC', 'created_at' => 'DESC']`
	 */
	private array $order_by = [];

	/**
	 * Current page number (1-based). When null, pagination is disabled and all items are returned.
	 * Note that when using page-based pagination, the offset is computed as (`page` - 1) * `max`.
	 * When using cursor-based pagination, this property is ignored and the offset.
	 */
	private ?int $page = null;

	private ?int $max        = null;
	private bool $ignore_max = false;

	/**
	 * Raw offset set by {@see setRawOffset()}. When non-null, takes priority over the
	 * page-computed offset in {@see getOffset()}.
	 */
	private ?int $_raw_offset = null;

	private int|string|null $cursor = null;

	private ?string $cursor_column = null;

	/**
	 * Cursor direction: 'ASC' or 'DESC'.
	 */
	private ?string $cursor_dir = null;

	/**
	 * ORMOptions constructor.
	 *
	 * Parses a raw request payload (e.g. the decoded JSON body of an API call) into typed
	 * properties: form data, filters, pagination, ordering, collection, and relations.
	 *
	 * When `$scope` is non-empty, only the sub-array at `$payload[SCOPES_PARAM][$scope]` is
	 * parsed, enabling caller-controlled namespacing of request parameters (e.g. a single
	 * request carrying data for multiple tables|relations simultaneously).
	 *
	 * @param array       $payload     the raw request payload (e.g. decoded JSON body)
	 * @param null|string $scope       optional scope to filter request parameters (when empty, the entire payload is parsed)
	 * @param int         $max_default default page size when the request does not specify `max`
	 * @param int         $max_allowed hard ceiling on page size; requests above this are capped or rejected
	 *
	 * @throws ORMQueryException
	 */
	public function __construct(
		protected array $payload = [],
		?string $scope = null,
		private readonly int $max_default = 10,
		private readonly int $max_allowed = 2000
	) {
		$scope_payload = $this->payload;

		if (!empty($scope)) {
			$scope_payload = $this->payload[self::SCOPES_PARAM][$scope] ?? [];

			if (!\is_array($scope_payload)) {
				throw new ORMQueryException(
					\sprintf(
						'Scope "%s" request data should be of type "array" not "%s".',
						$scope,
						\get_debug_type($scope_payload)
					)
				);
			}
		}

		$this->parse($scope_payload);
	}

	/**
	 * Creates scoped instance.
	 *
	 * @param string $scope
	 *
	 * @return static
	 *
	 * @throws ORMQueryException
	 */
	public function createScopedInstance(string $scope): static
	{
		return new self($this->payload, $scope, $this->max_default, $this->max_allowed);
	}

	/**
	 * Creates a paginated request with explicit offset-based pagination parameters.
	 *
	 * This bypasses HTTP payload parsing; useful when building queries programmatically.
	 *
	 * @param null|int                         $max      maximum number of items per page, or null for no limit
	 * @param null|int                         $page     1-based page number, or null to disable pagination (ignored if $max is null)
	 * @param null|array<string, 'ASC'|'DESC'> $order_by optional column sort order
	 *
	 * @return static
	 */
	public static function makePaginated(?int $max, ?int $page = null, ?array $order_by = null): static
	{
		$instance = new self();

		return $instance->setPage($page)->setMax($max)->setOrderBy($order_by);
	}

	/**
	 * Creates a request with the given filters pre-set.
	 *
	 * This bypasses HTTP payload parsing; useful when building queries programmatically.
	 *
	 * @param array $filters the filters to apply
	 *
	 * @return static
	 */
	public static function makeFromFilters(array $filters): static
	{
		$instance = new self();

		return $instance->setFilters($filters);
	}

	/**
	 * Creates a cursor-based paginated request.
	 *
	 * This bypasses HTTP payload parsing; useful when building queries programmatically.
	 *
	 * @param string          $cursor_column the column to use for cursor-based pagination
	 * @param int             $max           maximum number of items per page
	 * @param null|int|string $cursor        the cursor value from the previous page, or null to start from the beginning
	 * @param string          $direction     sort direction: 'ASC' or 'DESC'
	 *
	 * @return static
	 *
	 * @throws ORMQueryException when the direction is invalid
	 */
	public static function makeCursorBased(
		string $cursor_column,
		int $max,
		int|string|null $cursor = null,
		string $direction = 'ASC'
	): static {
		$instance = new self();

		return $instance->setCursor($cursor)
			->setCursorColumn($cursor_column)
			->setCursorDirection($direction)
			->setMax($max);
	}

	/**
	 * Returns parsed request data.
	 *
	 * @return array
	 */
	public function getParsedRequest(): array
	{
		$r = [];

		if (!empty($this->form_data)) {
			$r[self::FORM_DATA_PARAM] = $this->getFormData();
		}

		$collection = $this->getRequestedCollection();
		if (!empty($collection)) {
			$r[self::COLLECTION_PARAM] = $collection;
		}

		$filters = $this->getFilters();
		if (!empty($filters)) {
			$r[self::FILTERS_PARAM] = $filters;
		}

		$relations = $this->getRequestedRelations();
		if (!empty($relations)) {
			$r[self::RELATIONS_PARAM] = self::relationsEncode($relations);
		}

		$order_by = $this->getOrderBy();
		if (!empty($order_by)) {
			$r[self::ORDER_BY_PARAM] = self::orderByEncode($order_by);
		}

		$expected_columns = $this->getExpectedColumns();
		if (!empty($expected_columns)) {
			$r[self::EXPECTED_COLUMNS_PARAM] = $expected_columns;
		}

		if (isset($this->max)) {
			$r[self::MAX_PARAM] = $this->max;
		}

		if ($this->isCursorBased()) {
			if (isset($this->cursor)) {
				$r[self::CURSOR_PARAM] = $this->cursor;
			}

			if (isset($this->cursor_column)) {
				$r[self::CURSOR_COLUMN_PARAM] = $this->cursor_column;
			}

			if (isset($this->cursor_dir)) {
				$r[self::CURSOR_DIR_PARAM] = $this->cursor_dir;
			}
		} elseif (isset($this->page)) {
			$r[self::PAGE_PARAM] = $this->page;
		}

		return $r;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isCursorBased(): bool
	{
		return isset($this->cursor) || isset($this->cursor_column) || isset($this->cursor_dir);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getFormData(?Table $table = null): array
	{
		if (null === $table) {
			return $this->form_data;
		}

		$values = [];

		foreach ($this->form_data as $field => $value) {
			if (!$table->hasColumn($field)) {
				continue;
			}

			$column    = $table->getColumnOrFail($field);
			$full_name = $column->getFullName();
			// only full name will be used
			if ($full_name === $field) {
				$values[$field] = $value;
			}
		}

		return $values;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setFormData(array $form_data): static
	{
		$this->form_data = $form_data;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getFormField(string $name, $default = null): mixed
	{
		return $this->form_data[$name] ?? $default;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setFormField(string $name, mixed $value): static
	{
		$this->form_data[$name] = $value;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function unsetFormField(string $name): static
	{
		unset($this->form_data[$name]);

		return $this;
	}

	/**
	 * Alias for {@see unsetFormField()}.
	 */
	public function removeFormField(string $name): static
	{
		return $this->unsetFormField($name);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPage(): ?int
	{
		return $this->page;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setPage(?int $page = null): static
	{
		if (null !== $page) {
			if ($page <= 0) {
				throw new ORMQueryException('Page number must be a positive integer.');
			}

			$this->clearCursorPagination();

			$this->page = $page;
		} else {
			$this->page = null;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getMax(): ?int
	{
		return $this->ignore_max ? null : $this->max;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function ignoreMax(bool $ignore_max = true): static
	{
		$this->ignore_max = $ignore_max;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setMax(?int $max = null): static
	{
		$this->max = $max;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getOffset(): ?int
	{
		if (isset($this->_raw_offset)) {
			return $this->_raw_offset;
		}

		if (!isset($this->page)) {
			return null;
		}

		if (isset($this->max)) {
			return ($this->page - 1) * $this->max;
		}

		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setRawOffset(?int $offset): static
	{
		if (null !== $offset && $offset < 0) {
			throw new ORMQueryException('Offset must be a non-negative integer.');
		}

		$this->_raw_offset = $offset;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getCursor(): int|string|null
	{
		return $this->cursor;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setCursor(int|string|null $cursor): static
	{
		$this->cursor = $cursor;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getCursorColumn(): ?string
	{
		return $this->cursor_column;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setCursorColumn(?string $cursor_column): static
	{
		$this->cursor_column = $cursor_column;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getCursorDirection(): ?string
	{
		return $this->cursor_dir;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setCursorDirection(?string $direction): static
	{
		if (null !== $direction) {
			$direction = \strtoupper($direction);
			if ('ASC' !== $direction && 'DESC' !== $direction) {
				throw new ORMQueryException('Cursor direction must be either "ASC" or "DESC".');
			}
		}

		$this->cursor_dir = $direction;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getOrderBy(): ?array
	{
		return $this->order_by;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setOrderBy(?array $order_by): static
	{
		$this->order_by = $order_by ?? [];

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getFilters(): ?array
	{
		if (!empty($this->ensure_only_filters)) {
			if (!empty($this->filters)) {
				return [$this->ensure_only_filters, 'and', $this->filters];
			}

			return $this->ensure_only_filters;
		}

		return $this->filters;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setFilters(?array $filters): static
	{
		$this->filters = $filters ?? [];

		return $this;
	}

	/**
	 * Add filters to limit user filters.
	 *
	 * These filters act as a security layer: they are **always prepended** (AND-combined) to
	 * whatever filters the end-user provides, so the caller can guarantee that certain
	 * conditions (e.g. `user_id = current_user`) are always present regardless of user input.
	 *
	 * Calling this method multiple times accumulates filters (all are AND-combined).
	 *
	 * @param array $filters
	 *
	 * @return static
	 */
	public function ensureOnlyFilters(array $filters): static
	{
		if (!empty($filters)) {
			if (!empty($this->ensure_only_filters)) {
				$this->ensure_only_filters[] = 'and';
			}
			$this->ensure_only_filters[] = $filters;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getExpectedColumns(): ?array
	{
		return $this->expected_columns;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setExpectedColumns(?array $expected_columns): static
	{
		$this->expected_columns = $expected_columns ?? [];

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getRequestedRelations(): ?array
	{
		return \array_keys($this->relations);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function addRequestedRelation(string $name): static
	{
		if (!self::isValidRelationName($name)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATION_NAME', ['relation' => $name]);
		}

		$this->relations[$name] = true;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getRequestedCollection(): ?string
	{
		return $this->collection;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setRequestedCollection(?string $name): static
	{
		if (null !== $name && !self::isValidCollectionName($name)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION_NAME', [self::COLLECTION_PARAM => $name]);
		}

		$this->collection = $name;

		return $this;
	}

	/**
	 * Parse the request.
	 *
	 * @param array $options
	 *
	 * @throws ORMQueryException
	 */
	private function parse(array $options): void
	{
		if (isset($options[self::FORM_DATA_PARAM]) && \is_array($options[self::FORM_DATA_PARAM])) {
			$this->form_data = $options[self::FORM_DATA_PARAM];
		} else {
			$this->form_data = $options;
			foreach ($this->key_words as $n) {
				unset($this->form_data[$n]);
			}
		}

		// pagination
		$this->paginate($options);

		// filters
		if (isset($options[self::FILTERS_PARAM])) {
			$filters = $options[self::FILTERS_PARAM];

			if (!\is_array($filters)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_FILTERS', $options);
			}

			$this->filters = $filters;
		} else {
			$this->filters = [];
		}

		// collection
		$this->collection = self::collectionDecode($options);

		// relations
		$this->relations = \array_fill_keys(self::relationsDecode($options), true);

		// order by
		$this->order_by = self::orderByDecode($options);
	}

	/**
	 * Decode request pagination parameters.
	 *
	 * @throws ORMQueryException
	 */
	private function paginate(array $options): void
	{
		$page            = 1;
		$max             = null;
		$has_cursor_info = false;

		if (isset($options[self::MAX_PARAM])) {
			$max = $options[self::MAX_PARAM];

			if (!\is_numeric($max) || ($max = (int) $max) <= 0 || $max > $this->max_allowed) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_PAGINATION_MAX', $options);
			}
		}

		if (!$max) {
			$max = $this->max_default;
		}

		$this->max = $max;

		if (isset($options[self::CURSOR_PARAM])) {
			$c = $options[self::CURSOR_PARAM];
			if (!\is_string($c) && !\is_numeric($c)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_CURSOR', $options);
			}
			$this->cursor    = $c;
			$has_cursor_info = true;
		}

		if (isset($options[self::CURSOR_COLUMN_PARAM])) {
			$cc = $options[self::CURSOR_COLUMN_PARAM];
			if (!\is_string($cc)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_CURSOR_COLUMN', $options);
			}

			$this->cursor_column = $cc;
			$has_cursor_info     = true;
		}

		if (isset($options[self::CURSOR_DIR_PARAM])) {
			$cd = $options[self::CURSOR_DIR_PARAM];

			if (!\is_string($cd)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_CURSOR_DIR', $options);
			}

			$cd = \strtoupper($cd);

			if ('ASC' !== $cd && 'DESC' !== $cd) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_CURSOR_DIR', $options);
			}

			$this->cursor_dir = $cd;
			$has_cursor_info  = true;
		}

		if (isset($options[self::PAGE_PARAM])) {
			if ($has_cursor_info) {
				// page and cursor-based pagination are mutually exclusive
				// enforcing this at the parsing level simplifies pagination logic downstream,
				// as we can be sure that a request is either page-based or cursor-based, never both
				throw new ORMQueryException('GOBL_ORM_REQUEST_PAGE_AND_CURSOR_PAGINATION_ARE_MUTUALLY_EXCLUSIVE', $options);
			}

			$page = $options[self::PAGE_PARAM];

			if (!\is_numeric($page) || ($page = (int) $page) <= 0) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_PAGINATION_PAGE', $options);
			}
		}

		if (!$has_cursor_info) {
			$this->page = $page;
		}
	}

	/**
	 * Decode request collection.
	 *
	 * @param array $options
	 *
	 * @return null|string
	 *
	 * @throws ORMQueryException
	 */
	private static function collectionDecode(array $options): ?string
	{
		if (!isset($options[self::COLLECTION_PARAM])) {
			return null;
		}

		if (!\is_string($options[self::COLLECTION_PARAM])) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION', $options);
		}

		if ('' !== $options[self::COLLECTION_PARAM]) {
			if (!self::isValidCollectionName($options[self::COLLECTION_PARAM])) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_COLLECTION', $options);
			}

			return $options[self::COLLECTION_PARAM];
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
	 * @param array $options
	 *
	 * @return array
	 *
	 * @throws ORMQueryException
	 */
	private static function relationsDecode(array $options): array
	{
		if (!isset($options[self::RELATIONS_PARAM])) {
			return [];
		}

		if (\is_string($options[self::RELATIONS_PARAM])) {
			if ('' === $options[self::RELATIONS_PARAM]) {
				return [];
			}

			$relations = \array_unique(\explode(self::RELATIONS_DELIMITER, $options[self::RELATIONS_PARAM]));

			foreach ($relations as $relation) {
				if (self::isValidRelationName($relation)) {
					continue;
				}

				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $options);
			}

			return $relations;
		}

		if (\is_array($options[self::RELATIONS_PARAM])) {
			$relations = \array_unique($options[self::RELATIONS_PARAM]);

			foreach ($relations as $relation) {
				if (self::isValidRelationName($relation)) {
					continue;
				}

				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $options);
			}

			return $relations;
		}

		throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_RELATIONS', $options);
	}

	/**
	 * Encode request relations.
	 *
	 * @param list<string> $relations
	 *
	 * @return string
	 */
	private static function relationsEncode(array $relations): string
	{
		return \implode(self::RELATIONS_DELIMITER, $relations);
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
	 * Allowed orders examples:
	 * - `name` (defaults to ASC)
	 * - `name:desc` (explicit DESC)
	 * - `name:asc` (explicit ASC)
	 * - `name:DESC` (case-insensitive)
	 * - `name|created_at:desc` (multiple orders separated by `|`) => `['name' => 'ASC', 'created_at' => 'DESC']`
	 *
	 * @param array $options
	 *
	 * @return array<string, 'ASC'|'DESC'>
	 *
	 * @throws ORMQueryException
	 */
	private static function orderByDecode(array $options): array
	{
		if (!isset($options[self::ORDER_BY_PARAM])) {
			return [];
		}

		$rules = $options[self::ORDER_BY_PARAM];

		if (!\is_string($rules)) {
			throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $options);
		}

		if ('' === $rules) {
			return [];
		}

		$rules    = \array_unique(\explode(self::ORDER_BY_DELIMITER, $rules));
		$order_by = [];

		foreach ($rules as $rule) {
			if (empty($rule)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $options);
			}

			$parts = \explode(self::ORDER_BY_DELIMITER_ASC_DESC, $rule);
			$len   = \count($parts);

			if (2 === $len) {
				$name = $parts[0];
				$ord  = \strtoupper($parts[1]);
			} elseif (1 === $len) {
				$name = $rule;
				$ord  = 'ASC';
			} else {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $options);
			}

			// reject anything that is not column name
			if (!\preg_match(Column::NAME_REG, $name)) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $options);
			}

			if ('ASC' !== $ord && 'DESC' !== $ord) {
				throw new ORMQueryException('GOBL_ORM_REQUEST_INVALID_ORDER_BY', $options);
			}

			$order_by[$name] = $ord;
		}

		return $order_by;
	}

	/**
	 * Encode request order by.
	 *
	 * @param array<string, 'ASC'|'DESC'> $order_by
	 *
	 * @return string
	 */
	private static function orderByEncode(array $order_by): string
	{
		$list = [];

		foreach ($order_by as $name => $ord) {
			$list[] = 'ASC' === $ord ? $name : $name . self::ORDER_BY_DELIMITER_ASC_DESC . 'desc';
		}

		return \implode(self::ORDER_BY_DELIMITER, $list);
	}

	/**
	 * Clears cursor-based pagination parameters (cursor, cursor column and cursor direction).
	 */
	private function clearCursorPagination(): void
	{
		$this->cursor        = null;
		$this->cursor_column = null;
		$this->cursor_dir    = null;
	}
}
