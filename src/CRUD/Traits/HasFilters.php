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

namespace Gobl\CRUD\Traits;

use Gobl\ORM\ORMTableQuery;

/**
 * Trait HasFilters.
 */
trait HasFilters
{
	protected ORMTableQuery $filters;

	/**
	 * Returns the filters.
	 *
	 * @return ORMTableQuery
	 */
	public function getFilters(): ORMTableQuery
	{
		return $this->filters;
	}
}
