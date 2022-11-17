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
use Gobl\DBAL\Drivers\SQLQueryGeneratorBase;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\Gobl;

use const GOBL_ASSETS_DIR;

/**
 * Class SQLLiteQueryGenerator.
 */
class SQLLiteQueryGenerator extends SQLQueryGeneratorBase
{
	private static bool $templates_registered = false;

	/**
	 * SQLLiteQueryGenerator constructor.
	 *
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $db
	 * @param \Gobl\DBAL\DbConfig                  $config
	 */
	public function __construct(RDBMSInterface $db, DbConfig $config)
	{
		parent::__construct($db, $config);

		if (!self::$templates_registered) {
			self::$templates_registered = true;

			Gobl::addTemplates([
				'sqllite_db'           => ['path' => GOBL_ASSETS_DIR . '/sqllite/db.sql'],
				'sqllite_create_table' => ['path' => GOBL_ASSETS_DIR . '/sqllite/create_table.sql'],
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function dbQueryTemplate(): string
	{
		return 'sqllite_db';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createTableQueryTemplate(): string
	{
		return 'sqllite_create_table';
	}
}
