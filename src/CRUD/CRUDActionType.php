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
 * Enum CRUDActionType.
 */
enum CRUDActionType: string
{
	case CREATE = 'create';

	case READ = 'read';

	case UPDATE = 'update';

	case DELETE = 'delete';

	case READ_ALL = 'read_all';

	case UPDATE_ALL = 'update_all';

	case DELETE_ALL = 'delete_all';

	case COLUMN_UPDATE = 'column_update';

	public function getDefaultSuccessMessage(): string
	{
		return match ($this) {
			self::CREATE => 'CREATED',
			self::READ, self::READ_ALL => 'OK',
			self::UPDATE, self::UPDATE_ALL, self::COLUMN_UPDATE => 'UPDATED',
			self::DELETE, self::DELETE_ALL => 'DELETED',
		};
	}

	public function getDefaultErrorMessage(): string
	{
		return match ($this) {
			self::CREATE        => 'CREATE_ERROR',
			self::READ          => 'READ_ERROR',
			self::UPDATE        => 'UPDATE_ERROR',
			self::DELETE        => 'DELETE_ERROR',
			self::READ_ALL      => 'READ_ALL_ERROR',
			self::UPDATE_ALL    => 'UPDATE_ALL_ERROR',
			self::DELETE_ALL    => 'DELETE_ALL_ERROR',
			self::COLUMN_UPDATE => 'COLUMN_UPDATE_ERROR',
		};
	}
}
