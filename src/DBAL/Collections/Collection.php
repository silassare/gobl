<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Collections;

	use Gobl\ORM\ORMRequestContext;

	class Collection
	{
		const NAME_REG = '#^(?:[a-zA-Z][a-zA-Z0-9_-]*[a-zA-Z0-9]|[a-zA-Z])$#';

		/**
		 * @var callable
		 */
		protected $callable;
		/**
		 * @var string
		 */
		protected $name;

		/**
		 * Collection constructor.
		 *
		 * @param          $name
		 * @param callable $callable
		 */
		public function __construct($name, callable $callable)
		{
			if (!preg_match(Collection::NAME_REG, $name)) {
				throw new \InvalidArgumentException(sprintf('Invalid collection name "%s".', $name));
			}

			$this->name     = $name;
			$this->callable = $callable;
		}

		/**
		 * Gets collection name.
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}

		/**
		 * Runs the collection callable.
		 *
		 * @param \Gobl\ORM\ORMRequestContext $request_context
		 * @param int                         $total_records
		 *
		 * @return mixed
		 */
		public function run(ORMRequestContext $request_context, &$total_records = null)
		{
			return call_user_func_array($this->callable, [$request_context, &$total_records]);
		}
	}
