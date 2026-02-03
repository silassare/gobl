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

namespace Gobl\DBAL;

use Gobl\DBAL\Diff\Interfaces\DiffCapableInterface;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Traits\MetadataTrait;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Gobl\Gobl;
use InvalidArgumentException;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;
use Throwable;

/**
 * Class Column.
 */
final class Column implements ArrayCapableInterface, DiffCapableInterface
{
	use ArrayCapableTrait;
	use MetadataTrait;

	public const NAME_PATTERN = '[a-z](?:[a-z0-9_]*[a-z0-9])?';

	public const NAME_REG = '~^' . self::NAME_PATTERN . '$~';

	public const PREFIX_PATTERN = '[a-z](?:[a-z0-9_]*[a-z0-9])?';

	public const PREFIX_REG = '~^' . self::PREFIX_PATTERN . '$~';

	/**
	 * The column diff key.
	 *
	 * @var null|string
	 */
	private ?string $diff_key = null;

	/**
	 * The column name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The column prefix.
	 *
	 * @var string
	 */
	private string $prefix = '';

	/**
	 * Column private state.
	 *
	 * @var bool
	 */
	private bool $private = false;

	/**
	 * Column sensitive state.
	 *
	 * @var bool
	 */
	private bool $sensitive = false;

	/**
	 * Redacted value.
	 *
	 * @var mixed
	 */
	private mixed $sensitive_redacted_value = null;

	/**
	 * The column type instance.
	 *
	 * @var TypeInterface
	 */
	private TypeInterface $type;
	private ?string $reference = null;

	private bool $locked = false;

	private bool $locked_name = false;

	private ?Table $table = null;

	/**
	 * Column constructor.
	 *
	 * @param string                   $name   the column name
	 * @param null|string              $prefix the column prefix
	 * @param null|array|TypeInterface $type   the column type or type options
	 */
	public function __construct(string $name, ?string $prefix = null, null|array|TypeInterface $type = null)
	{
		$this->setName($name);

		if (!empty($prefix)) {
			$this->setPrefix($prefix);
		}

		if (null !== $type) {
			if (\is_array($type)) {
				$this->setTypeFromOptions($type);
			} elseif ($type instanceof TypeInterface) {
				$this->setType($type);
			} else {
				throw new InvalidArgumentException(
					\sprintf(
						'Column "%s" type should be an array or an instance of "%s".',
						$this->name,
						TypeInterface::class
					)
				);
			}
		} else {
			$this->type = new TypeString();
		}
	}

	/**
	 * Column clone helper.
	 */
	public function __clone()
	{
		$this->type        = clone $this->type;
		$this->locked      = false; // we clone because we want to edit
		$this->locked_name = false; // we clone because we want to edit
		$this->table       = null;
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo(): array
	{
		return ['instance_of' => self::class, 'column_name' => $this->getName()];
	}

	/**
	 * Sets type from type array options.
	 *
	 * @param array $options
	 *
	 * @return $this
	 */
	public function setTypeFromOptions(array $options): self
	{
		$this->assertNotLocked();

		try {
			$this->type = TypeUtils::buildTypeOrFail($options);
		} catch (Throwable $t) {
			throw new DBALRuntimeException(
				\sprintf(
					'Unable to instantiate type for column "%s".',
					$this->name
				),
				null,
				$t
			);
		}

		return $this;
	}

	/**
	 * Asserts if this column is not locked.
	 */
	public function assertNotLocked(): void
	{
		if ($this->locked) {
			throw new DBALRuntimeException(
				\sprintf(
					'You should not try to edit locked column "%s".',
					$this->name
				)
			);
		}
	}

	/**
	 * Gets column name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Sets the column name.
	 *
	 * @param string $name
	 *
	 * @return $this
	 */
	public function setName(string $name): self
	{
		$this->assertNotLocked();
		$this->assertNameNotLocked();

		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Column name "%s" should match: %s',
					$name,
					self::NAME_PATTERN
				)
			);
		}

		$this->name = $name;

		return $this;
	}

	/**
	 * Asserts if this table name is not locked.
	 */
	public function assertNameNotLocked(): void
	{
		if ($this->locked_name) {
			throw new DBALRuntimeException(
				\sprintf(
					'You should not try to edit locked column (%s) name or prefix.',
					$this->name
				)
			);
		}
	}

	/**
	 * Lock column name.
	 *
	 * @return $this
	 */
	public function lockName(): self
	{
		$this->locked_name = true;

		return $this;
	}

	/**
	 * Locks this column to prevent further changes.
	 *
	 * @return $this
	 */
	public function lock(Table $table): self
	{
		if (!$this->locked || !$this->table) {
			$this->assertIsValid();
			$this->locked = true;
			$this->table  = $table;
			$this->type->lock();
		} elseif ($table !== $this->table) {
			throw new DBALRuntimeException(
				\sprintf(
					'Can\'t lock column "%s" in table "%s" as it is already locked in "%s".',
					$this->getName(),
					$table->getName(),
					$this->table->getName()
				)
			);
		}

		return $this;
	}

	/**
	 * Asserts if this column definition/instance is valid.
	 */
	public function assertIsValid(): void
	{
		if (!Gobl::isAllowedColumnName($this->name)) {
			throw new DBALRuntimeException(
				\sprintf(
					'Column name "%s" is not allowed.',
					$this->name
				)
			);
		}
	}

	/**
	 * Gets column reference.
	 *
	 * @return null|string
	 */
	public function getReference(): ?string
	{
		return $this->reference;
	}

	/**
	 * Sets column reference.
	 *
	 * @param null|Column|string $reference Column instance or column reference string
	 * @param bool               $copy      If true and reference is a column instance, the column type will
	 *                                      be considered as a copy of the reference column type
	 *
	 * @return Column
	 */
	public function setReference(null|self|string $reference, bool $copy = false): self
	{
		$this->assertNotLocked();

		if (empty($reference)) {
			$this->reference = null;

			return $this;
		}

		if (\is_string($reference)) {
			if (!Db::isColumnReference($reference)) {
				throw new InvalidArgumentException(
					\sprintf('Invalid column reference "%s" for column "%s".', $reference, $this->getName())
				);
			}

			$this->reference = $reference;

			return $this;
		}

		$table = $reference->getTable();

		if ($table) {
			$this->reference = ($copy ? 'cp' : 'ref') . ':' . $table->getName() . '.' . $reference->getName();

			return $this;
		}

		throw new InvalidArgumentException(
			\sprintf(
				'Column "%s" not added to a known table could not be added as reference for column "%s".',
				$reference->getName(),
				$this->getName()
			)
		);
	}

	/**
	 * Gets the table in which the column is locked.
	 *
	 * @return null|Table
	 */
	public function getTable(): ?Table
	{
		return $this->table;
	}

	/**
	 * Checks if the column is private.
	 *
	 * @return bool
	 */
	public function isPrivate(): bool
	{
		return $this->private;
	}

	/**
	 * Sets this column as private.
	 *
	 * @return $this
	 */
	public function setPrivate(bool $private = true): self
	{
		$this->assertNotLocked();

		$this->private = $private;

		return $this;
	}

	/**
	 * Checks if the column is sensitive.
	 *
	 * @return bool
	 */
	public function isSensitive(): bool
	{
		return $this->sensitive;
	}

	/**
	 * Sets this column as sensitive.
	 *
	 * @param bool  $sensitive      If true, the column is sensitive
	 * @param mixed $redacted_value The value to use when the column is redacted
	 *
	 * @return $this
	 */
	public function setSensitive(bool $sensitive = true, mixed $redacted_value = null): self
	{
		$this->assertNotLocked();

		$this->sensitive                = $sensitive;
		$this->sensitive_redacted_value = $redacted_value;

		return $this;
	}

	/**
	 * Gets sensitive redacted value.
	 *
	 * @return mixed
	 */
	public function getSensitiveRedactedValue(): mixed
	{
		return $this->sensitive_redacted_value;
	}

	/**
	 * Gets type object.
	 *
	 * @return TypeInterface
	 */
	public function getType(): TypeInterface
	{
		return $this->type;
	}

	/**
	 * Sets the column type.
	 *
	 * @param TypeInterface $type
	 *
	 * @return $this
	 */
	public function setType(TypeInterface $type): self
	{
		$this->assertNotLocked();

		$this->type = $type;

		return $this;
	}

	/**
	 * Gets column prefix.
	 *
	 * @return string
	 */
	public function getPrefix(): string
	{
		return $this->prefix;
	}

	/**
	 * Sets the column prefix.
	 *
	 * @param string $prefix
	 *
	 * @return $this
	 */
	public function setPrefix(string $prefix): self
	{
		$this->assertNotLocked();
		$this->assertNameNotLocked();

		if (!empty($prefix) && !\preg_match(self::PREFIX_REG, $prefix)) {
			throw new InvalidArgumentException(
				\sprintf(
					'Column prefix "%s" for column "%s" should match: %s',
					$prefix,
					$this->name,
					self::PREFIX_PATTERN
				)
			);
		}

		$this->prefix = $prefix;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$options = [
			'diff_key' => $this->getDiffKey(),
			'type'     => $this->type->getName(),
		];

		if (!empty($this->meta)) {
			$options['meta'] = $this->meta->toArray();
		}
		if (!empty($this->prefix)) {
			$options['prefix'] = $this->prefix;
		}

		if ($this->private) {
			$options['private'] = $this->private;
		}

		if ($this->sensitive) {
			$options['sensitive']                = $this->sensitive;
			$options['sensitive_redacted_value'] = $this->sensitive_redacted_value;
		}

		$options = \array_merge($options, $this->type->toArray());

		if ($this->reference) {
			$options['type'] = $this->reference;
		}

		return $options;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDiffKey(): string
	{
		if (empty($this->diff_key)) {
			$table_key      = $this->table ? $this->table->getDiffKey() : \uniqid('', true);
			$this->diff_key = \md5($table_key . '/' . $this->getFullName());
		}

		return $this->diff_key;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setDiffKey(string $diff_key): self
	{
		$this->assertNotLocked();

		if (empty($diff_key)) {
			throw new InvalidArgumentException(\sprintf('Column "%s" diff key should not be empty', $this->name));
		}

		$this->diff_key = $diff_key;

		return $this;
	}

	/**
	 * Gets column full name.
	 *
	 * @return string
	 */
	public function getFullName(): string
	{
		if (empty($this->prefix)) {
			return $this->name;
		}

		return $this->prefix . '_' . $this->name;
	}
}
