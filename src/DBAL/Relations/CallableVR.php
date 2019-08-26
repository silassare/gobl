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

	use Gobl\ORM\ORMRequestBase;

	class CallableVR extends VirtualRelation
	{
		/**
		 * @var callable
		 */
		protected $callable;
		/**
		 * @var boolean
		 */
		protected $handle_list;

		/**
		 * CallableVR constructor.
		 *
		 * @param string   $name
		 * @param callable $callable
		 * @param bool     $handle_list
		 */
		public function __construct($name, callable $callable, $handle_list = false)
		{
			parent::__construct($name);

			$this->callable    = $callable;
			$this->handle_list = $handle_list;
		}

		/**
		 * @return bool
		 */
		public function canHandleList()
		{
			return $this->handle_list;
		}

		/**
		 * @param mixed                    $target
		 * @param \Gobl\ORM\ORMRequestBase $request
		 * @param int                      $total_records
		 *
		 * @return mixed
		 */
		public function run($target, ORMRequestBase $request, &$total_records = null)
		{
			return call_user_func_array($this->callable, [$target, $request, &$total_records]);
		}
	}
