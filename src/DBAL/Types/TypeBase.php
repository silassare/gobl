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

	use Gobl\DBAL\Types\Exceptions\TypesException;

	/**
	 * Class TypeBase
	 *
	 * @package Gobl\DBAL\Types
	 */
	abstract class TypeBase implements Type
	{
		protected $null           = false;
		protected $default        = null;
		protected $auto_increment = false;

		/**
		 * {@inheritdoc}
		 */
		public function isNullAble()
		{
			return $this->null;
		}

		/**
		 * {@inheritdoc}
		 */
		public function nullAble()
		{
			$this->null = true;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function isAutoIncremented()
		{
			return $this->auto_increment;
		}

		/**
		 * {@inheritdoc}
		 */
		public function autoIncrement()
		{
			$this->auto_increment = true;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function getDefault()
		{
			return $this->default;
		}

		/**
		 * {@inheritdoc}
		 */
		public function setDefault($value)
		{
			$this->default = $value;

			return $this;
		}

		/**
		 * @param     $min
		 * @param     $max
		 * @param int $range_min
		 * @param int $range_max
		 *
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 */
		final protected static function assertSafeIntRange($min, $max, $range_min = PHP_INT_MIN, $range_max = PHP_INT_MAX)
		{
			if (!is_int($min))
				throw new TypesException(sprintf('min=%s is not a valid integer.', $min));
			if (!is_int($max))
				throw new TypesException(sprintf('max=%s is not a valid integer.', $max));
			if ($min < $range_min)
				throw new TypesException(sprintf('min=%s is not in range (%s,%s).', $min, $range_min, $range_max));
			if ($max > $range_max)
				throw new TypesException(sprintf('max=%s is not in range (%s,%s).', $max, $range_min, $range_max));

			if ($min > $max)
				throw new TypesException(sprintf('min=%s and max=%s is not a valid condition.', $min, $max));
		}

		final protected static function getOptionKey(array $option, $key, $default = null)
		{
			if (isset($option[$key])) {
				return $option[$key];
			}

			return $default;
		}

		final protected static function getOptionKeys(array $option, array $keys, array $default = [])
		{
			foreach ($keys as $key) {
				if (array_key_exists($key, $option)) {
					$default[$key] = $option[$key];
				}
			}

			return $default;
		}
	}
