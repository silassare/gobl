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

use Gobl\DBAL\Types\Utils\Map;

/**
 * Enum ORMUniversalType.
 *
 * Here are data types that are common to most programming languages.
 */
enum ORMUniversalType: string
{
	case LIST = 'LIST';

	case MAP = 'MAP';

	case BIGINT = 'BIGINT';

	case BOOL = 'BOOL';

	case DECIMAL = 'DECIMAL';

	case FLOAT = 'FLOAT';

	case INT = 'INT';

	case STRING = 'STRING';

	case NULL = 'NULL';

	/**
	 * Permissive - accepted as-is, disables type checking downstream.
	 * Maps to: PHP `mixed`, TS `any`, Dart `dynamic`.
	 */
	case ANY = 'ANY';

	/**
	 * Safe unknown - value exists but its shape is not known.
	 * Maps to: PHP `mixed`, TS `unknown`, Dart `dynamic`.
	 * Use as the default `list_of` element type and for TypeJSON write hints.
	 */
	case UNKNOWN = 'UNKNOWN';

	/**
	 * Determines if the given value is of the universal type represented by this enum case.
	 */
	public function isValidValue(mixed $value): bool
	{
		return match ($this) {
			ORMUniversalType::LIST     => \is_array($value) && \array_is_list($value),
			ORMUniversalType::MAP      => \is_array($value) || $value instanceof Map,
			ORMUniversalType::STRING   => \is_string($value),
			ORMUniversalType::INT      => \is_int($value),
			ORMUniversalType::FLOAT    => \is_float($value),
			ORMUniversalType::BOOL     => \is_bool($value),
			ORMUniversalType::DECIMAL  => \is_numeric($value) && \preg_match('/^-?\d+(\.\d+)?$/', (string) $value),
			ORMUniversalType::BIGINT   => \is_numeric($value) && \preg_match('/^-?\d+$/', (string) $value),
			ORMUniversalType::NULL     => null === $value,
			ORMUniversalType::ANY, ORMUniversalType::UNKNOWN => true,
		};
	}

	/**
	 * Converts this universal type to a string representing the corresponding PHP type.
	 */
	public function toPHPType(): string
	{
		return match ($this) {
			ORMUniversalType::LIST              => 'list<mixed>',
			ORMUniversalType::MAP               => '\\' . Map::class,
			ORMUniversalType::DECIMAL, ORMUniversalType::STRING, ORMUniversalType::BIGINT => 'string',
			ORMUniversalType::BOOL              => 'bool',
			ORMUniversalType::FLOAT             => 'float',
			ORMUniversalType::INT               => 'int',
			ORMUniversalType::NULL              => 'null',
			ORMUniversalType::ANY, ORMUniversalType::UNKNOWN => 'mixed',
		};
	}
}
