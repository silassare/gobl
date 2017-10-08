<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL;

	use Gobl\DBAL\Types\Type;
	use Gobl\DBAL\Types\TypeBigint;
	use Gobl\DBAL\Types\TypeBool;
	use Gobl\DBAL\Types\TypeFloat;
	use Gobl\DBAL\Types\TypeInt;
	use Gobl\DBAL\Types\TypeString;

	/**
	 * Class Column
	 *
	 * @package Gobl\DBAL
	 */
	class Column
	{
		const NAME_REG   = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';
		const PREFIX_REG = '#^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$#';

		/**
		 * The column name.
		 *
		 * @var string
		 */
		protected $name;

		/**
		 * The column prefix.
		 *
		 * @var string
		 */
		protected $prefix;

		/**
		 * The column type instance.
		 *
		 * @var \Gobl\DBAL\Types\Type;
		 */
		protected $type;

		/**
		 * Allowed column types list.
		 *
		 * @var array
		 */
		private static $allowed_types = ['string', 'int', 'bigint', 'float', 'bool'];

		/**
		 * Column constructor.
		 *
		 * @param string $name   the column name
		 * @param string $prefix the column prefix
		 *
		 * @throws \Exception
		 */
		public function __construct($name, $prefix = '')
		{
			if (!preg_match(Column::NAME_REG, $name))
				throw new \Exception(sprintf('Invalid column name "%s".', $name));

			if (!empty($prefix)) {
				if (!preg_match(Column::PREFIX_REG, $prefix))
					throw new \Exception(sprintf('Invalid column prefix name "%s".', $prefix));
			}

			$this->name   = strtolower($name);
			$this->prefix = strtolower($prefix);
		}

		/**
		 * Set column type.
		 *
		 * @param \Gobl\DBAL\Types\Type $type the column type object.
		 *
		 * @return $this
		 *
		 */
		public function setType(Type $type)
		{
			$this->type = $type;

			return $this;
		}

		/**
		 * Set column options.
		 *
		 * @param array $options
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function setOptions(array $options)
		{
			if (!isset($options['type']))
				throw new \Exception(sprintf('you should define a column type for "%s".', $this->name));

			$type = $options['type'];

			if (!in_array($type, self::$allowed_types))
				throw new \Exception(sprintf('unsupported column type "%s" defined for "%s".', $type, $this->name));

			if ($type === 'int')
				$t = TypeInt::getInstance($options);
			elseif ($type === 'bigint')
				$t = TypeBigint::getInstance($options);
			elseif ($type === 'float')
				$t = TypeFloat::getInstance($options);
			elseif ($type === 'bool')
				$t = TypeBool::getInstance($options);
			else // if ($type === 'string')
				$t = TypeString::getInstance($options);

			$this->type = $t;

			return $this;
		}

		/**
		 * Gets column options.
		 *
		 * @return array
		 */
		public function getOptions()
		{
			return $this->type->getCleanOptions();
		}

		/**
		 * Gets type object.
		 *
		 * @return \Gobl\DBAL\Types\Type
		 */
		public function getTypeObject()
		{
			return $this->type;
		}

		/**
		 * Gets column name.
		 *
		 * @return string
		 */
		public function getName()
		{
			return $this->name;
		}

		/**
		 * Gets column prefix.
		 *
		 * @return string
		 */
		public function getPrefix()
		{
			return $this->prefix;
		}

		/**
		 * Gets column full name.
		 *
		 * @return string
		 */
		public function getFullName()
		{
			if (empty($this->prefix))
				return $this->name;

			return $this->prefix . '_' . $this->name;
		}
	}
