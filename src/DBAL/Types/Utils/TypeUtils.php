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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\Interfaces\TypeProviderInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeString;

/**
 * Class TypeUtils.
 */
class TypeUtils
{
	/**
	 * @var TypeProviderInterface[]
	 */
	private static array $type_providers = [];

	/**
	 * Adds type provider.
	 */
	public static function addTypeProvider(TypeProviderInterface $provider): void
	{
		self::$type_providers[\get_class($provider)] = $provider;
	}

	/**
	 * Gets type instance.
	 *
	 * @param string $name
	 * @param array  $options
	 *
	 * @return null|\Gobl\DBAL\Types\Interfaces\TypeInterface
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public static function getTypeInstance(string $name, array $options): ?TypeInterface
	{
		$reversed = \array_reverse(self::$type_providers);
		$found    = null;
		$found_in = null;

		foreach ($reversed as $provider) {
			$found = $provider->getTypeInstance($name, $options);

			if ($found) {
				$found_in = $provider;

				break;
			}
		}

		if (
			$found
			&& $found_in
		) {
			$base_types = self::getBaseTypes();
			$type_ok    = false;
			$found_bt   = $found->getBaseType();

			foreach ($base_types as $bt) {
				if ($found_bt instanceof $bt || \is_subclass_of($found_bt, $bt)) {
					$type_ok = true;

					break;
				}
			}

			if (!$type_ok) {
				throw new TypesException(\sprintf(
					'Custom column type "%s" provided by "%s" for type "%s" returned "%s" while expecting one of allowed base type: %s',
					\get_class($found),
					\get_class($found_in),
					$name,
					\get_class($found_bt),
					\implode('|', $base_types)
				));
			}
		}

		return $found;
	}

	/**
	 * Returns base types class map.
	 *
	 * @return class-string<\Gobl\DBAL\Types\Interfaces\BaseTypeInterface>[]
	 */
	public static function getBaseTypes(): array
	{
		return [
			TypeString::NAME  => TypeString::class,
			TypeInt::NAME     => TypeInt::class,
			TypeBigint::NAME  => TypeBigint::class,
			TypeFloat::NAME   => TypeFloat::class,
			TypeDecimal::NAME => TypeDecimal::class,
			TypeBool::NAME    => TypeBool::class,
		];
	}

	/**
	 * This enforce query expression value type (support array as value).
	 *
	 * @param string                               $table_name
	 * @param string                               $column_name
	 * @param string|string[]                      $expression
	 * @param \Gobl\DBAL\Interfaces\RDBMSInterface $rdbms
	 *
	 * @return string|string[]
	 */
	public static function runEnforceQueryExpressionValueType(
		string $table_name,
		string $column_name,
		array|string $expression,
		RDBMSInterface $rdbms
	): array|string {
		$table = $rdbms->getTableOrFail($table_name);
		$col   = $table->getColumnOrFail($column_name);
		$type  = $col->getType();

		if ($type->shouldEnforceQueryExpressionValueType($rdbms)) {
			if (\is_array($expression)) {
				$expressions = $expression;
				foreach ($expressions as $key => $entry) {
					$expressions[$key] = $type->enforceQueryExpressionValueType($entry, $rdbms);
				}

				return $expressions;
			}

			return $type->enforceQueryExpressionValueType($expression, $rdbms);
		}

		return $expression;
	}

	/**
	 * Merge two type options.
	 *
	 * @param array $options
	 * @param array $overrides
	 *
	 * @return array
	 */
	public static function mergeOptions(array $options, array $overrides): array
	{
		// for now we use array_merge,
		// in the future we may need to
		// use a custom merge strategy
		return \array_merge($options, $overrides);
	}
}
