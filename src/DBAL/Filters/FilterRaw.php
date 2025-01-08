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

namespace Gobl\DBAL\Filters;

use Gobl\DBAL\Filters\Interfaces\FilterInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class FilterRaw.
 */
final class FilterRaw implements FilterInterface
{
	use ArrayCapableTrait;

	/**
	 * FilterRaw constructor.
	 *
	 * This class is used to add raw sql filter query string to a query builder.
	 *
	 * @param string $filter_query_string
	 */
	public function __construct(protected string $filter_query_string) {}

	public function __toString(): string
	{
		return $this->filter_query_string;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			$this->filter_query_string,
		];
	}
}
