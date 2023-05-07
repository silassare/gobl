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

namespace Gobl\DBAL\Types;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Type.
 */
abstract class Type implements TypeInterface
{
	use ArrayCapableTrait;

	protected BaseTypeInterface $base_type;

	protected array $options = [];

	protected array $error_messages = [];

	protected bool $locked = false;

	/**
	 * Type constructor.
	 */
	protected function __construct(BaseTypeInterface $base_type)
	{
		$this->base_type       = $base_type;
		$this->options['type'] = $this->getName();
	}

	/**
	 * Type clone helper.
	 */
	public function __clone()
	{
		$this->locked = false; // we clone because we want to edit
	}

	/**
	 * {@inheritDoc}
	 */
	public function lock(): self
	{
		$this->locked = true;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		$opt         = $this->options;
		$opt['type'] = $this->getName();

		return $opt;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getBaseType(): BaseTypeInterface
	{
		return $this->base_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function assertFilterAllowed(Filter $filter): void
	{
		$this->safelyCallOnBaseType(__FUNCTION__, [$filter]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function shouldEnforceQueryExpressionValueType(RDBMSInterface $rdbms): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function enforceQueryExpressionValueType(string $expression, RDBMSInterface $rdbms): string
	{
		return $expression;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAllowedFilterOperators(): array
	{
		$operators = Operator::cases();

		foreach ($operators as $key => $op) {
			if (Operator::IS_FALSE === $op || Operator::IS_TRUE === $op) {
				unset($operators[$key]);
			}

			if (!$this->isNullable()) {
				if (Operator::IS_NULL === $op || Operator::IS_NOT_NULL === $op) {
					unset($operators[$key]);
				}
			}
		}

		return $operators;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isNullable(): bool
	{
		return (bool) $this->getOption('nullable', false);
	}

	/**
	 * {@inheritDoc}
	 */
	public function nullable(bool $nullable = true): self
	{
		// important as it will be used by the base type
		$this->safelyCallOnBaseType(__FUNCTION__, [$nullable]);

		return $this->setOption('nullable', $nullable);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEmptyValueOfType(): mixed
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, []);
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAutoIncremented(): bool
	{
		return (bool) $this->getOption('auto_increment', false);
	}

	/**
	 * {@inheritDoc}
	 */
	public function autoIncrement(bool $auto_increment = true): self
	{
		// important as it will be used by the base type
		$this->safelyCallOnBaseType(__FUNCTION__, [$auto_increment]);

		return $this->setOption('auto_increment', $auto_increment);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDefault(): mixed
	{
		return $this->getOption('default');
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasDefault(): bool
	{
		return null !== $this->getDefault();
	}

	/**
	 * {@inheritDoc}
	 */
	public function default(mixed $default): self
	{
		return $this->setOption('default', $default);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): array
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, []);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): array
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, []);
	}

	/**
	 * {@inheritDoc}
	 */
	public function configure(array $options): self
	{
		$nullable = $options['nullable'] ?? $options['null'] ?? null;
		if (null !== $nullable) {
			$this->nullable((bool) $nullable);
		}

		if (isset($options['auto_increment'])) {
			$this->autoIncrement((bool) $options['auto_increment']);
		}

		if (\array_key_exists('default', $options)) {
			$default = $options['default'];
			$this->default($default);
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): mixed
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, [$value, $rdbms]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): null|int|float|string
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, [$value, $rdbms]);
	}

	/**
	 * {@inheritDoc}
	 */
	final public function setOption(string $key, mixed $value): self
	{
		if ($this->locked) {
			throw new DBALRuntimeException('Locked type cannot be modified.');
		}

		$this->options[$key] = $value;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	final public function getOption(string $key, mixed $default = null): mixed
	{
		return $this->options[$key] ?? $default;
	}

	/**
	 * Checks if the first argument is the smallest.
	 *
	 * @param bool  $or_equal
	 * @param mixed $a
	 * @param mixed $b
	 *
	 * @return bool
	 */
	final protected static function isLt(mixed $a, mixed $b, bool $or_equal): bool
	{
		if ((\is_string($a) || \is_string($b)) && \function_exists('bccomp')) {
			// make sure to have bcmath
			$a = \sprintf('%F', $a);
			$b = \sprintf('%F', $b);
			$c = \bccomp($a, $b);

			return $or_equal ? $c <= 0 : $c < 0;
		}

		return $or_equal ? $a <= $b : $a < $b;
	}

	/**
	 * Checks if min & max value are in a given int range.
	 *
	 * @param mixed $min
	 * @param mixed $max
	 * @param int   $range_min
	 * @param int   $range_max
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	final protected static function assertSafeIntRange(
		mixed $min,
		mixed $max,
		int $range_min = \PHP_INT_MIN,
		int $range_max = \PHP_INT_MAX
	): void {
		if (!\is_int($min)) {
			throw new TypesException(\sprintf('min=%s is not a valid integer.', $min));
		}

		if (!\is_int($max)) {
			throw new TypesException(\sprintf('max=%s is not a valid integer.', $max));
		}

		if ($min < $range_min) {
			throw new TypesException(\sprintf('min=%s is not in range [%s,%s].', $min, $range_min, $range_max));
		}

		if ($max > $range_max) {
			throw new TypesException(\sprintf('max=%s is not in range [%s,%s].', $max, $range_min, $range_max));
		}

		if ($min > $max) {
			throw new TypesException(\sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}
	}

	/**
	 * Sets/Gets custom error message.
	 *
	 * @param string      $key     the error key
	 * @param null|string $message the error message
	 *
	 * @return string
	 */
	protected function msg(string $key, ?string $message = null): string
	{
		if (!empty($message)) {
			$this->error_messages[$key] = $message;
		}

		return $this->error_messages[$key] ?? $key;
	}

	/**
	 * Call the base type method only it is not the same as the current instance.
	 *
	 * This prevent infinite loop.
	 *
	 * @param string $method
	 * @param array  $args
	 *
	 * @return mixed
	 */
	private function safelyCallOnBaseType(string $method, array $args): mixed
	{
		if ($this->base_type->getName() === $this->getName()) {
			return null;
		}

		return \call_user_func_array([$this->base_type, $method], $args);
	}
}
