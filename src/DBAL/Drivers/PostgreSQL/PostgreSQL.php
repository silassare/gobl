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

namespace Gobl\DBAL\Drivers\PostgreSQL;

use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLDriverBase;
use PDO;

/**
 * Class PostgreSQL.
 */
class PostgreSQL extends SQLDriverBase
{
	public const NAME = 'postgresql';

	/**
	 * {@inheritDoc}
	 */
	public function getGenerator(): PostgreSQLQueryGenerator
	{
		return new PostgreSQLQueryGenerator($this, $this->config);
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
	public static function new(DbConfig $config): static
	{
		return new self($config);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function connect(): PDO
	{
		$host     = $this->config->getDbHost();
		$port     = $this->config->getDbPort();
		$dbname   = $this->config->getDbName();
		$user     = $this->config->getDbUser();
		$password = $this->config->getDbPass();

		$port = empty($port) ? 5432 : $port;

		$dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

		$pdo_options = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		return new PDO($dsn, $user, $password, $pdo_options);
	}
}
