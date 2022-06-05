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

namespace Gobl\DBAL\Queries;

use PDO;

/**
 * Class QBUtils.
 */
class QBUtils
{
	/** @var int */
	protected static int $GEN_IDENTIFIER_COUNTER = 0;

	/**
	 * Wrap an expression string in a query builder expression {@see \Gobl\DBAL\Queries\QBExpression}.
	 *
	 * @param string $expression
	 *
	 * @return \Gobl\DBAL\Queries\QBExpression
	 */
	public static function exp(string $expression): QBExpression
	{
		return new QBExpression($expression);
	}

	/**
	 * Generate a new unique table alias.
	 *
	 * @return string
	 */
	public static function newTableAlias(): string
	{
		return '_' . self::alphaId() . '_';
	}

	/**
	 * Generate a new unique parameter key.
	 *
	 * @return string
	 */
	public static function newParamKey(): string
	{
		return '_val_' . self::alphaId();
	}

	/**
	 * Returns PDO type for a given value.
	 *
	 * @param mixed $value
	 *
	 * @return int
	 */
	public static function paramType(mixed $value): int
	{
		$type = PDO::PARAM_STR;
		if (null === $value) {
			$type = PDO::PARAM_NULL;
		} elseif (\is_bool($value)) {
			$type = PDO::PARAM_BOOL;
		} elseif (\is_int($value)) {
			$type = PDO::PARAM_INT;
		}

		return $type;
	}

	/**
	 * Generate a new unique char sequence.
	 *
	 * infinite possibilities
	 * a,  b  ... z
	 * aa, ab ... az
	 * ba, bb ... bz
	 *
	 * @return string
	 */
	protected static function alphaId(): string
	{
		$x    = self::$GEN_IDENTIFIER_COUNTER++;
		$list = \range('a', 'z');
		$len  = \count($list);
		$a    = '';

		do {
			$r = ($x % $len);
			$n = ($x - $r) / $len;
			$x = $n - 1;
			$a = $list[$r] . $a;
		} while ($n);

		return $a;
	}
}
