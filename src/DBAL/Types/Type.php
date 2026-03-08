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

use Gobl\DBAL\Column;
use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Filters\Filter;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Table;
use Gobl\DBAL\Traits\LockTrait;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\BaseTypeInterface;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\Interfaces\TypeValidatorInterface;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Utils\TypeUtils;
use Gobl\DBAL\Types\Validation\ValidationSubject;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\ORMTypeHint;
use OLIUP\CG\PHPMethod;
use PHPUtils\Traits\ArrayCapableTrait;
use Throwable;

/**
 * Class Type.
 *
 * @template TUnsafe
 * @template TClean
 *
 * @implements TypeInterface<TUnsafe, TClean>
 */
abstract class Type implements TypeInterface
{
	use ArrayCapableTrait;
	use LockTrait;

	protected BaseTypeInterface $base_type;

	protected array $options = [];

	protected array $error_messages = [];

	private ?TypeValidatorInterface $_type_pre_validator = null;

	private ?TypeValidatorInterface $_type_post_validator = null;

	/**
	 * Type constructor.
	 */
	protected function __construct(BaseTypeInterface $base_type)
	{
		$this->base_type       = $base_type;
		$this->options['type'] = $this->getName();
	}

	/**
	 * Resets the lock and deep-clones the base type so the cloned instance
	 * can be safely modified without affecting the original.
	 *
	 * The `base_type` is rebuilt from its own `toArray()` representation via
	 * `TypeUtils::buildTypeOrFail()` rather than a shallow `clone`, ensuring all
	 * nested option state is independent.
	 */
	public function __clone()
	{
		$this->locked    = false; // we clone because we want to edit
		$this->base_type = TypeUtils::buildTypeOrFail($this->base_type->toArray());
	}

	public function assertFilterAllowed(Filter $filter): void
	{
		$this->safelyCallOnBaseType(__FUNCTION__, [$filter]);
	}

	public function autoIncrement(bool $auto_increment = true): static
	{
		// important as it will be used by the base type
		$this->safelyCallOnBaseType(__FUNCTION__, [$auto_increment]);

		return $this->setOption('auto_increment', $auto_increment);
	}

	public function configure(array $options): static
	{
		$nullable = $options['nullable'] ?? $options['null'] ?? null;
		if (null !== $nullable) {
			$this->nullable((bool) $nullable);
		}

		if (isset($options['auto_increment'])) {
			$this->autoIncrement((bool) $options['auto_increment']);
		}

		if (\array_key_exists('default', $options)) {
			$this->default($options['default']);
		}

		if (isset($options['validator:pre'])) {
			$this->preValidator((string) $options['validator:pre']);
		}

		if (isset($options['validator:post'])) {
			$this->postValidator((string) $options['validator:post']);
		}

		return $this;
	}

	public function dbQueryDefault(RDBMSInterface $rdbms): ?string
	{
		return null;
	}

	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): mixed
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, [$value, $rdbms]);
	}

	public function default(mixed $default): static
	{
		// we don't call the base type here
		// because the default value may not be a scalar value
		// and as default value set on base type is used to
		// generate table sql query it may cause errors
		// if required a custom type should override this method
		return $this->setOption('default', $default);
	}

	public function enforceQueryExpressionValueType(string $expression, RDBMSInterface $rdbms): string
	{
		return $expression;
	}

	public function queryBuilderApplyFilter(ORMTableQuery $qb, Column $column, Operator $operator, array $args): void
	{
		$value = $args[0] ?? null;

		$qb->filterBy($column->getFullName(), $operator, $value);
	}

	public function queryBuilderEnhanceFilterMethod(Table $table, Column $column, Operator $operator, PHPMethod $method): void
	{
		$this->safelyCallOnBaseType(__FUNCTION__, [$table, $column, $operator, $method]);
	}

	public function getAllowedFilterOperators(): array
	{
		$operators = $this->safelyCallOnBaseType(__FUNCTION__, []) ?? Operator::cases();

		foreach ($operators as $key => $op) {
			if (Operator::IS_FALSE === $op || Operator::IS_TRUE === $op) {
				unset($operators[$key]);
			}

			// JSON operators are only available with a native JSON column.
			if (
				(Operator::CONTAINS === $op || Operator::HAS_KEY === $op)
				&& !$this->getOption('native_json', false)
			) {
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

	public function getBaseType(): BaseTypeInterface
	{
		return $this->base_type;
	}

	public function getDefault(): mixed
	{
		return $this->getOption('default');
	}

	public function getEmptyValueOfType(): mixed
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, []);
	}

	final public function getOption(string $key, mixed $default = null): mixed
	{
		return $this->options[$key] ?? $default;
	}

	public function getReadTypeHint(): ORMTypeHint
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, []);
	}

	public function getWriteTypeHint(): ORMTypeHint
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, []);
	}

	public function hasDefault(): bool
	{
		return null !== $this->getDefault();
	}

	public function isAutoIncremented(): bool
	{
		return (bool) $this->getOption('auto_increment', false);
	}

	public function isNullable(): bool
	{
		return (bool) $this->getOption('nullable', false);
	}

	public function lock(): static
	{
		if ($this->locked) {
			return $this;
		}

		if ($this->hasDefault()) {
			try {
				$this->validate($this->getDefault());
			} catch (Throwable $e) {
				throw new DBALRuntimeException(
					\sprintf('Default value for type "%s" failed validation.', $this->getName()),
					null,
					$e
				);
			}
		}

		$this->locked = true;

		return $this;
	}

	public function nullable(bool $nullable = true): static
	{
		// important as it will be used by the base type
		$this->safelyCallOnBaseType(__FUNCTION__, [$nullable]);

		return $this->setOption('nullable', $nullable);
	}

	final public function applyValidation(ValidationSubjectInterface $subject): bool
	{
		// Pre-validator: skip when already terminal
		if (!$subject->isTerminal()) {
			$pre = $this->getPreValidator();

			if (null !== $pre) {
				$pre->preValidate($subject);
			}
		}

		// Core type validation: skip when already terminal
		if (!$subject->isTerminal()) {
			try {
				$this->runValidation($subject);
			} catch (TypesInvalidValueException $e) {
				$subject->reject($e);
			}
		}

		// Post-validator: always runs
		$post = $this->getPostValidator();

		if (null !== $post) {
			$post->postValidate($subject);
		}

		return $subject->isValid();
	}

	public function createValidationSubject(mixed $value, string $reference = '', string $referenceDebug = ''): ValidationSubjectInterface
	{
		return new ValidationSubject($value, $reference, $referenceDebug);
	}

	public function validate(mixed $value, string $reference = '', string $referenceDebug = ''): ValidationSubjectInterface
	{
		if ($value instanceof ValidationSubjectInterface) {
			$subject = $value;
		} else {
			$subject = $this->createValidationSubject($value, $reference, $referenceDebug);
		}

		if (!$this->applyValidation($subject)) {
			$ex = $subject->getRejectionException();

			throw $ex ?? new TypesInvalidValueException(
				\sprintf('Validation failed for type "%s".', $this->getName())
			);
		}

		return $subject;
	}

	/**
	 * Attaches a pre-validator to run before this type's own {@see runValidation()}.
	 *
	 * @param string|TypeValidatorInterface $validator a validator instance or its FQCN
	 *
	 * @return $this
	 *
	 * @throws TypesException when a FQCN is given that does not implement TypeValidatorInterface
	 */
	public function preValidator(string|TypeValidatorInterface $validator): static
	{
		$this->assertNotLocked();

		if ($validator instanceof TypeValidatorInterface) {
			$this->_type_pre_validator      = $validator;
			$this->options['validator:pre'] = $validator::class;
		} else {
			if (!\is_a($validator, TypeValidatorInterface::class, true)) {
				throw new TypesException(\sprintf(
					'validator:pre class "%s" must implement %s.',
					$validator,
					TypeValidatorInterface::class
				));
			}

			$this->options['validator:pre'] = $validator;
			$this->_type_pre_validator      = null; // lazy instantiation
		}

		return $this;
	}

	/**
	 * Attaches a post-validator to run after this type's own {@see runValidation()}.
	 *
	 * @param string|TypeValidatorInterface $validator a validator instance or its FQCN
	 *
	 * @return $this
	 *
	 * @throws TypesException when a FQCN is given that does not implement TypeValidatorInterface
	 */
	public function postValidator(string|TypeValidatorInterface $validator): static
	{
		$this->assertNotLocked();

		if ($validator instanceof TypeValidatorInterface) {
			$this->_type_post_validator      = $validator;
			$this->options['validator:post'] = $validator::class;
		} else {
			if (!\is_a($validator, TypeValidatorInterface::class, true)) {
				throw new TypesException(\sprintf(
					'validator:post class "%s" must implement %s.',
					$validator,
					TypeValidatorInterface::class
				));
			}

			$this->options['validator:post'] = $validator;
			$this->_type_post_validator      = null; // lazy instantiation
		}

		return $this;
	}

	public function phpToDb(mixed $value, RDBMSInterface $rdbms): float|int|string|null
	{
		return $this->safelyCallOnBaseType(__FUNCTION__, [$value, $rdbms]);
	}

	final public function setOption(string $key, mixed $value): static
	{
		$this->assertNotLocked();

		$this->options[$key] = $value;

		return $this;
	}

	public function shouldEnforceDefaultValue(RDBMSInterface $rdbms): bool
	{
		return true;
	}

	public function shouldEnforceQueryExpressionValueType(RDBMSInterface $rdbms): bool
	{
		return false;
	}

	public function toArray(): array
	{
		$opt         = $this->options;
		$opt['type'] = $this->getName();

		return $opt;
	}

	/**
	 * The core validation logic for this type.
	 *
	 * Called from {@see validate()} after the pre-validator and before the post-validator,
	 * both of which are skipped when the subject is already terminal.
	 * Call `$subject->accept($cleanValue)` on success, or `$subject->reject($reason)` on failure.
	 * May also throw {@see TypesInvalidValueException}; it is caught and forwarded to
	 * `$subject->reject()` automatically.
	 */
	abstract protected function runValidation(ValidationSubjectInterface $subject): void;

	/**
	 * Returns the pre-validator, instantiating from class name when set via options.
	 */
	protected function getPreValidator(): ?TypeValidatorInterface
	{
		if (null === $this->_type_pre_validator) {
			$class = $this->getOption('validator:pre');

			if (null !== $class) {
				$this->_type_pre_validator = new $class();
			}
		}

		return $this->_type_pre_validator;
	}

	/**
	 * Returns the post-validator, instantiating from class name when set via options.
	 */
	protected function getPostValidator(): ?TypeValidatorInterface
	{
		if (null === $this->_type_post_validator) {
			$class = $this->getOption('validator:post');

			if (null !== $class) {
				$this->_type_post_validator = new $class();
			}
		}

		return $this->_type_post_validator;
	}

	/**
	 * Checks if min & max value are in a given int range.
	 *
	 * @param mixed $min
	 * @param mixed $max
	 * @param int   $range_min
	 * @param int   $range_max
	 *
	 * @throws TypesException
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
			$a = \sprintf('%F', $a);
			$b = \sprintf('%F', $b);
			$c = \bccomp($a, $b);

			return $or_equal ? $c <= 0 : $c < 0;
		}

		return $or_equal ? $a <= $b : $a < $b;
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
	 * Forwards a method call to the base type only when the base type is a **different** type.
	 *
	 * When the wrapper type and the base type share the same `getName()` (i.e. `Type` wraps
	 * itself — a self-referential configuration), the call would recurse infinitely.
	 * This guard returns `null` in that case, preventing the infinite loop.
	 *
	 * @param string $method the method name to call on `$this->base_type`
	 * @param array  $args   positional arguments to pass
	 *
	 * @return mixed the return value from the base type method, or `null` for self-references
	 */
	protected function safelyCallOnBaseType(string $method, array $args): mixed
	{
		if ($this->base_type->getName() === $this->getName()) {
			return null;
		}

		return \call_user_func_array([$this->base_type, $method], $args);
	}
}
