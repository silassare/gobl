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

namespace Gobl\DBAL\Drivers\MySQL;

use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLDriverBase;
use PDO;

/**
 * Class MySQL.
 */
class MySQL extends SQLDriverBase
{
	public const NAME = 'mysql';

	/**
	 * {@inheritDoc}
	 */
	public static function createInstance(DbConfig $config): self
	{
		return new self($config);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getGenerator(): MySQLQueryGenerator
	{
		return new MySQLQueryGenerator($this, $this->config);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function connect(): PDO
	{
		$host     = $this->config->getDbHost();
		$dbname   = $this->config->getDbName();
		$user     = $this->config->getDbUser();
		$password = $this->config->getDbPass();
		$charset  = $this->config->getDbCharset();

		$pdo_options = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		// DSN => DATA SOURCE NAME
		$pdo_dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=' . $charset;

		return new PDO($pdo_dsn, $user, $password, $pdo_options);
	}
}
