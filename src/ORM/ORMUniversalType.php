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

/**
 * Enum ORMUniversalType.
 *
 * Here are data types that are common to most programming languages.
 */
enum ORMUniversalType: string
{
	case ARRAY = 'ARRAY';

	case MAP = 'MAP';

	case BIGINT = 'BIGINT';

	case BOOL = 'BOOL';

	case DECIMAL = 'DECIMAL';

	case FLOAT = 'FLOAT';

	case INT = 'INT';

	case STRING = 'STRING';

	case NULL = 'NULL';

	case MIXED = 'MIXED';
}
