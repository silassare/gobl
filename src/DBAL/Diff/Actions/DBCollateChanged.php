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
use Gobl\DBAL\Interfaces\RDBMSInterface;

/**
 * Class DBCollateChanged.
 */
final class DBCollateChanged extends DiffAction
{
	public function __construct(protected string $collate, protected RDBMSInterface $db, string $reason = 'default collation config changed')
	{
		parent::__construct(DiffActionType::DB_COLLATE_CHANGED, $reason);
	}

	/**
	 * @return string
	 */
	public function getCollate(): string
	{
		return $this->collate;
	}

	/**
	 * @return \Gobl\DBAL\Interfaces\RDBMSInterface
	 */
	public function getDb(): RDBMSInterface
	{
		return $this->db;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'action'  => $this->type->value,
			'reason'  => $this->reason,
			'collate' => $this->collate,
			'dbName'  => $this->db->getConfig()
				->getDbName(),
		];
	}
}
