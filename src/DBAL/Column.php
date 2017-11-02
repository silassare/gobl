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
		 * Column constructor.
		 *
		 * @param string      $name   the column name
		 * @param string|null $prefix the column prefix
		 *
		 * @throws \InvalidArgumentException
		 */
		public function __construct($name, $prefix = null)
		{
			if (!preg_match(Column::NAME_REG, $name))
				throw new \InvalidArgumentException(sprintf('Invalid column name "%s".', $name));

			if (!is_null($prefix)) {
				if (!preg_match(Column::PREFIX_REG, $prefix))
					throw new \InvalidArgumentException(sprintf('Invalid column prefix name "%s".', $prefix));
			}
			$this->name   = strtolower($name);
			$this->prefix = strtolower($prefix);
		}

		/**
		 * Sets column type.
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
		 * Sets column options.
		 *
		 * @param array $options
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 */
		public function setOptions(array $options)
		{
			if (!isset($options['type']))
				throw new DBALException(sprintf('you should define a column type for "%s".', $this->name));

			$type = $options['type'];

			if (isset(self::$columns_types[$type])) {
				$class = self::$columns_types[$type];
				/** @var Type $t */
				$t = call_user_func([$class, 'getInstance'], $options);
			} else {
				throw new DBALException(sprintf('unsupported column type "%s" defined for "%s".', $type, $this->name));
			}

			$this->type = $t;

			return $this;
		}

		/**
		 * Check if this column is auto incremented.
		 *
		 * @return bool
		 */
		public function isAutoIncrement()
		{
			$options = $this->getOptions();

			return isset($options['auto_increment']) AND $options['auto_increment'] === true;
		}

		/**
		 * Check if this column accept null value.
		 *
		 * @return bool
		 */
		public function isNullAble()
		{
			$options = $this->getOptions();

			return (bool)$options['null'];
		}

		/**
		 * Returns this column default value.
		 *
		 * @return mixed
		 */
		public function getDefaultValue()
		{
			$options = $this->getOptions();

			return $options['default'];
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

			$ok = is_subclass_of($type_class, TypeString::class)
			OR is_subclass_of($type_class, TypeInt::class)
			OR is_subclass_of($type_class, TypeBigint::class)
			OR is_subclass_of($type_class, TypeFloat::class)
			OR is_subclass_of($type_class, TypeBool::class);

			if (!$ok) {
				throw new DBALException(sprintf('Your custom column type "%s"("%s") should extends one of the standard column types.', $type_name, $type_class));
			}

			self::$columns_types[$type_name] = $type_class;
		}
	}
