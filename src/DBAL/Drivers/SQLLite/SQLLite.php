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

namespace Gobl\DBAL\Drivers\SQLLite;

use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLDriverBase;
use PDO;

/**
 * Class SQLLite.
 */
class SQLLite extends SQLDriverBase
{
	public const NAME = 'sqllite';

	/**
	 * {@inheritDoc}
	 */
	public static function createInstance(DbConfig $config): static
	{
		return new self($config);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getGenerator(): SQLLiteQueryGenerator
	{
		return new SQLLiteQueryGenerator($this, $this->config);
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
}
