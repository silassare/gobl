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

	use Gobl\DBAL\Exceptions\DBALException;
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
		 * Maps available type names to type class names.
		 *
		 * @var array
		 */
		private static $columns_types = [
			'int'    => TypeInt::class,
			'bigint' => TypeBigint::class,
			'string' => TypeString::class,
			'float'  => TypeFloat::class,
			'bool'   => TypeBool::class
		];

		/**
		 * The column is private
		 *
		 * @var bool
		 */
		protected $private;

		/**
		 * Column constructor.
		 *
		 * @param string                      $name    the column name
		 * @param array|\Gobl\DBAL\Types\Type $type    the column type instance or type options array
		 * @param string|null                 $prefix  the column prefix
		 * @param bool                        $private the column is private
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function __construct($name, $type, $prefix = null, $private = false)
		{
			if (!preg_match(Column::NAME_REG, $name)) {
				throw new \InvalidArgumentException(sprintf('Invalid column name "%s".', $name));
			}

			if (!is_null($prefix)) {
				if (!preg_match(Column::PREFIX_REG, $prefix)) {
					throw new \InvalidArgumentException(sprintf('Invalid column prefix name "%s".', $prefix));
				}
			}

			$this->name    = strtolower($name);
			$this->prefix  = strtolower($prefix);
			$this->private = $private;

			if ($type instanceof Type) {
				$this->type = $type;
			} elseif (is_array($type)) {
				$this->type = $this->arrayOptionsToType($type);
			} else {
				throw new \InvalidArgumentException("Invalid column type.");
			}
		}

		/**
		 * Sets column options.
		 *
		 * @param array $options
		 *
		 * @return \Gobl\DBAL\Types\Type
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		private function arrayOptionsToType(array $options)
		{
			if (!isset($options['type'])) {
				throw new DBALException(sprintf('You should define a column type for "%s".', $this->name));
			}

			$type = $options['type'];

			if (isset(self::$columns_types[$type])) {
				$class = self::$columns_types[$type];
				/** @var Type $t */
				$t = call_user_func([$class, 'getInstance'], $options);
			} else {
				throw new DBALException(sprintf('Unsupported column type "%s" defined for "%s".', $type, $this->name));
			}

			return $t;
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
			if (empty($this->prefix)) {
				return $this->name;
			}

			return $this->prefix . '_' . $this->name;
		}

		/**
		 * Adds custom column type.
		 *
		 * @param string $type_name  The custom type name
		 * @param string $type_class The custom type class fully qualified name
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public static function addCustomType($type_name, $type_class)
		{
			if (isset(self::$columns_types[$type_name])) {
				throw new DBALException(sprintf('You cannot overwrite column type "%s".', $type_name, $type_class));
			}

			if (!(is_subclass_of($type_class, TypeString::class)
				  OR is_subclass_of($type_class, TypeInt::class)
				  OR is_subclass_of($type_class, TypeBigint::class)
				  OR is_subclass_of($type_class, TypeFloat::class)
				  OR is_subclass_of($type_class, TypeBool::class))) {
				throw new DBALException(sprintf('Your custom column type "%s => %s" should extends one of the standard column types.', $type_name, $type_class));
			}

			self::$columns_types[$type_name] = $type_class;
		}

		/**
		 * Check if the column is private
		 *
		 * @return bool
		 */
		public function isPrivate()
		{
			return $this->private;
		}
	}
