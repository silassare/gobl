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
 * Class TableDeleted.
 */
final class TableDeleted extends DiffAction
{
	public function __construct(protected Table $table, string $reason = '')
	{
		parent::__construct(DiffActionType::TABLE_DELETED, $reason);
	}

	/**
	 * @return \Gobl\DBAL\Table
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'action'    => $this->type->value,
			'reason'    => $this->reason,
			'tableName' => $this->table->getFullName(),
		];
	}
}
