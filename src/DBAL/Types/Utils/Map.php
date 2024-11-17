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

namespace Gobl\DBAL\Types\Utils;

use PHPUtils\Store\Store;

/**
 * Class Map.
 *
 * @extends Store<array>
 */
class Map extends Store
{
	/**
	 * Map constructor.
	 */
	public function __construct(array &$data = [])
	{
		parent::__construct($data);
	}
}
