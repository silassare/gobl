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

use Gobl\DBAL\Operator;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use OLIUP\CG\PHPType;

/**
 * Class ORMTypeHint.
 */
final class ORMTypeHint
{
	/**
	 * @var array<string,\Gobl\ORM\ORMUniversalType> the universal types
	 */
	private array $universal_types = [];

	private ?PHPType $php_type = null;

	/**
	 * ORMTypeHint constructor.
	 *
	 * @param \Gobl\ORM\ORMUniversalType $universal_type            the universal type, we require at least one
	 * @param \Gobl\ORM\ORMUniversalType ...$others_universal_types the other universal types
	 */
	public function __construct(ORMUniversalType $universal_type, ORMUniversalType ...$others_universal_types)
	{
		$this->addUniversalTypes($universal_type, ...$others_universal_types);
	}

	/**
	 * Adds a universal type.
	 *
	 * @param \Gobl\ORM\ORMUniversalType ...$types
	 *
	 * @return $this
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
	 * @param \Gobl\DBAL\Types\Interfaces\TypeInterface $type
	 * @param \Gobl\DBAL\Operator                       $operator
	 *
	 * @return \Gobl\ORM\ORMTypeHint
	 *
	 * @internal
	 */
	public static function getOperatorRightOperandTypesHint(TypeInterface $type, Operator $operator): self
	{
		return match ($operator) {
			Operator::EQ, Operator::NEQ, Operator::LT, Operator::LTE, Operator::GT, Operator::GTE => $type->getWriteTypeHint(),
			Operator::LIKE, Operator::NOT_LIKE => self::string(),
			Operator::IS_NULL, Operator::IS_NOT_NULL => self::null(),
			Operator::IN, Operator::NOT_IN => self::array(),
			Operator::IS_TRUE, Operator::IS_FALSE => self::bool(),
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
	 * Creates array type hint instance.
	 *
	 * @return self
	 */
	public static function array(): self
	{
		return new self(ORMUniversalType::ARRAY);
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
	 * Creates mixed type hint instance.
	 *
	 * @return self
	 */
	public static function mixed(): self
	{
		return new self(ORMUniversalType::MIXED);
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
	 * Gets the PHP type.
	 *
	 * @return null|\OLIUP\CG\PHPType
	 */
	public function getPHPType(): ?PHPType
	{
		return $this->php_type;
	}

	/**
	 * Sets the PHP type.
	 *
	 * @param null|\OLIUP\CG\PHPType $php_type
	 *
	 * @return $this
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
	 * @return $this
	 */
	public function nullable(): self
	{
		$this->php_type?->nullable();

		return $this->addUniversalTypes(ORMUniversalType::NULL);
	}
}
