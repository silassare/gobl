<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gobl\DBAL;

use Gobl\DBAL\Exceptions\DBALException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeString;
use InvalidArgumentException;

/**
 * Class Column
 */
class Column
{
	const NAME_REG   = '~^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$~';

	const PREFIX_REG = '~^(?:[a-zA-Z][a-zA-Z0-9_]*[a-zA-Z0-9]|[a-zA-Z])$~';

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
		'bool'   => TypeBool::class,
	];

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
	 * @var TypeInterface;
	 */
	protected $type;

	/**
	 * The column options.
	 *
	 * @var array;
	 */
	protected $options;

	/**
	 * Column constructor.
	 *
	 * @param string $name    the column name
	 * @param string $prefix  the column prefix
	 * @param array  $options the column options
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public function __construct($name, $prefix, array $options)
	{
		if (!\preg_match(self::NAME_REG, $name)) {
			throw new InvalidArgumentException(\sprintf('Invalid column name "%s".', $name));
		}

		if (!empty($prefix)) {
			if (!\preg_match(self::PREFIX_REG, $prefix)) {
				throw new InvalidArgumentException(\sprintf(
					'Invalid column prefix name "%s" for column "%s".',
					$prefix,
					$name
				));
			}
		} else {
			$prefix = '';
		}

		$this->name    = \strtolower($name);
		$this->prefix  = \strtolower($prefix);
		$this->options = $options;
		$this->type    = $this->optionsToType();
	}

	/**
	 * Gets type object.
	 *
	 * @return TypeInterface
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
	 * Checks if the column is private
	 *
	 * @return bool
	 */
	public function isPrivate()
	{
		if (!isset($this->options['private'])) {
			return false;
		}

		return (bool) ($this->options['private']);
	}

	/**
	 * Returns column options.
	 *
	 * @return array
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * Returns type from column options.
	 *
	 * @throws DBALException
	 *
	 * @return TypeInterface
	 */
	private function optionsToType()
	{
		if (!isset($this->options['type'])) {
			throw new DBALException(\sprintf('You should define a type for column "%s".', $this->name));
		}

		$type = $this->options['type'];

		if ($type instanceof TypeInterface) {
			return $type;
		}

		if (!\is_string($type)) {
			throw new InvalidArgumentException('Invalid column type defined for "%s".', $this->name);
		}

		if (!isset(self::$columns_types[$type])) {
			throw new DBALException(\sprintf('Unsupported column type "%s" defined for "%s".', $type, $this->name));
		}

		$class = self::$columns_types[$type];
		/* @var TypeInterface $t */
		return \call_user_func([$class, 'getInstance'], $this->options);
	}

	/**
	 * Adds custom column type.
	 *
	 * @param string $type_name  The custom type name
	 * @param string $type_class The custom type's fully qualified class name
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 */
	public static function addCustomType($type_name, $type_class)
	{
		if (isset(self::$columns_types[$type_name])) {
			throw new DBALException(\sprintf(
				'You cannot overwrite column type "%s" with "%s".',
				$type_name,
				$type_class
			));
		}

		if (
			!(\is_subclass_of($type_class, TypeString::class)
			  || \is_subclass_of($type_class, TypeInt::class)
			  || \is_subclass_of($type_class, TypeBigint::class)
			  || \is_subclass_of($type_class, TypeFloat::class)
			  || \is_subclass_of($type_class, TypeBool::class))
		) {
			throw new DBALException(\sprintf(
				'Your custom column type "%s => %s" should extends one of the standard column types.',
				$type_name,
				$type_class
			));
		}

		self::$columns_types[$type_name] = $type_class;
	}

	/**
	 * Help var_dump().
	 *
	 * @return array
	 */
	public function __debugInfo()
	{
		return ['instance_of' => static::class, 'column_name' => $this->getName()];
	}
}
