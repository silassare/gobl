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

	use Gobl\ORM\ORMRequestContext;

	abstract class VirtualRelation
	{
		const NAME_REG = Relation::NAME_REG;

		/** @var string */
		protected $name;

		/**
		 * VirtualRelation constructor.
		 *
		 * @param  string $name
		 */
		public function __construct($name)
		{
			if (!preg_match(VirtualRelation::NAME_REG, $name)) {
				throw new \InvalidArgumentException(sprintf('Invalid virtual relation name "%s".', $name));
			}

			$this->name = $name;
		}

		/**
		 * Gets the virtual relation name.
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}

		/**
		 * @param mixed                       $target
		 * @param \Gobl\ORM\ORMRequestContext $request_context
		 * @param int                         $total_records
		 *
		 * @return mixed
		 */
		abstract public function run($target, ORMRequestContext $request_context, &$total_records = null);
	}
