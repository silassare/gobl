<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\ORM;

	use Gobl\DBAL\Db;
	use Gobl\ORM\Exceptions\ORMException;

	class ORM
	{
		/** @var \Gobl\DBAL\Db[] */
		private static $databases = [];

		/**
		 * Database setter.
		 *
		 * @param string        $namespace the database namespace
		 * @param \Gobl\DBAL\Db $db        the database to use
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function setDatabase($namespace, Db $db)
		{
			if (isset(self::$databases[$namespace])) {
				throw new ORMException(sprintf('A database instance is already registered for: %s', $namespace));
			}

			self::$databases[$namespace] = $db;
		}

		/**
		 * Database getter.
		 *
		 * @param string $namespace the database namespace
		 *
		 * @return \Gobl\DBAL\Db
		 */
		public static function getDatabase($namespace)
		{
			if (!isset(self::$databases[$namespace])) {
				throw new \RuntimeException(sprintf('No database registered for: %s', $namespace));
			}

			return self::$databases[$namespace];
		}
	}