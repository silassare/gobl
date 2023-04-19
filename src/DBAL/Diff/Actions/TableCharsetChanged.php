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
 * Class TableCharsetChanged.
 */
final class TableCharsetChanged extends DiffAction
{
	public function __construct(protected string $charset, protected Table $table, string $reason = 'table charset changed')
	{
		parent::__construct(DiffActionType::TABLE_CHARSET_CHANGED, $reason);
	}

	/**
	 * @return string
	 */
	public function getCharset(): string
	{
		return $this->charset;
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
			'charset'   => $this->charset,
			'tableName' => $this->getTable()
				->getFullName(),
		];
	}
}
