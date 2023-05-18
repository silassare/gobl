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

use Countable;
use Generator;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Queries\QBSelect;
use Gobl\ORM\Exceptions\ORMRuntimeException;
use Gobl\ORM\Utils\ORMClassKind;
use Iterator;
use PDO;
use PDOStatement;

/**
 * Class ORMResults.
 */
abstract class ORMResults implements Countable, Iterator
{
	/** @var RDBMSInterface */
	protected RDBMSInterface $db;

	/** @var \Gobl\DBAL\Queries\QBSelect */
	protected QBSelect $query;

	/** @var int */
	protected int $index = 0;

	/** @var null|int */
	protected ?int $limited_count_cache = null;

	/** @var null|int */
	protected ?int $total_count_cache = null;

	/** @var bool */
	protected bool $trust_row_count = true;

	/** @var int */
	protected int $foreach_count = 0;

	/** @var null|PDOStatement */
	protected ?PDOStatement $statement = null;

	/** @var null|\Gobl\ORM\ORMEntity */
	protected ?ORMEntity $current = null;

	/** @var string */
	protected string $entity_class;

	/**
	 * ORMResults constructor.
	 *
	 * @param string                      $namespace  the table namespace
	 * @param string                      $table_name the table name
	 * @param \Gobl\DBAL\Queries\QBSelect $query      the select query builder instance
	 */
	protected function __construct(string $namespace, protected string $table_name, QBSelect $query)
	{
		$this->db           = ORM::getDatabase($namespace);
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
	 * Lazily iterate through large result set.
	 *
	 * @param bool $strict
	 * @param int  $max
	 *
	 * @return Generator<\Gobl\ORM\ORMEntity>
	 */
	public function lazy(bool $strict = true, int $max = 100): Generator
	{
		$page = 1;

		while ($this->query->limit($max, ($page - 1) * $max) && $this->getStatement(true)) {
			$count = 0;
			while ($entry = $this->fetchClass($strict)) {
				++$count;

				yield $entry;
			}

			if ($count < $max) {
				break;
			}
			++$page;
		}
	}

	/**
	 * Creates new instance.
	 *
	 * @param \Gobl\DBAL\Queries\QBSelect $query the select query builder instance
	 *
	 * @return static
	 */
	abstract public static function createInstance(QBSelect $query): static;

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
	 * Fetches  all rows and return array of the entity class instance.
	 *
	 * @param bool $strict enable/disable strict mode on class fetch
	 *
	 * @return \Gobl\ORM\ORMEntity[]
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
	 * Returns the key of the current element.
	 *
	 * @return int scalar on success, or null on failure
	 */
	public function key(): int
	{
		return $this->index;
	}

	/**
	 * Move forward to next element.
	 */
	public function next(): void
	{
		$this->current = $this->fetchClass();

		if ($this->current) {
			++$this->index;
		}
	}

	/**
	 * Fetches  the next row into table of the entity class instance.
	 *
	 * @param bool $strict enable/disable strict mode on class fetch
	 *
	 * @return null|\Gobl\ORM\ORMEntity
	 */
	public function fetchClass(bool $strict = true): ?ORMEntity
	{
		/** @var \Gobl\ORM\ORMEntity $entity_class */
		$entity_class = $this->entity_class;
		$entity       = $entity_class::createInstance(false, $strict);
		$stmt         = $this->getStatement();

		$stmt->setFetchMode(PDO::FETCH_INTO, $entity);
		$entity = $stmt->fetch();

		if ($entity instanceof $entity_class) {
			$entity->isSaved(true); // the entity is fetched from the database

			return $entity;
		}

		return null;
	}

	/**
	 * Checks if current position is valid.
	 *
	 * @return bool returns true on success or false on failure
	 */
	public function valid(): bool
	{
		return (bool) $this->current;
	}

	/**
	 * Rewind the Iterator to the first element.
	 */
	public function rewind(): void
	{
		// not supported
		if ($this->foreach_count) {
			throw new ORMRuntimeException('You cannot use the same result set in multiple foreach.');
		}

		$this->current = $this->fetchClass();

		++$this->foreach_count;
	}

	/**
	 * Returns the current element.
	 *
	 * @return null|\Gobl\ORM\ORMEntity
	 */
	public function current(): ?ORMEntity
	{
		return $this->current;
	}

	/**
	 * Count number of elements in this results.
	 *
	 * @return int the custom count as an integer
	 */
	public function count(): int
	{
		if (null === $this->limited_count_cache) {
			if (false === $this->trust_row_count) {
				$this->limited_count_cache = $this->query->runTotalRowsCount(true);
			} else {
				$this->limited_count_cache = $this->getStatement()
					->rowCount();
			}
		}

		return $this->limited_count_cache;
	}

	/**
	 * Count rows without limit.
	 *
	 * @return int
	 */
	public function totalCount(): int
	{
		if (null === $this->total_count_cache) {
			$this->total_count_cache = $this->query->runTotalRowsCount(false);
		}

		return $this->total_count_cache;
	}

	/**
	 * Runs the current query and returns a statement.
	 *
	 * We lazily run query.
	 *
	 * @param bool $force
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
