<?php

/**
 * Copyright (c) Emile Silas Sare.
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
use Override;
use PDO;
use PDOException;

/**
 * Class PostgreSQL.
 */
final class PostgreSQL extends SQLDriverBase
{
	public const NAME = 'postgresql';

	#[Override]
	public function getGenerator(): PostgreSQLQueryGenerator
	{
		return new PostgreSQLQueryGenerator($this, $this->config);
	}

	#[Override]
	public function getType(): string
	{
		return self::NAME;
	}

	#[Override]
	public static function new(DbConfig $config): static
	{
		return new self($config);
	}

	/**
	 * {@inheritDoc}
	 *
	 * PostgreSQL's PDO::lastInsertId() calls lastval() which throws SQLSTATE[55000]
	 * when no sequence has been used in the current session (e.g. tables whose
	 * primary key is not a SERIAL / auto-increment column). In that case we return
	 * false gracefully instead of propagating the exception.
	 */
	#[Override]
	public function insert($sql, ?array $params = null, array $params_types = []): false|string
	{
		$stmt = $this->execute($sql, $params, $params_types);

		try {
			$last_id = $this->getConnection()->lastInsertId();
		} catch (PDOException) {
			// lastval() is not yet defined - no SERIAL sequence was used in this session.
			$last_id = false;
		}

		$stmt->closeCursor();

		return $last_id;
	}

	#[Override]
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
