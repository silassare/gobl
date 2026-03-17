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

use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\Utils\JsonOfInterface;
use OLIUP\CG\PHPType;

/**
 * Class ORMTypeHint.
 */
final class ORMTypeHint
{
	/**
	 * @var array<string,ORMUniversalType> the universal types
	 */
	private array $universal_types = [];

	/** Element type when this hint carries LIST - defaults to UNKNOWN. */
	private ORMUniversalType $list_of_u_type = ORMUniversalType::UNKNOWN;

	/** Revival class for typed list elements (implements JsonOfInterface). */
	private ?string $list_of_class = null;

	private ?PHPType $php_type = null;

	/**
	 * ORMTypeHint constructor.
	 *
	 * @param ORMUniversalType $universal_type            the universal type, we require at least one
	 * @param ORMUniversalType ...$others_universal_types the other universal types
	 */
	public function __construct(ORMUniversalType $universal_type, ORMUniversalType ...$others_universal_types)
	{
		$this->addUniversalTypes($universal_type, ...$others_universal_types);
	}

	/**
	 * Adds a universal type.
	 *
	 * @param ORMUniversalType ...$types
	 *
	 * @return static
	 */
	public function addUniversalTypes(ORMUniversalType ...$types): self
	{
		if (!empty($types)) {
			foreach ($types as $type) {
				$this->universal_types[$type->value] = $type;
			}
		}

		return $this;
	}

	/**
	 * Returns the type hint for the given operator right operand.
	 *
	 * @param TypeInterface $type
	 * @param Operator      $operator
	 *
	 * @return ORMTypeHint
	 *
	 * @internal
	 */
	public static function getOperatorRightOperandTypesHint(TypeInterface $type, Operator $operator): self
	{
		return match ($operator) {
			Operator::EQ, Operator::NEQ, Operator::LT, Operator::LTE, Operator::GT, Operator::GTE => $type->getWriteTypeHint(),
			Operator::LIKE, Operator::NOT_LIKE                                                    => self::string(),
			Operator::IS_NULL, Operator::IS_NOT_NULL                                              => self::null(),
			Operator::IN, Operator::NOT_IN                                                        => self::list(),
			Operator::IS_TRUE, Operator::IS_FALSE                                                 => self::bool(),
			Operator::CONTAINS, Operator::HAS_KEY => self::string(),
		};
	}

	/**
	 * Creates string type hint instance.
	 *
	 * @return self
	 */
	public static function string(): self
	{
		return new self(ORMUniversalType::STRING);
	}

	/**
	 * Creates null type hint instance.
	 *
	 * @return self
	 */
	public static function null(): self
	{
		return new self(ORMUniversalType::NULL);
	}

	/**
	 * Creates list type hint instance.
	 *
	 * @param null|ORMUniversalType $of element type for the list (defaults to UNKNOWN)
	 *
	 * @return self
	 */
	public static function list(?ORMUniversalType $of = null): self
	{
		$hint                 = new self(ORMUniversalType::LIST);
		$hint->list_of_u_type = $of ?? ORMUniversalType::UNKNOWN;

		return $hint;
	}

	/**
	 * Creates boolean type hint instance.
	 *
	 * @return self
	 */
	public static function bool(): self
	{
		return new self(ORMUniversalType::BOOL);
	}

	/**
	 * Creates integer type hint instance.
	 *
	 * @return self
	 */
	public static function int(): self
	{
		return new self(ORMUniversalType::INT);
	}

	/**
	 * Creates float type hint instance.
	 *
	 * @return self
	 */
	public static function float(): self
	{
		return new self(ORMUniversalType::FLOAT);
	}

	/**
	 * Creates any type hint instance (permissive, disables type checking).
	 *
	 * @return self
	 */
	public static function any(): self
	{
		return new self(ORMUniversalType::ANY);
	}

	/**
	 * Creates unknown type hint instance (safe unknown, forces narrowing in TS).
	 *
	 * @return self
	 */
	public static function unknown(): self
	{
		return new self(ORMUniversalType::UNKNOWN);
	}

	/**
	 * Creates decimal type hint instance.
	 *
	 * @return self
	 */
	public static function decimal(): self
	{
		return new self(ORMUniversalType::DECIMAL);
	}

	/**
	 * Creates bigint type hint instance.
	 *
	 * @return self
	 */
	public static function bigint(): self
	{
		return new self(ORMUniversalType::BIGINT);
	}

	/**
	 * Creates map type hint instance.
	 *
	 * @return self
	 */
	public static function map(): self
	{
		return new self(ORMUniversalType::MAP);
	}

	/**
	 * Gets the universal type for the list element when this hint carries LIST.
	 *
	 * @return ORMUniversalType
	 */
	public function getListOfUniversalType(): ORMUniversalType
	{
		return $this->list_of_u_type;
	}

	/**
	 * Sets the revival class for typed LIST element hints.
	 *
	 * @param class-string<JsonOfInterface> $class
	 *
	 * @return static
	 */
	public function setListOfClass(string $class): self
	{
		$this->list_of_class = $class;

		return $this;
	}

	/**
	 * Gets the revival class for LIST element hints, or null if not set.
	 *
	 * @return null|class-string<JsonOfInterface>
	 */
	public function getListOfClass(): ?string
	{
		/** @var null|class-string<JsonOfInterface> $v */
		return $this->list_of_class;
	}

	/**
	 * Gets the PHP type.
	 *
	 * @return null|PHPType
	 */
	public function getPHPType(): ?PHPType
	{
		return $this->php_type;
	}

	/**
	 * Sets the PHP type.
	 *
	 * @param null|PHPType $php_type
	 *
	 * @return static
	 */
	public function setPHPType(?PHPType $php_type): self
	{
		$this->php_type = $php_type;

		return $this;
	}

	/**
	 * Gets the universal type.
	 *
	 * @return ORMUniversalType[]
	 */
	public function getUniversalTypes(): array
	{
		return \array_values($this->universal_types);
	}

	/**
	 * Sets as nullable.
	 *
	 * @return static
	 */
	public function nullable(): self
	{
		$this->php_type?->nullable();

		return $this->addUniversalTypes(ORMUniversalType::NULL);
	}
}
