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

namespace Gobl\DBAL\Relations\Traits;

/**
 * Trait FilterableRelationTrait.
 */
trait FilterableRelationTrait
{
	protected ?array $filters;

	/**
	 * Get relations custom filters.
	 *
	 * @return null|array
	 */
	public function getFilters(): ?array
	{
		return $this->filters;
	}
}