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

namespace Gobl\DBAL\Queries;

/**
 * Enum QBType.
 */
enum QBType: int
{
	case SELECT = 1;

	case INSERT = 2;

	case UPDATE = 3;

	case DELETE = 4;
}
