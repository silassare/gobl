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

namespace Gobl\DBAL\Constraints;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Table;
use InvalidArgumentException;
use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Interfaces\LockInterface;
use PHPUtils\Traits\ArrayCapableTrait;
use PHPUtils\Traits\LockTrait;

/**
 * Class Constraint.
 */
abstract class Constraint implements ArrayCapableInterface, LockInterface
{
	use ArrayCapableTrait;
	use LockTrait;

	public const NAME_PATTERN = '[a-zA-Z](?:[a-zA-Z0-9_]*[a-zA-Z0-9])?';

	public const NAME_REG = '~^' . self::NAME_PATTERN . '$~';

	public const PRIMARY_KEY = 1;

	public const UNIQUE_KEY = 2;

	public const FOREIGN_KEY = 3;

	public const MAX_CONSTRAINT_NAME_LENGTH = 64;

	/** @var int */
	protected int $type;

	/** @var string */
	protected string $name;

	/** @var Table */
	protected Table $host_table;

	/**
	 * Constraint constructor.
	 *
	 * @param string $name       the constraint name
	 * @param Table  $host_table the table in which the constraint was defined
	 * @param int    $type       the constraint type
	 */
	public function __construct(string $name, Table $host_table, int $type)
	{
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf(
				'Constraint name "%s" in table "%s" should match: %s',
				$name,
				$host_table->getName(),
				self::NAME_PATTERN
			));
		}

		if (\strlen($name) > self::MAX_CONSTRAINT_NAME_LENGTH) {
			throw new InvalidArgumentException(\sprintf(
				'Constraint name "%s" in table "%s" should not exceed %d characters.',
				$name,
				$host_table->getName(),
				self::MAX_CONSTRAINT_NAME_LENGTH
			));
		}

		$this->name       = $name;
		$this->host_table = $host_table;
		$this->type       = $type;
	}

	/**
	 * Gets constraint name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Normalizes constraint name.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public static function normalizeName(string $name): string
	{
		$name = \preg_replace('~[^a-zA-Z0-9_]+~', '_', $name);
		$name = \preg_replace('~_+~', '_', $name);
		$name = \trim($name, '_');

		return \substr($name, 0, self::MAX_CONSTRAINT_NAME_LENGTH);
	}

	/**
	 * Returns constraints host table.
	 *
	 * @return Table
	 */
	public function getHostTable(): Table
	{
		return $this->host_table;
	}

	/**
	 * Locks this constraints to prevent further changes.
	 *
	 * @return static
	 */
	#[Override]
	public function lock(): static
	{
		if (!$this->locked) {
			$this->assertIsValid();

			$this->locked = true;
		}

		return $this;
	}

	/**
	 * Asserts if this constraint is valid.
	 */
	abstract public function assertIsValid(): void;

	/**
	 * Asserts if this constraint is not locked.
	 *
	 * @throws DBALException
	 */
	#[Override]
	public function assertNotLocked(): void
	{
		if ($this->locked) {
			throw new DBALException(\sprintf(
				'You should not try to edit locked constraint "%s".',
				$this->name
			));
		}
	}

	/**
	 * Gets constraint type.
	 *
	 * @return int
	 */
	public function getType(): int
	{
		return $this->type;
	}
}
