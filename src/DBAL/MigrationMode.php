<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL;

/**
 * Enum MigrationMode.
 */
enum MigrationMode: int
{
	/**
	 * The migration up queries mode.
	 */
	case UP = 1;

	/**
	 * The migration down queries mode.
	 */
	case DOWN = 2;

	/**
	 * The migration full queries mode.
	 */
	case FULL = 3;
}
