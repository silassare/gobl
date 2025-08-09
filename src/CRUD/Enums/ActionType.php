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

namespace Gobl\CRUD\Enums;

/**
 * Enum ActionType.
 */
enum ActionType: string
{
	case CREATE                 = 'CREATE';
	case UPDATE                 = 'UPDATE';
	case UPDATE_ALL             = 'UPDATE_ALL';
	case DELETE                 = 'DELETE';
	case DELETE_ALL             = 'DELETE_ALL';
	case READ                   = 'READ';
	case READ_ALL               = 'READ_ALL';
	case PK_COLUMN_WRITE        = 'PK_COLUMN_WRITE';
	case PRIVATE_COLUMN_WRITE   = 'PRIVATE_COLUMN_WRITE';
	case SENSITIVE_COLUMN_WRITE = 'SENSITIVE_COLUMN_WRITE';
	case COLUMN_UPDATE          = 'COLUMN_UPDATE';

	/**
	 * Gets default success message.
	 *
	 * @return string
	 */
	public function getDefaultSuccessMessage(): string
	{
		return match ($this) {
			self::CREATE => 'CREATED',
			self::UPDATE, self::UPDATE_ALL => 'UPDATED',
			self::DELETE, self::DELETE_ALL => 'DELETED',
			self::READ, self::READ_ALL => 'READ',
			self::PK_COLUMN_WRITE, self::PRIVATE_COLUMN_WRITE, self::SENSITIVE_COLUMN_WRITE, self::COLUMN_UPDATE => 'OK',
		};
	}

	/**
	 * Gets default error message.
	 *
	 * @return string
	 */
	public function getDefaultErrorMessage(): string
	{
		return $this->value . '_REFUSED';
	}
}
