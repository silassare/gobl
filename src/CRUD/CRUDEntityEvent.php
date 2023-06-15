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

namespace Gobl\CRUD;

/**
 * Enum CRUDEntityEvent.
 */
enum CRUDEntityEvent: string
{
	case AFTER_CREATE = 'after_create';

	case AFTER_READ = 'after_read';

	case BEFORE_UPDATE = 'before_update';

	case AFTER_UPDATE = 'after_update';

	case BEFORE_DELETE = 'before_delete';

	case AFTER_DELETE = 'after_delete';
}
