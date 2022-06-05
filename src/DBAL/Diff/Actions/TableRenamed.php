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

use Gobl\DBAL\Diff\DiffAction;
use Gobl\DBAL\Diff\DiffActionType;
use Gobl\DBAL\Table;

/**
 * Class TableRenamed.
 */
final class TableRenamed extends DiffAction
{
	public function __construct(protected Table $old_table, protected Table $new_table, string $reason = '')
	{
		parent::__construct(DiffActionType::TABLE_RENAMED, $reason);
	}

	/**
	 * @return \Gobl\DBAL\Table
	 */
	public function getOldTable(): Table
	{
		return $this->old_table;
	}

	/**
	 * @return \Gobl\DBAL\Table
	 */
	public function getNewTable(): Table
	{
		return $this->new_table;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'action'       => $this->type->value,
			'reason'       => $this->reason,
			'oldTableName' => $this->old_table->getFullName(),
			'newTableName' => $this->new_table->getFullName(),
		];
	}
}
