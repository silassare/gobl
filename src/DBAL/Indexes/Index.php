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

namespace Gobl\DBAL\Indexes;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;
use InvalidArgumentException;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Index.
 */
final class Index implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	public const NAME_PATTERN = '[a-zA-Z](?:[a-zA-Z0-9_]*[a-zA-Z0-9])?';

	public const NAME_REG = '~^' . self::NAME_PATTERN . '$~';

	public const PRIMARY_KEY = 1;

	public const UNIQUE_KEY = 2;

	public const FOREIGN_KEY = 3;

	public const MAX_INDEX_NAME_LENGTH = 64;

	/** @var null|IndexType */
	protected ?IndexType $type = null;

	/** @var string */
	protected string $name;

	/** @var Table */
	protected Table $host_table;

	/** @var bool */
	protected bool $locked = false;

	/** @var array<int,string> */
	private array $columns = [];

	/**
	 * Index constructor.
	 *
	 * @param string         $name       the index name
	 * @param Table          $host_table the table in which the index was defined
	 * @param null|IndexType $type       the RDBMS-specific index type, or null for the default B-Tree index
	 */
	public function __construct(string $name, Table $host_table, ?IndexType $type = null)
	{
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf(
				'Index name "%s" in table "%s" should match: %s',
				$name,
				$host_table->getName(),
				self::NAME_PATTERN
			));
		}

		if (\strlen($name) > self::MAX_INDEX_NAME_LENGTH) {
			throw new InvalidArgumentException(\sprintf(
				'Index name "%s" in table "%s" should not exceed %d characters.',
				$name,
				$host_table->getName(),
				self::MAX_INDEX_NAME_LENGTH
			));
		}

		$this->name       = $name;
		$this->host_table = $host_table;
		$this->type       = $type;
	}

	/**
	 * Gets index name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Returns index's host table.
	 *
	 * @return Table
	 */
	public function getHostTable(): Table
	{
		return $this->host_table;
	}

	/**
	 * Locks this index to prevent further changes.
	 *
	 * @return $this
	 */
	public function lock(): self
	{
		if (!$this->locked) {
			$this->assertIsValid();

			$this->locked = true;
		}

		return $this;
	}

	/**
	 * Asserts if this index is not locked.
	 *
	 * @throws DBALException
	 */
	public function assertNotLocked(): void
	{
		if ($this->locked) {
			throw new DBALException(\sprintf(
				'You should not try to edit locked index "%s".',
				$this->name
			));
		}
	}

	/**
	 * Adds a column to the index.
	 *
	 * @param string $name the column name
	 *
	 * @return $this
	 *
	 * @throws DBALException
	 */
	public function addColumn(string $name): self
	{
		$this->assertNotLocked();

		$this->columns[] = $this->host_table->getColumnOrFail($name)
			->getFullName();

		return $this;
	}

	/**
	 * Gets index columns.
	 *
	 * @return string[]
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}

	/**
	 * Gets the RDBMS-specific index type, or null for the default (B-Tree) index.
	 *
	 * @return null|IndexType
	 */
	public function getType(): ?IndexType
	{
		return $this->type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBALException
	 */
	public function assertIsValid(): void
	{
		if (empty($this->columns)) {
			throw new DBALException(\sprintf(
				'Index "%s" in table "%s" must have at least one column.',
				$this->name,
				$this->host_table->getName()
			));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$columns = [];

		foreach ($this->columns as $full_name) {
			$columns[] = $this->host_table->getColumnOrFail($full_name)
				->getName();
		}

		$result = [
			'columns' => $columns,
		];

		if (null !== $this->type) {
			$result['type'] = $this->type->value;
		}

		return $result;
	}
}
