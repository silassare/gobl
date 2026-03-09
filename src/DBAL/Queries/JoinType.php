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

namespace Gobl\DBAL\Queries;

/**
 * Enum JoinType.
 */
enum JoinType: string
{
	/** Returns only rows where a match exists in both the left and right table. */
	case INNER = 'INNER';

	/** Returns all rows from the left table and the matched rows from the right table; unmatched right rows are NULL. */
	case LEFT = 'LEFT';

	/** Returns all rows from the right table and the matched rows from the left table; unmatched left rows are NULL. */
	case RIGHT = 'RIGHT';
}
