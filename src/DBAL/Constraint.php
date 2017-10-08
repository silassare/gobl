<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL;

	/**
	 * Class Constraint
	 *
	 * @package Gobl\DBAL
	 */
	class Constraint
	{
		const PRIMARY_KEY = 1;
		const UNIQUE      = 2;
		const FOREIGN_KEY = 3;
	}
