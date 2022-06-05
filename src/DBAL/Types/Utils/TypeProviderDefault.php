<?php

/**
 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL\Types\Utils;

use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\Interfaces\TypeProviderInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;

/**
 * Class TypeProviderDefault.
 */
class TypeProviderDefault implements TypeProviderInterface
{
	/**
	 * Maps available type names to type class names.
	 *
	 * @var array
	 */
	private static array $types = [
		// base types
		TypeInt::NAME     => TypeInt::class,
		TypeBigint::NAME  => TypeBigint::class,
		TypeFloat::NAME   => TypeFloat::class,
		TypeDecimal::NAME => TypeDecimal::class,
		TypeBool::NAME    => TypeBool::class,
		TypeString::NAME  => TypeString::class,

		// other types
		TypeList::NAME    => TypeList::class,
		TypeMap::NAME     => TypeMap::class,
		TypeDate::NAME    => TypeDate::class,
	];

	/**
	 * {@inheritDoc}
	 */
	public function getTypeInstance(string $name, array $options): ?TypeInterface
	{
		if (isset(self::$types[$name])) {
			/** @var TypeInterface $class */
			$class = self::$types[$name];

			return $class::getInstance($options);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasType(string $name): bool
	{
		return isset(self::$types[$name]);
	}
}
