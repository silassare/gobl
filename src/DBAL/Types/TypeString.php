<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL\Types;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;

/**
 * Class TypeString
 */
class TypeString extends TypeBase
{
	private $min;

	private $max;

	private $truncate = false;

	private $pattern;

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		$instance = new self();
		$min      = self::getOptionKey($options, 'min', 0);
		$max      = self::getOptionKey($options, 'max', \PHP_INT_MAX);

		$instance->length($min, $max);

		if (isset($options['truncate']) && $options['truncate']) {
			$instance->truncate();
		}

		if (isset($options['pattern'])) {
			$instance->pattern($options['pattern']);
		}

		if (self::getOptionKey($options, 'null', false)) {
			$instance->nullAble();
		}

		if (\array_key_exists('default', $options)) {
			$instance->setDefault($options['default']);
		}

		return $instance;
	}

	/**
	 * TypeString constructor.
	 *
	 * @param int         $min     the minimum string length
	 * @param int         $max     the maximum string length
	 * @param null|string $pattern the string pattern
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct($min = 0, $max = \PHP_INT_MAX, $pattern = null)
	{
		$this->length($min, $max);

		if (isset($pattern)) {
			$this->pattern($pattern);
		}
	}

	/**
	 * Sets string length range.
	 *
	 * @param int $min the minimum string length
	 * @param int $max the maximum string length
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function length($min, $max)
	{
		self::assertSafeIntRange($min, $max, 0);

		$this->min = $min;
		$this->max = $max;

		return $this;
	}

	/**
	 * Enable truncating when string length greater than max.
	 *
	 * @return $this
	 */
	public function truncate()
	{
		$this->truncate = true;

		return $this;
	}

	/**
	 * Checks if truncate is enabled.
	 *
	 * @return bool
	 */
	public function canTruncate()
	{
		return $this->truncate;
	}

	/**
	 * Sets the string pattern.
	 *
	 * @param string $pattern the pattern (regular expression)
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function pattern($pattern)
	{
		if (false === \preg_match($pattern, null)) {
			throw new TypesException(\sprintf('invalid regular expression: %s', $pattern));
		}

		$this->pattern = $pattern;

		return $this;
	}

	/**
	 * @inheritdoc
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	public function validate($value, $column_name, $table_name)
	{
		$debug = [
			'value' => $value,
		];

		if (\is_numeric($value)) { // accept numeric value
			$value .= '';
		}

		if ((null === $value || $value === '') && $this->isNullAble()) {
			return $this->getDefault();
		}

		if (!\is_string($value)) {
			throw new TypesInvalidValueException('invalid_string_type', $debug);
		}

		if (isset($this->min) && \strlen($value) < $this->min) {
			throw new TypesInvalidValueException('string_length_lt_min', $debug);
		}

		if (isset($this->max) && \strlen($value) > $this->max) {
			if (!$this->canTruncate()) {
				throw new TypesInvalidValueException('string_length_gt_max', $debug);
			}

			$value = \substr($value, 0, $this->max);
		}

		if (isset($this->pattern) && !\preg_match($this->pattern, $value)) {
			throw new TypesInvalidValueException('string_pattern_check_fails', $debug);
		}

		return $value;
	}

	/**
	 * @inheritdoc
	 */
	public function getCleanOptions()
	{
		return [
			'type'     => 'string',
			'min'      => $this->min,
			'max'      => $this->max,
			'truncate' => $this->truncate,
			'pattern'  => $this->pattern,
			'null'     => $this->isNullAble(),
			'default'  => $this->getDefault(),
		];
	}

	/**
	 * @inheritdoc
	 */
	final public function getTypeConstant()
	{
		return TypeInterface::TYPE_STRING;
	}
}
