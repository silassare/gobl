<?php

/**
 * Copyright (c) Emile Silas Sare.
 *
 * This file is part of the Gobl package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Gobl\DBAL;

use Gobl\DBAL\Exceptions\DBALRuntimeException;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\Types\TypeEnum;

/**
 * Class MigrationSchema.
 *
 * The schema a migration carries ({@see MigrationInterface::getSchema()}) is written as PHP code, and
 * a migration is history: loading it must not depend on code that has moved on since. So that schema
 * is pure data (scalars and arrays of them), an enum default is its backing value, and an enum column
 * keeps its cases beside its class name ({@see TypeEnum}).
 */
final class MigrationSchema
{
	/**
	 * Throws when the data holds anything but null, scalars and arrays of them.
	 *
	 * @param array  $data
	 * @param string $path where the data is, for the message
	 */
	public static function assertPureData(array $data, string $path = 'schema'): void
	{
		foreach ($data as $key => $value) {
			$at = $path . '.' . $key;

			if (\is_array($value)) {
				self::assertPureData($value, $at);
			} elseif (null !== $value && !\is_scalar($value)) {
				throw new DBALRuntimeException(\sprintf(
					'A migration schema must be pure data: found "%s" at "%s". Write it as a scalar.',
					\get_debug_type($value),
					$at
				));
			}
		}
	}
}
