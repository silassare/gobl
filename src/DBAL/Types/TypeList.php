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
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Utils\JsonOfInterface;
use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use InvalidArgumentException;
use JsonException;
use OLIUP\CG\PHPType;

/**
 * Class TypeList.
 *
 * @extends Type<mixed, null|list<mixed>>
 */
class TypeList extends Type
{
	public const NAME = 'list';

	/**
	 * TypeArray constructor.
	 *
	 * @param null|string $message
	 */
	public function __construct(?string $message = null)
	{
		!empty($message) && $this->msg('invalid_list_type', $message);

		$base = new TypeJSON();
		$base->jsonDataType('array'); // enforce JSON array semantics on the base type for schema reflection

		parent::__construct($base);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(array $options): static
	{
		return (new self())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * When `list_of` is set to a {@see JsonOfInterface} class, each element is revived.
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?array
	{
		if (null === $value || '' === $value) {
			return null;
		}

		$list    = \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
		$class   = $this->getListOfClass();

		if (null !== $class) {
			return \array_values(\array_map(static fn (mixed $item) => $class::revive($item), $list));
		}

		return $list;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getEmptyValueOfType(): ?array
	{
		return $this->isNullable() ? null : [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * Enable native JSON column type in supporting RDBMS (MySQL >= 5.7, PostgreSQL).
	 *
	 * @param bool $native_json
	 *
	 * @return $this
	 */
	public function nativeJson(bool $native_json = true): static
	{
		/** @var TypeJSON $bt */
		$bt = $this->base_type;

		$bt->nativeJson($native_json);

		return $this->setOption('native_json', $native_json);
	}

	/**
	 * Sets whether the list is big (can hold large data).
	 */
	public function big(bool $big = true): static
	{
		/** @var TypeJSON $bt */
		$bt = $this->base_type;

		$bt->big($big);

		return $this->setOption('big', $big);
	}

	/**
	 * Sets the element type of the list for code generation type hints and runtime revival.
	 *
	 * Accepts either:
	 *  - a scalar {@see ORMUniversalType} case (e.g. STRING, INT, BIGINT, FLOAT, BOOL) for
	 *    hint-only metadata (no runtime coercion);
	 *  - a FQCN implementing {@see JsonOfInterface} for typed revival: each element of the
	 *    stored JSON array is passed through `ClassName::revive($element)` on read, and
	 *    instances are JSON-encoded on write.
	 *
	 * Defaults to {@see ORMUniversalType::UNKNOWN} (hint-only, no revival) when not set.
	 *
	 * @param class-string<JsonOfInterface>|ORMUniversalType $of Element type or revival class
	 *
	 * @return $this
	 */
	public function listOf(ORMUniversalType|string $of): static
	{
		if (\is_string($of)) {
			if (!\is_a($of, JsonOfInterface::class, true)) {
				throw new InvalidArgumentException(
					\sprintf(
						'list_of class "%s" must implement %s.',
						$of,
						JsonOfInterface::class
					)
				);
			}

			return $this->setOption('list_of', $of);
		}

		return $this->setOption('list_of', $of->value);
	}

	/**
	 * Returns the revival class name set via `list_of`, or null if none (or if it is a universal type).
	 *
	 * @return null|class-string<JsonOfInterface>
	 */
	public function getListOfClass(): ?string
	{
		$v = $this->getOption('list_of');

		if (\is_string($v) && \is_a($v, JsonOfInterface::class, true)) {
			/** @var class-string<JsonOfInterface> $v */
			return $v;
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function configure(array $options): static
	{
		if (isset($options['list_of'])) {
			$raw = (string) $options['list_of'];
			// Try as a universal type first, then as a class name.
			$of = ORMUniversalType::tryFrom($raw);

			if ($of) {
				$this->listOf($of);
			} elseif (\is_a($raw, JsonOfInterface::class, true)) {
				$this->listOf($raw);
			}
		}

		if (isset($options['native_json'])) {
			$this->nativeJson((bool) $options['native_json']);
		}

		if (isset($options['big'])) {
			$this->big((bool) $options['big']);
		}

		return parent::configure($options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Delegates to the base type {@see TypeJSON} so that JSON path operators
	 * (EQ, NEQ, LIKE, NOT_LIKE with a path string as first arg, plus
	 * CONTAINS and HAS_KEY including their path-aware variants) are handled
	 * correctly by TypeJSON's path-aware implementation.
	 */
	public function queryBuilderApplyFilter(ORMTableQuery $qb, Column $column, Operator $operator, array $args): void
	{
		$this->safelyCallOnBaseType(__FUNCTION__, [$qb, $column, $operator, $args]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		$class = $this->getListOfClass();

		if (null !== $class) {
			$hint = ORMTypeHint::list();
			$hint->setListOfClass($class);

			return $hint;
		}

		$of = ORMUniversalType::tryFrom((string) ($this->getOption('list_of') ?? ''));

		return ORMTypeHint::list($of);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Accepts array or {@see JsonPatch}.
	 * A {@see JsonPatch} instance is coerced to its underlying array inside {@see validate()}.
	 * When `list_of` is a {@see JsonOfInterface} class, also accepts instances of that class
	 * as individual elements.
	 */
	public function getWriteTypeHint(): ORMTypeHint
	{
		$class = $this->getListOfClass();

		if (null !== $class) {
			$hint = ORMTypeHint::list();
			$hint->setListOfClass($class);
			$hint->setPHPType(new PHPType('array', '\\' . JsonPatch::class));

			return $hint;
		}

		$of   = ORMUniversalType::tryFrom((string) ($this->getOption('list_of') ?? ''));
		$hint = ORMTypeHint::list($of);
		$hint->setPHPType(new PHPType('array', '\\' . JsonPatch::class));

		return $hint;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 * @throws TypesInvalidValueException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		$value = $this->validate($value)->getCleanValue();

		if (null === $value) {
			return null;
		}

		return empty($value) ? '[]' : \json_encode($value, \JSON_THROW_ON_ERROR);
	}

	/**
	 * {@inheritDoc}
	 *
	 * When `list_of` is a {@see JsonOfInterface} class, each element that is an array
	 * is revived via `ClassName::revive($element)` before JSON-encoding.
	 *
	 * @throws JsonException
	 * @throws TypesInvalidValueException
	 */
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();

		if ($value instanceof JsonPatch) {
			$value = $value->toArray();
		}

		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				$subject->accept(null);

				return;
			}
		}

		if (!\is_array($value)) {
			$subject->reject($this->msg('invalid_list_type'), $debug);

			return;
		}

		$class = $this->getListOfClass();

		if (null !== $class) {
			// Revive array elements that aren't already the right type.
			try {
				$value = \array_values(\array_map(static function (mixed $item) use ($class, $debug): mixed {
					if (\is_array($item)) {
						return $class::revive($item);
					}

					if ($item instanceof $class) {
						return $item;
					}

					throw new TypesInvalidValueException('Invalid list element type.', $debug);
				}, $value));
			} catch (TypesInvalidValueException $e) {
				$subject->reject($e);

				return;
			}
		}

		try {
			// this checks if we can serialize to JSON
			\json_encode($value, \JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			$subject->reject(new TypesInvalidValueException($this->msg('unable_to_serialize_list_value'), $debug, $e));

			return;
		}

		$subject->accept($this->ensureList($value));
	}

	/**
	 * Ensure the value is a list (indexed array).
	 *
	 * @param array $value
	 *
	 * @return array
	 */
	private function ensureList(array $value): array
	{
		return \array_values($value);
	}
}
