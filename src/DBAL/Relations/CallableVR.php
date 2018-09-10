<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Relations;

	class CallableVR extends VirtualRelation
	{
		/**
		 * @var callable
		 */
		protected $callable;

		public function __construct($name, callable $callable)
		{
			parent::__construct($name);

			$this->callable = $callable;
		}

		/**
		 * @param mixed $target
		 * @param array $request
		 * @param int   $max
		 * @param int   $offset
		 * @param int   $total_records
		 *
		 * @return mixed
		 */
		public function run($target, array $request, $max = null, $offset = 0, &$total_records = null)
		{
			return call_user_func($this->callable, $target, $request, $max, $offset, $total_records);
		}
	}
