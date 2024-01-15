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
 * Class DBCharsetChanged.
 */
final class DBCharsetChanged extends DiffAction
{
	public function __construct(protected string $charset, protected RDBMSInterface $db, string $reason = 'default charset config changed')
	{
		parent::__construct(DiffActionType::DB_CHARSET_CHANGED, $reason);
	}

	/**
	 * @return string
	 */
	public function getCharset(): string
	{
		return $this->charset;
	}

	/**
	 * @return RDBMSInterface
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
			'charset' => $this->charset,
			'dbName'  => $this->db->getConfig()
				->getDbName(),
		];
	}
}
