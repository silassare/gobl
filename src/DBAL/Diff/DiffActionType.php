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

namespace Gobl\DBAL\Diff;

/**
 * Enum DiffActionType.
 */
enum DiffActionType: string
{
	// DDL ACTIONS
	case TABLE_ADDED = 'TABLE_ADDED';

	case TABLE_DELETED = 'TABLE_DELETED';

	case TABLE_RENAMED = 'TABLE_RENAMED';

	case COLUMN_DELETED = 'COLUMN_DELETED';

	case COLUMN_ADDED = 'COLUMN_ADDED';

	case COLUMN_RENAMED = 'COLUMN_RENAMED';

	case COLUMN_TYPE_CHANGED = 'COLUMN_TYPE_CHANGED';

	case PRIMARY_KEY_CONSTRAINT_ADDED = 'PRIMARY_KEY_CONSTRAINT_ADDED';

	case PRIMARY_KEY_CONSTRAINT_DELETED = 'PRIMARY_KEY_CONSTRAINT_DELETED';

	case FOREIGN_KEY_CONSTRAINT_ADDED = 'FOREIGN_KEY_CONSTRAINT_ADDED';

	case FOREIGN_KEY_CONSTRAINT_DELETED = 'FOREIGN_KEY_CONSTRAINT_DELETED';

	case UNIQUE_KEY_CONSTRAINT_ADDED = 'UNIQUE_KEY_CONSTRAINT_ADDED';

	case UNIQUE_KEY_CONSTRAINT_DELETED = 'UNIQUE_KEY_CONSTRAINT_DELETED';

	case DB_COLLATE_CHANGED = 'DB_COLLATE_CHANGED';

	case DB_CHARSET_CHANGED = 'DB_CHARSET_CHANGED';

	case TABLE_COLLATE_CHANGED = 'TABLE_COLLATE_CHANGED';

	case TABLE_CHARSET_CHANGED = 'TABLE_CHARSET_CHANGED';
	// DML ACTIONS
	case ROW_INSERTED = 'ROW_INSERTED';

	case ROW_DELETED = 'ROW_DELETED';

	case ROW_UPDATED = 'ROW_UPDATED';

	public function getPriority(): int
	{
		return match ($this) {
			self::FOREIGN_KEY_CONSTRAINT_DELETED => 10,
			self::PRIMARY_KEY_CONSTRAINT_DELETED => 11,
			self::UNIQUE_KEY_CONSTRAINT_DELETED      => 12,

			self::TABLE_DELETED  => 20,
			self::COLUMN_DELETED => 21,

			self::DB_CHARSET_CHANGED => 30,
			self::DB_COLLATE_CHANGED => 31,

			self::TABLE_CHARSET_CHANGED => 40,
			self::TABLE_COLLATE_CHANGED => 41,

			self::COLUMN_RENAMED      => 50,
			self::COLUMN_TYPE_CHANGED => 51,
			self::COLUMN_ADDED        => 52,

			self::TABLE_RENAMED => 60,
			self::TABLE_ADDED   => 61,

			self::ROW_DELETED  => 70,
			self::ROW_UPDATED  => 71,
			self::ROW_INSERTED => 72,

			self::PRIMARY_KEY_CONSTRAINT_ADDED => 80,
			self::UNIQUE_KEY_CONSTRAINT_ADDED      => 81,
			self::FOREIGN_KEY_CONSTRAINT_ADDED => 82,
		};
	}
}
