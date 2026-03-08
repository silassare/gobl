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

namespace Gobl\DBAL\Traits;

use Gobl\DBAL\Exceptions\DBALRuntimeException;

/**
 * Trait LockTrait.
 *
 * Provides a basic locking mechanism for classes implementing LockInterface.
 */
trait LockTrait
{
	protected bool $locked = false;

	public function isLocked(): bool
	{
		return $this->locked;
	}

	public function assertNotLocked(): void
	{
		if ($this->locked) {
			throw new DBALRuntimeException(
				\sprintf('Locked "%s" instance cannot be modified.', static::class)
			);
		}
	}
}
