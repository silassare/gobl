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

namespace Gobl\DBAL\Drivers\SQLite;

use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLDriverBase;
use Override;
use PDO;
use PDOException;

/**
 * Class SQLite.
 */
final class SQLite extends SQLDriverBase
{
	public const NAME = 'sqlite';

	#[Override]
	public function getGenerator(): SQLiteQueryGenerator
	{
		return new SQLiteQueryGenerator($this, $this->config);
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

	#[Override]
	protected function connect(): PDO
	{
		$host = $this->config->getDbHost();

		$pdo_options = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		// DSN => DATA SOURCE NAME
		$pdo_dsn = 'sqlite:' . $host;

		return new PDO($pdo_dsn, '', '', $pdo_options);
	}

	/**
	 * {@inheritDoc}
	 *
	 * `UNIQUE constraint failed: table.a, table.b`: no constraint name, the columns.
	 */
	#[Override]
	protected function readUniqueViolation(PDOException $e): ?array
	{
		if (!\preg_match('~UNIQUE constraint failed: (.+)$~', $e->getMessage(), $m)) {
			return null;
		}

		$table   = null;
		$columns = [];

		foreach (\explode(', ', $m[1]) as $one) {
			$parts = \explode('.', $one, 2);

			if (2 !== \count($parts)) {
				return null;
			}

			[$table, $columns[]] = $parts;
		}

		return ['table' => $table, 'constraint' => null, 'columns' => $columns];
	}

	/**
	 * {@inheritDoc}
	 *
	 * `BEGIN IMMEDIATE`: the transaction takes the write lock as it starts, waiting for it while another
	 * holds it. A plain `BEGIN` takes it at the first write, and SQLite then fails at once with
	 * "database is locked" instead of waiting (waiting could deadlock with a reader that also wants to
	 * write): two transactions that read, then write (check an email, insert the user) failed so.
	 */
	#[Override]
	protected function beginOuterTransaction(PDO $con): bool
	{
		$con->exec('BEGIN IMMEDIATE');

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Started by {@see beginOuterTransaction()}, unknown to PDO, so ended by SQL as well.
	 */
	#[Override]
	protected function commitOuterTransaction(PDO $con): bool
	{
		$con->exec('COMMIT');

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function rollBackOuterTransaction(PDO $con): bool
	{
		$con->exec('ROLLBACK');

		return true;
	}
}
