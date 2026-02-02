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
use Gobl\DBAL\Diff\Actions\DBCharsetChanged;
use Gobl\DBAL\Diff\Actions\DBCollateChanged;
use Gobl\DBAL\Diff\Actions\TableCharsetChanged;
use Gobl\DBAL\Diff\Actions\TableCollateChanged;
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Gobl;
use RuntimeException;

use const GOBL_ASSETS_DIR;

/**
 * Class PostgreSQLQueryGenerator.
 */
class PostgreSQLQueryGenerator extends SQLQueryGeneratorBase
{
	private static bool $templates_registered = false;

	/**
	 * PostgreSQLQueryGenerator constructor.
	 *
	 * @param RDBMSInterface $db
	 * @param DbConfig       $config
	 */
	public function __construct(RDBMSInterface $db, DbConfig $config)
	{
		parent::__construct($db, $config);

		if (!self::$templates_registered) {
			self::$templates_registered = true;

			Gobl::addTemplates([
				'postgresql_db'           => ['path' => GOBL_ASSETS_DIR . '/postgresql/db.sql'],
				'postgresql_create_table' => ['path' => GOBL_ASSETS_DIR . '/postgresql/create_table.sql'],
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function wrapDatabaseDefinitionQuery(string $query): string
	{
		return $query;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function dbQueryTemplate(): string
	{
		return 'postgresql_db';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createTableQueryTemplate(): string
	{
		return 'postgresql_create_table';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getDBCharsetChangeString(DBCharsetChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing database charset via query.');
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getDBCollateChangeString(DBCollateChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing database collate via query.');
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getTableCharsetChangeString(TableCharsetChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing table charset via query.');
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getTableCollateChangeString(TableCollateChanged $action): string
	{
		throw new RuntimeException('PostgreSQL does not support changing table collate via query.');
	}
}
