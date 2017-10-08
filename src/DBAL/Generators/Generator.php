<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Generators;

	use Gobl\DBAL\QueryBuilder;

	/**
	 * Interface Generator
	 *
	 * @package Gobl\DBAL\Generators
	 */
	interface Generator
	{
		/**
		 * Generator constructor.
		 *
		 * @param \Gobl\DBAL\QueryBuilder $query
		 */
		function __construct(QueryBuilder $query);

		/**
		 * Converts query object into sql.
		 *
		 * @return string
		 */
		public function buildQuery();
	}
