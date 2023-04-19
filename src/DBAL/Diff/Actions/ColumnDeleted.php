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
 * Class ColumnDeleted.
 */
final class ColumnDeleted extends DiffAction
{
	public function __construct(protected Table $table, protected Column $column, string $reason = 'column deleted')
	{
		parent::__construct(DiffActionType::COLUMN_DELETED, $reason);
	}

	/**
	 * @return \Gobl\DBAL\Table
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 * @return \Gobl\DBAL\Column
	 */
	public function getColumn(): Column
	{
		return $this->column;
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
			'columnName' => $this->column->getFullName(),
		];
	}
}
