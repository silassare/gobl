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

namespace Gobl\DBAL\Diff\Actions;

use Gobl\DBAL\Column;
use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Diff\DiffActionType;
use Gobl\DBAL\Table;

/**
 * Class ColumnTypeChanged.
 */
final class ColumnTypeChanged extends DiffAction
{
	public function __construct(protected Table $table, protected Column $old_column, protected Column $new_column, string $reason = 'column type changed')
	{
		parent::__construct(DiffActionType::COLUMN_TYPE_CHANGED, $reason);
	}

	/**
	 * @return Table
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 * @return Column
	 */
	public function getOldColumn(): Column
	{
		return $this->old_column;
	}

	/**
	 * @return Column
	 */
	public function getNewColumn(): Column
	{
		return $this->new_column;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'action'     => $this->type->value,
			'reason'     => $this->reason,
			'tableName'  => $this->table->getFullName(),
			'columnName' => $this->new_column->getFullName(),
			'columnType' => $this->new_column->getType()
				->toArray(),
		];
	}
}
