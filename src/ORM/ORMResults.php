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

use Countable;
use Generator;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\DBAL\Table;
use Gobl\ORM\Interfaces\PaginationAwareListInterface;
use Gobl\ORM\Interfaces\WithPaginationInterface;
use Gobl\ORM\Utils\Helpers;
use Gobl\ORM\Utils\ORMClassKind;
use Iterator;
use Override;
use PDO;
use PDOStatement;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class ORMResults.
 *
 * @template TEntity of ORMEntity
 *
 * @implements PaginationAwareListInterface<TEntity>
 * @implements Iterator<int, TEntity>
 */
abstract class ORMResults implements PaginationAwareListInterface, Countable, Iterator
{
	use ArrayCapableTrait;

	/** @var RDBMSInterface */
	protected RDBMSInterface $db;

	/** @var Table */
	protected Table $table;

	/** @var QBSelect */
	protected QBSelect $query;

	/** @var int */
	protected int $index = 0;

	/** @var null|int */
	protected ?int $limited_count_cache = null;

	/** @var null|int */
	protected ?int $total_count_cache = null;

	/** @var bool */
	protected bool $trust_row_count = true;

	/** @var null|PDOStatement */
	protected ?PDOStatement $statement = null;

	/**
	 * @var null|ORMEntity
	 *
	 * @psalm-var null|TEntity
	 */
	protected ?ORMEntity $current = null;

	/** @var class-string<TEntity> */
	protected string $entity_class;

	/**
	 * ORMResults constructor.
	 *
	 * @param string   $namespace  the table namespace
	 * @param string   $table_name the table name
	 * @param QBSelect $query      the select query builder instance
	 */
	protected function __construct(
		string $namespace,
		protected string $table_name,
		QBSelect $query,
	) {
		$this->db           = ORM::getDatabase($namespace);
		$this->table        = $this->db->getTable($this->table_name);
		$this->entity_class = ORMClassKind::ENTITY->getClassFQN($this->db->getTableOrFail($table_name));
		$this->query        = $query;
		$driver             = $this->db->getConnection()
			->getAttribute(PDO::ATTR_DRIVER_NAME);

		// According to the response at: https://stackoverflow.com/a/4911430
		//  - sqlite 3.x does not support rowCount
		//  - so we should not trust rowCount
		$this->trust_row_count = ('sqlite' !== $driver);
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo(): array
	{
		return ['instance_of' => static::class];
	}

	/**
	 * Returns new instance.
	 *
	 * @param QBSelect $query the select query builder instance
	 *
	 * @return static
	 */
	abstract public static function new(QBSelect $query): static;

	/**
	 * Lazily iterate through large result set.
	 *
	 * @param bool $strict    enable/disable strict mode on class fetch
	 * @param int  $chunk_max maximum number of rows to fetch per chunk
	 *
	 * @return Generator<int,TEntity>
	 */
	public function lazy(bool $strict = true, int $chunk_max = 100): Generator
	{
		$page = 1;

		while ($this->query->limit($chunk_max, ($page - 1) * $chunk_max) && $this->getStatement(true)) {
			$count = 0;
			while ($entry = $this->fetchClass($strict)) {
				++$count;

				yield $entry;
			}

			if ($count < $chunk_max) {
				break;
			}
			++$page;
		}
	}

	/**
	 * Fetches the next row into table of the entity class instance.
	 *
	 * @param bool $strict enable/disable strict mode on class fetch
	 *
	 * @return null|TEntity
	 */
	public function fetchClass(bool $strict = true): ?ORMEntity
	{
		$entity = ORM::entity($this->table, false, $strict);
		$stmt   = $this->getStatement();

		$stmt->setFetchMode(PDO::FETCH_INTO, $entity);
		$entity = $stmt->fetch();

		if ($entity instanceof $this->entity_class) {
			/**
			 * @psalm-suppress UnnecessaryVarAnnotation
			 *
			 * @var TEntity $entity
			 */
			$entity->isSaved(true); // the entity is fetched from the database

			return $entity;
		}

		return null;
	}

	/**
	 * Fetches the next row.
	 *
	 * The db type to php type conversion will be applied to fetched result
	 * only if the fetch style style options contains `\PDO::FETCH_ASSOC`
	 *
	 * @param int $fetch_style
	 *
	 * @return mixed
	 */
	public function fetch(int $fetch_style = PDO::FETCH_ASSOC): mixed
	{
		$row = $this->getStatement()
			->fetch($fetch_style);

		if ($row && ($fetch_style & PDO::FETCH_ASSOC)) {
			return $this->db->getTableOrFail($this->table_name)
				->doDbToPhpConversion($row, $this->db);
		}

		return $row;
	}

	/**
	 * Fetches all rows and return array of the entity class instance.
	 *
	 * @param bool $strict enable/disable strict mode on class fetch
	 *
	 * @return TEntity[]
	 */
	public function fetchAllClass(bool $strict = true): array
	{
		// according to https://phpdelusions.net/pdo/fetch_modes#FETCH_CLASS
		// in PDO::FETCH_CLASS mode, PDO assign class properties before calling a constructor.
		// To amend this behavior, use the following flag \PDO::FETCH_PROPS_LATE.
		$fetch_style = PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE;

		return $this->getStatement()
			->fetchAll($fetch_style, $this->entity_class, [false, $strict]);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getItems(bool $strict = true): iterable
	{
		return $this->lazy($strict);
	}

	/**
	 * Fetches all rows and returns an array of the entity class instances along with cursor metadata.
	 *
	 * The extra row used to detect `has_more` is fetched automatically by `applyPaginationLogic`.
	 * Pass plain `$max` (e.g. 25) to `makeCursorBased()` -- do NOT add 1 yourself.
	 *
	 * @param WithPaginationInterface $options pagination options
	 * @param bool                    $strict  enable/disable strict mode on class fetch
	 *
	 * @return array{items: TEntity[], next_cursor: null|int|string, cursor_column: null|string, has_more: bool}
	 */
	#[Override]
	public function getItemsWithCursorMeta(WithPaginationInterface $options, bool $strict = true): array
	{
		$expected_max  = $options->getMax();

		if (null === $expected_max || $expected_max <= 0) {
			return [
				'items'         => $this->fetchAllClass($strict),
				'next_cursor'   => null,
				'cursor_column' => null,
				'has_more'      => false,
			];
		}

		$cursor_column = Helpers::requireCursorColumn($this->table, $options);
		$all           = $this->fetchAllClass($strict);

		$has_more    = \count($all) > $expected_max;
		$items       = $has_more ? \array_slice($all, 0, $expected_max) : $all;
		$last        = !empty($items) ? $items[\count($items) - 1] : null;
		$next_cursor = null;

		if ($has_more && $last) {
			$next_cursor = (string) $last->{$cursor_column->getFullName()};
		}

		return [
			'items'         => $items,
			'next_cursor'   => $next_cursor,
			'cursor_column' => $cursor_column->getFullName(),
			'has_more'      => $has_more,
		];
	}

	/**
	 * Lazily computes the total row count by running a `SELECT COUNT(*)` query if necessary.
	 *
	 * In cursor based pagination:
	 *  - we add +1 to the max limit to detect has_more without an extra COUNT query.
	 *  - the total returned should mean total number of items available for the current cursor, not total number of items in the whole result set.
	 * In non-cursor based pagination:
	 *  - when we are on the last page, we can calculate total without an extra COUNT query if we have some rows in the current page and the number of rows found is less than the max limit (or when max limit is not set, which also means we are on the last page).
	 *  - in other cases, we need to run the COUNT query to get the total number of items in the whole result set.
	 *
	 * @param null|WithPaginationInterface $options the pagination options; when null, a default non-cursor-based context is used
	 * @param bool                         $force   whether to force a refresh of the total count
	 *
	 * @return int
	 */
	#[Override]
	public function getTotal(?WithPaginationInterface $options = null, bool $force = false): int
	{
		if (null === $this->total_count_cache || $force) {
			$computed = null;

			if (!$options?->isCursorBased()) {
				$max    = $this->query->getOptionsLimitMax();
				$offset = $this->query->getOptionsLimitOffset() ?? 0;
				$found  = $this->limited_count_cache;

				// we have some rows in the current page
				// in paginated queries we can calculate the total count without an extra query when we are on a page that is not full (i.e. the last page)
				// 1) max is set and we found less than max rows => we are on the last page, so total is offset + found
				// 2) max is not set => we are on the last page, so total is offset + found
				if (null !== $found) {
					if (null !== $max && $found < $max) {
						$computed = $offset + $found;
					} elseif (null === $max) {
						$computed = $offset + $found;
					}
				}
			}

			$this->total_count_cache = $computed ?? $this->query->runTotalRowsCount(false);
		}

		return $this->total_count_cache;
	}

	/**
	 * Returns an array containing all of the result set rows.
	 *
	 * The db type to php type conversion will be applied to fetched result
	 * only if `$fetch_style` options contains `\PDO::FETCH_ASSOC`
	 *
	 * @param int $fetch_style
	 *
	 * @return array
	 */
	public function fetchAll(int $fetch_style = PDO::FETCH_ASSOC): array
	{
		$result = $this->getStatement()
			->fetchAll($fetch_style);

		if ($result && ($fetch_style & PDO::FETCH_ASSOC)) {
			$table = $this->db->getTableOrFail($this->table_name);

			foreach ($result as $k => $row) {
				$result[$k] = $table->doDbToPhpConversion($row, $this->db);
			}
		}

		return $result;
	}

	/**
	 * Returns the current element.
	 *
	 * @return null|TEntity
	 */
	#[Override]
	public function current(): ?ORMEntity
	{
		return $this->current;
	}

	/**
	 * Returns the key of the current element.
	 *
	 * @return int scalar on success, or null on failure
	 */
	#[Override]
	public function key(): int
	{
		return $this->index;
	}

	/**
	 * Move forward to next element.
	 */
	#[Override]
	public function next(): void
	{
		$this->current = $this->fetchClass();

		if ($this->current) {
			++$this->index;
		}
	}

	/**
	 * Rewind the Iterator to the first element.
	 */
	#[Override]
	public function rewind(): void
	{
		$this->statement           = null; // reset statement to re-execute the query
		$this->limited_count_cache = null;
		$this->total_count_cache   = null;
		$this->index               = 0;

		// Note this should be called after resetting the statement and caches,
		// otherwise we might have some unexpected behavior when rewinding after
		// iterating at least one element
		$this->current             = $this->fetchClass(); // prime the first element
	}

	/**
	 * Checks if current position is valid.
	 *
	 * @return bool returns true on success or false on failure
	 */
	#[Override]
	public function valid(): bool
	{
		return (bool) $this->current;
	}

	/**
	 * Count number of elements in this results.
	 *
	 * When `$trust_row_count` is `true` (set by the driver when `rowCount()` is reliable),
	 * returns the PDO statement's `rowCount()`, which is O(1).
	 * Otherwise, re-runs the query as `SELECT COUNT(*)` with the current limit preserved
	 * (`runTotalRowsCount(true)`). The result is cached after the first call.
	 *
	 * @return int the custom count as an integer
	 */
	#[Override]
	public function count(): int
	{
		if (null === $this->limited_count_cache) {
			if (!$this->trust_row_count) {
				$this->limited_count_cache = $this->query->runTotalRowsCount(true);
			} else {
				$this->limited_count_cache = $this->getStatement()
					->rowCount();
			}
		}

		return $this->limited_count_cache;
	}

	/**
	 * Returns the underlying QBSelect instance.
	 *
	 * @return QBSelect
	 */
	public function getSelect(): QBSelect
	{
		return $this->query;
	}

	/**
	 * Returns a generator that yields entities keyed by their identity key ({@see ORMEntity::toIdentityKey()}).
	 *
	 * @param bool $strict enable/disable strict mode on class fetch
	 *
	 * @return Generator<string, TEntity>
	 */
	public function groupByKey(bool $strict = true): Generator
	{
		foreach ($this->lazy($strict) as $entity) {
			yield $entity->toIdentityKey() => $entity;
		}
	}

	/**
	 * Returns a generator that yields entities grouped by a key generated from the entity using the provided callable.
	 *
	 * @param callable(TEntity):string $key_factory callable that receives an entity and returns its group key
	 * @param bool                     $strict      enable/disable strict mode on class fetch
	 *
	 * @return Generator<string, TEntity>
	 */
	public function groupBy(callable $key_factory, bool $strict = true): Generator
	{
		foreach ($this->lazy($strict) as $entity) {
			yield $key_factory($entity) => $entity; // yield the key and the entity
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toArray(): array
	{
		return \iterator_to_array($this->lazy());
	}

	/**
	 * Runs the current query and returns a statement.
	 *
	 * Lazily executes the underlying `QBSelect` on the first call and caches the
	 * `PDOStatement`. Pass `$force = true` to re-execute the query (useful after
	 * modifying the QB between calls, though this is generally not recommended).
	 *
	 * @param bool $force re-execute even when a cached statement already exists
	 *
	 * @return PDOStatement
	 */
	protected function getStatement(bool $force = false): PDOStatement
	{
		if ($force || null === $this->statement) {
			$this->statement = $this->query->execute();
		}

		return $this->statement;
	}
}
