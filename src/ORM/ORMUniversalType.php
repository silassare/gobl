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
}
