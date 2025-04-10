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

namespace Gobl\DBAL\Queries\Traits;

use Gobl\DBAL\Filters\Filters;
use Gobl\DBAL\Filters\Interfaces\FiltersScopeInterface;

/**
 * Trait QBFilterableTrait.
 */
trait QBFilterableTrait
{
	/**
	 * Gets query filters instance.
	 *
	 * @param null|FiltersScopeInterface $scope
	 *
	 * @return Filters
	 */
	public function filters(?FiltersScopeInterface $scope = null): Filters
	{
		return new Filters($this, $scope);
	}
}
