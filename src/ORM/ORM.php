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

	use Gobl\ORM\Generators\Generator;

	class ORM
	{
		/**
		 * @param \Gobl\ORM\ORMDbProvider $provider
		 *
		 * @return \Gobl\ORM\Generators\Generator
		 */
		public static function getClassGenerator(ORMDbProvider $provider)
		{
			$db = $provider::getInstance();

			return new Generator($db);
		}
	}