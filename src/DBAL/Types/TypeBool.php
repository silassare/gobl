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
	class TypeBool implements Type
	{
		private        $null          = false;
		private        $default       = null;
		private        $strict;
		private static $list          = [true, false, 1, 0];
		private static $extended_list = [true, false, 1, 0, '1', '0', 'true', 'false', 'yes', 'no', 'y', 'n'];
		private static $map           = [
			'1'     => 1,
			'0'     => 0,
			'true'  => 1,
			'false' => 0,
			'yes'   => 1,
			'no'    => 0,
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
		public function nullAble()
		{
			$this->null = true;
		}

		/**
		 * {@inheritdoc}
		 */
		public function def($value)
		{
			$this->default = intval(boolval($value));

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function validate($value)
		{
			$debug = ['value' => $value, 'strict' => $this->strict, 'default' => $this->default];

			if (is_null($value) AND $this->null)
				return $this->default;

			if (!in_array($value, ($this->strict ? self::$list : self::$extended_list)))
				throw new TypesInvalidValueException('invalid_bool_type', $debug);

			return (is_string($value) ? self::$map[strtolower($value)] : intval($value));
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$strict   = array_key_exists('strict', $options) ? boolval($options['strict']) : true;
			$instance = new self($strict);

			if (isset($options['null']) AND $options['null'])
				$instance->nullAble();

			if (array_key_exists('default', $options))
				$instance->def($options['default']);

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
				'null'    => $this->null,
				'default' => $this->default
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
