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

namespace Gobl\DBAL\Constraints;

/**
 * Enum ForeignKeyAction.
 */
enum ForeignKeyAction: string
{
	case NO_ACTION = 'none';

	case SET_NULL = 'set_null';

	case CASCADE = 'cascade';

	case RESTRICT = 'restrict';
}
