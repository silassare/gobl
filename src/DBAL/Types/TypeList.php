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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\ORM\ORMTypeHint;
use JsonException;

/**
 * Class TypeList.
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

		parent::__construct(new TypeString());
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
	 * @throws JsonException
	 */
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): ?array
	{
		if (null === $value || '' === $value) {
			return null;
		}

		return \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAllowedFilterOperators(): array
	{
		$operators = [
			Operator::EQ,
			Operator::NEQ,
			Operator::LIKE,
			Operator::NOT_LIKE,
		];

		if ($this->isNullable()) {
			$operators[] = Operator::IS_NULL;
			$operators[] = Operator::IS_NOT_NULL;
		}

		return $operators;
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
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): ORMTypeHint
	{
		return ORMTypeHint::array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 * @throws TypesInvalidValueException
	 */
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		$value = $this->validate($value);

		if (null === $value) {
			return null;
		}

		return empty($value) ? '[]' : \json_encode($value, \JSON_THROW_ON_ERROR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate(mixed $value): ?array
	{
		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				return null;
			}
		}

		if (!\is_array($value)) {
			throw new TypesInvalidValueException($this->msg('invalid_list_type'), $debug);
		}

		try {
			// this checks if we can serialize to JSON
			\json_encode($value, \JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new TypesInvalidValueException($this->msg('unable_to_serialize_list_value'), $debug, $e);
		}

		return $this->ensureList($value);
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
