<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the Gobl package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace Gobl\DBAL\Types;

	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;

	/**
	 * Class TypeBool
	 *
	 * @package Gobl\DBAL\Types
	 */
	class TypeBool extends TypeBase
	{
		private        $strict        = true;
		private static $list          = [true, false, 1, 0];
		private static $extended_list = [
			true,
			false,
			1,
			0,
			'1',
			'0',
			'true',
			'false',
			'yes',
			'no',
			'on',
			'off',
			'y',
			'n'
		];

		private static $map = [
			'1'     => 1,
			'0'     => 0,
			'true'  => 1,
			'false' => 0,
			'yes'   => 1,
			'no'    => 0,
			'on'    => 1,
			'off'   => 0,
			'y'     => 1,
			'n'     => 0
		];

		/**
		 * TypeBool Constructor.
		 *
		 * @param bool $strict whether to limit bool value to (true,false,1,0)
		 */
		public function __construct($strict = true)
		{
			$this->strict = (bool)$strict;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function setDefault($value)
		{
			$this->default = intval(boolval($value));

			return $this;
		}

		/**
		 * {@inheritdoc}
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 */
		public function validate($value, $column_name, $table_name)
		{
			$debug = [
				"value" => $value
			];

			if (is_null($value) AND $this->isNullAble())
				return $this->getDefault();

			$allowed = $this->strict ? self::$list : self::$extended_list;

			if (!in_array($value, $allowed))
				throw new TypesInvalidValueException('invalid_bool_type', $debug);

			if (is_string($value) AND isset(self::$map[$value])) {
				$value = strtolower($value);

				return self::$map[$value];
			}

			return intval(boolval($value));
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self(self::getOptionKey($options, 'strict', true));

			if (self::getOptionKey($options, 'null', false))
				$instance->nullAble();

			if (array_key_exists('default', $options))
				$instance->setDefault($options['default']);

			return $instance;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getCleanOptions()
		{
			$options = [
				'type'    => 'bool',
				'strict'  => $this->strict,
				'null'    => $this->isNullAble(),
				'default' => $this->getDefault()
			];

			return $options;
		}

		/**
		 * {@inheritdoc}
		 */
		final public function getTypeConstant()
		{
			return Type::TYPE_BOOL;
		}
	}
