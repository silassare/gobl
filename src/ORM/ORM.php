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
	use Gobl\ORM\Generators\Generator;

	class ORM
	{
		/** @var  \Gobl\DBAL\Db */
		private static $db;

		/**
		 * Returns class generator instance.
		 *
		 * @return \Gobl\ORM\Generators\Generator
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function getClassGenerator()
		{

		}

		/**
		 * Database setter.
		 *
		 * @param \Gobl\DBAL\Db $db the database to use
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function setDatabase(Db $db)
		{
			if (isset(self::$db)) {
				throw new ORMException('You cannot reset the database.');
			}

			self::$db = $db;
		}

		/**
		 * Database getter.
		 *
		 * @return \Gobl\DBAL\Db
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function getDatabase()
		{
			if (!isset(self::$db)) {
				throw new ORMException('No database defined.');
			}

			return self::$db;
		}
	}