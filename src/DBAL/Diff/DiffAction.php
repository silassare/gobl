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

namespace Gobl\DBAL\Diff;

use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class DiffAction.
 */
abstract class DiffAction implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	public function __construct(protected DiffActionType $type, protected string $reason) {}

	/**
	 * Gets the reason.
	 *
	 * @return string
	 */
	public function getReason(): string
	{
		return $this->reason;
	}

	/**
	 * Sets the reason.
	 *
	 * @param string $reason
	 *
	 * @return $this
	 */
	public function setReason(string $reason): static
	{
		$this->reason = $reason;

		return $this;
	}

	/**
	 * Gets the type.
	 *
	 * @return DiffActionType
	 */
	public function getType(): DiffActionType
	{
		return $this->type;
	}
}
