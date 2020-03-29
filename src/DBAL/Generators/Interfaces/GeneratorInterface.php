<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL\Generators\Interfaces;

use Gobl\DBAL\DbConfig;
use Gobl\DBAL\QueryBuilder;

/**
 * Interface Generator
 */
interface GeneratorInterface
{
	/**
	 * Generator constructor.
	 */
	public function __construct(QueryBuilder $qb, DbConfig $config);

	/**
	 * Converts query object into sql.
	 *
	 * @return string
	 */
	public function buildQuery();
}
