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

namespace Gobl\DBAL\Interfaces;

use Gobl\DBAL\Exceptions\DBALRuntimeException;

/**
 * Interface LockInterface.
 */
interface LockInterface
{
	/**
	 * Locks this instance to prevent further changes.
	 *
	 * @return $this
	 */
	public function lock(): static;

	/**
	 * Checks if this instance is locked.
	 */
	public function isLocked(): bool;

	/**
	 * Asserts that this instance is not locked.
	 *
	 * @throws DBALRuntimeException when already locked
	 */
	public function assertNotLocked(): void;
}
