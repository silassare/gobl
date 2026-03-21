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

namespace Gobl\DBAL;

use Gobl\DBAL\Diff\Interfaces\DiffCapableInterface;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Gobl\Gobl;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Interfaces\MetaCapableInterface;
use PHPUtils\Lock\Interfaces\LockableInterface;
use PHPUtils\Lock\Traits\PermanentlyLockableTrait;
use PHPUtils\Str;
use PHPUtils\Traits\ArrayCapableTrait;
use PHPUtils\Traits\MetaCapableTrait;
use Throwable;

/**
 * Class Column.
 */
final class Column implements ArrayCapableInterface, MetaCapableInterface, DiffCapableInterface, LockableInterface
{
	use ArrayCapableTrait;
	use MetaCapableTrait;
	use PermanentlyLockableTrait {
		lock as private traitLock;
	}

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
	 * The old column name used to compute the diff key during rename migration.
	 * Not serialized in toArray() - remove after the migration has run.
	 *
	 * @var null|string
	 */
	private ?string $old_name = null;

	/**
	 * The old column prefix used together with old_name to compute the diff key.
	 * null means "use the current prefix" when old_name is set.
	 * Not serialized in toArray() - remove after the migration has run.
	 *
	 * @var null|string
	 */
	private ?string $old_prefix = null;

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

	private bool $locked_name = false;

	private ?Table $table = null;

	/**
	 * Column constructor.
	 *
	 * @param string                   $name   the column name
	 * @param null|string              $prefix the column prefix
	 * @param null|array|TypeInterface $type   the column type or type options
	 */
	public function __construct(string $name, ?string $prefix = null, array|TypeInterface|null $type = null)
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
		$this->type  = clone $this->type;
		$this->meta  = $this->meta ? clone $this->meta : null;
		$this->table = null;

		// we clone because we want to edit
		$this->lock_instance = $this->createLock();
		$this->locked_name   = false;
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
	public function setTypeFromOptions(array $options): static
	{
		$this->assertNotLocked();

		try {
			$this->type = TypeUtils::buildTypeOrFail($options);
			$this->syncWithType();
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
	public function setName(string $name): static
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
	public function lockName(): static
	{
		$this->locked_name = true;

		return $this;
	}

	/**
	 * Locks this column to prevent further changes.
	 *
	 * Associates the column with `$table`, finalizes the full column name, and locks the
	 * column's type. Locking is idempotent for the same `$table` instance - calling lock
	 * again with the same table is a no-op. Calling lock with a **different** table throws
	 * `DBALRuntimeException` to prevent a column instance from being shared across tables.
	 *
	 * @param Table $table the table this column belongs to
	 *
	 * @return $this
	 *
	 * @throws DBALRuntimeException when already locked to a different table
	 */
	public function lockWithTable(Table $table): static
	{
		if (!$this->isLocked() || !$this->table) {
			$this->assertIsValid();

			$this->table  = $table;

			$this->type->lock();
			$this->syncWithType();

			$this->traitLock();
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
	 * Locks this column without a table context.
	 */
	#[Override]
	public function lock(): static
	{
		throw new LogicException(\sprintf(
			'Column "%s" must be added to a table before it can be locked. Use %s to lock "%s" column with its table context.',
			$this->name,
			Str::callableName([$this, 'lockWithTable']),
			$this->name
		));
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
	 * Sets the column reference, which causes this column to inherit its type from another column.
	 *
	 * Two reference modes are supported:
	 *  - **`ref:` (reference)** - the resolved type is shared; changes to the source column's
	 *    type options (via `cleanColumnTypeOptionsForReference`) are reflected here.
	 *    `auto_increment` is stripped from the derived column.
	 *  - **`cp:` (copy)** - the resolved type options are cloned independently at resolution
	 *    time; subsequent changes to the source have no effect.
	 *
	 * The stored string format is `ref:table_name.column_name` or `cp:table_name.column_name`.
	 * When a `Column` instance is passed, the mode is determined by `$copy`.
	 * When a plain string is passed, it must already be in the `ref:`/`cp:` format.
	 *
	 * @param null|Column|string $reference `Column` instance, formatted reference string, or `null` to clear
	 * @param bool               $copy      When `true` (and `$reference` is a `Column` instance),
	 *                                      stores the reference as `cp:` (independent copy)
	 *
	 * @return $this
	 *
	 * @throws InvalidArgumentException when the string format is invalid or the column instance
	 *                                  has not yet been added to a table
	 */
	public function setReference(self|string|null $reference, bool $copy = false): static
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
	public function setPrivate(bool $private = true): static
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
	public function setSensitive(bool $sensitive = true, mixed $redacted_value = null): static
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
	public function setType(TypeInterface $type): static
	{
		$this->assertNotLocked();

		$this->type = $type;

		$this->syncWithType();

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
	public function setPrefix(string $prefix): static
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

	#[Override]
	public function toArray(): array
	{
		if (!$this->isLocked()) {
			$this->syncWithType();
		}

		$options = (array) $this->type->toArray();

		$meta = $this->getMeta()->toArray();

		if (!empty($meta)) {
			$options['meta'] = $meta;
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

		if ($this->reference) {
			$options['type'] = $this->reference;
		}

		$options['diff_key'] = $this->getDiffKey();

		return $options;
	}

	#[Override]
	public function getDiffKey(): string
	{
		if (null !== $this->old_name) {
			// when old_name is set, derive the diff key from the old full name so the
			// diff engine can match this column against its previous identity
			$table_key  = $this->table ? $this->table->getDiffKey() : \uniqid('', true);
			$old_prefix = $this->old_prefix ?? $this->prefix;
			$old_full   = empty($old_prefix) ? $this->old_name : $old_prefix . '_' . $this->old_name;

			return \md5($table_key . '/' . $old_full);
		}

		if (empty($this->diff_key)) {
			$table_key      = $this->table ? $this->table->getDiffKey() : \uniqid('', true);
			$this->diff_key = \md5($table_key . '/' . $this->getFullName());
		}

		return $this->diff_key;
	}

	#[Override]
	public function setDiffKey(string $diff_key): static
	{
		$this->assertNotLocked();

		if (empty($diff_key)) {
			throw new InvalidArgumentException(\sprintf('Column "%s" diff key should not be empty', $this->name));
		}

		$this->diff_key = $diff_key;

		return $this;
	}

	/**
	 * Sets the old column name used for rename-tracking across migrations.
	 *
	 * When set, `getDiffKey()` returns a key derived from the old full name
	 * (`old_prefix` (or current prefix if omitted) + `_` + `old_name`), allowing
	 * the diff engine to match this column against its previous identity.
	 *
	 * Remove this option from the schema once the migration has been applied.
	 *
	 * @param string $old_name the column short name before the rename
	 *
	 * @return $this
	 */
	public function oldName(string $old_name): static
	{
		$this->assertNotLocked();

		if (!\preg_match(self::NAME_REG, $old_name)) {
			throw new InvalidArgumentException(
				\sprintf('Column old name "%s" should match: %s', $old_name, self::NAME_PATTERN)
			);
		}

		$this->old_name = $old_name;

		return $this;
	}

	/**
	 * Sets the old column prefix used together with `oldName()` to reconstruct the previous full name.
	 *
	 * When omitted (null), the current column prefix is used.
	 * Pass an empty string explicitly when the column previously had no prefix.
	 *
	 * @param string $old_prefix the column prefix before the rename (may be empty)
	 *
	 * @return $this
	 */
	public function oldPrefix(string $old_prefix): static
	{
		$this->assertNotLocked();

		if (!empty($old_prefix) && !\preg_match(self::PREFIX_REG, $old_prefix)) {
			throw new InvalidArgumentException(
				\sprintf('Column old prefix "%s" should match: %s', $old_prefix, self::PREFIX_PATTERN)
			);
		}

		$this->old_prefix = $old_prefix;

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

	private function syncWithType(): void
	{
		$this->getMeta()->merge($this->type->getMeta());
	}
}
