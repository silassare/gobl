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

namespace Gobl\ORM;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Exceptions\ORMRuntimeException;

/**
 * Class ORM.
 */
class ORM
{
	/** @var RDBMSInterface[] */
	private static array $databases = [];

	/**
	 * Database setter.
	 *
	 * @param string         $namespace the database namespace
	 * @param RDBMSInterface $db        the database to use
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 */
	public static function setDatabase(string $namespace, RDBMSInterface $db): void
	{
		if (isset(self::$databases[$namespace])) {
			throw new ORMException(\sprintf('A database instance is already registered for: %s', $namespace));
		}

		self::$databases[$namespace] = $db;
	}

	/**
	 * Database getter.
	 *
	 * @param string $namespace the database namespace
	 *
	 * @return RDBMSInterface
	 */
	public static function getDatabase(string $namespace): RDBMSInterface
	{
		if (!isset(self::$databases[$namespace])) {
			throw new ORMRuntimeException(\sprintf('No database registered for: %s', $namespace));
		}

		return self::$databases[$namespace];
	}
}
